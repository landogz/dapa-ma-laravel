# 9. Monitoring, logs, and incidents

## 9.1 Daily health (5 minutes)

| Check | Command or URL |
|---|---|
| API | `curl -fsS https://dapemade.ddb.gov.ph/api/v1/health` |
| Laravel up | `curl -fsS https://dapemade.ddb.gov.ph/up` |
| Admin login page | `https://dapemade.ddb.gov.ph/admin/login` |
| PHP-FPM | `systemctl is-active php8.3-fpm` |
| Nginx | `systemctl is-active nginx` |
| Queue | `sudo supervisorctl status dape-ma-queue:*` |
| Disk | `df -h /` |
| APK | `curl -sI https://dapemade.ddb.gov.ph/download/dape-ma.apk` |

## 9.2 Log files

| Log | Path |
|---|---|
| Laravel | `/var/www/dape-ma-laravel/storage/logs/laravel.log` |
| Nginx access | `/var/log/nginx/dape-ma-access.log` |
| Nginx error | `/var/log/nginx/dape-ma-error.log` |
| Installer (first setup) | `/var/log/dape-ma-install.log` |
| PHP-FPM | `journalctl -u php8.3-fpm` |

Production `LOG_LEVEL` should be `warning` or `error`, not `debug`.

## 9.3 Audit Trail

Staff mutations should show under `/admin/audit-trail` (Super Admin). Use it after a disputed publish, user delete, or permission change. Meta must not contain passwords, tokens, or FCM keys.

## 9.4 Common incidents

| Symptom | First action |
|---|---|
| `/admin` loops to login | Token vs admin gate cookie. Confirm `dape_admin_gate` is not encrypted. Do not wipe sessions blindly. |
| 500 after deploy | `storage/logs/laravel.log`, then `php artisan migrate --force` if a migration was skipped |
| OTP never arrives | `MAIL_MAILER`, from-address, queue worker |
| App shows old labels | Seed `AppTranslationSeeder`, wait 20s, or reopen the app |
| App shows old features | APK URL still serving previous `dape-ma.apk` |
| 413 on upload | Nginx `client_max_body_size 14M` and PHP `upload_max_filesize` |
| Address autocomplete empty | `/api/v1/geocode/search` proxy; do not call Nominatim from the browser |
| Queue stuck | `supervisorctl restart dape-ma-queue:*` |
| Disk full | Logs and old dumps under `/var` |

## 9.5 Maintenance window

```
cd /var/www/dape-ma-laravel
sudo -u www-data php artisan down
# perform update or restore
sudo -u www-data php artisan up
```

Tell Publishers before a down window. The mobile app will fail API calls until `up`.

## 9.6 Exercise

Tail `laravel.log` while signing in on production-like mail. Confirm either a successful code send or a clear mailer error — never an empty log with a user still waiting.
