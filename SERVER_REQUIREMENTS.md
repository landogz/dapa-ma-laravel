# DAPE-MA Laravel — Server Requirements

This document describes hosting requirements for **dape-ma-laravel** (admin SPA + `/api/v1` for mobile). Use **Apache** (XAMPP/local) or **Nginx** (typical production) with **PHP 8.2+** and **MySQL/MariaDB**.

---

## 1. Stack summary

| Component | Requirement |
|-----------|-------------|
| PHP | **8.2 or newer** (`composer.json`: `^8.2`, Laravel 12) |
| Database | **MySQL 8.0+** or **MariaDB 10.6+** (charset `utf8mb4`) |
| Web server | **Apache 2.4+** (`mod_rewrite`) **or** **Nginx 1.18+** |
| Composer | 2.x (install/update PHP dependencies) |
| Node.js | **20 LTS+** — **build time only** (`npm run build`); production can serve committed `public/build/` |
| TLS | **HTTPS required** in production (Sanctum Bearer tokens, admin session) |

---

## 2. PHP extensions

Enable in `php.ini` (XAMPP: `xamppfiles/etc/php.ini`):

| Extension | Purpose |
|-----------|---------|
| `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`, `tokenizer`, `xml` | Laravel core |
| **`pdo_mysql`** | MySQL database |
| **`gd`** or **`imagick`** | Profile photos, post media uploads |
| **`zip`** | Composer |
| **`intl`** | Recommended |
| **`opcache`** | Strongly recommended in production |

### Recommended `php.ini` values

```ini
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 120
max_input_vars = 3000
```

---

## 3. Application paths (this repo)

| Path | Role |
|------|------|
| Project root | `/Applications/XAMPP/xamppfiles/htdocs/DAPE-MA/dape-ma-laravel` (local XAMPP example) |
| **Document root** | `{project}/public` — **must** be the web root |
| Writable | `{project}/storage`, `{project}/bootstrap/cache` |
| Built assets | `{project}/public/build` (from `npm run build`) |
| Public uploads | `{project}/storage/app/public` → symlink `public/storage` |

---

## 4. Environment (`.env`)

Production example:

```env
APP_NAME="DAPE-MA"
APP_ENV=production
APP_KEY=base64:...          # php artisan key:generate
APP_DEBUG=false
APP_URL=https://your-domain.gov.ph

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=DAPE-MA
DB_USERNAME=dape_ma_user
DB_PASSWORD=strong_password_here

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local
LOG_CHANNEL=stack
LOG_LEVEL=warning
```

---

## 5. GitHub deployment (git clone)

Repository: **https://github.com/landogz/dapa-ma-laravel**

### First-time deploy on the server

```bash
# 1. Install git if needed (Ubuntu/Debian example)
sudo apt update && sudo apt install -y git

# 2. Clone into your web root parent directory
sudo mkdir -p /var/www
cd /var/www

sudo git clone https://github.com/landogz/dapa-ma-laravel.git dape-ma-laravel
cd dape-ma-laravel

# 3. Use the main branch (or a release tag)
git checkout main
git pull origin main
```

### SSH clone (recommended for private repos or CI)

```bash
cd /var/www
git clone git@github.com:landogz/dapa-ma-laravel.git dape-ma-laravel
cd dape-ma-laravel
git checkout main
```

Generate a deploy key on the server, add the public key to GitHub → **Settings → Deploy keys** (read-only is enough for pull deploys).

### Update an existing deployment

```bash
cd ~/www/dapa-ma-laravel   # or /var/www/dape-ma-laravel

git fetch origin
git checkout main
git pull origin main

composer install --no-dev --optimize-autoloader

# Frontend assets — see "PaaS / shared hosting" below (often skip npm on server)
# npm ci && npm run build

php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### PaaS / shared hosting (no root, no `sudo`)

Many hosts (e.g. **AlwaysData**, **Plesk**, **cPanel**, managed PHP) **do not allow `sudo`**. That is normal.

**Recommended:** Do **not** run `npm` on the server. This repo ships **pre-built** assets in `public/build/` (committed to Git). After `git pull`, verify:

```bash
cd ~/www/dapa-ma-laravel
ls public/build/manifest.json
ls public/build/assets/
```

If those files exist, **skip `npm install` and `npm run build`** — Laravel will load CSS/JS from `public/build/`.

**If you must build on the server** (assets missing or you changed frontend code):

```bash
cd ~/www/dapa-ma-laravel

