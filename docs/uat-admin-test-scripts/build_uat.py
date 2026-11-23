#!/usr/bin/env python3
"""Build DAPE-MA Admin UAT test scripts (Excel) — Nov 27 delta pack only.

Covers new/fixed work since the Oct 30 baseline pack:
role-access corrections, Sticker Library, Color Fun Library,
Relaxing Music, and Analytics multi-format export.
"""

from __future__ import annotations

from pathlib import Path

from openpyxl import Workbook
from openpyxl.formatting.rule import FormulaRule
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.datavalidation import DataValidation

NAVY = "123A60"
BLUE = "055498"
GOLD = "FBD116"
WHITE = "FFFFFF"
GRAY = "BFBFBF"
ZEBRA = "F3F6FA"

HERE = Path(__file__).resolve().parent
OUT = HERE / "DAPE-MA-ADMIN-UAT-TEST-SCRIPTS.xlsx"

# Live production admin host — every /admin path in scripts uses this base.
LIVE_BASE = "https://dapemade.ddb.gov.ph"


def admin(path: str) -> str:
    """Full live admin URL, e.g. admin('/admin/audit-trail')."""
    clean = "/" + str(path).lstrip("/")
    return f"{LIVE_BASE.rstrip('/')}{clean}"

HEADERS = [
    "Script ID",
    "Module",
    "Ref",
    "Scenario",
    "Preconditions",
    "Test Steps",
    "Test Data",
    "Expected Result",
    "Actual Result",
    "Status",
    "Severity (if Fail)",
    "Tester",
    "Date",
    "Remarks",
]
COL_WIDTHS = [12, 22, 14, 36, 32, 52, 28, 46, 30, 12, 16, 14, 12, 26]

title_font = Font(name="Arial", size=14, bold=True, color=NAVY)
label_font = Font(name="Arial", size=11, bold=True, color=NAVY)
body_font = Font(name="Arial", size=11, color="0A0A0A")
header_font = Font(name="Arial", size=10, bold=True, color=WHITE)
small_font = Font(name="Arial", size=10, color="374151")
navy_fill = PatternFill("solid", fgColor=NAVY)
gold_fill = PatternFill("solid", fgColor=GOLD)
blue_fill = PatternFill("solid", fgColor=BLUE)
zebra_fill = PatternFill("solid", fgColor=ZEBRA)
thin = Border(
    left=Side(style="thin", color=GRAY),
    right=Side(style="thin", color=GRAY),
    top=Side(style="thin", color=GRAY),
    bottom=Side(style="thin", color=GRAY),
)
wrap_top = Alignment(wrap_text=True, vertical="top")
wrap_center = Alignment(wrap_text=True, vertical="center")


def r(id_, module, ref, scenario, pre, steps, data, expected):
    return (id_, module, ref, scenario, pre, steps, data, expected)


def style_info_sheet(ws):
    ws.sheet_view.showGridLines = False
    ws.column_dimensions["A"].width = 28
    ws.column_dimensions["B"].width = 110
    ws.row_dimensions[1].height = 22


def write_kv(ws, row, key, value):
    c1 = ws.cell(row, 1, key)
    c1.font = label_font
    c1.alignment = wrap_top
    c2 = ws.cell(row, 2, value)
    c2.font = body_font
    c2.alignment = wrap_top
    ws.row_dimensions[row].height = 48 if "\n" in str(value) else 20


