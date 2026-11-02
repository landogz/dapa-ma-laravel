# DAPE-MA Administrator Manual

Official desk guide for the **DAPE-MA Admin Control Panel** (`/admin`). Staff use this workspace to publish and maintain content that appears in the DAPE-MA mobile app (Android and iPhone).

This folder is the **source** for the printed Word manual. Edit the Markdown chapters here, then rebuild the `.docx` when you are ready to distribute it.

---

## Who this is for

| Audience | Use this guide to |
|---|---|
| Super Admin | Operate the full panel, including users, analytics, and journal review |
| Publisher | Review, approve, and publish content submitted by editors |
| Editor | Draft, submit, archive, and restore content across admin modules |
| Analytics Viewer | Read usage charts and exports |
| Records / IT | Onboard staff, assign the smallest role that fits the job, and retire accounts promptly |

The published Word file is the copy staff should keep at their desks. The Markdown files are for authors who update the guide.

IT operators who install, update, and support the production host should use the **System Administrator Training** pack (`docs/DAPE-MA-SYSADMIN-TRAINING.docx`, source in [`../sysadmin-training/`](../sysadmin-training/README.md)).

---

## Files

| Item | Location |
|---|---|
| Word manual (distribute this) | [`docs/DAPE-MA-ADMINISTRATOR-MANUAL.docx`](../DAPE-MA-ADMINISTRATOR-MANUAL.docx) |
| Chapter source | [`chapters/`](chapters/) |
| Screenshots and DDB seal | [`figures/`](figures/) |
| Word builder | [`build_docx.py`](build_docx.py) |

---

## Rebuild the Word document

Run from `dape-ma-laravel`:

```bash
python3 docs/administrator-manual/build_docx.py
```

**Requirements:** Python 3 and `python-docx`

```bash
pip3 install python-docx
```

After you open the generated Word file, right-click the **Contents** field and choose **Update Field** if you need Word’s automatic page numbers. A chapter list is already printed on that page.

---

## Contents

Chapters follow the left menu, from sign-in through administration.

| # | Chapter | Admin page | Source |
|---|---|---|---|
| — | Where to find everything | — | [`00-where-to-find.md`](chapters/00-where-to-find.md) |
| 1 | How to sign in | `/admin/login` | [`01-sign-in.md`](chapters/01-sign-in.md) |
| 2 | Administrator duties | Users, Dashboard | [`02-duties.md`](chapters/02-duties.md) |
| 3 | How lists work | Every listing page | [`03-lists.md`](chapters/03-lists.md) |
| 4 | Dashboard | `/admin/dashboard` | [`04-dashboard.md`](chapters/04-dashboard.md) |
| 5 | Posts | `/admin/posts` | [`05-posts.md`](chapters/05-posts.md) |
| 6 | Contests | `/admin/contests` | [`06-contests.md`](chapters/06-contests.md) |
| 7 | IEC Materials | `/admin/iec-materials` | [`07-iec.md`](chapters/07-iec.md) |
| 8 | Rehab Centers | `/admin/rehab-centers` | [`08-rehab.md`](chapters/08-rehab.md) |
| 9 | Care Toolkit | `/admin/care-toolkit` | [`09-care-toolkit.md`](chapters/09-care-toolkit.md) |
| 10 | Care Checks | `/admin/care-checks` | [`10-care-checks.md`](chapters/10-care-checks.md) |
| 11 | Care Support | `/admin/care-support` | [`11-care-support.md`](chapters/11-care-support.md) |
| 12 | Journal | `/admin/diary` | [`12-journal.md`](chapters/12-journal.md) |
| 13 | Hope Directory | `/admin/hope-directory` | [`13-hope-directory.md`](chapters/13-hope-directory.md) |
| 14 | Hope Events | `/admin/hope-events` | [`14-hope-events.md`](chapters/14-hope-events.md) |
| 15 | Notifications | `/admin/notifications` | [`15-notifications.md`](chapters/15-notifications.md) |
| 16 | Analytics | `/admin/analytics` | [`16-analytics.md`](chapters/16-analytics.md) |
| 17 | Users | `/admin/users` | [`17-users.md`](chapters/17-users.md) |
| 18 | Settings | `/admin/settings` | [`18-settings.md`](chapters/18-settings.md) |
| 19 | Lookups | `/admin/lookups` | [`19-lookups.md`](chapters/19-lookups.md) |
| 20 | App Translations | `/admin/app-translations` | [`20-translations.md`](chapters/20-translations.md) |
| 21 | Word Blocking | `/admin/word-blocking` | [`21-word-blocking.md`](chapters/21-word-blocking.md) |
| 22 | Troubleshooting and security | — | [`22-troubleshooting.md`](chapters/22-troubleshooting.md) |
| 23 | Quick reference | — | [`23-quick-reference.md`](chapters/23-quick-reference.md) |

### Menu groups

| Group | Chapters |
|---|---|
| Overview | 1–4 |
| Content | 5–7 |
| Services | 8 |
| Care | 9–12 |
| Hope | 13–14 |
| Reach | 15–16 |
| Administration | 17–21 |
| Reference | 22–23 |

---

## Not covered here

These admin surfaces exist in the panel but are **not** part of this manual:

- Trainings (`/admin/trainings`) — not in the left menu
- Sticker Library
- Color Fun Library
- Audit Trail

Do not document installer database passwords, Code Snippets, or plugin updates. DAPE-MA is not a WordPress site.

---

## How to update a chapter

1. Edit the matching file in `chapters/`.
2. Replace screenshots in `figures/` at **1440×812** when the screen layout changes. Keep the existing file names so the builder still finds them.

   ```bash
   cd docs/administrator-manual/capture
   npm install
   node capture-screenshots.mjs
   ```

3. Rebuild the Word document and review the chapter in Word before you circulate it.

Write for staff who will click through the panel: short steps, the exact button labels, and the role that may perform the action.
