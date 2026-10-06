#!/usr/bin/env bash
# =============================================================================
# DAPE-MA Laravel — Ubuntu 24.04 first-time server installer
# =============================================================================
# Installs: Nginx, PHP 8.3 (+ extensions), MySQL 8, Composer, Git
# Then: clones the app, creates DB/user, .env, migrate, caches, Nginx site
#
# Usage (as root or with sudo):
#   curl -fsSL https://raw.githubusercontent.com/landogz/dapa-ma-laravel/main/deploy/install-ubuntu.sh | sudo bash
#   # or copy this folder to the server and:
#   sudo bash deploy/install-ubuntu.sh
#
# Optional environment overrides before running:
#   APP_DOMAIN=api.example.com
#   APP_URL=https://api.example.com
#   GIT_REPO=https://github.com/landogz/dapa-ma-laravel.git
#   APP_DIR=/var/www/dape-ma-laravel
#   DB_DATABASE=DAPE_MA
#   DB_USERNAME=dape_ma_user
#   DB_PASSWORD='your-strong-password'   # generated if omitted
#   SKIP_CLONE=0                         # set 1 if code already present
#   SKIP_MIGRATE=0
#   ENABLE_SSL=0                         # set 1 + APP_DOMAIN for Certbot
# =============================================================================
set -euo pipefail

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "Run as root: sudo bash $0" >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

APP_NAME="${APP_NAME:-DAPE-MA}"
APP_DIR="${APP_DIR:-/var/www/dape-ma-laravel}"
GIT_REPO="${GIT_REPO:-https://github.com/landogz/dapa-ma-laravel.git}"
GIT_BRANCH="${GIT_BRANCH:-main}"
PHP_VERSION="${PHP_VERSION:-8.3}"
WEB_USER="${WEB_USER:-www-data}"

DB_DATABASE="${DB_DATABASE:-DAPE_MA}"
DB_USERNAME="${DB_USERNAME:-dape_ma_user}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-}"

# Detect public/private IP for default APP_URL when no domain is set
PRIMARY_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
APP_DOMAIN="${APP_DOMAIN:-}"
if [[ -n "${APP_DOMAIN}" ]]; then
  APP_URL="${APP_URL:-https://${APP_DOMAIN}}"
else
  APP_URL="${APP_URL:-http://${PRIMARY_IP}}"
fi

SKIP_CLONE="${SKIP_CLONE:-0}"
# Prefer the browser installer UI at /install/ for .env + migrate on first boot.
WEB_INSTALLER="${WEB_INSTALLER:-1}"
SKIP_MIGRATE="${SKIP_MIGRATE:-$WEB_INSTALLER}"
ENABLE_SSL="${ENABLE_SSL:-0}"
INSTALL_NODE="${INSTALL_NODE:-0}"   # assets are usually pre-built in public/build/

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="/var/log/dape-ma-install.log"
mkdir -p "$(dirname "$LOG_FILE")"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "============================================================"
echo " DAPE-MA install — Ubuntu $(lsb_release -rs 2>/dev/null || echo '?')"
echo " App dir:  ${APP_DIR}"
echo " App URL:  ${APP_URL}"
echo " PHP:      ${PHP_VERSION}"
echo " Log:      ${LOG_FILE}"
echo "============================================================"

random_secret() {
  openssl rand -base64 32 | tr -d '/+=' | head -c 32
}

if [[ -z "${DB_PASSWORD}" ]]; then
  DB_PASSWORD="$(random_secret)"
  echo "[info] Generated DB_PASSWORD (saved to ${APP_DIR}/.install-credentials later)"
fi

# ---------------------------------------------------------------------------
# 1) System packages
# ---------------------------------------------------------------------------
echo "[1/8] Installing system packages..."
apt-get update -y
apt-get upgrade -y
apt-get install -y \
  software-properties-common \
  ca-certificates \
  curl \
  wget \
  gnupg \
  lsb-release \
  git \
  unzip \
  zip \
  ufw \
  nginx \
  mysql-server \
  supervisor \
  openssl

# PHP from Ubuntu 24.04 (8.3) — no PPA required on 24.04
apt-get install -y \
  "php${PHP_VERSION}-fpm" \
  "php${PHP_VERSION}-cli" \
  "php${PHP_VERSION}-common" \
  "php${PHP_VERSION}-mysql" \
  "php${PHP_VERSION}-pgsql" \
  "php${PHP_VERSION}-sqlite3" \
  "php${PHP_VERSION}-mbstring" \
  "php${PHP_VERSION}-xml" \
  "php${PHP_VERSION}-curl" \
  "php${PHP_VERSION}-zip" \
  "php${PHP_VERSION}-bcmath" \
  "php${PHP_VERSION}-gd" \
  "php${PHP_VERSION}-intl" \
  "php${PHP_VERSION}-tokenizer" \
  "php${PHP_VERSION}-opcache" \
  "php${PHP_VERSION}-readline"

