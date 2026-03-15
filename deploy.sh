#!/usr/bin/env bash
###############################################################################
#  Flo POS — Production Deployment Script
#  Target: Ubuntu 24.04 LTS (fresh install)
#  Domain: app.flopos.com (API served from same domain under /api)
#  Stack:  PHP 8.4 + Laravel 12 | Next.js 16 | PostgreSQL 16 | Redis | Caddy
#
#  Usage:
#    bash deploy.sh          # run all steps
#    bash deploy.sh 7        # resume from step 7
###############################################################################
set -euo pipefail

# ─── Resume support ──────────────────────────────────────────────────────────
START_STEP=${1:-1}

# ─── Configuration ───────────────────────────────────────────────────────────
DOMAIN="app.flopos.com"
APP_DIR="/var/www/flopos"
REPO="https://github.com/codify-apps/flopos.git"
DB_NAME="flopos_main"
DB_USER="flopos_user"
NODE_MAJOR=22
PHP_VERSION="8.4"
SWAP_SIZE="2G"
SECRETS_FILE="/root/.flopos-secrets"

# Colors
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
step()  { echo -e "\n${CYAN}━━━ $1 ━━━${NC}"; }
ok()    { echo -e "${GREEN}✓ $1${NC}"; }
warn()  { echo -e "${YELLOW}⚠ $1${NC}"; }
fail()  { echo -e "${RED}✗ $1${NC}"; exit 1; }

# Must run as root
[[ $EUID -ne 0 ]] && fail "Run this script as root:  bash deploy.sh"

# ─── Secrets: persist DB_PASS & JWT_SECRET across re-runs ────────────────────
if [[ -f "$SECRETS_FILE" ]]; then
    source "$SECRETS_FILE"
    ok "Loaded existing secrets from ${SECRETS_FILE}"
else
    DB_PASS=$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)
    JWT_SECRET=$(openssl rand -base64 48 | tr -d '/+=' | head -c 48)
    cat > "$SECRETS_FILE" <<EOF
DB_PASS="${DB_PASS}"
JWT_SECRET="${JWT_SECRET}"
EOF
    chmod 600 "$SECRETS_FILE"
    ok "Generated & saved secrets to ${SECRETS_FILE}"
fi

echo -e "${CYAN}Resuming from step ${START_STEP}${NC}"

###############################################################################
if [[ $START_STEP -le 1 ]]; then
step "1/12  System update & swap"
###############################################################################
apt-get update -qq && apt-get upgrade -y -qq
if ! swapon --show | grep -q '/swapfile'; then
    fallocate -l $SWAP_SIZE /swapfile
    chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
    grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    ok "Swap ${SWAP_SIZE} created"
else
    ok "Swap already exists"
fi
fi

###############################################################################
if [[ $START_STEP -le 2 ]]; then
step "2/12  Install system packages"
###############################################################################
apt-get install -y -qq \
    software-properties-common curl wget git unzip ufw fail2ban \
    supervisor acl jq
fi

###############################################################################
if [[ $START_STEP -le 3 ]]; then
step "3/12  Install PHP ${PHP_VERSION}"
###############################################################################
add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1
apt-get update -qq
apt-get install -y -qq \
    php${PHP_VERSION}-cli php${PHP_VERSION}-fpm php${PHP_VERSION}-pgsql \
    php${PHP_VERSION}-redis php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-intl php${PHP_VERSION}-gd php${PHP_VERSION}-opcache \
    php${PHP_VERSION}-readline php${PHP_VERSION}-tokenizer

# Tune PHP-FPM for production
PHP_FPM_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
sed -i 's/^pm = .*/pm = dynamic/'                  "$PHP_FPM_CONF"
sed -i 's/^pm.max_children = .*/pm.max_children = 20/'  "$PHP_FPM_CONF"
sed -i 's/^pm.start_servers = .*/pm.start_servers = 4/'  "$PHP_FPM_CONF"
sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 2/' "$PHP_FPM_CONF"
sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 6/' "$PHP_FPM_CONF"

# OPcache tuning
cat > /etc/php/${PHP_VERSION}/mods-available/opcache-custom.ini <<'OPCACHE'
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
OPCACHE
phpenmod opcache-custom

systemctl restart php${PHP_VERSION}-fpm
ok "PHP ${PHP_VERSION} installed & tuned"
fi