# Never use sudo on PaaS
npm install --include=dev
npm run build
```

> On some hosts, `NODE_ENV=production` skips devDependencies and **`vite` will not install**. Use `npm install --include=dev` (npm 7+) or `NODE_ENV=development npm install` before `npm run build`.

**Best practice for PaaS:** Build on your **local machine** or **GitHub Actions**, commit/push `public/build/`, then on the server only run:

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

**`vite: not found`** means `node_modules` was never installed (or Vite was skipped). Fix: use pre-built assets from Git, or run `npm install --include=dev` **without sudo**.

### Deploy user & permissions (Linux)

Run git/composer as a deploy user, then fix ownership for the web server:

```bash
sudo chown -R deploy:www-data /var/www/dape-ma-laravel
sudo chmod -R 775 /var/www/dape-ma-laravel/storage
sudo chmod -R 775 /var/www/dape-ma-laravel/bootstrap/cache
```

Point Apache/Nginx **DocumentRoot** to `/var/www/dape-ma-laravel/public` (see sections 8–9).

> **Do not commit `.env`** — create it on the server after clone (`cp .env.example .env`).

---

## 6. Deploy commands

```bash
cd /var/www/dape-ma-laravel

composer install --no-dev --optimize-autoloader
cp .env.example .env   # then edit .env
php artisan key:generate
php artisan migrate --force
php artisan storage:link

# Assets (on build server or CI)
npm ci
npm run build

# Production caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Clear caches after .env changes
php artisan optimize:clear
```

### Queue worker (optional)

If using `QUEUE_CONNECTION=database`, run a persistent worker (Supervisor example):

```ini
[program:dape-ma-queue]
command=php /var/www/dape-ma-laravel/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
```

### Scheduler (optional)

```cron
* * * * * cd /var/www/dape-ma-laravel && php artisan schedule:run >> /dev/null 2>&1
```

---

## 7. Apache (XAMPP & production)

### Required modules

- `mod_rewrite` (required)
- `mod_headers` (recommended)
- `mod_ssl` (HTTPS)

### XAMPP — Virtual host (local)

Edit `xamppfiles/etc/extra/httpd-vhosts.conf` (or `httpd.conf`):

```apache
<VirtualHost *:80>
    ServerName dape-ma.local
    DocumentRoot "/Applications/XAMPP/xamppfiles/htdocs/DAPE-MA/dape-ma-laravel/public"

    <Directory "/Applications/XAMPP/xamppfiles/htdocs/DAPE-MA/dape-ma-laravel/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/dape-ma-error.log"
    CustomLog "logs/dape-ma-access.log" common
</VirtualHost>
```

Add to `/etc/hosts` (macOS/Linux):

```
127.0.0.1   dape-ma.local
```

Restart Apache. Open: `http://dape-ma.local/admin/login`

> **Note:** `public/.htaccess` already passes the `Authorization` header for **Sanctum Bearer** tokens. Keep `AllowOverride All`.

### Production — HTTPS virtual host

```apache
<VirtualHost *:443>
    ServerName admin.yourdomain.gov.ph
    DocumentRoot /var/www/dape-ma-laravel/public

    <Directory /var/www/dape-ma-laravel/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    SSLEngine on
    SSLCertificateFile      /etc/ssl/certs/your-fullchain.pem
    SSLCertificateKeyFile   /etc/ssl/private/your-privkey.pem

    # Security headers (adjust CSP if needed)
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    ErrorLog ${APACHE_LOG_DIR}/dape-ma-error.log
    CustomLog ${APACHE_LOG_DIR}/dape-ma-access.log combined
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName admin.yourdomain.gov.ph
    Redirect permanent / https://admin.yourdomain.gov.ph/
</VirtualHost>
```

### Linux permissions (Apache `www-data`)

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 8. Nginx (production)

### Server block

Replace paths, domain, and PHP-FPM socket for your server.

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name admin.yourdomain.gov.ph;

    root /var/www/dape-ma-laravel/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/admin.yourdomain.gov.ph/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/admin.yourdomain.gov.ph/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    charset utf-8;
    client_max_body_size 12M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # Required for Laravel Sanctum Bearer authentication
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|webp|woff2?)$ {
        expires 7d;
        access_log off;
    }
}

server {
    listen 80;
    listen [::]:80;
    server_name admin.yourdomain.gov.ph;
    return 301 https://$host$request_uri;
}
```

### PHP-FPM pool (snippet)

```ini
[www]
user = www-data
group = www-data
listen = /run/php/php8.2-fpm.sock
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
```

---

## 9. Database

- Engine: **InnoDB**
- Charset: **utf8mb4**, collation **utf8mb4_unicode_ci**
- User: grant only required privileges on the `DAPE-MA` (or your) database

```sql
CREATE DATABASE `DAPE-MA` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dape_ma_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP
  ON `DAPE-MA`.* TO 'dape_ma_user'@'localhost';
