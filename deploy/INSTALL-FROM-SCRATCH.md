# DAPE-MA Laravel — Install from a blank Ubuntu server

Use this when the VPS has **nothing installed** yet (fresh Ubuntu **24.04**).

**HTML version (open in a browser):** [install-guide.html](install-guide.html)

**What you get**

| Item | Result |
|------|--------|
| Stack | Nginx, PHP 8.3-FPM, MySQL 8, Composer, Supervisor, UFW |
| App path | `/var/www/dape-ma-laravel` |
| Web root | `/var/www/dape-ma-laravel/public` |
| Admin | `https://YOUR_DOMAIN/admin/login` |
| API | `https://YOUR_DOMAIN/api/v1` |

Replace placeholders:

- `YOUR_USER` — SSH username  
- `SERVER_IP` — public IP (e.g. `203.82.35.169`)  
- `YOUR_DOMAIN` — e.g. `dapemade.ddb.gov.ph`  
- `DB_NAME` / `DB_USER` / `DB_PASS` — MySQL app credentials  

---

## Step 1 — Prepare DNS (recommended before HTTPS)

In your domain DNS, create an **A record**:

```text
YOUR_DOMAIN  →  SERVER_IP
```

Wait until it resolves (can take a few minutes).

Skip this step if you will use the IP only for now (`http://SERVER_IP`).

---

## Step 2 — SSH into the server

From your Mac:

```bash
ssh YOUR_USER@SERVER_IP
```

You need a user that can run `sudo`.

---

## Step 3 — Update Ubuntu and install Git

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y git
```

---

## Step 4 — Download the installer (clone the repo)

```bash
git clone https://github.com/landogz/dapa-ma-laravel.git /tmp/dape-ma-laravel
```

Always use a **fresh** clone so you get the latest `deploy/install-ubuntu.sh`.

---

## Step 5 — Run the stack installer

This installs Nginx, PHP, MySQL, Composer, clones the app into `/var/www/dape-ma-laravel`, creates a DB user, configures Nginx, queue worker, and firewall.

### Option A — Domain + your own DB password

```bash
sudo \
  APP_DOMAIN=YOUR_DOMAIN \
  APP_URL=https://YOUR_DOMAIN \
  DB_DATABASE=DB_NAME \
  DB_USERNAME=DB_USER \
  DB_PASSWORD='DB_PASS' \
  bash /tmp/dape-ma-laravel/deploy/install-ubuntu.sh
```

### Option B — IP only (no domain yet)

```bash
sudo \
  APP_URL=http://SERVER_IP \
  DB_DATABASE=DB_NAME \
  DB_USERNAME=DB_USER \
  DB_PASSWORD='DB_PASS' \
  bash /tmp/dape-ma-laravel/deploy/install-ubuntu.sh
```

### Option C — Let the script generate a DB password

```bash
sudo bash /tmp/dape-ma-laravel/deploy/install-ubuntu.sh
```

Then read credentials:

```bash
sudo cat /var/www/dape-ma-laravel/.install-credentials
```

Wait until you see:

```text
INSTALL COMPLETE
```

Log file: `/var/log/dape-ma-install.log`

**Default behavior:** `WEB_INSTALLER=1` skips migrate so you finish in the browser at `/install/`.

---

## Step 5b — Add MySQL connection to `.env` (manual)

Laravel reads the database from `/var/www/dape-ma-laravel/.env`.

The stack installer and the web installer (`/install/`) usually write these for you. Use this section if you need to set or fix MySQL **by hand**.

### 1) Open the file

```bash
cd /var/www/dape-ma-laravel
sudo nano .env
```

(Or: `sudo vi .env`)

### 2) Set these MySQL lines

Find (or add) these keys and set your values:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=DB_NAME
DB_USERNAME=DB_USER
DB_PASSWORD=DB_PASS
```

**Notes**

- On the same server as MySQL, host is almost always `127.0.0.1` (not the public IP).
- If the password contains `@`, `#`, spaces, or quotes, wrap it in double quotes:

```env
DB_PASSWORD="dapemadb@2026"
```

### 3) Example (DDB server)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dapemadb
DB_USERNAME=dapemadb
DB_PASSWORD="dapemadb@2026"
```

Also set the public URL:

```env
APP_URL=https://dapemade.ddb.gov.ph
```

### 4) Before first migrate (important)

If tables are not created yet, use file drivers so `optimize:clear` does not fail on missing `cache` / `sessions` tables:

```env
CACHE_STORE=file
SESSION_DRIVER=file
```

After a successful `php artisan migrate --force`, you can switch back to:

```env
CACHE_STORE=database
SESSION_DRIVER=database
```

### 5) Apply and test

```bash
cd /var/www/dape-ma-laravel

# Clear old config
CACHE_STORE=file SESSION_DRIVER=file php artisan optimize:clear

# Create tables (first time)
php artisan migrate --force

# Optional demo users/content
php artisan db:seed --force

# After migrate, switch to database cache/session if you want
sudo sed -i 's/^CACHE_STORE=.*/CACHE_STORE=database/' .env
sudo sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=database/' .env
php artisan config:cache

# Quick DB test
php artisan db:show
```

### 6) Create the MySQL user/database yourself (if needed)

If the DB does not exist yet:

```bash
sudo mysql -e "
CREATE DATABASE IF NOT EXISTS dapemadb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'dapemadb'@'localhost' IDENTIFIED BY 'dapemadb@2026';
ALTER USER 'dapemadb'@'localhost' IDENTIFIED BY 'dapemadb@2026';
GRANT ALL PRIVILEGES ON dapemadb.* TO 'dapemadb'@'localhost';
FLUSH PRIVILEGES;
"
```

Then put the same name/user/password into `.env` as above.

---

## Step 6 — Open the web installer

Browser:

```text
http://SERVER_IP/install/
```

or (if DNS is ready):

```text
https://YOUR_DOMAIN/install/
```

(If HTTPS is not set up yet, use `http://`.)