def add_script_sheet(wb, title, scripts, tab_color=NAVY):
    ws = wb.create_sheet(title)
    ws.sheet_properties.tabColor = tab_color
    ws.freeze_panes = "E2"
    ws.sheet_view.zoomScale = 100

    for i, h in enumerate(HEADERS, 1):
        cell = ws.cell(1, i, h)
        cell.font = header_font
        cell.fill = navy_fill
        cell.alignment = Alignment(wrap_text=True, vertical="center", horizontal="center")
        cell.border = thin
        ws.column_dimensions[get_column_letter(i)].width = COL_WIDTHS[i - 1]
    ws.row_dimensions[1].height = 30

    for idx, script in enumerate(scripts, 2):
        for col, value in enumerate(script, 1):
            cell = ws.cell(idx, col, value)
            cell.font = body_font
            cell.alignment = wrap_top
            cell.border = thin
            if idx % 2 == 0:
                cell.fill = zebra_fill
        ws.row_dimensions[idx].height = 96

    last = max(len(scripts) + 1, 2)
    ws.auto_filter.ref = f"A1:N{last}"

    status_dv = DataValidation(
        type="list",
        formula1='"Pass,Fail,Blocked,Not Tested"',
        allow_blank=True,
        showDropDown=False,
        showErrorMessage=True,
        errorTitle="Status",
        error="Choose Pass, Fail, Blocked, or Not Tested.",
    )
    status_dv.add(f"J2:J{last}")
    sev_dv = DataValidation(
        type="list",
        formula1='"Critical,High,Medium,Low,N/A"',
        allow_blank=True,
        showDropDown=False,
        showErrorMessage=True,
        errorTitle="Severity",
        error="Choose Critical, High, Medium, Low, or N/A.",
    )
    sev_dv.add(f"K2:K{last}")
    ws.add_data_validation(status_dv)
    ws.add_data_validation(sev_dv)

    green = PatternFill("solid", fgColor="C6EFCE")
    red = PatternFill("solid", fgColor="FFC7CE")
    yellow = PatternFill("solid", fgColor="FFEB9C")
    gray = PatternFill("solid", fgColor="D9D9D9")
    ws.conditional_formatting.add(
        f"J2:J{last}",
        FormulaRule(formula=['J2="Pass"'], fill=green),
    )
    ws.conditional_formatting.add(
        f"J2:J{last}",
        FormulaRule(formula=['J2="Fail"'], fill=red),
    )
    ws.conditional_formatting.add(
        f"J2:J{last}",
        FormulaRule(formula=['J2="Blocked"'], fill=yellow),
    )
    ws.conditional_formatting.add(
        f"J2:J{last}",
        FormulaRule(formula=['J2="Not Tested"'], fill=gray),
    )
    ws.page_setup.orientation = "landscape"
    ws.page_setup.fitToPage = True
    ws.page_setup.fitToWidth = 1
    ws.page_setup.fitToHeight = 0
    ws.page_setup.paperSize = ws.PAPERSIZE_A4
    ws.oddHeader.left.text = "DAPE-MA Admin UAT (Nov 27 delta)"
    ws.oddFooter.center.text = "&P / &N"
    return last


# ---------------------------------------------------------------------------
# Scripts — Nov 27 delta only (new / fixed since Oct 30 baseline)
# ---------------------------------------------------------------------------

EDITOR_VISIBLE = (
    "Posts, Contests, IEC Materials, Rehab Centers, Sticker Library, Color Fun Library, "
    "Relaxing Music, App Translations, Notifications, Care Toolkit, Care Support, "
    "Hope Directory, Hope Events, Settings, Lookups, Word Blocking"
)
EDITOR_HIDDEN = "Users, Analytics, Journal (Diary), Care Checks, Audit Trail"
PUBLISHER_VISIBLE = EDITOR_VISIBLE
PUBLISHER_HIDDEN = EDITOR_HIDDEN