# Optional Node for building assets on the server
if [[ "${INSTALL_NODE}" == "1" ]]; then
  echo "[1b] Installing Node.js 20 LTS..."
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi

# Composer
if ! command -v composer >/dev/null 2>&1; then
  echo "[1c] Installing Composer..."
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# PHP production tweaks
PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
if [[ -f "${PHP_INI}" ]]; then
  sed -i 's/^memory_limit = .*/memory_limit = 256M/' "${PHP_INI}"
  sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 12M/' "${PHP_INI}"
  sed -i 's/^post_max_size = .*/post_max_size = 14M/' "${PHP_INI}"
  sed -i 's/^max_execution_time = .*/max_execution_time = 120/' "${PHP_INI}"
  sed -i 's/^;cgi.fix_path = 0/cgi.fix_path = 1/' "${PHP_INI}" || true
fi

systemctl enable --now nginx
systemctl enable --now "php${PHP_VERSION}-fpm"
systemctl enable --now mysql

# ---------------------------------------------------------------------------
# 2) Firewall (allow SSH + HTTP/HTTPS)
# ---------------------------------------------------------------------------
echo "[2/8] Configuring UFW..."
ufw allow OpenSSH >/dev/null 2>&1 || true
ufw allow 'Nginx Full' >/dev/null 2>&1 || true
ufw --force enable >/dev/null 2>&1 || true

# ---------------------------------------------------------------------------
# 3) MySQL database + user
# ---------------------------------------------------------------------------
echo "[3/8] Creating MySQL database and user..."
mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';
FLUSH PRIVILEGES;
SQL

# ---------------------------------------------------------------------------
# 4) Clone / refresh application
# ---------------------------------------------------------------------------
echo "[4/8] Application code at ${APP_DIR}..."
mkdir -p "$(dirname "${APP_DIR}")"

if [[ "${SKIP_CLONE}" != "1" ]]; then
  if [[ -d "${APP_DIR}/.git" ]]; then
    echo "  Existing git repo — pulling ${GIT_BRANCH}..."
    git -C "${APP_DIR}" fetch origin
    git -C "${APP_DIR}" checkout "${GIT_BRANCH}"
    git -C "${APP_DIR}" pull --ff-only origin "${GIT_BRANCH}"
  elif [[ -d "${APP_DIR}" ]] && [[ -f "${APP_DIR}/artisan" ]]; then
    echo "  Code present without .git — skipping clone (set SKIP_CLONE=0 and empty dir to re-clone)."
  else
    rm -rf "${APP_DIR}"
    git clone --branch "${GIT_BRANCH}" --depth 1 "${GIT_REPO}" "${APP_DIR}"
  fi
fi

if [[ ! -f "${APP_DIR}/artisan" ]]; then
  echo "[error] Laravel app not found at ${APP_DIR} (missing artisan)." >&2
  exit 1
fi

cd "${APP_DIR}"

# ---------------------------------------------------------------------------
# 5) .env
# ---------------------------------------------------------------------------
echo "[5/8] Writing .env..."
if [[ ! -f .env ]]; then
  if [[ -f .env.example ]]; then
    cp .env.example .env
  elif [[ -f "${SCRIPT_DIR}/env.production.example" ]]; then
    cp "${SCRIPT_DIR}/env.production.example" .env
  else
    touch .env
  fi
fi

set_env() {
  local key="$1"
  local value="$2"
  if grep -qE "^${key}=" .env; then
    # Escape sed replacement carefully
    local escaped
    escaped="$(printf '%s' "${value}" | sed -e 's/[\\/&]/\\&/g')"
    sed -i "s|^${key}=.*|${key}=${escaped}|" .env
  else
    printf '%s=%s\n' "${key}" "${value}" >> .env
  fi
}

set_env APP_NAME "\"${APP_NAME}\""
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "${APP_URL}"
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "${DB_DATABASE}"
set_env DB_USERNAME "${DB_USERNAME}"
set_env DB_PASSWORD "${DB_PASSWORD}"
set_env SESSION_DRIVER database
set_env CACHE_STORE database
set_env QUEUE_CONNECTION database
set_env FILESYSTEM_DISK local
set_env LOG_CHANNEL stack
set_env LOG_LEVEL warning

# ---------------------------------------------------------------------------
# 6) Composer + Laravel setup
# ---------------------------------------------------------------------------
echo "[6/8] Composer install + Laravel setup..."
composer install --no-dev --optimize-autoloader --no-interaction

if ! grep -qE '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

php artisan storage:link || true

