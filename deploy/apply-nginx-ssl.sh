#!/usr/bin/env bash
# =============================================================================
# Apply HTTPS-only (443) Nginx site for DAPE-MA
# Use when Network Admin blocks port 80.
#
# On DDB servers, site certs are often left in:
#   /home/*/Installer/cert/ddb.gov.ph.crt + ddb.gov.ph.key  (wildcard *.ddb.gov.ph)
# This script finds them, installs into /etc/ssl, then configures Nginx 443-only.
#
# Usage:
#   sudo bash deploy/apply-nginx-ssl.sh
#
# Optional overrides:
#   APP_DOMAIN=dapemade.ddb.gov.ph
#   APP_DIR=/var/www/dape-ma-laravel
#   SSL_CERTIFICATE=/path/to/cert
#   SSL_CERTIFICATE_KEY=/path/to/key
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

TARGET_CERT="/etc/ssl/certs/${APP_DOMAIN}.crt"
TARGET_KEY="/etc/ssl/private/${APP_DOMAIN}.key"

SSL_CERTIFICATE="${SSL_CERTIFICATE:-}"
SSL_CERTIFICATE_KEY="${SSL_CERTIFICATE_KEY:-}"

find_pair() {
  local cert="$1"
  local key="$2"
  if [[ -f "${cert}" && -f "${key}" ]]; then
    SSL_CERTIFICATE="${cert}"
    SSL_CERTIFICATE_KEY="${key}"
    return 0
  fi
  return 1
}

# 1) Explicit paths already set
# 2) Standard installed paths
# 3) DDB Installer drop folder (real server layout)
# 4) Other common names
if [[ -z "${SSL_CERTIFICATE}" || -z "${SSL_CERTIFICATE_KEY}" ]]; then
  if find_pair "${TARGET_CERT}" "${TARGET_KEY}"; then
    :
  elif find_pair "/home/dapemade/Installer/cert/ddb.gov.ph.crt" "/home/dapemade/Installer/cert/ddb.gov.ph.key"; then
    :
  else
    shopt -s nullglob
    for dir in /home/*/Installer/cert /root/Installer/cert /opt/ssl /etc/ssl/ddb; do
      [[ -d "${dir}" ]] || continue
      for cert in \
        "${dir}/ddb.gov.ph.crt" \
        "${dir}/${APP_DOMAIN}.crt" \
        "${dir}"/*.crt \
        "${dir}"/*fullchain*.pem \
        "${dir}"/*.pem
      do
        [[ -f "${cert}" ]] || continue
        base="${cert%.*}"
        for key in "${base}.key" "${dir}/ddb.gov.ph.key" "${dir}/${APP_DOMAIN}.key" "${dir}"/*.key; do
          if [[ -f "${key}" ]] && find_pair "${cert}" "${key}"; then
            break 2
          fi
        done
      done
    done
    shopt -u nullglob
  fi
fi

# Fallbacks under /etc/ssl if still empty
if [[ -z "${SSL_CERTIFICATE}" || ! -f "${SSL_CERTIFICATE}" ]]; then
  for candidate in \
    "/etc/ssl/certs/${APP_DOMAIN}.pem" \
    "/etc/ssl/certs/${APP_DOMAIN}.fullchain.pem" \
    "/etc/ssl/certs/ddb.gov.ph.crt" \
    "/etc/ssl/certs/fullchain.pem"
  do
    if [[ -f "${candidate}" ]]; then
      SSL_CERTIFICATE="${candidate}"
      break
    fi
  done
fi

if [[ -z "${SSL_CERTIFICATE_KEY}" || ! -f "${SSL_CERTIFICATE_KEY}" ]]; then
  for candidate in \
    "/etc/ssl/private/${APP_DOMAIN}.pem" \
    "/etc/ssl/private/ddb.gov.ph.key" \
    "/etc/ssl/private/privkey.pem" \
    "/etc/ssl/certs/${APP_DOMAIN}.key" \
    "/etc/ssl/certs/ddb.gov.ph.key"
  do
    if [[ -f "${candidate}" ]]; then
      SSL_CERTIFICATE_KEY="${candidate}"
      break
    fi
  done
fi

echo "Domain:     ${APP_DOMAIN}"
echo "App dir:    ${APP_DIR}"
echo "Cert:       ${SSL_CERTIFICATE:-"(not found)"}"
echo "Key:        ${SSL_CERTIFICATE_KEY:-"(not found)"}"

if [[ -z "${SSL_CERTIFICATE:-}" || ! -f "${SSL_CERTIFICATE}" ]]; then
  echo "[error] Site SSL certificate not found." >&2
  echo "  /etc/ssl/certs usually only has system CA roots — that is NOT your site cert." >&2
  echo "  Look for files like:" >&2
  echo "    /home/*/Installer/cert/ddb.gov.ph.crt" >&2
  echo "    /home/*/Installer/cert/ddb.gov.ph.key" >&2
  echo "  Then either re-run (auto-detect) or:" >&2
  echo "    sudo SSL_CERTIFICATE=/real/cert.crt SSL_CERTIFICATE_KEY=/real/key.key bash deploy/apply-nginx-ssl.sh" >&2
  exit 1
fi

if [[ -z "${SSL_CERTIFICATE_KEY:-}" || ! -f "${SSL_CERTIFICATE_KEY}" ]]; then
  echo "[error] SSL private key not found." >&2
  echo "  Private keys are often under /etc/ssl/private or next to the .crt in Installer/cert/." >&2
  exit 1
fi

# Install into standard paths for Nginx (if source is elsewhere)
mkdir -p /etc/ssl/private
if [[ "${SSL_CERTIFICATE}" != "${TARGET_CERT}" ]]; then
  cp -f "${SSL_CERTIFICATE}" "${TARGET_CERT}"
  SSL_CERTIFICATE="${TARGET_CERT}"
fi
if [[ "${SSL_CERTIFICATE_KEY}" != "${TARGET_KEY}" ]]; then
  cp -f "${SSL_CERTIFICATE_KEY}" "${TARGET_KEY}"
  SSL_CERTIFICATE_KEY="${TARGET_KEY}"
fi
chmod 644 "${SSL_CERTIFICATE}"
chmod 600 "${SSL_CERTIFICATE_KEY}"

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
echo "  Cert installed: ${SSL_CERTIFICATE}"
echo "  Key installed:  ${SSL_CERTIFICATE_KEY}"
echo "Test: https://${APP_DOMAIN}/admin/login"
echo "API:  https://${APP_DOMAIN}/api/v1/health"
echo
echo "Note: port 80 is not configured (Network Admin policy)."
echo "If the app returns 500, check DB_HOST in .env (remote MySQL is often not 127.0.0.1)."