SA = [
    r("SA-001", "Dashboard", "ADM-DASH", "New Care library cards appear on Dashboard",
      "Signed in as Super Admin",
      "1. Open Overview → Dashboard\n2. Find count cards for Sticker Library, Color Fun Library and Relaxing Music\n3. Click each card / quick link if present",
      "N/A",
      f"Cards (or mega-menu entries) for Stickers, Color Fun and Relaxing Music are visible to Super Admin and open the correct pages ({admin('/admin/sticker-library')}, {admin('/admin/color-fun-library')}, {admin('/admin/relaxing-music')}) without error."),
    r("SA-002", "Sticker Library", "ADM-STICKERS", "Open Sticker Library and upload stickers",
      f"On {admin('/admin/sticker-library')}; sample PNG/WebP ready",
      "1. Open Care/Stickers → Sticker Library\n2. Click Add / upload\n3. Select one or more sticker images\n4. Save\n5. Confirm toast and gallery refresh",
      "UAT sticker PNG(s)",
      "Gallery loads. Upload succeeds with toast (not a success Swal). New sticker(s) appear. No full page reload."),
    r("SA-003", "Sticker Library", "ADM-STICKERS", "Edit and delete a sticker / pack item",
      "UAT sticker exists",
      "1. Edit title or pack assignment and save\n2. Delete the UAT sticker and confirm",
      "UAT sticker",
      "Edit toast updates the tile. Delete uses Swal confirm; after confirm the item is gone."),
    r("SA-004", "Color Fun Library", "ADM-COLOR", "Upload a Color Fun worksheet",
      f"On {admin('/admin/color-fun-library')}; worksheet image ready",
      "1. Open Color Fun Library\n2. Add worksheet (image)\n3. Set Active and sort order\n4. Save",
      "UAT coloring page image",
      "Worksheet appears in the gallery as Active. Toast confirms. Eligible for the mobile Calm Corner Color Fun list."),
    r("SA-005", "Color Fun Library", "ADM-COLOR", "Edit, deactivate and delete worksheet",
      "UAT worksheet exists",
      "1. Edit title/sort and save\n2. Set Inactive and save\n3. Delete and confirm",
      "UAT worksheet",
      "Edits persist. Inactive is excluded from the public/mobile list. Delete removes the row after confirm."),
    r("SA-006", "Relaxing Music", "ADM-MUSIC", "Open Relaxing Music; seeded published tracks",
      f"Seeder run or tracks already uploaded; on {admin('/admin/relaxing-music')}",
      "1. Open Relaxing Music\n2. Confirm Workflow tabs (All / Draft / Pending / Published / Archived) and Active/Inactive chips\n3. Confirm track cards show title, Filipino title, workflow badge, Active, Order",
      "N/A",
      "Page loads. Seeded tracks appear as Published. Workflow tabs and Active chips filter without reload."),
    r("SA-007", "Relaxing Music", "ADM-MUSIC", "Upload track(s) and preview play/pause (publishes immediately)",
      "On Relaxing Music; MP3 or M4A ≤20 MB",
      "1. Click Add tracks\n2. Select one audio file (optional EN/TL titles)\n3. Save\n4. On the new card, click Play then Pause on the same button",
      "UAT track.m4a",
      "Toast confirms create. Super Admin track is Published immediately. Preview play/pause works; gold ring marks the active track."),
    r("SA-008", "Relaxing Music", "ADM-MUSIC", "Edit titles and archive a track",
      "UAT track exists",
      "1. Edit → change Title / Title (Filipino) → Save\n2. Archive and confirm (or delete if still Draft)",
      "Updated titles",
      "Titles update on the card. Archive hides from the public API; draft delete removes the row after confirm."),
    r("SA-009", "Relaxing Music", "ADM-MUSIC", "Mobile Calm Corner plays CMS tracks with prev/next",
      "At least two Published + Active tracks; mobile app on live/UAT API",
      "1. Open Calm Corner → Relaxing Audio\n2. Select a track and Play\n3. Use Previous and Next\n4. Pause",
      "App build pointed at live API",
      "Track list matches admin Published+Active tracks (or bundled fallback if API empty). Play works. Prev/Next wrap the playlist and auto-start. Pause stops playback."),
    r("SA-010", "Analytics", "ADM-ANALYTICS", "Multi-format export (CSV / Excel / PDF)",
      f"On {admin('/admin/analytics')} as Super Admin",
      "1. Click Export (not a plain Export CSV-only button)\n2. Confirm report type for the active tab (e.g. Overview or Demographics)\n3. Download CSV\n4. Repeat for Excel (xlsx)\n5. Repeat for PDF",
      "Range: Last 7 days",
      "Export dialog offers CSV, Excel and PDF. Each format downloads without error. Demographics report is available when that tab/report is selected. Toast confirms download started."),
]

