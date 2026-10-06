# DAPE-MA Laravel — Ubuntu server automation

Scripts + browser installer for a **fresh Ubuntu 24.04** VPS (Nginx, PHP 8.3, MySQL 8, Composer, queue worker).

---

## Start to finish (recommended)

Use this path on a new server. Example IP: `173.16.18.178`.

### Step 0 — Push deploy code (on your Mac, once)

The installer lives in this repo. Push `deploy/` and `public/install/` to GitHub before installing on the server (or use Step 1B / SCP below).

```bash
cd /path/to/dape-ma-laravel
git add deploy public/install routes/web.php
git commit -m "Add Ubuntu deploy scripts and web installer UI."
git push origin main
```

### Step 1 — SSH into the server

```bash
ssh YOUR_USER@173.16.18.178
```

Use a user that can run `sudo`.

### Step 2 — Install Git and clone the repo

```bash
sudo apt update
sudo apt install -y git
git clone https://github.com/landogz/dapa-ma-laravel.git /tmp/dape-ma-laravel
```

**If GitHub does not have the new files yet**, copy from your Mac instead:

```bash
# on Mac
scp -r /Applications/XAMPP/xamppfiles/htdocs/DAPE-MA/dape-ma-laravel/deploy \
  YOUR_USER@173.16.18.178:/tmp/dape-deploy
```

Then on the server you still need the full Laravel app — prefer cloning (or rsync the whole project).

### Step 3 — Run the stack installer (Nginx + PHP + MySQL + app clone)

```bash
sudo bash /tmp/dape-ma-laravel/deploy/install-ubuntu.sh
```

This installs:

- Nginx → `/var/www/dape-ma-laravel/public`
- PHP 8.3-FPM + extensions
- MySQL 8 + app database/user
- Composer dependencies
- Queue worker (Supervisor) + scheduler cron
- UFW (SSH + HTTP/HTTPS)

Default `WEB_INSTALLER=1` **skips migrate** so you finish in the browser.

Wait until you see `INSTALL COMPLETE`. Log: `/var/log/dape-ma-install.log`  
DB password (if auto-generated): `/var/www/dape-ma-laravel/.install-credentials`

### Step 4 — Open the web installer UI

In a browser:

```text
http://173.16.18.178/install/
```

(or `http://YOUR_DOMAIN/install/` if DNS already points here)

### Step 5 — Requirements tab

1. Wait for the checklist (PHP, extensions, `vendor/`, writable folders).
2. If anything is **FAIL**, fix it and click **Recheck**.
3. Click **Continue**.

### Step 6 — App tab

1. **App name:** `DAPE-MA` (or your name).
2. **App URL:** `http://173.16.18.178` (or `https://your-domain.com`).
3. Leave **Seed demo data & accounts** checked if you want sample content + demo logins.
4. Click **Continue**.

### Step 7 — Database tab

**Option A — use credentials created by the script**

1. Open on the server: `sudo cat /var/www/dape-ma-laravel/.install-credentials`
2. Fill in host `127.0.0.1`, port `3306`, database / username / password from that file.
3. Leave **Create database & user with MySQL root** unchecked.
4. Click **Test connection** → should say success.
5. Click **Install now**.

**Option B — create DB from the UI**

1. Check **Create database & user with MySQL root**.
2. Enter MySQL root password (often empty on a fresh Ubuntu install until you set one).
3. Set app DB name/user/password you want.
4. **Test connection**, then **Install now**.

The UI will: write `.env` → `key:generate` → `migrate` → optional `db:seed` → `storage:link` → caches → create `storage/app/installed`.

### Step 8 — Finish tab

1. Confirm success message.
2. Click **Open Admin Login**.
3. If you seeded demo accounts, sign in with for example:
   - Email: `superadmin@dape-ma.local`
   - Password: `password`
4. Change that password immediately.
5. For security, remove or protect `public/install` after you verify login:
   ```bash
   sudo mv /var/www/dape-ma-laravel/public/install /var/www/dape-ma-laravel/public/install.bak
   ```

### Step 9 — Verify API

```text
http://173.16.18.178/api/v1/health
http://173.16.18.178/api/v1/posts
```

### Step 10 — (Optional) Domain + HTTPS

When DNS `A` record points to this server:

```bash
sudo APP_DOMAIN=your.domain.com APP_URL=https://your.domain.com ENABLE_SSL=1 \
  bash /var/www/dape-ma-laravel/deploy/install-ubuntu.sh
```

Or install Certbot only:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your.domain.com
```

Then set `APP_URL=https://your.domain.com` in `.env` and run:

```bash
cd /var/www/dape-ma-laravel
sudo -u www-data php artisan config:cache
```

### Step 11 — Point the mobile app at this server

In Flutter `lib/core/network/endpoints.dart`:

```dart
static const bool useLocalApi = false;
static const String _productionBaseUrl = 'https://YOUR_DOMAIN/api/v1';
// or http://173.16.18.178/api/v1 for IP-only testing
```

Rebuild/export the APK.

### Step 12 — Later updates (code deploys)

```bash
sudo bash /var/www/dape-ma-laravel/deploy/update.sh
```

---

## What the stack script installs

| Piece | Notes |
|-------|--------|
| Nginx | Document root → `/var/www/dape-ma-laravel/public` |
| PHP-FPM 8.3 | gd, mbstring, mysql, zip, intl, opcache, bcmath, … |
| MySQL 8 | Database + app user |
| Composer | Global binary |
| Supervisor | `queue:work` |
| Cron | `schedule:run` every minute |
| UFW | SSH + HTTP/HTTPS |

## Useful overrides

```bash
sudo \
  APP_DIR=/var/www/dape-ma-laravel \
  GIT_REPO=https://github.com/landogz/dapa-ma-laravel.git \
  GIT_BRANCH=main \
  DB_DATABASE=DAPE_MA \
  DB_USERNAME=dape_ma_user \
  DB_PASSWORD='your-strong-password' \
  WEB_INSTALLER=1 \
  bash /tmp/dape-ma-laravel/deploy/install-ubuntu.sh
```

Skip the web UI and migrate in the shell:

```bash
sudo WEB_INSTALLER=0 bash /tmp/dape-ma-laravel/deploy/install-ubuntu.sh
```

## Notes

- Prefer committed `public/build/` assets. Use `INSTALL_NODE=1` only if you must build on the server.
- Do **not** commit `.env` or `.install-credentials`.
- Install lock file: `storage/app/installed` — delete it only if you intentionally re-run `/install/`.
