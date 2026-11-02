# 7. Updates and the mobile APK

## 7.1 Code update (Laravel)

On the DDB app host, after GitHub `main` has the commits:

```
sudo bash /var/www/dape-ma-laravel/deploy/update.sh
```

The script:

1. `git fetch` / `git pull --ff-only origin main`
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan migrate --force`
4. Seeds **BlockedWordSeeder**, **AppOptionSeeder**, **AppTranslationSeeder** (safe, additive)
5. `storage:link`, `optimize:clear`, config/route/view cache
6. Restores `www-data` ownership and 775 on storage
7. Reloads PHP-FPM 8.3 (or 8.2)
8. Restarts Supervisor `dape-ma-queue:*`

Do **not** skip hooks. Do **not** force-push `main`.

To skip migrate/seed in an emergency only:

```
sudo SKIP_MIGRATE=1 bash /var/www/dape-ma-laravel/deploy/update.sh
```

Then run migrate as soon as the window allows.

## 7.2 Flutter / APK (required when the app changes)

Any change under `dape-ma-flutter/dape_ma_mobile/` that ships in the app needs a **release APK** on the public URL — not only a simulator build.

From `dape-ma-flutter/dape_ma_mobile`:

```
flutter build apk --release
```

Copy:

| From | To |
|---|---|
| `build/app/outputs/flutter-apk/app-release.apk` | `dape-ma-laravel/public/download/dape-ma.apk` |

Public URL:

```
https://dapemade.ddb.gov.ph/download/dape-ma.apk
```

Nginx serves that path as `application/vnd.android.package-archive` with `Cache-Control: no-store` so staff do not keep an old file.

Then:

1. Commit and push **Flutter**.
2. Commit and push **Laravel** including `public/download/dape-ma.apk`.
3. Pull on the server with `deploy/update.sh`.

Point the app at production in `lib/core/network/endpoints.dart`:

```
useLocalApi = false
_productionBaseUrl = 'https://dapemade.ddb.gov.ph/api/v1'
```

## 7.3 What not to do

- Leave a new mobile build only on a simulator.
- Serve yesterday’s APK after Flutter behavior changed.
- End a release after GitHub push without pulling production.
- Run `npm run build` on the live host as a substitute for committed `public/build/` unless that is the agreed process.

## 7.4 Exercise

On staging, run `update.sh`, then `curl -I https://HOST/api/v1/health` and `curl -I https://HOST/download/dape-ma.apk`. Confirm HTTP 200 and that the APK `Content-Type` is an Android package.