PB = [
    r("PB-001", "Access", "ADM-ACCESS", "Publisher sidebar matches allowed sections",
      "Signed in as Publisher",
      "1. Scan the left nav and mega menu\n2. Confirm new Care entries: Sticker Library, Color Fun Library, Relaxing Music\n3. Confirm forbidden sections are absent",
      "N/A",
      f"Visible: {PUBLISHER_VISIBLE}. Not visible: {PUBLISHER_HIDDEN}."),
    r("PB-002", "Access", "ADM-ACCESS", "Publisher blocked from forbidden URLs",
      "Publisher session",
      f"1. Open {admin('/admin/users')}\n2. Open {admin('/admin/analytics')}\n3. Open {admin('/admin/diary')}\n4. Open {admin('/admin/care-checks')}\n5. Open {admin('/admin/audit-trail')}",
      "Direct URLs",
      "Each URL is refused (redirect and/or toast). No users, analytics, journal, care-checks or audit data is shown."),
    r("PB-003", "Sticker Library", "ADM-STICKERS", "Publisher can maintain stickers",
      f"On {admin('/admin/sticker-library')} as Publisher",
      "1. Upload a disposable sticker\n2. Edit then delete it",
      "UAT Publisher sticker",
      "Create/edit/delete succeed with toasts. No permission error."),
    r("PB-004", "Color Fun Library", "ADM-COLOR", "Publisher can maintain Color Fun worksheets",
      f"On {admin('/admin/color-fun-library')} as Publisher",
      "1. Upload a worksheet\n2. Edit Active/sort\n3. Delete",
      "UAT Publisher worksheet",
      "CRUD works. Inactive worksheets do not appear in the app list."),
    r("PB-005", "Relaxing Music", "ADM-MUSIC", "Publisher has no Add tracks; review queue only",
      f"On {admin('/admin/relaxing-music')} as Publisher",
      "1. Confirm Add tracks is hidden\n2. Confirm Workflow filter defaults to Pending\n3. Open a pending track if present → Review",
      "N/A",
      "Publisher cannot create tracks. Pending queue / Review UI is available. Preview play still works on cards."),
    r("PB-006", "Relaxing Music", "ADM-MUSIC", "Publisher publishes a pending track",
      f"Editor submitted a draft; on {admin('/admin/relaxing-music')} as Publisher",
      "1. Open Pending\n2. Open the UAT track → Publish (or Approve update)\n3. Confirm toast",
      "Pending UAT track",
      "Track becomes Published. Toast confirms. Public GET /api/v1/relaxing-music-tracks includes it when Active."),
    r("PB-007", "Relaxing Music", "ADM-MUSIC", "Publisher returns a track for revision",
      "A pending track exists",
      "1. Open pending track → Revision\n2. Enter notes and confirm",
      "Revision notes text",
      "Track returns to Draft with notes visible to Editor. Toast confirms. Not in public API."),
]

