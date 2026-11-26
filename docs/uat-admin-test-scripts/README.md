# DAPE-MA Admin UAT Test Scripts

Excel UAT pack for the **Admin Control Panel**.

| File | Path |
|------|------|
| Workbook | `DAPE-MA-ADMIN-UAT-TEST-SCRIPTS.xlsx` |
| Rebuild | `python3 docs/uat-admin-test-scripts/build_uat.py` (from `dape-ma-laravel`, needs `openpyxl`) |

Sheets: Instructions, Summary (auto counts), Test Users, Super Admin, Publisher, Editor, Analytics Viewer, Screen Checklist.

Status dropdown: Pass / Fail / Blocked / Not Tested. Failures need a Severity.

**Workflow rule:** Editor = Add / Edit / Submit → Pending. Publisher = Publish (or Revision). Super Admin can do both.

**v1.4 (Nov 27, 2026 internal UAT):** Covers:

- **Sticker Library**, **Color Fun Library**, **Relaxing Music** (Editor Draft → Submit → Publisher Publish + mobile smoke)
- **Posts** (Editor Draft → Pending → Publisher Publish / Revision)
- **Treatment Rehabilitation Centers** (add / edit / archive; no Draft/Pending machine)
- **Lookups** (Editor Submit → Pending → Publisher Publish)
- **Analytics multi-export** (CSV / Excel / PDF) + Analytics Viewer access

All admin paths use the live host: `https://dapemade.ddb.gov.ph/`.