###############################################################################
if [[ $START_STEP -le 4 ]]; then
step "4/12  Install PostgreSQL 16"
###############################################################################
if ! command -v psql &>/dev/null; then
    sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /etc/apt/trusted.gpg.d/pgdg.gpg
    apt-get update -qq
    apt-get install -y -qq postgresql-16
fi
systemctl enable --now postgresql
ok "PostgreSQL 16 ready"
fi

###############################################################################
if [[ $START_STEP -le 5 ]]; then
step "5/12  Install Redis"
###############################################################################
apt-get install -y -qq redis-server
sed -i 's/^supervised no/supervised systemd/' /etc/redis/redis.conf 2>/dev/null || true
systemctl enable --now redis-server
ok "Redis ready"
fi

###############################################################################
if [[ $START_STEP -le 6 ]]; then
step "6/12  Install Node.js ${NODE_MAJOR} & Caddy"
###############################################################################
# Node.js
if ! command -v node &>/dev/null || [[ "$(node -v | cut -d. -f1 | tr -d v)" -lt $NODE_MAJOR ]]; then
    curl -fsSL https://deb.nodesource.com/setup_${NODE_MAJOR}.x | bash - >/dev/null 2>&1
    apt-get install -y -qq nodejs
fi
ok "Node.js $(node -v)"

# Caddy
if ! command -v caddy &>/dev/null; then
    apt-get install -y -qq debian-keyring debian-archive-keyring apt-transport-https
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list >/dev/null
    apt-get update -qq && apt-get install -y -qq caddy
fi
ok "Caddy $(caddy version | head -c 12)"

# Composer
if ! command -v composer &>/dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer >/dev/null 2>&1
fi
ok "Composer $(composer --version 2>/dev/null | grep -oP '[\d.]+')"
fi

###############################################################################
if [[ $START_STEP -le 7 ]]; then
step "7/12  Clone repository"
###############################################################################
echo ""
echo -e "${YELLOW}Enter your GitHub Personal Access Token (PAT):${NC}"
read -rp "PAT: " GITHUB_PAT
echo ""

if [[ -z "$GITHUB_PAT" ]]; then
    fail "PAT cannot be empty"
fi

# Disable git terminal prompts so it doesn't ask for password on failure
export GIT_TERMINAL_PROMPT=0

AUTHED_REPO="https://x-access-token:${GITHUB_PAT}@github.com/codify-apps/flopos.git"

if [[ -d "$APP_DIR/.git" ]]; then
    warn "Repo already exists at ${APP_DIR}, pulling latest..."
    cd "$APP_DIR"
    git remote set-url origin "$AUTHED_REPO"
    git pull origin main
else
    rm -rf "$APP_DIR"
    git clone "$AUTHED_REPO" "$APP_DIR"
fi

# Remove PAT from remote URL after clone (security)
cd "$APP_DIR"
git remote set-url origin "$REPO"
ok "Code cloned to ${APP_DIR}"
fi

###############################################################################
if [[ $START_STEP -le 8 ]]; then
step "8/12  Setup PostgreSQL database"
###############################################################################
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASS}' CREATEDB;"

sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"

# Update password in case user already existed
sudo -u postgres psql -c "ALTER USER ${DB_USER} WITH PASSWORD '${DB_PASS}';"
ok "Database ${DB_NAME} ready (user: ${DB_USER})"
fi

###############################################################################
if [[ $START_STEP -le 9 ]]; then
step "9/12  Configure & deploy backend"
###############################################################################
cd "${APP_DIR}/backend"

# Install PHP dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Create production .env
cat > .env <<ENV
APP_NAME="Flo POS"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://${DOMAIN}
APP_TIMEZONE=Asia/Kolkata

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

TENANT_DB_CONNECTION=pgsql
TENANT_DB_HOST=127.0.0.1
TENANT_DB_PORT=5432

SESSION_DRIVER=redis
SESSION_LIFETIME=720
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=${DOMAIN}

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis

CACHE_STORE=redis
CACHE_PREFIX=flopos_cache

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=587
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@flopos.com"
MAIL_FROM_NAME="\${APP_NAME}"

JWT_SECRET=${JWT_SECRET}

VITE_APP_NAME="\${APP_NAME}"
ENV

# Generate app key
php artisan key:generate --force
ok "Backend .env configured"

# Run migrations
php artisan migrate --path=database/migrations/main --database=pgsql --force
ok "Main database migrated"

# Migrate existing tenant DBs if the command exists
php artisan tenants:migrate --force 2>/dev/null || true

# Cache everything for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
ok "Backend deployed"
fi

