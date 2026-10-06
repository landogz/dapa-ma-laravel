#!/usr/bin/env bash
# =============================================================================
# DAPE-MA Laravel — pull + migrate + cache (existing server)
# Usage: sudo bash /var/www/dape-ma-laravel/deploy/update.sh
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/dape-ma-laravel}"
GIT_BRANCH="${GIT_BRANCH:-main}"
WEB_USER="${WEB_USER:-www-data}"
SKIP_MIGRATE="${SKIP_MIGRATE:-0}"

cd "${APP_DIR}"

echo "[update] Fetching ${GIT_BRANCH}..."
git fetch origin
git checkout "${GIT_BRANCH}"
git pull --ff-only origin "${GIT_BRANCH}"

echo "[update] Composer..."
composer install --no-dev --optimize-autoloader --no-interaction

if [[ "${SKIP_MIGRATE}" != "1" ]]; then
  echo "[update] Migrate..."
  php artisan migrate --force
fi

php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R "${WEB_USER}:${WEB_USER}" "${APP_DIR}"
find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type d -exec chmod 775 {} \;

# Reload PHP-FPM (detect version)
if systemctl list-units --type=service --all | grep -q 'php8.3-fpm'; then
  systemctl reload php8.3-fpm
elif systemctl list-units --type=service --all | grep -q 'php8.2-fpm'; then
  systemctl reload php8.2-fpm
fi

supervisorctl restart dape-ma-queue:* 2>/dev/null || true

echo "[update] Done. App: ${APP_DIR}"