FLUSH PRIVILEGES;
```

---

## 10. External services (runtime)

The admin rehab map uses third-party services over HTTPS:

| Service | Use |
|---------|-----|
| OpenStreetMap tiles | Map display |
| Nominatim (OpenStreetMap) | Address search / reverse geocoding |

Ensure the server allows **outbound HTTPS** (firewall/proxy). Respect [Nominatim usage policy](https://operations.osmfoundation.org/policies/nominatim/) in production (rate limits, caching).

---

## 11. Hosting server (hardware & sizing)

Typical setup: **one VPS or dedicated host** running the web stack (Apache or Nginx + PHP-FPM), **MySQL on the same server** (small/medium) or a **managed database** (larger production). The Flutter/mobile app calls the same host over HTTPS — no separate app server is required.

### What runs on the hosting server

| Service | Required | Notes |
|---------|----------|--------|
| Web server | Yes | Apache **or** Nginx; document root = `public/` |
| PHP-FPM 8.2+ | Yes | Runs Laravel |
| MySQL / MariaDB | Yes | Application + sessions/cache/queue tables |
| Node.js | Build only | Run `npm run build` on deploy; not needed at runtime if `public/build` is deployed |
| Redis | Optional | Faster cache/queue if you enable it in `.env` |
| Queue worker | Optional | `php artisan queue:work` if using database queue |
| SSL (Let’s Encrypt / org cert) | Yes (production) | Terminate HTTPS on the host or load balancer |

### Recommended hosting tiers

| Tier | Use case | vCPU | RAM | Disk (SSD) | Bandwidth |
|------|----------|------|-----|------------|-----------|
| **Development** (XAMPP/local) | Local coding & testing | 2 cores | 4 GB | 10 GB+ free | N/A |
| **Starter production** | Pilot, low admin + API traffic, &lt; ~500 daily API users | 2 vCPU | **4 GB** | **40 GB** | 1–2 TB/mo |
| **Standard production** | Live admin portal + mobile API, regular uploads | **4 vCPU** | **8 GB** | **80 GB** | 2–4 TB/mo |
| **High availability** | Busier API, analytics, queue workers, backups | **4–8 vCPU** | **16 GB** | **160 GB+** | 4+ TB/mo or unmetered |

> **RAM:** MySQL and PHP-FPM share memory. Below **4 GB** on production, enable **swap** and keep OPcache on; **8 GB** is the practical minimum for comfortable production with admin + API together.

### Disk planning (production)

| Item | Approx. space |
|------|----------------|
| Application + `vendor/` | 200–400 MB |
| `node_modules` (if kept on server) | 300–500 MB — prefer building assets in CI and omitting `node_modules` on prod |
| Database (MySQL) | 1–5 GB+ (grows with posts, analytics, users) |
| `storage/app` (uploads) | Plan **5–20 GB+** for media/profile images |
| Logs & backups | **10–20%** of total disk reserved |

### OS & hosting providers

- **OS:** Ubuntu **22.04 LTS** or **24.04 LTS** (recommended), or RHEL/AlmaLinux for enterprise hosts.
- **Providers:** Any VPS with root/SSH works (AWS EC2, DigitalOcean, Linode, Vultr, Azure VM, on-prem VM).
- **Not suitable alone:** Static-only hosting (no PHP, no MySQL) — Laravel must run on a **PHP + MySQL** capable server.

### Network & firewall (hosting)

- Open inbound: **80** (redirect to HTTPS), **443** (HTTPS).
- **Do not** expose MySQL (**3306**) to the public internet; bind to `127.0.0.1` or private VPC only.
- Allow **outbound HTTPS** for OS updates, Composer (deploy), Nominatim/OSM map tiles, and optional mail (SMTP).

### Split database (optional, larger deployments)

| Component | Suggested size |
|-----------|----------------|
| App server (Nginx/Apache + PHP-FPM) | 4 vCPU, 8 GB RAM, 40 GB SSD |
| Managed MySQL / DB server | 2 vCPU, 4–8 GB RAM, 40–80 GB SSD, automated backups |

---

## 12. Security checklist (production)

- [ ] `APP_DEBUG=false`
- [ ] HTTPS only; valid TLS certificate
- [ ] Document root = `public/` only
- [ ] Strong DB password; DB not exposed to the internet
- [ ] `storage/` and `bootstrap/cache/` not web-accessible
- [ ] Run `composer install --no-dev` on production
- [ ] Restrict `/admin/register` after first super admin (app already redirects when super admin exists)
- [ ] Regular backups (DB + `storage/app`)
- [ ] Keep PHP, MySQL, and OS patched

---

## 13. URLs (reference)

| Area | Path |
|------|------|
| Admin login | `/admin/login` |
| Admin dashboard | `/admin/dashboard` |
| API base | `/api/v1` |
| Health check | `/api/v1/health` |

---

## 14. Troubleshooting

| Issue | Fix |
|-------|-----|
| 404 on all routes except `/` | Enable `mod_rewrite` (Apache) or `try_files` (Nginx); document root must be `public/` |
| API 401 with Bearer token | Apache: `AllowOverride All`; Nginx: pass `HTTP_AUTHORIZATION` (see above) |
| 500 after deploy | `php artisan optimize:clear`; check `storage/logs/laravel.log`; fix permissions |
| Images not loading | `php artisan storage:link`; check `FILESYSTEM_DISK` and `storage/app/public` |
| Blank CSS/JS | Run `npm run build`; verify `public/build/manifest.json` exists |

---

*Last updated for Laravel 12, Sanctum 4, MapLibre admin rehab centers, and MySQL deployment.*