ED = [
    r("ED-001", "Access", "ADM-ACCESS", "Editor sidebar is no longer Posts-only",
      "Signed in as Editor",
      "1. Scan the left nav\n2. Confirm Contests, IEC, Rehab, Stickers, Color Fun, Relaxing Music, Care Toolkit, Care Support, Hope, Notifications, Settings, Lookups, Translations, Word Blocking\n3. Confirm forbidden sections are absent",
      "N/A",
      f"Visible: {EDITOR_VISIBLE}. Not visible: {EDITOR_HIDDEN}. (Regression: Editor must NOT be Posts-only.)"),
    r("ED-002", "Access", "ADM-ACCESS", "Editor blocked from Users / Analytics / Journal / Care Checks / Audit",
      "Editor session",
      f"1. Open {admin('/admin/users')}\n2. Open {admin('/admin/analytics')}\n3. Open {admin('/admin/diary')}\n4. Open {admin('/admin/care-checks')}\n5. Open {admin('/admin/audit-trail')}",
      "Direct URLs",
      "Each URL is refused. No forbidden module data is shown."),
    r("ED-003", "Sticker Library", "ADM-STICKERS", "Editor can upload and delete stickers",
      f"On {admin('/admin/sticker-library')} as Editor",
      "1. Upload a UAT sticker\n2. Delete after confirm",
      "UAT Editor sticker",
      "Upload and delete succeed. Toast feedback. No permission error."),
    r("ED-004", "Color Fun Library", "ADM-COLOR", "Editor can manage Color Fun worksheets",
      f"On {admin('/admin/color-fun-library')} as Editor",
      "1. Upload worksheet\n2. Edit title/sort\n3. Delete",
      "UAT Editor worksheet",
      "CRUD succeeds for Editor."),
    r("ED-005", "Relaxing Music", "ADM-MUSIC", "Editor creates a draft track (no direct publish)",
      f"On {admin('/admin/relaxing-music')} as Editor",
      "1. Add tracks → upload a short audio file\n2. Confirm toast\n3. Confirm card shows Draft workflow badge\n4. Preview play/pause",
      "Short audio file",
      "Track is Draft (not live). Add/Edit/Submit available; Publish is not. Preview play/pause works."),
    r("ED-006", "Relaxing Music", "ADM-MUSIC", "Editor submits draft for publisher review",
      "Draft UAT track exists",
      "1. On the Draft card click Submit\n2. Confirm toast\n3. Confirm Pending badge",
      "N/A",
      "Track moves to Pending. Toast confirms. Still absent from public API until Publisher publishes."),
    r("ED-007", "Relaxing Music", "ADM-MUSIC", "Editor deletes draft or archives published",
      "Draft or published track owned in queue",
      "1. If Draft → Delete and confirm\n2. If Published → Archive and confirm",
      "N/A",
      "Draft hard-deletes. Published archives (hidden from app). Toast + Swal confirm only for destructive step."),
]

AV = [
    r("AV-001", "Access", "ADM-ACCESS", "Analytics Viewer still Analytics-only",
      "Signed in as Analytics Viewer",
      f"1. Scan the left menu\n2. Confirm Stickers / Color Fun / Relaxing Music / Posts are not in the menu\n3. Open {admin('/admin/sticker-library')} and {admin('/admin/relaxing-music')} directly",
      "Direct URLs",
      "Only Analytics (and overview metric cards if shown) are available. New Care library URLs are refused."),
    r("AV-002", "Analytics", "ADM-ANALYTICS", "Multi-format export as Analytics Viewer",
      f"On {admin('/admin/analytics')} as Analytics Viewer",
      "1. Open Export\n2. Download CSV for Last 7 days\n3. Download Excel\n4. Download PDF (optional demographics tab if available)",
      "Last 7 days",
      "Export dialog offers CSV / Excel / PDF. Downloads succeed. Viewer cannot open content CMS pages."),
]

SCREENS = [
    ("Care", f"Sticker Library — {admin('/admin/sticker-library')}", "Gallery, upload, edit, delete, ~375 px"),
    ("Care", f"Color Fun Library — {admin('/admin/color-fun-library')}", "Worksheet cards, upload, active/sort"),
    ("Care", f"Relaxing Music — {admin('/admin/relaxing-music')}", "Track cards, workflow tabs, Add tracks form"),
    ("Care", "Relaxing Music preview play/pause", "Play toggles to pause; active ring"),
    ("Care", "Relaxing Music Editor/Publisher workflow", "Draft → Submit → Publish / Revision"),
    ("Reach", f"Analytics export dialog — {admin('/admin/analytics')}", "Report type, CSV / Excel / PDF, range"),
    ("Access", "Editor nav (delta)", "Libraries visible; Users/Analytics/Diary/Checks/Audit hidden"),
    ("Access", "Publisher nav (delta)", "Libraries visible; Care Checks hidden"),
]


