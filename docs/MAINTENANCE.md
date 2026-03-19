# Flo POS — Server Maintenance Guide

## Process Managers

Two process managers are used. Do **not** use both for the same service.

| Service | Manager | Restart command |
|---|---|---|
| Next.js frontend | **Supervisor** | `supervisorctl restart flopos-frontend` |
| Laravel queue workers | **Supervisor** | `supervisorctl restart flopos-worker:*` |
| Laravel scheduler | Cron (`www-data`) | — |
| PHP-FPM | systemd | `systemctl restart php8.4-fpm` |
| Caddy / Nginx | systemd | `systemctl restart caddy` or `systemctl restart nginx` |

> **Warning:** Do not use PM2 to start the Next.js frontend. It will conflict with
> Supervisor, causing `EADDRINUSE` errors on port 3000 and the app will appear stuck
> on "Loading…". PM2 may still be installed on the system but must not manage the
> `flopos-frontend` service.

---

## Deploying Frontend Changes

```bash
cd /var/www/flopos/frontend

# 1. Pull latest code
git pull

# 2. Install any new dependencies
npm install

# 3. Build
npm run build

# 4. Restart the frontend server (Supervisor is the process manager)
supervisorctl restart flopos-frontend

# 5. Verify it's running
supervisorctl status flopos-frontend
```

---

## Deploying Backend Changes

```bash
cd /var/www/flopos/backend

# 1. Pull latest code (if not already done above)
git pull

# 2. Install PHP dependencies (if composer.json changed)
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php artisan migrate --force

# 4. Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Restart PHP-FPM to clear opcache (REQUIRED after any PHP file change)
systemctl restart php8.4-fpm

# 6. Restart queue workers so they pick up code changes
supervisorctl restart flopos-worker:*
```

> **Opcache note:** PHP-FPM caches compiled PHP. Changing a `.php` file without
> restarting FPM means the web server keeps serving the old code. CLI (`artisan`,
> `tinker`) is unaffected because it runs without opcache.

---

## Logs

### Frontend (Next.js)
```bash
# Live tail
tail -f /var/log/flopos-frontend.log
tail -f /var/log/flopos-frontend-error.log

# Last 50 lines of errors
tail -50 /var/log/flopos-frontend-error.log
```

### Backend (Laravel)
```bash
tail -f /var/www/flopos/backend/storage/logs/laravel.log
```

### Queue Workers
```bash
tail -f /var/log/flopos-worker.log
```

### PHP-FPM
```bash
tail -f /var/log/php8.4-fpm.log
```

### Web server
```bash
# Caddy
journalctl -u caddy -f

# Nginx (if used instead)
tail -f /var/log/nginx/error.log
```

---

## Status Check (quick health)

```bash
supervisorctl status          # All supervised processes
systemctl status caddy        # or nginx
systemctl status php8.4-fpm
curl -s -o /dev/null -w "%{http_code}" http://localhost:3000/
```

---

## Self-Healing

Supervisor is configured with `autorestart=true`. If the frontend crashes it will
automatically restart. To verify the configuration:

```bash
cat /etc/supervisor/conf.d/flopos-frontend.conf
supervisorctl reread   # pick up config file changes
supervisorctl update   # apply them
```

If Supervisor itself needs to restart:
```bash
systemctl restart supervisor
```

---

## Common Problems

### App stuck on "Loading…" / port 3000 conflict

**Symptom:** `EADDRINUSE: address already in use :::3000` in
`/var/log/flopos-frontend-error.log`.

**Cause:** A stale Next.js process from a previous session (e.g. a PM2-managed instance)
is holding port 3000 while Supervisor tries to start a new one.

**Fix:**
```bash
# Find what holds port 3000
fuser 3000/tcp

# Kill it (replace <PID> with the output above)
kill <PID>

# Then restart via Supervisor
supervisorctl restart flopos-frontend
```

### Frontend serves stale code after a build

Always run `supervisorctl restart flopos-frontend` after `npm run build`. The build
only writes new files; the running server process must be restarted to serve them.

### PHP changes not reflected on the web

Run `systemctl restart php8.4-fpm`. The CLI (`artisan`, `tinker`) always uses fresh
code — the web server won't until FPM is restarted.

### Queue jobs not processing

```bash
supervisorctl status flopos-worker:*
supervisorctl restart flopos-worker:*

# Check for failed jobs
cd /var/www/flopos/backend && php artisan queue:failed
```
