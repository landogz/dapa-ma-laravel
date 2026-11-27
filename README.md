# DAPE-MA Laravel

API + admin SPA for **DAPE-MA** (Dangerous Drugs Board). Mobile clients use `/api/v1/`.

## Mobile APK / updates

| Field | Value |
| --- | --- |
| Current app version | **1.0.2** (build **4**) |
| Public APK | https://dapemade.ddb.gov.ph/download/dape-ma.apk |
| Version manifest | `public/download/dape-ma-version.json` |
| Update API | `GET /api/v1/app-update` |
| GitHub Release (APK archive) | https://github.com/landogz/dape-ma-flutter/releases/tag/v1.0.2-b4 |

On every mobile ship, keep `dape-ma-version.json`, `config/dape.php` `mobile.*` defaults, **this README**, and `public/download/dape-ma.apk` in sync with the Flutter `pubspec.yaml` version.

## PSGC locations

- Snapshot-first: `GET /api/v1/locations/regions|provinces|cities|tree`
- Daily refresh: `php artisan psgc:refresh` (scheduled `02:30`)
- Shipped extract: `database/seeders/data/psgc/`

## Deploy

See `deploy/` and `SERVER_REQUIREMENTS.md`. Production pull:

```bash
sudo bash /var/www/dape-ma-laravel/deploy/update.sh
```
