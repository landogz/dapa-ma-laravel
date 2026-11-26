#!/usr/bin/env python3
"""Build DAPE-MA Admin UAT test scripts (Excel) — Nov 27 internal pack.

Covers Stickers, Color Fun, Relaxing Music, Analytics export, Posts,
Treatment Rehabilitation Centers, and Lookups — with Editor
(Add/Edit/Submit→Pending) and Publisher (Publish/Revision) workflow
where the product supports it.
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
    ws.oddHeader.left.text = "DAPE-MA Admin UAT (Nov 27 internal)"
    ws.oddFooter.center.text = "&P / &N"
    return last


# ---------------------------------------------------------------------------
# Scripts — Nov 27 internal UAT
# Workflow: Editor = Add / Edit / Submit → Pending.
#           Publisher = Publish (or Revision).
#           Super Admin can do both.
# Rehab Centers: no Draft/Pending machine — Editor/Publisher/SA add/edit/archive.
# ---------------------------------------------------------------------------

WORKFLOW_RULE = (
    "Editor = Add / Edit / Submit → Pending. "
    "Publisher = Publish (or Revision). "
    "Super Admin can do both."
)

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
    r("SA-011", "Posts", "ADM-POSTS", "Create / edit / publish post",
      f"On {admin('/admin/posts')} as Super Admin; sample image optional",
      "1. Create a post (title + Body HTML)\n2. Save / publish as Super Admin\n3. Confirm toast and list refresh (no full page reload)\n4. Edit the same post and save again",
      "UAT Super Admin post",
      "Post saves with toast (not success Swal). List updates without reload. Super Admin can publish immediately."),
    r("SA-012", "Posts", "ADM-POSTS", "View body HTML, media zoom, archive/delete",
      "UAT post with Body HTML and featured image exists",
      "1. Open View on the post\n2. Confirm Body renders as HTML (not raw tags)\n3. Click featured/body image to zoom\n4. Archive or delete as allowed; confirm via Swal",
      "UAT post",
      "View modal shows rendered HTML. Image opens shared zoom lightbox. Destructive action uses Swal confirm; toast after success."),
    r("SA-013", "Rehab Centers", "ADM-REHAB", "Add treatment rehabilitation center",
      f"On {admin('/admin/rehab-centers')}; PH address and phone ready",
      "1. Click Add center\n2. Fill name, category, Address (autocomplete suggestions)\n3. Enter PH phone; place map pin\n4. Save",
      "UAT Rehab Center",
      "Center saves with toast. Address autocomplete suggests PH places. Phone accepts PH formats only. Pin appears on overview map."),
    r("SA-014", "Rehab Centers", "ADM-REHAB", "Edit / Active-Inactive / archive or delete; map updates",
      "UAT rehab center exists",
      "1. Edit name or address and save\n2. Toggle Active / Inactive (or Archive)\n3. Confirm overview map / list updates\n4. Super Admin may hard-delete if allowed",
      "UAT Rehab Center",
      "Edits persist with toast. Inactive/archived centers leave the public/mobile list. Map overview reflects the change."),
    r("SA-015", "Lookups", "ADM-LOOKUPS", "Add / edit lookup option",
      f"On {admin('/admin/lookups')} as Super Admin",
      "1. Open Lookups\n2. Add option (catalog/group, label EN, key, order, Active)\n3. Save → toast\n4. Edit label or order and save",
      "UAT lookup option",
      "Option appears in the table. Toast confirms. Super Admin publish path works for the catalog."),
    r("SA-016", "Lookups", "ADM-LOOKUPS", "Deactivate option; app stops showing it",
      "UAT Active lookup option exists and is wired in the app",
      "1. Set option Inactive (or archive per workflow)\n2. Confirm toast\n3. On mobile/app, open the screen that uses that lookup",
      "UAT lookup option",
      "Inactive option no longer appears where the catalog is consumed (e.g. Calm Corner / profile options)."),
]

# Publisher sheet — Publish / Revision focus (IDs match the run sheet)
PB = [
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
    r("PB-008", "Posts", "ADM-POSTS", "Publisher publishes a pending post",
      f"Editor submitted a pending post; on {admin('/admin/posts')} as Publisher",
      "1. Open Pending / In review filter\n2. Open the UAT post → Publish\n3. Confirm toast",
      "Pending UAT post",
      "Post becomes Published and is live on app/site. Toast confirms. No full page reload."),
    r("PB-009", "Posts", "ADM-POSTS", "Publisher rejects / returns post for revision",
      "A pending post exists",
      "1. Open pending post → Reject / Revision\n2. Enter notes and confirm",
      "Revision notes text",
      "Post returns to Editor (Draft / needs revision). Not public. Notes visible to Editor. Toast confirms."),
    r("PB-010", "Rehab Centers", "ADM-REHAB", "Publisher can add / edit / archive a center",
      f"On {admin('/admin/rehab-centers')} as Publisher",
      "1. Add a disposable center (address autocomplete + PH phone + pin)\n2. Edit and save\n3. Archive / Inactive and confirm list + map update",
      "UAT Publisher rehab center",
      "Rehab has no Draft/Pending queue — Publisher may add/edit/archive directly. Toast feedback. Center appears/disappears from app list when Active."),
    r("PB-011", "Rehab Centers", "ADM-REHAB", "Publisher restores or corrects an archived center",
      "Archived UAT rehab center exists",
      "1. Filter Archived\n2. Restore / set Active\n3. Confirm toast and map/list",
      "Archived UAT center",
      "Center returns to Active and shows on overview map / mobile list."),
    r("PB-012", "Lookups", "ADM-LOOKUPS", "Publisher publishes a pending lookup option",
      f"Editor submitted a pending option; on {admin('/admin/lookups')} as Publisher",
      "1. Open Pending workflow filter\n2. Open the UAT option → Publish\n3. Confirm toast",
      "Pending UAT lookup",
      "Option becomes Published. App uses the new/updated value where that catalog is wired."),
    r("PB-013", "Lookups", "ADM-LOOKUPS", "Publisher returns lookup for revision",
      "A pending lookup option exists",
      "1. Open pending option → Revision / Reject\n2. Enter notes and confirm",
      "Revision notes text",
      "Option returns to Draft for Editor. Not live in the app. Toast confirms."),
]

# Editor sheet — Add / Edit / Submit only (no Publish)
ED = [
    r("ED-003", "Sticker Library", "ADM-STICKERS", "Editor uploads and edits stickers (no publish action)",
      f"On {admin('/admin/sticker-library')} as Editor",
      "1. Upload a UAT sticker\n2. Edit title/pack and save\n3. Confirm no separate Publish action is required for gallery packs",
      "UAT Editor sticker",
      "Upload and edit succeed with toast. Editor can maintain stickers; no Publisher gate for Stickers gallery."),
    r("ED-004", "Color Fun Library", "ADM-COLOR", "Editor uploads and edits worksheets (no publish action)",
      f"On {admin('/admin/color-fun-library')} as Editor",
      "1. Upload worksheet\n2. Edit title / sort / Active\n3. Save",
      "UAT Editor worksheet",
      "CRUD/edit succeeds. Active worksheets can appear in the app list per product rules."),
    r("ED-005", "Relaxing Music", "ADM-MUSIC", "Editor upload auto-submits to Pending (no Publish)",
      f"On {admin('/admin/relaxing-music')} as Editor",
      "1. Add tracks → upload a short audio file\n2. Confirm toast (submitted for publisher review)\n3. Confirm card shows Pending workflow badge\n4. Confirm Publish is not available\n5. Preview play/pause",
      "Short audio file",
      "New Editor uploads land in Pending automatically (not Draft, not live). Publish is not available. Preview play/pause works."),
    r("ED-006", "Relaxing Music", "ADM-MUSIC", "Editor Pending track stays private until Publisher publishes",
      "Pending UAT track from ED-005",
      "1. Confirm Pending badge remains\n2. Confirm track is absent from public API / mobile Calm Corner\n3. Confirm Editor still cannot Publish",
      "N/A",
      "Track stays Pending and not public until Publisher (or Super Admin) publishes."),
    r("ED-007", "Relaxing Music", "ADM-MUSIC", "Editor deletes pending draft-state or archives published",
      "Pending or published track in Editor queue",
      "1. If still Pending/Draft after Revision → Delete and confirm\n2. If already Published by Publisher/SA → Archive and confirm",
      "N/A",
      "Unpublished rows can be removed per workflow rules. Published archives (hidden from app). Swal confirm + toast."),
    r("ED-008", "Posts", "ADM-POSTS", "Editor creates Draft post (no Publish)",
      f"On {admin('/admin/posts')} as Editor",
      "1. Create post (title + Body)\n2. Save as Draft\n3. Edit title/body/media\n4. Confirm Publish is not available to Editor",
      "UAT Editor draft post",
      "Post is Draft. Editor can Add/Edit only. Publish control is absent or disabled."),
    r("ED-009", "Posts", "ADM-POSTS", "Editor submits post for review → Pending",
      "Draft UAT post exists",
      "1. Click Submit for review\n2. Confirm toast\n3. Confirm Pending / In review status",
      "N/A",
      "Post moves to Pending. Not public on app/site until Publisher publishes."),
    r("ED-010", "Rehab Centers", "ADM-REHAB", "Editor adds / edits a rehab center",
      f"On {admin('/admin/rehab-centers')} as Editor",
      "1. Add center (name, category, address autocomplete, PH phone, map pin)\n2. Save\n3. Edit and save again",
      "UAT Editor rehab center",
      "Rehab has no Draft/Pending machine — Editor may add/edit directly. Toast confirms. Address autocomplete and PH phone validation work."),
    r("ED-011", "Rehab Centers", "ADM-REHAB", "Editor archives a center (no Publish step)",
      "UAT Editor rehab center exists",
      "1. Archive / set Inactive and confirm\n2. Confirm toast and list/map update",
      "N/A",
      "Center becomes Inactive/archived and leaves the public/mobile list. Editor does not use a Publish action for Rehab."),
    r("ED-012", "Lookups", "ADM-LOOKUPS", "Editor adds / edits lookup; Submit → Pending",
      f"On {admin('/admin/lookups')} as Editor",
      "1. Add a lookup option (label, key, order)\n2. Save as Draft if prompted\n3. Edit if needed\n4. Submit → Pending",
      "UAT Editor lookup",
      "Option is Draft then Pending. Publish is not available to Editor."),
    r("ED-013", "Lookups", "ADM-LOOKUPS", "Editor cannot Publish; waits for Publisher",
      "Pending UAT lookup exists",
      "1. Confirm Publish action is hidden/disabled for Editor\n2. Confirm Pending badge remains until Publisher acts",
      "N/A",
      "Editor cannot Publish. Pending waits for Publisher (Publish or Revision)."),
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
    ("Content", f"Posts — {admin('/admin/posts')}", "Draft → Submit → Publish / Revision; HTML body view"),
    ("Content", f"Rehab Centers — {admin('/admin/rehab-centers')}", "Add/edit, address autocomplete, PH phone, map pin, archive"),
    ("System", f"Lookups — {admin('/admin/lookups')}", "Add/edit option; Editor Submit → Publisher Publish"),
    ("Reach", f"Analytics export dialog — {admin('/admin/analytics')}", "Report type, CSV / Excel / PDF, range"),
]


def build_instructions(wb):
    ws = wb.active
    ws.title = "Instructions"
    ws.sheet_properties.tabColor = GOLD
    style_info_sheet(ws)
    ws["A1"] = "DAPE-MA – Admin UAT Scripts (Nov 27, 2026 internal)"
    ws["A1"].font = title_font
    ws.merge_cells("A1:B1")
    write_kv(ws, 3, "Project", "DAPE-MA")
    write_kv(
        ws,
        4,
        "Scope",
        "Internal UAT pack for Stickers, Color Fun, Relaxing Music, Analytics export, "
        "Posts, Treatment Rehabilitation Centers, and Lookups.",
    )
    write_kv(
        ws,
        5,
        "Document status",
        "Version 1.4 – Nov 27, 2026 internal UAT. All admin links use live URL "
        f"{LIVE_BASE}/",
    )
    write_kv(ws, 6, "Prepared by", "")
    write_kv(ws, 7, "Workflow rule", WORKFLOW_RULE)
    write_kv(
        ws,
        8,
        "Modules covered",
        "Sticker Library · Color Fun Library · Relaxing Music (Editor/Publisher workflow + mobile smoke) · "
        "Posts (Draft → Pending → Publish) · Rehab Centers (add/edit/archive) · Lookups (workflow) · "
        "Analytics export (CSV / Excel / PDF) · Analytics Viewer access",
    )
    write_kv(
        ws,
        9,
        "Out of scope",
        "Full Oct 30 regression (sign-in edge cases, Contests, IEC, Journal, Users, Audit Trail, "
        "Hope Directory/Events, Care Toolkit/Support/Checks, App Translations, Word Blocking) "
        "unless added in a later pack.",
    )
    write_kv(
        ws,
        11,
        "How to use",
        "1. Each role has its own sheet. Use seeded Test Users accounts.\n"
        "2. Suggested order: Lookups / Posts / Music (Editor Draft → Submit → Publisher Publish), "
        "then Stickers / Color Fun / Rehab, then Super Admin + Analytics, then mobile smoke.\n"
        "3. Fill Actual Result; set Status (Pass / Fail / Blocked / Not Tested).\n"
        "4. Failures need Severity. Summary sheet counts Status automatically.\n"
        "5. Complete the Screen Checklist on desktop and ~375 px.",
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
        "For Nov 27 internal test: all scripts executed, no Critical/High open, "
        "Screen Checklist completed for listed modules.",
    )
    write_kv(
        ws,
        15,
        "Environment / URL",
        f"Live admin: {admin('/admin/login')}. Seeded accounts: "
        "superadmin@yopmail.com, editor@yopmail.com, publisher@yopmail.com, analytics@yopmail.com "
        "(see Test Users). Mobile smoke needs the app on the same API.",
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
         "Can Add/Edit/Publish. Smoke Stickers, Color Fun, Music, Posts, Rehab, Lookups, Analytics export. Disposable UAT media then delete."),
        ("Publisher", "Publisher", "publisher@yopmail.com",
         "Content / program staff",
         "Publish / Revision for Music, Posts, Lookups. Maintain Stickers/Color Fun/Rehab. No Users/Analytics/Journal/Care Checks/Audit."),
        ("Editor", "Editor", "editor@yopmail.com",
         "Content staff",
         "Add / Edit / Submit → Pending only (no Publish) for Music, Posts, Lookups. Stickers/Color Fun/Rehab add-edit as listed."),
        ("Analytics Viewer", "Analytics Viewer", "analytics@yopmail.com",
         "M&E / analytics staff",
         "Analytics-only. Multi-format export. Care libraries / Posts URLs blocked."),
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
    ws["A1"] = "Admin UAT (Nov 27 internal) – Results Summary"
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
        f"{WORKFLOW_RULE} Critical/High failures on these scripts block Nov 27 internal acceptance.",
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
        "Screens for Nov 27 internal UAT. Rehab = add/edit/archive (no Draft/Pending). "
        "Posts / Music / Lookups use Editor Submit → Publisher Publish.",
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
