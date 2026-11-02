# 10. Training checklist and sign-off

Use this page in class. The trainer initials each line only after the trainee **does** the task on a non-production host (or on production with an approved change ticket).

## 10.1 Knowledge

| # | The trainee can explain… | Initials |
|---|---|---|
| K1 | API vs Admin SPA vs Flutter, and which GitHub repo is which | |
| K2 | Why production requires login OTP and local does not | |
| K3 | Difference between Super Admin, Publisher, Editor, Analytics Viewer | |
| K4 | Password policy, 90-day expiry, and `must_change_password` | |
| K5 | What `deploy/update.sh` does (pull, migrate, seed, cache, queue) | |
| K6 | Why a Flutter change is not live until the APK is on `/download/dape-ma.apk` and production has pulled | |
| K7 | App Translations vs CMS Body | |
| K8 | Where backups live and why `APP_KEY` must match a restore | |

## 10.2 Hands-on (training host)

| # | Task | Initials |
|---|---|---|
| H1 | Open `/api/v1/health` and `/up` | |
| H2 | Create an Editor user, sign in as that user, confirm Users is hidden, delete the user | |
| H3 | Toggle **Scrollable** on a DataTable and restore default | |
| H4 | Archive and restore a draft/published item as Editor | |
| H5 | Read `laravel.log` and Nginx error log | |
| H6 | `supervisorctl status` on the queue worker | |
| H7 | Dry-run: recite the production update command without executing on live | |
| H8 | Locate `public/download/dape-ma.apk` and the public URL | |
| H9 | Confirm `DAPE_SHOW_DEMO_LOGIN` is false in a production-like `.env` | |
| H10 | Find Audit Trail for a user create/delete | |

## 10.3 Production go-live gate

Do not hand over the live host until:

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `DAPE_SHOW_DEMO_LOGIN=false`
- [ ] `/install/` is moved or blocked
- [ ] Seeded demo passwords are changed
- [ ] TLS 1.2+ on 443; health URL returns JSON without env dumps
- [ ] Mail sends a real OTP to a test Super Admin
- [ ] Queue worker is `RUNNING`
- [ ] Nightly backup job is registered and a restore was tested once
- [ ] APK URL downloads the current release
- [ ] Super Admin accounts are named people, not shared mailboxes

## 10.4 Sign-off

| Role | Name | Date | Signature |
|---|---|---|---|
| Trainee (System Administrator) | | | |
| Trainer / Super Admin | | | |
| Records / IT supervisor | | | |

After sign-off, give the trainee:

1. This Word file (`DAPE-MA-SYSADMIN-TRAINING.docx`)
2. The staff Administrator Manual (`DAPE-MA-ADMINISTRATOR-MANUAL.docx`)
3. Access to `deploy/INSTALL-FROM-SCRATCH.md` in the repo (not printed secrets)

End of the DAPE-MA System Administrator Training pack.
