# 1. About this training

DAPE-MA is the Dangerous Drugs Board (DDB) mobile information platform. Staff publish content from a web **Admin Control Panel**. The **Laravel API** stores that content and serves it to the **Flutter** Android/iOS app.

This course trains **system administrators**: people who keep the servers, certificates, database, and releases healthy. It does not replace the staff Administrator Manual.

**Figure 1.** Admin sign-in is the front door for operators. Production requires a 6-digit email code after password.

![Figure 1. Admin login](../administrator-manual/figures/01-login.png)

## 1.1 Learning outcomes

By the end of this pack you will be able to:

1. Name the three parts of the system (API, Admin SPA, mobile app) and which Git repository owns each.
2. Describe the production host path, public URL, and health checks.
3. Assign the smallest staff role that fits a job and retire accounts the same day someone leaves.
4. Pull a production update with `deploy/update.sh` without skipping hooks.
5. Place a new Android APK on the public download URL.
6. Read logs, restart the queue worker, and restore from backup.

## 1.2 How this pack is used

| Session | Chapters | Time |
|---|---|---|
| Day 1 — picture of the system | 1–4 | 2 hours |
| Day 2 — host operations | 5–7 | 3 hours |
| Day 3 — incidents and sign-off | 8–10 | 2 hours |

Hands-on work uses a **non-production** clone first. Do not run installer or seed commands on live until a Super Admin confirms the window.

> **Important.** Do not write database passwords, installer credentials, or private keys into this document, tickets, or chat. Store them in the approved DDB secret store.

## 1.3 Related documents

| Document | Audience |
|---|---|
| `docs/DAPE-MA-ADMINISTRATOR-MANUAL.docx` | Editors, publishers, Super Admins using `/admin` |
| `deploy/INSTALL-FROM-SCRATCH.md` | First install on a blank Ubuntu 24.04 VPS |
| `deploy/README.md` | Ubuntu automation and HTTPS notes |
| This training pack | IT operators and Super Admins who own the host |
