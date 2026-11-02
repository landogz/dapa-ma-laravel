# 8. Mail, queue, and backups

## 8.1 Outbound email

Every message uses the DAPE-MA branded Markdown mail shell (`resources/views/vendor/mail/`).

| Rule | Value |
|---|---|
| From name | **DAPE-MA** (`MAIL_FROM_NAME`) |
| Sign-off | Dangerous Drugs Board (do not print `config('app.name')` in mail views) |
| Seal | `public/email/ddb-seal.png` |
| 6-digit codes | Passed as a line so they render as the code block |

Production must not use `MAIL_MAILER=log`. OTP, password reset, and login codes fail silently for users if mail is still logging to disk.

Preview layouts with `MailMessage::render()`. Do not send live mail just to check HTML.

## 8.2 Queue and scheduler

| Process | How it runs |
|---|---|
| `queue:work` | Supervisor program `dape-ma-queue` |
| `schedule:run` | Cron every minute as `www-data` |

If notifications or mail sit unpublished:

```
sudo supervisorctl status dape-ma-queue:*
sudo supervisorctl restart dape-ma-queue:*
sudo tail -n 80 /var/www/dape-ma-laravel/storage/logs/laravel.log
```

Failed jobs live in the `failed_jobs` table when using the database driver.

## 8.3 What to back up

| Item | Why |
|---|---|
| MySQL database | Accounts, content, workflow, audit, queue |
| `storage/app` | Uploads (post images, IEC media, rehab files) |
| `.env` | Off-server secret store — not Git |
| TLS key/cert | DDB installer cert path / `/etc/ssl` |
| `public/download/dape-ma.apk` | If not always rebuilt from Flutter CI |

Suggested nightly (adjust to DDB backup policy):

```
mysqldump -u BACKUP_USER -p DAPE_MA | gzip > /secure/backups/dape-ma-$(date +%F).sql.gz
```

Keep dumps **off the app host**. Test a restore on a spare VM quarterly.

## 8.4 Restore outline

1. Put the host in maintenance (`php artisan down`) if users are live.
2. Restore MySQL from the dated dump.
3. Restore `storage/app` file-for-file.
4. Confirm `.env` `APP_KEY` matches the dump (encrypted columns).
5. `php artisan migrate --force` (should be no-op if dump is current).
6. `php artisan optimize:clear && php artisan config:cache`
7. `php artisan up`
8. Hit `/api/v1/health` and sign in as Super Admin.

> **Important.** Restoring a dump onto a host with a **different** `APP_KEY` will make encrypted fields unreadable.

## 8.5 Exercise

On training, take a `mysqldump`, change a harmless App Translation, restore the dump, and confirm the translation rolled back.
