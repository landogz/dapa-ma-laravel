# 4. Identity, roles, and security

**Figure 3.** Users is Super Admin only. This is where you create staff, set roles, suspend, and force a password change.

![Figure 3. Users](../administrator-manual/figures/16-users.png)

## 4.1 Staff roles

| Role | Code | Access |
|---|---|---|
| Super Admin | `super_admin` | Full console: users, content, publishing, analytics, journal, audit, settings |
| Publisher | `publisher` | Review queue, campaigns, notifications. **Not** Users, Analytics, or Journal |
| Editor | `editor` | Draft, submit, archive, restore-to-review. Cannot publish live |
| Analytics Viewer | `analytics_viewer` | Analytics only |
| App user | `app_user` | Mobile app only — not an admin role |

Give the **smallest** role that fits the job. Most content staff should be Publisher, not Super Admin.

Mobile accounts (`app_user`) must never receive admin menus.

## 4.2 Sign-in flow (technical)

1. `POST /api/v1/auth/login` with email and password.
2. Suspended accounts receive **403** and no token.
3. If `APP_ENV=local`, login completes and a full Sanctum token is issued.
4. Otherwise Laravel emails a **6-digit** code. The API returns a **pending** token (`requires_verification: true`).
5. `POST /api/v1/auth/login-code` (or verify-login-code) completes login and replaces the pending token.
6. Admin pages also set an httpOnly **admin gate cookie** (`dape_admin_gate`). It is excepted from cookie encryption so the SPA and middleware stay in sync.
7. If `must_change_password` is true (admin-set or expired), middleware `password.current` blocks other APIs until the user sets their own password on Edit Profile.

**Figure 4.** Forced password change lands on Edit Profile.

![Figure 4. Edit Profile](../administrator-manual/figures/22-profile.png)

## 4.3 Password policy (all new passwords)

Applies to register, reset, change, and admin-set passwords. Login **current password** is not checked for complexity.

| Rule | Value |
|---|---|
| Minimum length | 8 |
| Upper and lower case | Required |
| Number | Required |
| Symbol | Required |
| Breached passwords | Rejected (`Password::uncompromised()` / Have I Been Pwned) |
| Expiration | 90 days after `password_changed_at` (null = not expired) |
| Reuse | Current hash plus last 5 in `password_histories` |
| Admin-created / reset | Sets `must_change_password` |

Hint shown in the UI: *Password must include at least 8 characters, upper and lower case, a number, a symbol.*

Persist passwords only through `App\Services\Password\PasswordService` so history and timestamps stay correct.

## 4.4 Other security controls

- Laravel Sanctum Bearer tokens; revoke on password change and logout.
- CSRF on the SPA session.
- FormRequest validation on writes.
- API rate limiting and throttles on auth.
- Philippine phone numbers only (`App\Support\Phone\PhilippinePhoneNumber`).
- Address fields use LibreMap autocomplete via `GET /api/v1/geocode/search` (Nominatim proxy). Do not call Nominatim from the browser.
- HTML Body fields go through CKEditor and are sanitized on save/render.
- Successful mutations should appear in **Audit Trail** (`/admin/audit-trail`) without passwords or secrets in meta.
- First public register cannot create `super_admin` once one exists. After that, staff are added only from Users.

## 4.5 When someone joins or leaves

Join:

1. Users → Add User (name, work email, role, temporary password).
2. Tell them they must change the password on first sign-in.
3. Confirm they see only the menus they need.

Leave:

1. Delete or suspend the account **the same day**.
2. Confirm they cannot sign in.
3. Do not reuse that mailbox for a different person without a new account.

## 4.6 Exercise

On a training database, create an Editor, sign in as that user, and confirm Users / Analytics / Journal are hidden. Delete the account afterward.
