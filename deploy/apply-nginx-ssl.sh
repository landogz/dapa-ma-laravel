#!/usr/bin/env bash
# =============================================================================
# Apply HTTPS-only (443) Nginx site for DAPE-MA
# Use when Network Admin blocks port 80 and certs are already on the server.
#
# Usage:
#   sudo bash deploy/apply-nginx-ssl.sh
#
# Optional overrides:
#   APP_DOMAIN=dapemade.ddb.gov.ph
#   APP_DIR=/var/www/dape-ma-laravel
#   SSL_CERTIFICATE=/etc/ssl/certs/dapemade.ddb.gov.ph.crt
#   SSL_CERTIFICATE_KEY=/etc/ssl/private/dapemade.ddb.gov.ph.key
#   PHP_VERSION=8.3
# =============================================================================
set -euo pipefail

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "Run as root: sudo bash $0" >&2
  exit 1
fi

APP_DOMAIN="${APP_DOMAIN:-dapemade.ddb.gov.ph}"
APP_DIR="${APP_DIR:-/var/www/dape-ma-laravel}"
PHP_VERSION="${PHP_VERSION:-8.3}"
PHP_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"

SSL_CERTIFICATE="${SSL_CERTIFICATE:-/etc/ssl/certs/${APP_DOMAIN}.crt}"
SSL_CERTIFICATE_KEY="${SSL_CERTIFICATE_KEY:-/etc/ssl/private/${APP_DOMAIN}.key}"

# Auto-detect common filenames if defaults missing
if [[ ! -f "${SSL_CERTIFICATE}" ]]; then
  for candidate in \
    "/etc/ssl/certs/${APP_DOMAIN}.pem" \
    "/etc/ssl/certs/${APP_DOMAIN}.fullchain.pem" \
    "/etc/ssl/certs/fullchain.pem" \
    "/etc/ssl/certs/${APP_DOMAIN}.cer"
  do
    if [[ -f "${candidate}" ]]; then
      SSL_CERTIFICATE="${candidate}"
      break
    fi
  done
fi

if [[ ! -f "${SSL_CERTIFICATE_KEY}" ]]; then
  for candidate in \
    "/etc/ssl/private/${APP_DOMAIN}.pem" \
    "/etc/ssl/private/${APP_DOMAIN}.key.pem" \
    "/etc/ssl/certs/${APP_DOMAIN}.key" \
    "/etc/ssl/private/privkey.pem"
  do
    if [[ -f "${candidate}" ]]; then
      SSL_CERTIFICATE_KEY="${candidate}"
      break
    fi
  done
fi

echo "Domain:     ${APP_DOMAIN}"
echo "App dir:    ${APP_DIR}"
echo "Cert:       ${SSL_CERTIFICATE}"
echo "Key:        ${SSL_CERTIFICATE_KEY}"

if [[ ! -f "${SSL_CERTIFICATE}" ]]; then
  echo "[error] SSL certificate not found. List files with: ls -la /etc/ssl/certs /etc/ssl/private" >&2
  echo "        Then re-run with SSL_CERTIFICATE=/path/to/cert SSL_CERTIFICATE_KEY=/path/to/key" >&2
  exit 1
fi

if [[ ! -f "${SSL_CERTIFICATE_KEY}" ]]; then
  echo "[error] SSL private key not found." >&2
  echo "        Re-run with SSL_CERTIFICATE_KEY=/path/to/key" >&2
  echo "        (Keys are often under /etc/ssl/private, not /etc/ssl/certs)" >&2
  exit 1
fi

if [[ ! -d "${APP_DIR}/public" ]]; then
  echo "[error] App public dir missing: ${APP_DIR}/public" >&2
  exit 1
fi

cat >/etc/nginx/sites-available/dape-ma <<NGINX
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ${APP_DOMAIN};

    ssl_certificate     ${SSL_CERTIFICATE};
    ssl_certificate_key ${SSL_CERTIFICATE_KEY};

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;

    root ${APP_DIR}/public;
    index index.php;

    charset utf-8;
    client_max_body_size 14M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

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

# Firewall: HTTPS only (port 80 not allowed by Network Admin)
if command -v ufw >/dev/null 2>&1; then
  ufw allow OpenSSH >/dev/null 2>&1 || true
  ufw allow 443/tcp >/dev/null 2>&1 || true
  ufw delete allow 'Nginx Full' >/dev/null 2>&1 || true
  ufw delete allow 80/tcp >/dev/null 2>&1 || true
  ufw allow 'Nginx HTTPS' >/dev/null 2>&1 || true
fi

nginx -t
systemctl reload nginx

# Keep Laravel APP_URL on https
if [[ -f "${APP_DIR}/.env" ]]; then
  if grep -qE '^APP_URL=' "${APP_DIR}/.env"; then
    sed -i "s|^APP_URL=.*|APP_URL=https://${APP_DOMAIN}|" "${APP_DIR}/.env"
  else
    echo "APP_URL=https://${APP_DOMAIN}" >> "${APP_DIR}/.env"
  fi
  (cd "${APP_DIR}" && php artisan config:cache) || true
fi

echo
echo "HTTPS-only Nginx applied for ${APP_DOMAIN}"
echo "Test: https://${APP_DOMAIN}/admin/login"
echo "API:  https://${APP_DOMAIN}/api/v1/health"
