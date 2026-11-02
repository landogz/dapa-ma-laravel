#!/usr/bin/env python3
"""Build DAPE-MA-SYSADMIN-TRAINING.docx from Markdown chapters."""

from __future__ import annotations

import re
from pathlib import Path

from docx import Document
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor

ROOT = Path(__file__).resolve().parent
CHAPTERS_DIR = ROOT / "chapters"
FIGURES_DIR = ROOT.parent / "administrator-manual" / "figures"
OUTPUT = ROOT.parent / "DAPE-MA-SYSADMIN-TRAINING.docx"
SEAL = FIGURES_DIR / "ddb-seal.png"

NAVY = RGBColor(0x12, 0x3A, 0x60)
BLUE = RGBColor(0x05, 0x54, 0x98)
GOLD = RGBColor(0xFB, 0xD1, 0x16)
RED = RGBColor(0xCE, 0x20, 0x28)
BLACK = RGBColor(0x0A, 0x0A, 0x0A)
GRAY = RGBColor(0x37, 0x41, 0x51)
CODE_BG = "F1F5F9"
CODE_BORDER = "123A60"

CHAPTER_FILES = [
    "00-about.md",
    "01-architecture.md",
    "02-environments.md",
    "03-security-auth.md",
    "04-api-workflow.md",
    "05-server-install.md",
    "06-updates-apk.md",
    "07-mail-queue-backup.md",
    "08-monitoring.md",
    "09-checklist.md",
]


def set_run_font(run, name: str = "Calibri", size: int = 11, bold: bool = False, color=BLACK, italic: bool = False):
    run.font.name = name
    run._element.rPr.rFonts.set(qn("w:eastAsia"), name)
    run.font.size = Pt(size)
    run.bold = bold
    run.italic = italic
    if color is not None:
        run.font.color.rgb = color


def rgb_from_hex(hex_color: str) -> RGBColor:
    hex_color = hex_color.lstrip("#")
    return RGBColor(int(hex_color[0:2], 16), int(hex_color[2:4], 16), int(hex_color[4:6], 16))


def shade_cell(cell, hex_color: str):
    tc = cell._tc
    tc_pr = tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:fill"), hex_color)
    shd.set(qn("w:val"), "clear")
    tc_pr.append(shd)


def set_cell_border(cell, **kwargs):
    tc = cell._tc
    tc_pr = tc.get_or_add_tcPr()
    tc_borders = OxmlElement("w:tcBorders")
    for edge in ("top", "left", "bottom", "right"):
        if edge in kwargs:
            element = OxmlElement(f"w:{edge}")
            element.set(qn("w:val"), kwargs[edge].get("val", "single"))
            element.set(qn("w:sz"), str(kwargs[edge].get("sz", 4)))
            element.set(qn("w:color"), kwargs[edge].get("color", "123A60"))
            tc_borders.append(element)
    tc_pr.append(tc_borders)


def add_page_border(section):
    sect_pr = section._sectPr
    pg_borders = OxmlElement("w:pgBorders")
    pg_borders.set(qn("w:offsetFrom"), "page")
    for edge in ("top", "left", "bottom", "right"):
        border = OxmlElement(f"w:{edge}")
        border.set(qn("w:val"), "single")
        border.set(qn("w:sz"), "12")
        border.set(qn("w:space"), "18")
        border.set(qn("w:color"), "123A60")
        pg_borders.append(border)
    sect_pr.append(pg_borders)


def add_header_footer(section):
    header = section.header
    header.is_linked_to_previous = False
    hp = header.paragraphs[0]
    hp.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    run = hp.add_run("DAPE-MA  ·  System Administrator Training  ·  Confidential")
    set_run_font(run, size=9, color=NAVY, italic=True)

    gold = header.add_paragraph()
    gold.paragraph_format.space_before = Pt(2)
    gold.paragraph_format.space_after = Pt(0)
    pPr = gold._p.get_or_add_pPr()
    pBdr = OxmlElement("w:pBdr")
    bottom = OxmlElement("w:bottom")
    bottom.set(qn("w:val"), "single")
    bottom.set(qn("w:sz"), "12")
    bottom.set(qn("w:space"), "1")
    bottom.set(qn("w:color"), "FBD116")
    pBdr.append(bottom)
    pPr.append(pBdr)

    footer = section.footer
    footer.is_linked_to_previous = False
    fp = footer.paragraphs[0]
    fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = fp.add_run("DAPE-MA System Administrator Training  ·  Version 1.0  ·  Dangerous Drugs Board")
    set_run_font(run, size=8, color=NAVY)


