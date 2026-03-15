#!/bin/bash
set -e

# Flo POS One-Click Installer for DigitalOcean
# Usage: curl -s https://raw.githubusercontent.com/FreeOpenSourcePOS/FloPOS/main/scripts/install.sh | sh

# Configuration
APP_DIR="/var/www/flopos"
DOMAIN="${DOMAIN:-}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-}"
DO_REFERAL_CODE="6abf2aadc639"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    log_error "Please run as root or with sudo"
    exit 1
fi

log_info "Flo POS Installer for DigitalOcean"
log_info "===================================="

# Detect if this is a fresh DO droplet
if [ -f /etc/digitalocean ]; then
    log_info "DigitalOcean droplet detected"
fi

# Generate secure passwords if not provided
if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
    MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)
    log_warn "MySQL root password generated (save this: $MYSQL_ROOT_PASSWORD)"
fi

if [ -z "$MYSQL_PASSWORD" ]; then
    MYSQL_PASSWORD=$(openssl rand -base64 32)
    log_warn "MySQL flopos password generated (save this: $MYSQL_PASSWORD)"
fi

APP_KEY=$(openssl rand -base64 32)
JWT_SECRET=$(openssl rand -base64 32)

log_info "Updating system packages..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y

log_info "Installing required packages..."

# Install PHP 8.4
add-apt-repository -y ppa:ondrej/php
apt-get update -y
apt-get install -y php8.4 php8.4-fpm php8.4-pgsql php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-gd php8.4-redis php8.4-bcmath php8.4-intl

# Install Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js 22
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y nodejs

# Install PostgreSQL 16
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /usr/share/keyrings/postgresql-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/postgresql-keyring.gpg] http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" | tee /etc/apt/sources.list.d/pgdg.list
apt-get update -y
apt-get install -y postgresql-16 postgresql-client-16

# Install Redis
apt-get install -y redis-server

# Install Nginx
apt-get install -y nginx

# Install Certbot
apt-get install -y certbot python3-certbot-nginx

# Configure PostgreSQL
log_info "Configuring PostgreSQL..."
systemctl enable postgresql
systemctl start postgresql

sudo -u postgres psql -c "CREATE USER flopos WITH PASSWORD '$MYSQL_PASSWORD';" 2>/dev/null || true
sudo -u postgres psql -c "CREATE DATABASE flopos_main OWNER flopos;" 2>/dev/null || true
sudo -u postgres psql -c "ALTER USER flopos CREATEDB;" 2>/dev/null || true

# Clone the repository
log_info "Cloning Flo POS..."
mkdir -p $APP_DIR
cd $APP_DIR
git clone https://github.com/FreeOpenSourcePOS/FloPOS.git .

# Setup backend
log_info "Setting up Laravel backend..."
cd $APP_DIR/backend

# Create .env file
cat > .env <<EOF
APP_NAME="Flo POS"
APP_ENV=production
APP_KEY=base64:$APP_KEY
APP_DEBUG=false
APP_URL=https://$DOMAIN

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
EOF

# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Run migrations
php artisan migrate --force --no-interaction

# Setup frontend
log_info "Setting up Next.js frontend..."
cd $APP_DIR/frontend
npm install -g pnpm
pnpm install
pnpm build

# Configure Nginx
log_info "Configuring Nginx..."
cat > /etc/nginx/sites-available/flopos <<EOF
server {
    listen 80;
    server_name $DOMAIN;
    root $APP_DIR/frontend/out;
    index index.html;

    # API proxy
    location /api/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_cache_bypass \$http_upgrade;
    }

    # Static files
    location /_next/ {
        alias $APP_DIR/frontend/.next/server/;
        try_files \$uri \$uri.html =404;
    }

    # Frontend static
    location / {
        try_files \$uri \$uri.html =404;
    }
}
EOF

ln -sf /etc/nginx/sites-available/flopos /etc/nginx/sites-enabled/
nginx -t

# Setup systemd service for Laravel
log_info "Setting up Laravel queue worker..."
cat > /etc/systemd/system/flopos-worker.service <<EOF
[Unit]
Description=Flo POS Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=$APP_DIR/backend
ExecStart=/usr/bin/php $APP_DIR/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=10s

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable flopos-worker
systemctl start flopos-worker

# Enable services
systemctl enable nginx php8.4-fpm redis-server

# Setup firewall
log_info "Configuring firewall..."
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# Get SSL certificate if domain provided
if [ -n "$DOMAIN" ]; then
    log_info "Getting SSL certificate for $DOMAIN..."
    certbot --nginx -d $DOMAIN --non-interactive --agree-tos --email $ADMIN_EMAIL
fi

log_info "===================================="
log_info "Flo POS Installation Complete!"
log_info "===================================="
log_info ""
log_info "Access your Flo POS:"
if [ -n "$DOMAIN" ]; then
    log_info "  - Frontend: https://$DOMAIN"
else
    log_info "  - Frontend: http://your-server-ip"
fi
log_info "  - Backend API: http://your-server-ip:8000"
log_info ""
log_info "Database Credentials (save these!):"
log_info "  - Host: localhost"
log_info "  - Port: 5432"
log_info "  - Database: flopos_main"
log_info "  - Username: flopos"
log_info "  - Password: $MYSQL_PASSWORD"
log_info ""
log_info "To view logs:"
log_info "  - Laravel: journalctl -u flopos-worker -f"
log_info "  - Nginx: tail -f /var/log/nginx/error.log"
log_info ""
log_info "Referral link for \$200 credit: https://m.do.co/c/$DO_REFERAL_CODE"
