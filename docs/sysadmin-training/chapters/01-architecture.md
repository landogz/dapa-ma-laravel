# 2. Architecture

## 2.1 Three products, one backend

| Layer | Technology | Repository | What it does |
|---|---|---|---|
| API + Admin | Laravel 12, Sanctum, Axios SPA, Tailwind, DataTables | `landogz/dapa-ma-laravel` (spelling on GitHub) | JSON APIs under `/api/v1/`, Admin Control Panel at `/admin` |
| Mobile app | Flutter | `landogz/dape-ma-flutter` | Android/iOS client. Talks only to `/api/v1/` with a Bearer token |
| Public download | File in Laravel `public/download/` | Same Laravel repo | `https://dapemade.ddb.gov.ph/download/dape-ma.apk` |

The admin UI is **100% API-based**. Saving, searching, and deleting never submit a classic HTML form. The same APIs power Flutter.

**Figure 2.** Dashboard after a Super Admin signs in. Counts come from `/api/v1/` — the Blade page is a shell.

![Figure 2. Dashboard](../administrator-manual/figures/02-dashboard.png)

## 2.2 Request path (production)

1. Browser or app calls `https://dapemade.ddb.gov.ph/...` on **port 443 only** (Network Admin does not allow HTTP 80).
2. Nginx serves `public/` and forwards PHP to **PHP 8.3-FPM**.
3. Laravel routes:
   - `/admin/*` — staff SPA (Sanctum token in `sessionStorage` plus an admin gate cookie)
   - `/api/v1/*` — JSON for admin and mobile
   - `/download/dape-ma.apk` — Android package
4. MySQL 8 stores accounts, content, workflow, audit, queues, and cache (database drivers).
5. Supervisor runs `php artisan queue:work`. Cron runs `schedule:run` every minute.

## 2.3 Standard API envelope

Success:

```
{ "status": true, "message": "...", "data": {} }
```

Error:

```
{ "status": false, "message": "...", "errors": {} }
```

Health (no environment leak):

```
GET https://dapemade.ddb.gov.ph/api/v1/health
GET https://dapemade.ddb.gov.ph/up
```

## 2.4 Module pattern

Every admin feature follows the same folders:

| Piece | Path |
|---|---|
| Thin API controller | `app/Http/Controllers/API/` |
| Service (business rules) | `app/Services/` |
| Repository (queries) | `app/Repositories/` |
| FormRequest validation | `app/Http/Requests/` |
| SPA module (Axios) | `resources/js/modules/` |

Controllers must stay thin. Do not put publish/archive rules in a Blade view.

## 2.5 Exercise

On a training host, open `/api/v1/health` in a browser. Confirm JSON `status: true` and that the payload does **not** include `APP_ENV` or stack traces.