def configure_styles(doc: Document):
    styles = doc.styles
    normal = styles["Normal"]
    normal.font.name = "Calibri"
    normal.font.size = Pt(11)
    normal.font.color.rgb = BLACK
    normal.paragraph_format.space_after = Pt(8)
    normal.paragraph_format.line_spacing = 1.15

    for style_name, size, color, space_before, space_after in (
        ("Title", 28, NAVY, 0, 6),
        ("Heading 1", 18, NAVY, 18, 8),
        ("Heading 2", 14, BLUE, 14, 6),
        ("Heading 3", 12, NAVY, 10, 4),
    ):
        style = styles[style_name]
        style.font.name = "Calibri"
        style.font.size = Pt(size)
        style.font.bold = True
        style.font.color.rgb = color
        style.paragraph_format.space_before = Pt(space_before)
        style.paragraph_format.space_after = Pt(space_after)
        style.paragraph_format.keep_with_next = True

    caption = styles["Caption"]
    caption.font.name = "Calibri"
    caption.font.size = Pt(10)
    caption.font.italic = True
    caption.font.color.rgb = NAVY
    caption.paragraph_format.space_before = Pt(4)
    caption.paragraph_format.space_after = Pt(12)


def add_cover(doc: Document):
    section = doc.sections[0]
    section.page_width = Inches(8.5)
    section.page_height = Inches(11)
    section.left_margin = Inches(0.9)
    section.right_margin = Inches(0.9)
    section.top_margin = Inches(0.8)
    section.bottom_margin = Inches(0.8)
    add_page_border(section)

    spacer = doc.add_paragraph()
    spacer.paragraph_format.space_before = Pt(24)

    if SEAL.exists():
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        run = p.add_run()
        run.add_picture(str(SEAL), width=Inches(1.35))

    agency = doc.add_paragraph()
    agency.alignment = WD_ALIGN_PARAGRAPH.CENTER
    agency.paragraph_format.space_before = Pt(12)
    run = agency.add_run("REPUBLIC OF THE PHILIPPINES")
    set_run_font(run, size=11, bold=True, color=NAVY)

    board = doc.add_paragraph()
    board.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = board.add_run("DANGEROUS DRUGS BOARD")
    set_run_font(run, size=14, bold=True, color=BLUE)

    line = doc.add_paragraph()
    line.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = line.add_run("━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
    set_run_font(run, size=10, color=GOLD)

    title = doc.add_paragraph()
    title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    title.paragraph_format.space_before = Pt(18)
    run = title.add_run("DAPE-MA")
    set_run_font(run, size=32, bold=True, color=NAVY)

    sub = doc.add_paragraph()
    sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = sub.add_run("SYSTEM ADMINISTRATOR TRAINING")
    set_run_font(run, size=20, bold=True, color=BLUE)

    tag = doc.add_paragraph()
    tag.alignment = WD_ALIGN_PARAGRAPH.CENTER
    tag.paragraph_format.space_before = Pt(8)
    run = tag.add_run("Technical operations pack for IT staff who install,\nupdate, and support the production platform")
    set_run_font(run, size=12, italic=True, color=GRAY)

    meta = doc.add_table(rows=5, cols=2)
    meta.alignment = WD_TABLE_ALIGNMENT.CENTER
    rows = [
        ("Document type", "Internal training (IT / operations)"),
        ("Companion manual", "DAPE-MA Administrator Manual (staff)"),
        ("Audience", "System administrators, Super Admins, DDB ICT"),
        ("Version", "1.0  ·  November 2026"),
        ("Classification", "Official use only — do not publish passwords"),
    ]
    for i, (k, v) in enumerate(rows):
        meta.rows[i].cells[0].text = k
        meta.rows[i].cells[1].text = v
        for j, cell in enumerate(meta.rows[i].cells):
            shade_cell(cell, "F8FAFC" if i % 2 == 0 else "FFFFFF")
            for p in cell.paragraphs:
                p.paragraph_format.space_after = Pt(2)
                p.paragraph_format.space_before = Pt(2)
                if p.runs:
                    set_run_font(p.runs[0], size=11, bold=(j == 0), color=NAVY if j == 0 else BLACK)

    notice = doc.add_paragraph()
    notice.alignment = WD_ALIGN_PARAGRAPH.CENTER
    notice.paragraph_format.space_before = Pt(18)
    run = notice.add_run(
        "This pack is for people who keep the server running.\n"
        "Content officers should use the Administrator Manual instead."
    )
    set_run_font(run, size=11, italic=True, color=NAVY)

    for line in (
        "DANGEROUS DRUGS BOARD",
        "3rd Floor DDB-PDEA Bldg.,",
        "National Government Center,",
        "NIA Road, Brgy. Pinyahan",
        "1100 Quezon City, Philippines",
        "www.ddb.gov.ph",
    ):
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_before = Pt(0)
        p.paragraph_format.space_after = Pt(0)
        run = p.add_run(line)
        set_run_font(run, size=10, bold=(line == "DANGEROUS DRUGS BOARD"), color=NAVY if line == "DANGEROUS DRUGS BOARD" else GRAY)

    doc.add_page_break()


def add_toc(doc: Document):
    h = doc.add_heading("Contents", level=1)
    items = [
        "1. About this training",
        "2. Platform architecture",
        "3. Environments and configuration",
        "4. Identity, roles, and security operations",
        "5. API contract and content workflow",
        "6. Server install and first boot",
        "7. Updates, Git, and the mobile APK",
        "8. Mail, queue, and backups",
        "9. Monitoring, logs, and incidents",
        "10. Training checklist and sign-off",
    ]
    for item in items:
        p = doc.add_paragraph()
        p.paragraph_format.space_after = Pt(4)
        run = p.add_run(item)
        set_run_font(run, size=12, color=NAVY)
    doc.add_page_break()


INLINE_RE = re.compile(r"(\*\*[^*]+\*\*|`[^`]+`|\*[^*]+\*)")


def add_inline(paragraph, text: str, base_size: int = 11):
    parts = INLINE_RE.split(text)
    for part in parts:
        if not part:
            continue
        if part.startswith("**") and part.endswith("**"):
            run = paragraph.add_run(part[2:-2])
            set_run_font(run, size=base_size, bold=True)
        elif part.startswith("`") and part.endswith("`"):
            run = paragraph.add_run(part[1:-1])
            set_run_font(run, name="Consolas", size=base_size - 1, color=BLUE)
        elif part.startswith("*") and part.endswith("*"):
            run = paragraph.add_run(part[1:-1])
            set_run_font(run, size=base_size, italic=True)
        else:
            run = paragraph.add_run(part)
            set_run_font(run, size=base_size)


def add_code_block(doc: Document, lines: list[str]):
    table = doc.add_table(rows=1, cols=1)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    cell = table.rows[0].cells[0]
    shade_cell(cell, CODE_BG)
    set_cell_border(
        cell,
        top={"sz": 8, "color": CODE_BORDER},
        left={"sz": 16, "color": "055498"},
        bottom={"sz": 8, "color": CODE_BORDER},
        right={"sz": 8, "color": CODE_BORDER},
    )
    text = "\n".join(lines).rstrip() or " "
    p = cell.paragraphs[0]
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after = Pt(6)
    p.paragraph_format.line_spacing = 1.08
    run = p.add_run(text)
    set_run_font(run, name="Consolas", size=9, color=NAVY)
    spacer = doc.add_paragraph()
    spacer.paragraph_format.space_after = Pt(6)


def add_callout(doc: Document, kind: str, body: str):
    colors = {
        "Note": ("E8F1F8", "055498"),
        "Tip": ("FFF8DC", "123A60"),
        "Warning": ("FDECEC", "CE2028"),
        "Important": ("FDECEC", "CE2028"),
    }
    fill, accent = colors.get(kind, ("F3F4F6", "123A60"))
    table = doc.add_table(rows=1, cols=1)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    cell = table.rows[0].cells[0]
    shade_cell(cell, fill)
    set_cell_border(
        cell,
        top={"sz": 4, "color": accent},
        left={"sz": 24, "color": accent},
        bottom={"sz": 4, "color": accent},
        right={"sz": 4, "color": accent},
    )
    p = cell.paragraphs[0]
    label = p.add_run(f"{kind}. ")
    set_run_font(label, size=11, bold=True, color=rgb_from_hex(accent))
    add_inline(p, body)
    spacer = doc.add_paragraph()
    spacer.paragraph_format.space_after = Pt(4)


def add_image(doc: Document, rel_path: str, alt: str):
    name = Path(rel_path).name
    path = FIGURES_DIR / name
    if not path.exists():
        p = doc.add_paragraph()
        run = p.add_run(f"[Figure missing: {name}]")
        set_run_font(run, italic=True, color=RED)
        return
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(8)
    p.paragraph_format.space_after = Pt(2)
    run = p.add_run()
    run.add_picture(str(path), width=Inches(6.5))
    cap = doc.add_paragraph()
    cap.alignment = WD_ALIGN_PARAGRAPH.CENTER
    cap.style = "Caption"
    run = cap.add_run(alt.strip() or name)
    set_run_font(run, size=10, italic=True, color=NAVY)


def add_table_from_md(doc: Document, rows: list[list[str]]):
    if not rows:
        return
    table = doc.add_table(rows=len(rows), cols=len(rows[0]))
    table.style = "Table Grid"
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, row in enumerate(rows):
        for j, value in enumerate(row):
            cell = table.rows[i].cells[j]
            cell.text = ""
            p = cell.paragraphs[0]
            add_inline(p, value.strip(), base_size=10)
            if i == 0:
                shade_cell(cell, "123A60")
                for run in p.runs:
                    run.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
                    run.bold = True
            elif i % 2 == 0:
                shade_cell(cell, "F8FAFC")
    spacer = doc.add_paragraph()
    spacer.paragraph_format.space_after = Pt(6)


def parse_table_line(line: str) -> list[str]:
    return [c.strip() for c in line.strip().strip("|").split("|")]


def is_separator(line: str) -> bool:
    cells = parse_table_line(line)
    return all(re.fullmatch(r":?-{3,}:?", c.replace(" ", "")) for c in cells)


def render_chapter(doc: Document, text: str):
    lines = text.splitlines()
    i = 0
    in_code = False
    code_buf: list[str] = []
    while i < len(lines):
        line = lines[i]
        if line.startswith("```"):
            if in_code:
                add_code_block(doc, code_buf)
                code_buf = []
                in_code = False
            else:
                in_code = True
            i += 1
            continue
        if in_code:
            code_buf.append(line)
            i += 1
            continue
        if not line.strip():
            i += 1
            continue
        if line.startswith("# "):
            doc.add_heading(line[2:].strip(), level=1)
            i += 1
            continue
        if line.startswith("## "):
            doc.add_heading(line[3:].strip(), level=2)
            i += 1
            continue
        if line.startswith("### "):
            doc.add_heading(line[4:].strip(), level=3)
            i += 1
            continue
        if line.startswith("!["):
            m = re.match(r"!\[([^\]]*)\]\(([^)]+)\)", line)
            if m:
                add_image(doc, m.group(2), m.group(1))
            i += 1
            continue
        if line.startswith("|"):
            rows = []
            while i < len(lines) and lines[i].startswith("|"):
                if not is_separator(lines[i]):
                    rows.append(parse_table_line(lines[i]))
                i += 1
            add_table_from_md(doc, rows)
            continue
        if line.startswith("> "):
            body = line[2:]
            kind = "Note"
            for label in ("Warning", "Tip", "Note", "Important"):
                if body.startswith(f"**{label}.**"):
                    kind = label
                    body = body[len(f"**{label}.**") :].strip()
                    break
            i += 1
            extra = []
            while i < len(lines) and lines[i].startswith("> "):
                extra.append(lines[i][2:])
                i += 1
            if extra:
                body = (body + " " + " ".join(extra)).strip()
            add_callout(doc, kind, body)
            continue
        if re.match(r"^- \[[ xX]\] ", line):
            p = doc.add_paragraph(style="List Bullet")
            mark = "☑ " if re.match(r"^- \[[xX]\] ", line) else "☐ "
            add_inline(p, mark + line[6:].strip())
            i += 1
            continue
        if line.startswith("- "):
            p = doc.add_paragraph(style="List Bullet")
            add_inline(p, line[2:].strip())
            i += 1
            continue
        if re.match(r"^\d+\. ", line):
            p = doc.add_paragraph(style="List Number")
            add_inline(p, re.sub(r"^\d+\. ", "", line).strip())
            i += 1
            continue
        p = doc.add_paragraph()
        add_inline(p, line.strip())
        i += 1


def build():
    missing = [name for name in CHAPTER_FILES if not (CHAPTERS_DIR / name).exists()]
    if missing:
        raise SystemExit(f"Missing chapters: {missing}")

    doc = Document()
    configure_styles(doc)
    add_cover(doc)
    add_header_footer(doc.sections[0])
    add_toc(doc)

    for index, name in enumerate(CHAPTER_FILES):
        render_chapter(doc, (CHAPTERS_DIR / name).read_text(encoding="utf-8"))
        if index < len(CHAPTER_FILES) - 1:
            doc.add_page_break()

    doc.save(str(OUTPUT))
    print(f"Wrote {OUTPUT} ({OUTPUT.stat().st_size:,} bytes)")


if __name__ == "__main__":
    build()
