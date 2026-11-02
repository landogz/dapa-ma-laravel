# 5. APIs and content workflow

## 5.1 Prefixes you will support

| Prefix | Consumers |
|---|---|
| `/api/v1/` | Admin SPA and Flutter |
| `/admin/` | Staff pages (Blade shells; data still via Axios) |
| `/install/` | First-time web installer — disable after go-live |

Public-ish reads (posts, rehab, translations, legal pages, IEC, contests) exist for the app. Admin writes sit under `/api/v1/admin/...` and require a verified staff token.

## 5.2 Workflow states

Most publishable records use:

| Status | Meaning |
|---|---|
| Draft | Editor working copy |
| Pending review | Waiting for a Publisher |
| Scheduled | Approved, not live yet (posts) |
| Published | Live in the app |
| Archived | Hidden from the app |

**Editor:** draft, submit, archive published/pending, restore → pending review.

**Publisher:** approve, reject, publish.

**Super Admin:** publish and restore immediately.

Archive is **not** a hard delete. Restore does not skip the publisher unless the actor is Super Admin.

## 5.3 Lists operators should know

Every admin DataTable:

- Search, sort, pagination
- Actions column (tooltips) plus right-click / long-press menu
- **Scrollable** checkbox beside Search — keeps all columns visible, horizontal scroll, Actions pinned on the right
- Confirmations use SweetAlert2; success/error use **toasts**

Mobile (≤1023 px) uses card/summary rows unless Scrollable is on.

## 5.4 App Translations vs CMS Body

| What | Where |
|---|---|
| App chrome (buttons, titles, empty states) | `/admin/app-translations` — keys must match Flutter `AppStrings` |
| CMS Body (posts, legal pages, Kid Listo quotes) | Module Body fields (CKEditor HTML) — **not** the translation catalog |

After adding a translation key, seed:

```
php artisan db:seed --class=AppTranslationSeeder --force
```

The live app polls the catalog on launch, every 20 seconds, and on resume.

## 5.5 Word blocking

Blocked words replace matches with `****` in user-generated text (comments, journal). The app syncs the list automatically. Editors archive; restore waits for a Publisher.

## 5.6 Exercise

As Editor, archive a published test post. Confirm it disappears from the public API feed. Restore it and confirm it sits in Pending until a Publisher (or Super Admin) publishes.