def build_instructions(wb):
    ws = wb.active
    ws.title = "Instructions"
    ws.sheet_properties.tabColor = GOLD
    style_info_sheet(ws)
    ws["A1"] = "DAPE-MA – Admin UAT Scripts (Nov 27, 2026 delta)"
    ws["A1"].font = title_font
    ws.merge_cells("A1:B1")
    write_kv(ws, 3, "Project", "DAPE-MA")
    write_kv(
        ws,
        4,
        "Scope",
        "Delta only — new or fixed admin functions since the Oct 30 baseline UAT pack. "
        "Do not use this file as the full regression suite.",
    )
    write_kv(
        ws,
        5,
        "Document status",
        "Version 1.3 – Nov 27, 2026 internal UAT (Stickers, Color Fun, Relaxing Music workflow, "
        "role-access corrections, Analytics multi-export). All admin links use live URL "
        f"{LIVE_BASE}/",
    )
    write_kv(ws, 6, "Prepared by", "")
    write_kv(
        ws,
        8,
        "Modules covered",
        "Role access (Editor / Publisher / Analytics Viewer) · Sticker Library · "
        "Color Fun Library · Relaxing Music (Editor/Publisher workflow + mobile smoke) · "
        "Analytics export (CSV / Excel / PDF)",
    )
    write_kv(
        ws,
        9,
        "Out of scope",
        "Baseline scripts from the Oct 30 pack (sign-in, Posts full workflow, Contests, "
        "IEC, Rehab, Journal, Users, Audit Trail, etc.) are intentionally omitted. "
        "Run the previous workbook for full regression if required.",
    )
    write_kv(
        ws,
        11,
        "How to use",
        "1. Each role has its own sheet. Use seeded Test Users accounts.\n"
        "2. Execute scripts in order within a module.\n"
        "3. Fill Actual Result; set Status (Pass / Fail / Blocked / Not Tested).\n"
        "4. Failures need Severity. Summary sheet counts Status automatically.\n"
        "5. Complete the Screen Checklist (new screens only) on desktop and ~375 px.",
    )
    write_kv(
        ws,
        12,
        "Severity guide",
        "Critical – crash, data loss, or core function unusable. High – major function wrong, no workaround. "
        "Medium – wrong but has a workaround. Low – cosmetic or wording.",
    )
    write_kv(
        ws,
        13,
        "Acceptance guidance",
        "For Nov 27 internal test: all delta scripts executed, no Critical/High open, "
        "Screen Checklist completed for new Care libraries and Analytics export.",
    )
    write_kv(
        ws,
        15,
        "Environment / URL",
        f"Live admin: {admin('/admin/login')}. Seeded accounts: "
        "superadmin@yopmail.com, editor@yopmail.com, publisher@yopmail.com, analytics@yopmail.com "
        "(see Test Users). Mobile smoke for Relaxing Music needs the app on the same API.",
    )
    write_kv(
        ws,
        16,
        "Related document",
        "Prior full suite: same folder (rebuild history / Oct 30 baseline). "
        "Administrator Manual for staff how-to.",
    )


