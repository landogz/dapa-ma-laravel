# DAPE-MA Admin UAT Test Scripts

Excel UAT pack for the **Admin Control Panel**, in the same layout as `DAPE-MA_Phase1_UAT_Test_Scripts.xlsx`.

| File | Path |
|------|------|
| Workbook | `DAPE-MA-ADMIN-UAT-TEST-SCRIPTS.xlsx` |
| Rebuild | `python3 docs/uat-admin-test-scripts/build_uat.py` (from `dape-ma-laravel`) |

Requires `openpyxl`.

Sheets: Instructions, Summary (auto counts), Test Users, Super Admin, Publisher, Editor, Analytics Viewer, Screen Checklist.

Status dropdown: Pass / Fail / Blocked / Not Tested. Failures need a Severity.