###############################################################################
if [[ $START_STEP -le 10 ]]; then
step "10/12  Build & deploy frontend"
###############################################################################
cd "${APP_DIR}/frontend"

# Production environment — API is on same domain proxied by Caddy
cat > .env.local <<ENV
NEXT_PUBLIC_API_URL=https://${DOMAIN}/api
ENV

npm ci
npm run build
ok "Frontend built"
fi

###############################################################################
if [[ $START_STEP -le 11 ]]; then
step "11/12  Configure services"
###############################################################################

# ─── Caddyfile ───────────────────────────────────────────────────────────────
cat > /etc/caddy/Caddyfile <<CADDY
${DOMAIN} {
    # Security headers
    header {
        X-Content-Type-Options    nosniff
        X-Frame-Options           SAMEORIGIN
        Referrer-Policy           strict-origin-when-cross-origin
        Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
        -Server
    }

    # API — Laravel via PHP-FPM
    handle /api/* {
        root * ${APP_DIR}/backend/public
        php_fastcgi unix//run/php/php${PHP_VERSION}-fpm.sock
        file_server
        encode gzip zstd
    }

    # Health check for uptime monitors
    handle /health {
        root * ${APP_DIR}/backend/public
        php_fastcgi unix//run/php/php${PHP_VERSION}-fpm.sock
        file_server
    }

    # Everything else — Next.js
    handle {
        reverse_proxy localhost:3000
    }
}
CADDY
ok "Caddyfile written"

# ─── Supervisor: Next.js ─────────────────────────────────────────────────────
cat > /etc/supervisor/conf.d/flopos-frontend.conf <<SUP
[program:flopos-frontend]
command=/usr/bin/npm run start
directory=${APP_DIR}/frontend
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/log/flopos-frontend.log
stderr_logfile=/var/log/flopos-frontend-error.log
environment=NODE_ENV="production",PORT="3000"
SUP

# ─── Supervisor: Laravel Queue Worker ────────────────────────────────────────
cat > /etc/supervisor/conf.d/flopos-worker.conf <<SUP
[program:flopos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${APP_DIR}/backend/artisan queue:work redis --sleep=3 --tries=3 --timeout=90 --max-jobs=500
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/flopos-worker.log
stopwaitsecs=3600
SUP

# ─── Cron: Laravel Scheduler ─────────────────────────────────────────────────
(crontab -u www-data -l 2>/dev/null; echo "* * * * * cd ${APP_DIR}/backend && php artisan schedule:run >> /dev/null 2>&1") | sort -u | crontab -u www-data -

# ─── File permissions ────────────────────────────────────────────────────────
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type f -exec chmod 644 {} \;
find "$APP_DIR" -type d -exec chmod 755 {} \;
chmod -R 775 "${APP_DIR}/backend/storage" "${APP_DIR}/backend/bootstrap/cache"

# Restart all services
supervisorctl reread
supervisorctl update
supervisorctl restart all 2>/dev/null || true
systemctl restart php${PHP_VERSION}-fpm
systemctl restart caddy
ok "All services configured & started"
fi

###############################################################################
if [[ $START_STEP -le 12 ]]; then
step "12/12  Firewall"
###############################################################################
ufw --force reset >/dev/null 2>&1
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP  (Caddy redirect)
ufw allow 443/tcp   # HTTPS
ufw --force enable
ok "Firewall enabled (SSH + HTTP/S only)"
fi

###############################################################################
echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  Flo POS deployed successfully!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "  ${CYAN}URL:${NC}        https://${DOMAIN}"
echo -e "  ${CYAN}API:${NC}        https://${DOMAIN}/api/health"
echo -e "  ${CYAN}DB User:${NC}    ${DB_USER}"
echo -e "  ${CYAN}DB Pass:${NC}    ${DB_PASS}"
echo -e "  ${CYAN}DB Name:${NC}    ${DB_NAME}"
echo ""
echo -e "  ${YELLOW}Save the DB password above — it won't be shown again.${NC}"
echo -e "  ${YELLOW}Secrets stored in ${SECRETS_FILE}${NC}"
echo ""
echo -e "  Useful commands:"
echo -e "    supervisorctl status            # check services"
echo -e "    caddy reload --config /etc/caddy/Caddyfile"
echo -e "    tail -f /var/log/flopos-*.log   # view logs"
echo -e "    cd ${APP_DIR}/backend && php artisan tinker"
echo -e "    bash deploy.sh <step>           # re-run from step N"
echo ""
###############################################################################
