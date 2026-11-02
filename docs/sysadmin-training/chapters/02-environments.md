# 3. Environments and URLs

## 3.1 Map

| Environment | Typical URL | `APP_ENV` | Login OTP | Demo sign-in buttons |
|---|---|---|---|---|
| Local Mac (XAMPP / `artisan serve`) | `http://127.0.0.1:8000` | `local` | Skipped | Allowed for training |
| Production (DDB) | `https://dapemade.ddb.gov.ph` | `production` | Required (6-digit email) | **Must be off** |

Login OTP is required whenever `APP_ENV` is **not** `local` (`App\Support\Auth\LoginOtp`). Staging with `APP_ENV=staging` therefore also requires a code.

## 3.2 Production paths

| Item | Value |
|---|---|
| App directory | `/var/www/dape-ma-laravel` |
| Web root | `/var/www/dape-ma-laravel/public` |
| Web user | `www-data` |
| Nginx site | `dapemade.ddb.gov.ph` listen **443 ssl http2** |
| Certificate | Wildcard `*.ddb.gov.ph` installed under `/etc/ssl/` |
| APK | `public/download/dape-ma.apk` |
| Update script | `sudo bash /var/www/dape-ma-laravel/deploy/update.sh` |
| Git branch | `main` |

## 3.3 Critical `.env` keys

Operators edit `/var/www/dape-ma-laravel/.env` then recache config. Never commit `.env`.

| Key | Production expectation |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://dapemade.ddb.gov.ph` |
| `APP_KEY` | Set (`php artisan key:generate` once) |
| `APP_NAME` / `MAIL_FROM_NAME` | **DAPE-MA** |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `127.0.0.1` if MySQL is local; private IP if split servers |
| `QUEUE_CONNECTION` | `database` |
| `CACHE_STORE` | `database` |
| `SESSION_DRIVER` | `database` |
| `MAIL_MAILER` | Real SMTP/API in production (not `log`) |
| `MAIL_FROM_ADDRESS` | DDB-approved mailbox |
| `DAPE_SHOW_DEMO_LOGIN` | `false` |
| `FCM_SERVER_KEY` | Firebase Cloud Messaging **legacy server key** (not a JSON service account) |

After any `.env` change:

```
cd /var/www/dape-ma-laravel
sudo -u www-data php artisan config:cache
```

## 3.4 Demo login

Quick sign-in buttons appear only when demo mode is on. Production must keep:

```
DAPE_SHOW_DEMO_LOGIN=false
```

If someone left it true after a seed:

```
sudo sed -i 's/^DAPE_SHOW_DEMO_LOGIN=.*/DAPE_SHOW_DEMO_LOGIN=false/' /var/www/dape-ma-laravel/.env
sudo -u www-data php artisan config:cache
```

Then change every seeded password (`superadmin@yopmail.com` and friends).

## 3.5 Exercise

Compare local `.env` with production. List three keys that **must** differ (`APP_DEBUG`, `APP_ENV`, `DAPE_SHOW_DEMO_LOGIN`). Do not copy secret values into your notes.
