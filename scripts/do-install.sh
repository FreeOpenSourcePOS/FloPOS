#!/bin/bash
# Flo POS DigitalOcean One-Click Droplet User Data
# Use this script in DigitalOcean Droplet "User Data" field
# OR run: curl -s https://raw.githubusercontent.com/FreeOpenSourcePOS/FloPOS/main/scripts/do-install.sh | sh

set -e

# Configuration - Edit these or set as environment variables
DOMAIN="${DOMAIN:-}"  # Set your domain or leave empty
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
APP_DIR="/var/www/flopos"

# Generate secure passwords
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 24 2>/dev/null || cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 24 | head -n 1)
MYSQL_PASSWORD=$(openssl rand -base64 24 2>/dev/null || cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 24 | head -n 1)
APP_KEY=$(openssl rand -base64 32 2>/dev/null || cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)
JWT_SECRET=$(openssl rand -base64 32 2>/dev/null || cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[FloPOS]${NC} $1"; }
warn() { echo -e "${YELLOW}[FloPOS]${NC} $1"; }

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    warn "Installing with sudo..."
    exec sudo "$0" "$@"
fi

log "Starting Flo POS installation..."

# Update system
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y

# Install PHP 8.4
log "Installing PHP 8.4..."
add-apt-repository -y ppa:ondrej/php 2>/dev/null || true
apt-get update -y
apt-get install -y php8.4 php8.4-fpm php8.4-pgsql php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-gd php8.4-redis php8.4-bcmath

# Install Composer
log "Installing Composer..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js 22
log "Installing Node.js..."
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y nodejs
npm install -g pnpm

# Install PostgreSQL 16
log "Installing PostgreSQL..."
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /usr/share/keyrings/postgresql-keyring.gpg 2>/dev/null || true
echo "deb [signed-by=/usr/share/keyrings/postgresql-keyring.gpg] http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" | tee /etc/apt/sources.list.d/pgdg.list > /dev/null
apt-get update -y
apt-get install -y postgresql-16

# Install Redis & Nginx
log "Installing Redis & Nginx..."
apt-get install -y redis-server nginx

# Configure PostgreSQL
log "Configuring PostgreSQL..."
systemctl enable postgresql
systemctl start postgresql
sudo -u postgres psql -c "CREATE USER flopos WITH PASSWORD '$MYSQL_PASSWORD';" 2>/dev/null || true
sudo -u postgres psql -c "CREATE DATABASE flopos_main OWNER flopos;" 2>/dev/null || true

# Clone repository
log "Cloning Flo POS..."
mkdir -p $APP_DIR
cd $APP_DIR
git clone --depth 1 https://github.com/FreeOpenSourcePOS/FloPOS.git .

# Setup Laravel
log "Setting up Laravel backend..."
cd $APP_DIR/backend
cat > .env <<ENVVARS
APP_NAME="Flo POS"
APP_ENV=production
APP_KEY=base64:$APP_KEY
APP_DEBUG=false
APP_URL=https://${DOMAIN:-localhost}

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flopos_main
DB_USERNAME=flopos
DB_PASSWORD=$MYSQL_PASSWORD

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

JWT_SECRET=$JWT_SECRET
ENVVARS

composer install --optimize-autoloader --no-dev --no-interaction
php artisan migrate --force --no-interaction

# Setup Next.js
log "Setting up Next.js frontend..."
cd $APP_DIR/frontend
pnpm install --frozen-lockfile
pnpm build

# Configure Nginx
log "Configuring Nginx..."
if [ -n "$DOMAIN" ]; then
    cat > /etc/nginx/sites-available/flopos <<NGINX
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root $APP_DIR/frontend/out;
    index index.html;

    location /api/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_cache_bypass \$http_upgrade;
    }

    location / {
        try_files \$uri \$uri.html =404;
    }
}
NGINX
else
    cat > /etc/nginx/sites-available/flopos <<NGINX
server {
    listen 80;
    server_name _;
    root $APP_DIR/frontend/out;
    index index.html;

    location /api/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_cache_bypass \$http_upgrade;
    }

    location / {
        try_files \$uri \$uri.html =404;
    }
}
NGINX
fi

ln -sf /etc/nginx/sites-available/flopos /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t

# Setup Queue Worker
log "Setting up queue worker..."
cat > /etc/systemd/system/flopos-worker.service <<SERVICE
[Unit]
Description=Flo POS Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=$APP_DIR/backend
ExecStart=/usr/bin/php $APP_DIR/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
SERVICE

systemctl daemon-reload
systemctl enable flopos-worker
systemctl start flopos-worker

# Enable services
systemctl enable nginx php8.4-fpm redis-server
systemctl restart nginx

# Get SSL if domain provided
if [ -n "$DOMAIN" ]; then
    log "Getting SSL certificate..."
    apt-get install -y certbot python3-certbot-nginx
    certbot --nginx -d $DOMAIN --non-interactive --agree-tos --email $ADMIN_EMAIL || true
fi

log "=========================================="
log "Flo POS Installation Complete!"
log "=========================================="
log ""
log "Frontend: http://${DOMAIN:-YOUR_SERVER_IP}"
log "API: http://${DOMAIN:-YOUR_SERVER_IP}:8000"
log ""
log "Database:"
log "  Database: flopos_main"
log "  Username: flopos"
log "  Password: $MYSQL_PASSWORD"
log ""
log "Referral: Get \$200 credit - https://m.do.co/c/6abf2aadc639"