if [[ "${SKIP_MIGRATE}" != "1" ]]; then
  php artisan migrate --force
  # Mark installed so / redirects to admin (web installer also writes this).
  mkdir -p storage/app
  echo "{\"installed_at\":\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\",\"via\":\"install-ubuntu.sh\"}" > storage/app/installed
fi

# Prefer committed Vite build; only npm-build when explicitly requested
if [[ "${INSTALL_NODE}" == "1" ]] && [[ ! -f public/build/manifest.json ]]; then
  echo "  Building frontend assets..."
  npm ci || npm install --include=dev
  npm run build
fi

php artisan optimize:clear
if [[ "${WEB_INSTALLER}" != "1" ]]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

chown -R "${WEB_USER}:${WEB_USER}" "${APP_DIR}"
find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type d -exec chmod 775 {} \;
find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type f -exec chmod 664 {} \;

# ---------------------------------------------------------------------------
# 7) Nginx site
# ---------------------------------------------------------------------------
echo "[7/8] Configuring Nginx..."
PHP_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
SERVER_NAME="${APP_DOMAIN:-_}"

cat >/etc/nginx/sites-available/dape-ma <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${SERVER_NAME};

    root ${APP_DIR}/public;
    index index.php;

    charset utf-8;
    client_max_body_size 14M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \\.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
        # Pass Authorization header for Sanctum Bearer tokens
        fastcgi_param HTTP_AUTHORIZATION \$http_authorization;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/dape-ma-access.log;
    error_log  /var/log/nginx/dape-ma-error.log;
}
NGINX

ln -sfn /etc/nginx/sites-available/dape-ma /etc/nginx/sites-enabled/dape-ma
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
systemctl restart "php${PHP_VERSION}-fpm"

# ---------------------------------------------------------------------------
# 8) Optional SSL + queue worker + scheduler
# ---------------------------------------------------------------------------
echo "[8/8] Optional services..."

# Queue worker via Supervisor
cat >/etc/supervisor/conf.d/dape-ma-queue.conf <<SUPERVISOR
[program:dape-ma-queue]
process_name=%(program_name)s_%(process_num)02d
command=php ${APP_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${WEB_USER}
numprocs=1
redirect_stderr=true
stdout_logfile=${APP_DIR}/storage/logs/queue-worker.log
stopwaitsecs=3600
SUPERVISOR
supervisorctl reread
supervisorctl update
supervisorctl start dape-ma-queue:* || supervisorctl restart dape-ma-queue:* || true

# Laravel scheduler
CRON_LINE="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"
(crontab -u "${WEB_USER}" -l 2>/dev/null | grep -v 'artisan schedule:run'; echo "${CRON_LINE}") \
  | crontab -u "${WEB_USER}" - || true

if [[ "${ENABLE_SSL}" == "1" ]] && [[ -n "${APP_DOMAIN}" ]]; then
  echo "  Enabling Let's Encrypt for ${APP_DOMAIN}..."
  apt-get install -y certbot python3-certbot-nginx
  certbot --nginx -d "${APP_DOMAIN}" --non-interactive --agree-tos \
    --register-unsafely-without-email --redirect || true
  set_env APP_URL "https://${APP_DOMAIN}"
  php artisan config:cache
fi

# Save credentials (root-only)
CRED_FILE="${APP_DIR}/.install-credentials"
umask 077
cat >"${CRED_FILE}" <<CRED
# Generated $(date -u +%Y-%m-%dT%H:%M:%SZ) — keep private (chmod 600)
APP_DIR=${APP_DIR}
APP_URL=${APP_URL}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}
CRED
chown root:root "${CRED_FILE}"
chmod 600 "${CRED_FILE}"

echo
echo "============================================================"
echo " INSTALL COMPLETE"
echo "============================================================"
echo " App URL:     ${APP_URL}"
echo " Admin login: ${APP_URL%/}/admin/login"
echo " API base:    ${APP_URL%/}/api/v1"
echo " App path:    ${APP_DIR}"
echo " Credentials: ${CRED_FILE}"
echo
echo " Next steps:"
if [[ "${WEB_INSTALLER}" == "1" ]]; then
  echo "  1) Open the web installer: ${APP_URL%/}/install/"
  echo "     Complete App URL + MySQL, then migrate/seed from the UI."
else
  echo "  1) Open admin: ${APP_URL%/}/admin/login"
fi
echo "  2) Point DNS A record to this server (if using a domain)."
echo "  3) Re-run with ENABLE_SSL=1 APP_DOMAIN=your.domain for HTTPS."
echo "  4) Update mobile Endpoints.baseUrl to https://your.domain/api/v1"
echo "  5) Later deploys: sudo bash ${APP_DIR}/deploy/update.sh"
echo "============================================================"
