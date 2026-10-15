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
- `SERVER_IP` — public IP (e.g. `203.0.113.10`)  
- `YOUR_DOMAIN` — e.g. `your-domain.example`  
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

- If MySQL is **on the same server** as the app → `DB_HOST=127.0.0.1`
- If MySQL is on a **separate DB server** (DDB layout) → use the DB **private IP**, not `127.0.0.1`
- If the password contains `@`, `#`, spaces, or quotes, wrap it in double quotes:

```env
DB_PASSWORD="YourPassword@here"
```

### 3) Example (DDB split app + DB servers)

| Role | Host |
|------|------|
| App (Nginx/PHP) | `10.0.0.10` / public `203.0.113.10` |
| MySQL | `10.0.0.20` / public `203.0.113.20` |

On the **app** server `.env` (replace with your values):

```env
DB_CONNECTION=mysql
DB_HOST=10.0.0.20
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_db_user
DB_PASSWORD="YourPassword@here"

APP_URL=https://your-domain.example
```

DBA must allow MySQL from the app host (`10.0.0.10`) on port **3306**. Test:

```bash
mysql -h 10.0.0.20 -u your_db_user -p your_database -e "SELECT 1;"
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
CREATE DATABASE IF NOT EXISTS your_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'your_db_user'@'localhost' IDENTIFIED BY 'YourPassword@here';
ALTER USER 'your_db_user'@'localhost' IDENTIFIED BY 'YourPassword@here';
GRANT ALL PRIVILEGES ON your_database.* TO 'your_db_user'@'localhost';
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
- If you seeded demo data, use **Quick sign-in (demo)** buttons on the page (enabled while `DAPE_SHOW_DEMO_LOGIN=true`), or:
  - Email: `superadmin@dape-ma.local`
  - Password: `password`
- Quick login is on for DEV for now; set `DAPE_SHOW_DEMO_LOGIN=false` and `php artisan config:cache` before production go-live
- Change that password immediately

Optional security cleanup:

```bash
sudo mv /var/www/dape-ma-laravel/public/install \
  /var/www/dape-ma-laravel/public/install.bak
```

---

## Step 7 — Enable HTTPS

### Option A — Network Admin SSL (port 80 blocked) — recommended for DDB

**Important:** `/etc/ssl/certs` is mostly the Ubuntu **system CA store**. Your site cert is often elsewhere.

On the DDB app server the real files were found at:

```text
/home/YOUR_USER/Installer/cert/your-domain.crt
/home/YOUR_USER/Installer/cert/your-domain.key
```

(Or a wildcard that covers `YOUR_DOMAIN`.)

```bash
cd /var/www/dape-ma-laravel

# Fix git ownership if needed
sudo git config --global --add safe.directory /var/www/dape-ma-laravel
sudo git pull origin main