The web installer **also writes MySQL into `.env`** when you fill the Database step and click **Install now**. Prefer that on first setup; use Step 5b only for manual fixes.

### 6.1 Requirements

- Wait for all checks to show **OK**
- Click **Recheck** if anything fails, then **Continue**

### 6.2 App

- **App name:** `DAPE-MA`
- **App URL:** `https://YOUR_DOMAIN` (or `http://SERVER_IP`)
- Optional: check **Seed demo data & accounts**
- Click **Continue**

### 6.3 Database

| Field | Value |
|--------|--------|
| DB host | `127.0.0.1` |
| DB port | `3306` |
| Database name | `DB_NAME` |
| DB username | `DB_USER` |
| DB password | `DB_PASS` |

- If the DB/user were already created by Step 5 → leave **Create database with MySQL root** unchecked  
- Click **Test connection**  
- Click **Install now**

### 6.4 Finish

- Open **Admin Login**
- If you seeded demo data:
  - Email: `superadmin@dape-ma.local`
  - Password: `password`
- Change that password immediately

Optional security cleanup:

```bash
sudo mv /var/www/dape-ma-laravel/public/install \
  /var/www/dape-ma-laravel/public/install.bak
```

---

## Step 7 — Enable HTTPS

### Option A — Network Admin already uploaded SSL (port 80 blocked)

When certs are in `/etc/ssl/certs` and **port 80 is not allowed**, use the HTTPS-only Nginx block (443 only — no HTTP redirect needed):

```bash
cd /var/www/dape-ma-laravel
sudo git pull origin main

# Defaults look for:
#   /etc/ssl/certs/dapemade.ddb.gov.ph.crt
#   /etc/ssl/private/dapemade.ddb.gov.ph.key
sudo bash deploy/apply-nginx-ssl.sh
```

If file names differ, ask Network Admin for the exact paths, then:

```bash
sudo \
  APP_DOMAIN=dapemade.ddb.gov.ph \
  SSL_CERTIFICATE=/etc/ssl/certs/YOUR_FILE.crt \
  SSL_CERTIFICATE_KEY=/etc/ssl/private/YOUR_FILE.key \
  bash deploy/apply-nginx-ssl.sh
```

List uploaded files:

```bash
sudo ls -la /etc/ssl/certs /etc/ssl/private
```

Template config: `deploy/nginx-dape-ma-ssl.conf`

### Option B — Let’s Encrypt (needs port 80 open for challenge)

Only if Network Admin allows HTTP temporarily:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d YOUR_DOMAIN
cd /var/www/dape-ma-laravel
sudo sed -i 's|^APP_URL=.*|APP_URL=https://YOUR_DOMAIN|' .env
sudo -u www-data php artisan config:cache
```

---

## Step 8 — Verify

| Check | URL |
|--------|-----|
| Admin | `https://YOUR_DOMAIN/admin/login` |
| API health | `https://YOUR_DOMAIN/api/v1/health` |
| Posts API | `https://YOUR_DOMAIN/api/v1/posts` |

---

## Step 9 — Point the mobile app (later)

In Flutter `lib/core/network/endpoints.dart`:

```dart
static const bool useLocalApi = false;
static const String _productionBaseUrl = 'https://YOUR_DOMAIN/api/v1';
```

Rebuild/export the APK.

*(Current production in use may still be AlwaysData until you switch.)*

---

## Step 10 — Later code updates

```bash
sudo bash /var/www/dape-ma-laravel/deploy/update.sh
```

That pulls `main`, runs `composer install`, migrate, and caches.

---

## Example filled in (your DDB server)

```bash
# Step 5
sudo \
  APP_DOMAIN=dapemade.ddb.gov.ph \
  APP_URL=https://dapemade.ddb.gov.ph \
  DB_DATABASE=dapemadb \
  DB_USERNAME=dapemadb \
  DB_PASSWORD='dapemadb@2026' \
  bash /tmp/dape-ma-laravel/deploy/install-ubuntu.sh

# Step 6 — browser
# https://dapemade.ddb.gov.ph/install/
# or http://203.82.35.169/install/

# Step 7
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d dapemade.ddb.gov.ph
```

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `Table '….cache' doesn't exist` during install | Use latest script; or set `CACHE_STORE=file` and `SESSION_DRIVER=file` in `.env`, then open `/install/` |
| `Access denied for user` | Check `DB_USERNAME` / `DB_PASSWORD` in `.env`; recreate MySQL user (Step 5b.6) |
| Old script from `/tmp` | `rm -rf /tmp/dape-ma-laravel` and clone again |
| 502 Bad Gateway | `sudo systemctl status php8.3-fpm nginx` |
| Permission errors | `sudo chown -R www-data:www-data /var/www/dape-ma-laravel/storage /var/www/dape-ma-laravel/bootstrap/cache` |
| Wrong APP_URL | Edit `.env`, then `php artisan config:cache` |

---

## What the script installs (short)

- **Always:** Nginx, MySQL 8, PHP 8.3 (+ extensions), Composer, Git, Supervisor, UFW  
- **Optional:** Node 20 (`INSTALL_NODE=1`), Certbot (`ENABLE_SSL=1`)  
- **Also:** app clone, DB user, Nginx site → `public/`, queue worker, scheduler cron  