def build_test_users(wb):
    ws = wb.create_sheet("Test Users")
    ws.sheet_properties.tabColor = BLUE
    headers = ["User Type", "Sheet", "Account / Access", "Who should test", "Notes"]
    widths = [22, 22, 42, 36, 62]
    for i, (h, w) in enumerate(zip(headers, widths), 1):
        cell = ws.cell(1, i, h)
        cell.font = header_font
        cell.fill = navy_fill
        cell.alignment = wrap_center
        cell.border = thin
        ws.column_dimensions[get_column_letter(i)].width = w
    rows = [
        ("Super Admin", "Super Admin", "superadmin@yopmail.com",
         "MISD / project owner",
         "Runs library CRUD + Analytics export + dashboard cards. Create disposable UAT media then delete."),
        ("Publisher", "Publisher", "publisher@yopmail.com",
         "Content / program staff",
         "Confirms expanded nav + library maintain. No Users/Analytics/Journal/Care Checks/Audit."),
        ("Editor", "Editor", "editor@yopmail.com",
         "Content staff",
         "Confirms Editor is NOT Posts-only; libraries + content sections visible; forbidden URLs blocked."),
        ("Analytics Viewer", "Analytics Viewer", "analytics@yopmail.com",
         "M&E / analytics staff",
         "Analytics-only. Multi-format export. New Care libraries blocked."),
    ]
    for r_idx, row in enumerate(rows, 2):
        for c, val in enumerate(row, 1):
            cell = ws.cell(r_idx, c, val)
            cell.font = body_font
            cell.alignment = wrap_top
            cell.border = thin
        ws.row_dimensions[r_idx].height = 48
    ws.freeze_panes = "A2"
    note = ws.cell(
        7,
        1,
        "Passwords come from DefaultAccountsSeeder / UAT ops — do not store production secrets in this file.",
    )
    note.font = small_font
    ws.merge_cells("A7:E7")


def build_summary(wb, counts):
    ws = wb.create_sheet("Summary", 1)
    ws.sheet_properties.tabColor = GOLD
    ws["A1"] = "Admin UAT (Nov 27 delta) – Results Summary"
    ws["A1"].font = title_font
    ws.merge_cells("A1:G1")
    headers = ["User Type / Sheet", "Total Scripts", "Pass", "Fail", "Blocked", "Not Tested", "Pass Rate"]
    for i, h in enumerate(headers, 1):
        cell = ws.cell(3, i, h)
        cell.font = header_font
        cell.fill = navy_fill
        cell.alignment = wrap_center
        cell.border = thin
    widths = [22, 14, 10, 10, 12, 12, 12]
    for i, w in enumerate(widths, 1):
        ws.column_dimensions[get_column_letter(i)].width = w

    sheets = [
        (4, "Super Admin", "Super Admin", counts["Super Admin"]),
        (5, "Publisher", "Publisher", counts["Publisher"]),
        (6, "Editor", "Editor", counts["Editor"]),
        (7, "Analytics Viewer", "Analytics Viewer", counts["Analytics Viewer"]),
    ]
    for row, label, sheet, n in sheets:
        last = max(n + 1, 2)
        ws.cell(row, 1, label).font = body_font
        ws.cell(row, 2, f"=COUNTA('{sheet}'!A2:A{last})")
        ws.cell(row, 3, f"=COUNTIF('{sheet}'!J2:J{last},\"Pass\")")
        ws.cell(row, 4, f"=COUNTIF('{sheet}'!J2:J{last},\"Fail\")")
        ws.cell(row, 5, f"=COUNTIF('{sheet}'!J2:J{last},\"Blocked\")")
        ws.cell(row, 6, f"=B{row}-C{row}-D{row}-E{row}")
        ws.cell(row, 7, f"=IF(B{row}=0,0,C{row}/B{row})")
        ws.cell(row, 7).number_format = "0%"
        for c in range(1, 8):
            ws.cell(row, c).border = thin
            ws.cell(row, c).font = body_font

    ws.cell(8, 1, "TOTAL").font = Font(name="Arial", size=11, bold=True, color=WHITE)
    ws.cell(8, 2, "=SUM(B4:B7)")
    ws.cell(8, 3, "=SUM(C4:C7)")
    ws.cell(8, 4, "=SUM(D4:D7)")
    ws.cell(8, 5, "=SUM(E4:E7)")
    ws.cell(8, 6, "=SUM(F4:F7)")
    ws.cell(8, 7, "=IF(B8=0,0,C8/B8)")
    ws.cell(8, 7).number_format = "0%"
    for c in range(1, 8):
        ws.cell(8, c).fill = navy_fill
        ws.cell(8, c).font = Font(name="Arial", size=11, bold=True, color=WHITE)
        ws.cell(8, c).border = thin

    ws.cell(10, 1, "Exit criteria check").font = label_font
    checks = [
        (11, "All delta scripts executed (none Not Tested)", '=IF(F8=0,"MET","NOT MET")'),
        (12, "No failed scripts outstanding", '=IF(D8=0,"MET","NOT MET")'),
        (13, "No blocked scripts outstanding", '=IF(E8=0,"MET","NOT MET")'),
        (14, "Screen Checklist (new screens) complete", "Complete manually"),
    ]
    for row, label, formula in checks:
        ws.cell(row, 1, label).font = body_font
        ws.cell(row, 2, formula).font = body_font
        ws.merge_cells(start_row=row, start_column=2, end_row=row, end_column=4)

    ws.cell(
        16,
        1,
        "Delta pack only. Critical/High failures on these scripts block Nov 27 internal acceptance for the new features.",
    ).font = small_font
    ws.merge_cells("A16:G16")
    ws.column_dimensions["A"].width = 62