# Auto-finds Installer/cert, copies into /etc/ssl, applies Nginx 443-only
sudo bash deploy/apply-nginx-ssl.sh
```

What the script does:

1. Looks for certs in `/home/*/Installer/cert/` and `/etc/ssl/...`
2. Copies them to `/etc/ssl/certs/YOUR_DOMAIN.crt` + `/etc/ssl/private/...key`
3. Writes Nginx **listen 443 ssl** only (no port 80)
4. Sets `APP_URL=https://YOUR_DOMAIN`

Manual override if needed:

```bash
sudo \
  APP_DOMAIN=your-domain.example \
  SSL_CERTIFICATE=/home/YOUR_USER/Installer/cert/your-domain.crt \
  SSL_CERTIFICATE_KEY=/home/YOUR_USER/Installer/cert/your-domain.key \
  bash deploy/apply-nginx-ssl.sh
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

## Example filled in (DDB DEV server)

```bash
# SSH
ssh YOUR_USER@203.0.113.10

# Step 5 — stack (app on this host)
sudo \
  APP_DOMAIN=your-domain.example \
  APP_URL=https://your-domain.example \
  DB_DATABASE=your_database \
  DB_USERNAME=your_db_user \
  DB_PASSWORD='YourPassword@here' \
  bash /tmp/dape-ma-laravel/deploy/install-ubuntu.sh

# MySQL: this app host already had local mysqld working.
# Use DB_HOST=127.0.0.1 (remote 10.0.0.20 refused connections from the app).
# Quote password in .env: DB_PASSWORD="YourPassword@here"

# Step 6 — browser installer (HTTPS after Step 7)
# https://your-domain.example/install/
# If you see “Migration failed”, follow Troubleshooting → Migration failed recovery

# Step 7 — 443-only (certs from Installer/cert)
cd /var/www/dape-ma-laravel
sudo bash deploy/apply-nginx-ssl.sh
```

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `Table '….cache' doesn't exist` during install | Use latest script; or set `CACHE_STORE=file` and `SESSION_DRIVER=file` in `.env`, then open `/install/` |
| **Web installer: “Migration failed. Check DB credentials and logs.”** | See **[Migration failed recovery](#migration-failed-recovery)** below (credentials OK ≠ migrate OK) |
| `Access denied for user` | Check `DB_USERNAME` / `DB_PASSWORD` in `.env`; quote passwords that contain `@` (`DB_PASSWORD="YourPassword@here"`); recreate MySQL user (Step 5b.6) |
| `SQLSTATE[HY000] [2002] Connection refused` to remote IP | MySQL on the DB host is not listening / not open to the app IP. Use `DB_HOST=127.0.0.1` if MySQL runs **on the app server**, or ask DBA to open `3306` from the app private IP |
| Artisan still uses old `DB_HOST` after editing `.env` | Cached config: `rm -f bootstrap/cache/config.php` then `php artisan optimize:clear` |
| Old script from `/tmp` | `rm -rf /tmp/dape-ma-laravel` and clone again |
| 502 Bad Gateway | `sudo systemctl status php8.3-fpm nginx` |
| Permission errors | `sudo chown -R www-data:www-data /var/www/dape-ma-laravel/storage /var/www/dape-ma-laravel/bootstrap/cache` |
| Wrong APP_URL | Edit `.env`, then `php artisan config:cache` |

### Migration failed recovery

On DAPEMA DEV this showed up when `/install/` used `DB_HOST=127.0.0.1` but migrate still failed. Root causes and fixes:

#### 1) Wrong / stale DB host in config cache

Installer UI can show `127.0.0.1` while a previous `config:cache` still points at an unreachable remote host (e.g. `10.0.0.20`).

```bash
cd /var/www/dape-ma-laravel

# Confirm MySQL locally (app server has mysqld)
mysql -h 127.0.0.1 -u your_db_user -p your_database -e "SELECT 1;"

# Align .env (quote password if it contains @)
sudo sed -i 's/^DB_HOST=.*/DB_HOST=127.0.0.1/' .env
sudo sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD="YOUR_DB_PASSWORD"/' .env
sudo sed -i 's/^CACHE_STORE=.*/CACHE_STORE=file/' .env
sudo sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=file/' .env

sudo rm -f bootstrap/cache/config.php
sudo -u www-data php artisan optimize:clear
```

Use `DB_HOST=10.0.0.20` (or your DB private IP) **only** after `mysql -h THAT_IP -u … -e "SELECT 1;"` succeeds from the app server.

#### 2) Migration order bug: `daily_verses` missing

If logs show:

```text
Table 'your_database.daily_verses' doesn't exist
alter table `daily_verses` add `kid_listo_message` ...
```

the alter migration was dated **before** the create migration. Current repo fix (already applied in `main` / local tree):

| Wrong (runs too early) | Correct (after create) |
|------------------------|-------------------------|
| `2026_04_06_160000_add_kid_listo_fields_to_daily_verses.php` | `2026_07_01_130200_add_kid_listo_fields_to_daily_verses.php` |

On a server that still has the old filename:

```bash
cd /var/www/dape-ma-laravel/database/migrations
sudo mv 2026_04_06_160000_add_kid_listo_fields_to_daily_verses.php \
        2026_07_01_130200_add_kid_listo_fields_to_daily_verses.php
```

Then finish install from the shell (or retry `/install/`):

```bash
cd /var/www/dape-ma-laravel
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --force   # optional demo accounts

sudo sed -i 's/^CACHE_STORE=.*/CACHE_STORE=database/' .env
sudo sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=database/' .env
sudo -u www-data php artisan config:cache

# Mark installed (same lock the web installer creates)
sudo bash -c 'mkdir -p storage/app; echo "{\"installed_at\":\"'"$(date -u +%Y-%m-%dT%H:%M:%SZ)"'\"}" > storage/app/installed'
sudo chown -R www-data:www-data storage bootstrap/cache
```

Default seeded admin (change after login): `superadmin@dape-ma.local` / `password`.

Quick sign-in buttons on `/admin/login` require `DAPE_SHOW_DEMO_LOGIN=true` in `.env` (installer sets this when seeding; enabled on DEV for now).

---

## What the script installs (short)

- **Always:** Nginx, MySQL 8, PHP 8.3 (+ extensions), Composer, Git, Supervisor, UFW  
- **Optional:** Node 20 (`INSTALL_NODE=1`), Certbot (`ENABLE_SSL=1`)  
- **Also:** app clone, DB user, Nginx site → `public/`, queue worker, scheduler cron  
