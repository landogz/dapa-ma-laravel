# DAPE-MA Admin UAT Test Scripts

Excel UAT pack for the **Admin Control Panel**, same layout as the Phase 1 / Oct 30 workbook.

| File | Path |
|------|------|
| Workbook | `DAPE-MA-ADMIN-UAT-TEST-SCRIPTS.xlsx` |
| Rebuild | `python3 docs/uat-admin-test-scripts/build_uat.py` (from `dape-ma-laravel`) |

Requires `openpyxl`.

Sheets: Instructions, Summary (auto counts), Test Users, Super Admin, Publisher, Editor, Analytics Viewer, Screen Checklist.

Status dropdown: Pass / Fail / Blocked / Not Tested. Failures need a Severity.

**v1.3 (Nov 27, 2026 internal UAT):** Delta-only pack. Baseline Oct 30 scripts were removed from this rebuild. Covers:

- Editor / Publisher / Analytics Viewer **role-access corrections** (Editor is not Posts-only; Publisher has no Care Checks)
- **Sticker Library**, **Color Fun Library**, **Relaxing Music** (Editor/Publisher content workflow + preview play/pause; mobile smoke)
- **Analytics multi-export** (CSV / Excel / PDF)

All admin paths in the workbook use the live host: `https://dapemade.ddb.gov.ph/` (e.g. `https://dapemade.ddb.gov.ph/admin/audit-trail`).

Run a prior full-suite workbook separately if you need complete regression (sign-in, Posts workflow, Users, Audit Trail, etc.).