def build_screen_checklist(wb):
    ws = wb.create_sheet("Screen Checklist")
    ws.sheet_properties.tabColor = BLUE
    headers = [
        "Section",
        "Screen / dialog",
        "What to check",
        "Matches design?",
        "Controls all work?",
        "OK on ~375 px?",
        "Tester",
        "Remarks",
    ]
    widths = [18, 42, 48, 16, 18, 16, 14, 36]
    for i, (h, w) in enumerate(zip(headers, widths), 1):
        cell = ws.cell(1, i, h)
        cell.font = header_font
        cell.fill = navy_fill
        cell.alignment = Alignment(wrap_text=True, vertical="center", horizontal="center")
        cell.border = thin
        ws.column_dimensions[get_column_letter(i)].width = w
    ws.row_dimensions[1].height = 30
    yes_no = DataValidation(type="list", formula1='"Yes,No,N/A"', allow_blank=True, showDropDown=False)
    last = len(SCREENS) + 1
    yes_no.add(f"D2:F{last}")
    ws.add_data_validation(yes_no)
    for idx, (section, screen, check) in enumerate(SCREENS, 2):
        values = (section, screen, check, None, None, None, None, None)
        for c, val in enumerate(values, 1):
            cell = ws.cell(idx, c, val)
            cell.font = body_font
            cell.alignment = wrap_top
            cell.border = thin
            if idx % 2 == 0:
                cell.fill = zebra_fill
        ws.row_dimensions[idx].height = 28
    ws.freeze_panes = "A2"
    ws.auto_filter.ref = f"A1:H{last}"
    note = ws.cell(
        last + 2,
        1,
        "New/fixed screens only for Nov 27. Baseline screens remain in the Oct 30 checklist if needed.",
    )
    note.font = small_font
    ws.merge_cells(start_row=last + 2, start_column=1, end_row=last + 2, end_column=8)


def build():
    wb = Workbook()
    build_instructions(wb)
    add_script_sheet(wb, "Super Admin", SA, NAVY)
    add_script_sheet(wb, "Publisher", PB, BLUE)
    add_script_sheet(wb, "Editor", ED, "374151")
    add_script_sheet(wb, "Analytics Viewer", AV, "055498")
    build_summary(
        wb,
        {
            "Super Admin": len(SA),
            "Publisher": len(PB),
            "Editor": len(ED),
            "Analytics Viewer": len(AV),
        },
    )
    build_test_users(wb)
    build_screen_checklist(wb)

    order = [
        "Instructions",
        "Summary",
        "Test Users",
        "Super Admin",
        "Publisher",
        "Editor",
        "Analytics Viewer",
        "Screen Checklist",
    ]
    wb._sheets = [wb[name] for name in order]

    OUT.parent.mkdir(parents=True, exist_ok=True)
    wb.save(OUT)
    print(f"Wrote {OUT}")
    print(
        "scripts",
        len(SA) + len(PB) + len(ED) + len(AV),
        "SA",
        len(SA),
        "PB",
        len(PB),
        "ED",
        len(ED),
        "AV",
        len(AV),
        "screens",
        len(SCREENS),
    )


if __name__ == "__main__":
    build()
