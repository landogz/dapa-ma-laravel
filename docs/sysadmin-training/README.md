# DAPE-MA System Administrator Training

Technical training pack for **IT / records staff** who install, update, and operate the DAPE-MA platform (Laravel API, Admin Control Panel, and the mobile app that consumes it).

This is **not** the click-through staff guide. Content editors should use the [Administrator Manual](../administrator-manual/README.md) (`docs/DAPE-MA-ADMINISTRATOR-MANUAL.docx`).

---

## Who this is for

| Audience | After this course they can |
|---|---|
| System administrator | Deploy, update, back up, and restore the production host |
| Records / IT Super Admin | Assign roles, lock demo login, read Audit Trail, retire accounts |
| On-call operator | Check health, queues, logs, and SSL without guessing |

---

## Files

| Item | Location |
|---|---|
| Word training pack (distribute this) | [`docs/DAPE-MA-SYSADMIN-TRAINING.docx`](../DAPE-MA-SYSADMIN-TRAINING.docx) |
| Chapter source | [`chapters/`](chapters/) |
| Word builder | [`build_docx.py`](build_docx.py) |
| Screenshots (shared) | [`../administrator-manual/figures/`](../administrator-manual/figures/) |

---

## Rebuild the Word document

Run from `dape-ma-laravel`:

```bash
python3 docs/sysadmin-training/build_docx.py
```

**Requirements:** Python 3 and `python-docx`

After you open the generated Word file, right-click the **Contents** field and choose **Update Field**.
