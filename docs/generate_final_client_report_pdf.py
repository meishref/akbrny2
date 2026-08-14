#!/usr/bin/env python3
"""Generate docs/FINAL_CLIENT_REPORT.pdf — client-facing upgrade report.

Uses verified project facts and real screenshots only.
Does not embed secrets, credentials, or .env content.
"""

from __future__ import annotations

import os
from pathlib import Path

from PIL import Image as PILImage
from PIL import ImageDraw, ImageFont
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT, TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm, mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    HRFlowable,
    Image,
    KeepTogether,
    ListFlowable,
    ListItem,
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "docs" / "FINAL_CLIENT_REPORT.pdf"
SHOTS = ROOT / "docs" / "screenshots" / "final"

# Fonts
pdfmetrics.registerFont(TTFont("DejaVu", "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"))
pdfmetrics.registerFont(TTFont("DejaVuBold", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"))
pdfmetrics.registerFont(TTFont("DejaVuMono", "/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf"))
AR_REG = "/usr/share/fonts/truetype/noto/NotoNaskhArabic-Regular.ttf"
AR_BOLD = "/usr/share/fonts/truetype/noto/NotoNaskhArabic-Bold.ttf"
if Path(AR_REG).exists():
    pdfmetrics.registerFont(TTFont("NotoAr", AR_REG))
    pdfmetrics.registerFont(TTFont("NotoArBold", AR_BOLD if Path(AR_BOLD).exists() else AR_REG))
else:
    pdfmetrics.registerFont(TTFont("NotoAr", "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"))
    pdfmetrics.registerFont(TTFont("NotoArBold", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"))

PAGE_W, PAGE_H = A4
MARGIN = 1.8 * cm

PRIMARY = colors.HexColor("#1a365d")
ACCENT = colors.HexColor("#2b6cb0")
LIGHT = colors.HexColor("#ebf4ff")
OK = colors.HexColor("#276749")
WARN = colors.HexColor("#975a16")
MUTED = colors.HexColor("#4a5568")
BORDER = colors.HexColor("#cbd5e0")

SCREENSHOTS_USED: list[str] = []


def styles():
    s = getSampleStyleSheet()
    s.add(ParagraphStyle(name="CoverTitle", fontName="DejaVuBold", fontSize=26, leading=32,
                         alignment=TA_CENTER, textColor=PRIMARY, spaceAfter=12))
    s.add(ParagraphStyle(name="CoverSub", fontName="DejaVu", fontSize=14, leading=20,
                         alignment=TA_CENTER, textColor=ACCENT, spaceAfter=8))
    s.add(ParagraphStyle(name="CoverMeta", fontName="DejaVu", fontSize=11, leading=16,
                         alignment=TA_CENTER, textColor=MUTED, spaceAfter=6))
    s.add(ParagraphStyle(name="ArabicTitle", fontName="NotoArBold", fontSize=22, leading=30,
                         alignment=TA_CENTER, textColor=PRIMARY, spaceAfter=10))
    s.add(ParagraphStyle(name="H1", fontName="DejaVuBold", fontSize=16, leading=22,
                         textColor=PRIMARY, spaceBefore=14, spaceAfter=8))
    s.add(ParagraphStyle(name="H2", fontName="DejaVuBold", fontSize=12, leading=16,
                         textColor=ACCENT, spaceBefore=10, spaceAfter=6))
    s.add(ParagraphStyle(name="Body", fontName="DejaVu", fontSize=10, leading=14,
                         alignment=TA_JUSTIFY, textColor=colors.HexColor("#1a202c"), spaceAfter=6))
    s.add(ParagraphStyle(name="BodySmall", fontName="DejaVu", fontSize=9, leading=12,
                         textColor=MUTED, spaceAfter=4))
    s.add(ParagraphStyle(name="Caption", fontName="DejaVu", fontSize=8, leading=11,
                         alignment=TA_CENTER, textColor=MUTED, spaceBefore=4, spaceAfter=10))
    s.add(ParagraphStyle(name="CodeBlock", fontName="DejaVuMono", fontSize=8, leading=11,
                         textColor=colors.HexColor("#2d3748"), spaceAfter=4))
    s.add(ParagraphStyle(name="TOC", fontName="DejaVu", fontSize=10, leading=16,
                         textColor=colors.HexColor("#1a202c")))
    s.add(ParagraphStyle(name="StatusOk", fontName="DejaVuBold", fontSize=9, leading=12,
                         textColor=OK))
    s.add(ParagraphStyle(name="StatusWarn", fontName="DejaVuBold", fontSize=9, leading=12,
                         textColor=WARN))
    s.add(ParagraphStyle(name="Cell", fontName="DejaVu", fontSize=8, leading=11,
                         textColor=colors.HexColor("#1a202c")))
    s.add(ParagraphStyle(name="CellBold", fontName="DejaVuBold", fontSize=8, leading=11,
                         textColor=colors.HexColor("#1a202c")))
    s.add(ParagraphStyle(name="Footer", fontName="DejaVu", fontSize=8, leading=10,
                         alignment=TA_CENTER, textColor=MUTED))
    return s


S = styles()


def footer(canvas, doc):
    canvas.saveState()
    canvas.setStrokeColor(BORDER)
    canvas.setLineWidth(0.5)
    canvas.line(MARGIN, 1.2 * cm, PAGE_W - MARGIN, 1.2 * cm)
    canvas.setFont("DejaVu", 8)
    canvas.setFillColor(MUTED)
    canvas.drawString(MARGIN, 0.7 * cm, "Akbrny — Laravel 5.8 → 13 Upgrade Report")
    canvas.drawRightString(PAGE_W - MARGIN, 0.7 * cm, f"Page {doc.page}")
    canvas.restoreState()


def hr():
    return HRFlowable(width="100%", thickness=1, color=BORDER, spaceBefore=4, spaceAfter=8)


def status_badge(text: str):
    color = OK if text in ("IMPLEMENTED", "VERIFIED") else WARN
    data = [[Paragraph(f"<b>{text}</b>", ParagraphStyle(
        "badge", fontName="DejaVuBold", fontSize=8, textColor=colors.white, alignment=TA_CENTER))]]
    t = Table(data, colWidths=[5.2 * cm])
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), color),
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
        ("ROUNDEDCORNERS", [3, 3, 3, 3]),
    ]))
    return t


def make_table(headers, rows, col_widths=None):
    head = [Paragraph(h, S["CellBold"]) for h in headers]
    body = []
    for row in rows:
        body.append([Paragraph(str(c), S["Cell"]) for c in row])
    data = [head] + body
    t = Table(data, colWidths=col_widths, repeatRows=1)
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), PRIMARY),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("FONTNAME", (0, 0), (-1, 0), "DejaVuBold"),
        ("BACKGROUND", (0, 1), (-1, -1), colors.white),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, LIGHT]),
        ("GRID", (0, 0), (-1, -1), 0.4, BORDER),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
    ]))
    # Override header text color via Paragraph already set; force white bg cell style
    for i, h in enumerate(headers):
        data[0][i] = Paragraph(f'<font color="white"><b>{h}</b></font>', S["Cell"])
    t = Table(data, colWidths=col_widths, repeatRows=1)
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), PRIMARY),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, LIGHT]),
        ("GRID", (0, 0), (-1, -1), 0.4, BORDER),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
    ]))
    return t


def add_screenshot(story, filename: str, caption: str, max_width=16.5 * cm, max_height=9.5 * cm):
    path = SHOTS / filename
    note = KeepTogether([
        Paragraph(
            f"<b>Screenshot unavailable:</b> <font face='DejaVuMono'>{filename}</font> "
            f"was not found under docs/screenshots/final/. No fabricated image was inserted.",
            S["BodySmall"],
        ),
        Spacer(1, 6),
    ])
    if not path.exists() and not path.is_symlink():
        # resolve broken symlink
        story.append(note)
        return False
    try:
        resolved = path.resolve(strict=True)
    except FileNotFoundError:
        story.append(note)
        return False

    with PILImage.open(resolved) as im:
        w, h = im.size
    ratio = min(max_width / w, max_height / h, 1.0)
    disp_w, disp_h = w * ratio, h * ratio
    img = Image(str(resolved), width=disp_w, height=disp_h)
    story.append(KeepTogether([
        img,
        Paragraph(caption, S["Caption"]),
    ]))
    SCREENSHOTS_USED.append(str(resolved.relative_to(ROOT)) if resolved.is_relative_to(ROOT) else str(resolved))
    return True


def section(story, number: str, title: str):
    story.append(Paragraph(f"{number}. {title}", S["H1"]))
    story.append(hr())


def arabic_brand_image(out_path: Path, text: str = "اخبرني", width=520, height=90) -> Path:
    """Render Arabic with Pillow (correct shaping) for embedding in PDF."""
    font = ImageFont.truetype(
        "/usr/share/fonts/truetype/noto/NotoNaskhArabic-Bold.ttf", 56
    )
    img = PILImage.new("RGB", (width, height), (255, 255, 255))
    draw = ImageDraw.Draw(img)
    bbox = draw.textbbox((0, 0), text, font=font)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    draw.text(((width - tw) / 2, (height - th) / 2 - 4), text, fill=(26, 54, 93), font=font)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    img.save(out_path, "PNG")
    return out_path


def build():
    story = []
    brand_png = arabic_brand_image(SHOTS / "_brand_akbrny.png")

    # -------- COVER --------
    story.append(Spacer(1, 1.6 * cm))
    brand = Image(str(brand_png), width=9 * cm, height=1.55 * cm)
    brand_table = Table([[brand]], colWidths=[PAGE_W - 2 * MARGIN])
    brand_table.setStyle(TableStyle([("ALIGN", (0, 0), (-1, -1), "CENTER")]))
    story.append(brand_table)
    story.append(Paragraph("Akbrny Platform", S["CoverTitle"]))
    story.append(Paragraph("Final Client Technical Report", S["CoverSub"]))
    story.append(Paragraph("Laravel 5.8 → Laravel 13.24.0 Upgrade", S["CoverSub"]))
    story.append(Spacer(1, 0.4 * cm))
    story.append(hr())
    story.append(Paragraph("Anonymous messaging &amp; polls platform modernization", S["CoverMeta"]))
    story.append(Paragraph("Report date: 12 August 2026", S["CoverMeta"]))
    story.append(Paragraph("Branch: production-readiness", S["CoverMeta"]))
    story.append(Paragraph("Verified Laravel version: 13.24.0", S["CoverMeta"]))
    story.append(Spacer(1, 1.0 * cm))

    cover_stats = make_table(
        ["Metric", "Verified Result"],
        [
            ["Laravel", "5.8 → 13.24.0"],
            ["PHP compatibility", "^8.3 (test server PHP 8.3.33)"],
            ["Tests", "51 / 51 passing (211 assertions)"],
            ["API contract tests", "33 / 33"],
            ["FCM tests", "9 / 9"],
            ["API v1 tests", "4 / 4"],
            ["Legacy AJAX endpoints preserved", "14 / 14"],
            ["AJAX controllers / Services", "7 / 7 (+ FCM &amp; Storage services)"],
            ["Production database", "NOT modified"],
        ],
        col_widths=[7 * cm, 9.5 * cm],
    )
    story.append(cover_stats)
    story.append(Spacer(1, 1.2 * cm))
    story.append(Paragraph(
        "This report documents only work that was actually implemented and verified. "
        "Items that require server access or production deployment are labeled clearly.",
        S["BodySmall"],
    ))
    story.append(Paragraph(
        "Status labels used throughout: IMPLEMENTED · VERIFIED · REQUIRES SERVER VERIFICATION · PENDING PRODUCTION DEPLOYMENT",
        S["BodySmall"],
    ))
    story.append(PageBreak())

    # -------- TOC --------
    story.append(Paragraph("Table of Contents", S["H1"]))
    story.append(hr())
    toc_items = [
        "1. Executive Summary",
        "2. Laravel 5.8 → Laravel 13.24.0 Upgrade",
        "3. PHP 8.3 Compatibility",
        "4. Composer & Symfony Dependency Alignment",
        "5. AJAX Architecture Refactor",
        "6. Controllers Organization",
        "7. Service Layer",
        "8. Database Query Optimization",
        "9. Eloquent Conversion",
        "10. N+1 Query Fix",
        "11. Database Index Optimization",
        "12. API v1",
        "13. Legacy AJAX Compatibility",
        "14. Laravel Cache",
        "15. Laravel Queues / Jobs",
        "16. Firebase FCM HTTP v1",
        "17. Laravel Storage & Image Handling",
        "18. Rate Limiting",
        "19. Frontend Build Verification",
        "20. Test Server Deployment",
        "21. Database / Test Database",
        "22. Testing Results",
        "23. Security Improvements",
        "24. Performance Improvements",
        "25. Git History / Major Commits",
        "26. Before vs After",
        "27. Production Readiness Status",
        "28. Remaining Server-Side Checks",
        "29. Final Summary",
    ]
    for item in toc_items:
        story.append(Paragraph(item, S["TOC"]))
    story.append(PageBreak())

    # -------- 1 --------
    section(story, "1", "Executive Summary")
    story.append(Paragraph(
        "The Akbrny (اخبرني) platform was upgraded from Laravel 5.8 to Laravel 13.24.0 with "
        "PHP 8.3 compatibility, a full AJAX architecture refactor, Eloquent query modernization, "
        "API v1, Firebase FCM HTTP v1, Laravel Storage for profile images, caching for static pages, "
        "queued notifications, and rate limiting.",
        S["Body"],
    ))
    story.append(Paragraph(
        "All 14 legacy AJAX endpoints retain their original URLs, HTTP methods, and route names. "
        "The production database was not modified. Application migrations were verified on a "
        "separate test database (<font face='DejaVuMono'>akbrny2026_akbrny_test</font>).",
        S["Body"],
    ))
    story.append(Paragraph("Key verified outcomes", S["H2"]))
    story.append(make_table(
        ["Area", "Result", "Status"],
        [
            ["Framework", "Laravel 13.24.0", "VERIFIED"],
            ["PHP", "^8.3 / test server 8.3.33", "VERIFIED"],
            ["Automated tests", "51 tests, 211 assertions", "VERIFIED"],
            ["AJAX compatibility", "14/14 endpoints preserved", "VERIFIED"],
            ["API v1", "5 endpoints", "IMPLEMENTED"],
            ["Production DB", "Not modified", "VERIFIED"],
        ],
        col_widths=[4.5 * cm, 7.5 * cm, 4.5 * cm],
    ))
    story.append(Spacer(1, 8))
    story.append(Paragraph(
        "Screenshot note: Directory <font face='DejaVuMono'>docs/screenshots/final/</font> was not "
        "initially present. Available verified screenshots from <font face='DejaVuMono'>docs/screenshots/</font> "
        "were linked into <font face='DejaVuMono'>final/</font> for this report. Topics without a real "
        "image are marked “Screenshot unavailable” — no images were fabricated.",
        S["BodySmall"],
    ))

    # -------- 2 --------
    section(story, "2", "Laravel 5.8 → Laravel 13.24.0 Upgrade")
    story.append(status_badge("VERIFIED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "The application framework was upgraded from Laravel 5.8 to Laravel 13.24.0. "
        "This includes modern bootstrap structure (<font face='DejaVuMono'>bootstrap/app.php</font>), "
        "updated middleware configuration, and Composer dependency resolution aligned with Laravel 13.",
        S["Body"],
    ))
    story.append(Paragraph(
        "<font face='DejaVuMono'>php artisan --version</font> → Laravel Framework 13.24.0",
        S["CodeBlock"],
    ))
    story.append(Paragraph(
        "Major upgrade commit: <font face='DejaVuMono'>469d783</font> — feat: upgrade project to Laravel 13 and PHP 8.4 "
        "(later aligned to PHP 8.3 in <font face='DejaVuMono'>4e4a844</font>).",
        S["Body"],
    ))

    # -------- 3 --------
    section(story, "3", "PHP 8.3 Compatibility")
    story.append(status_badge("VERIFIED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Composer requires PHP <font face='DejaVuMono'>^8.3</font> with platform pin "
        "<font face='DejaVuMono'>8.3.30</font> to keep dependency resolution on PHP 8.3-compatible packages. "
        "The test server runs PHP 8.3.33.",
        S["Body"],
    ))
    story.append(make_table(
        ["Item", "Value", "Status"],
        [
            ["composer.json php", "^8.3", "IMPLEMENTED"],
            ["platform.php", "8.3.30", "IMPLEMENTED"],
            ["Test server PHP", "8.3.33", "VERIFIED"],
            ["composer check-platform-reqs", "Pass", "VERIFIED"],
        ],
        col_widths=[5.5 * cm, 6 * cm, 5 * cm],
    ))

    # -------- 4 --------
    section(story, "4", "Composer &amp; Symfony Dependency Alignment")
    story.append(status_badge("VERIFIED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "After the initial Laravel 13 upgrade targeted PHP 8.4, dependencies were re-aligned for PHP 8.3. "
        "Symfony components were resolved to the 7.4.x line (Symfony 8.x requires PHP ≥ 8.4.1). "
        "Laravel itself remained at 13.24.0 — Laravel 13 officially supports PHP 8.3.",
        S["Body"],
    ))
    story.append(Paragraph(
        "Commit: <font face='DejaVuMono'>4e4a844</font> — fix(deps): align Laravel 13 dependencies with PHP 8.3",
        S["Body"],
    ))

    # -------- 5 --------
    section(story, "5", "AJAX Architecture Refactor")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "The monolithic <font face='DejaVuMono'>AjaxController</font> (~708 lines) was split into focused "
        "controllers under <font face='DejaVuMono'>app/Http/Controllers/Ajax/</font>, with business logic "
        "moved into service classes. Routes remain grouped under <font face='DejaVuMono'>/ajax</font> with "
        "identical URLs, methods, and names.",
        S["Body"],
    ))
    story.append(Paragraph("Project structure after refactor", S["H2"]))
    add_screenshot(story, "01-structure.png",
                   "Figure: Application structure — Ajax controllers, Api/V1, and Services.")

    # -------- 6 --------
    section(story, "6", "Controllers Organization")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(make_table(
        ["Controller", "Responsibility"],
        [
            ["EmailCheckController", "Email availability check"],
            ["MessageController", "Reply / edit / delete messages"],
            ["QuestionController", "Add poll/question posts"],
            ["UserAccountController", "Profile, password, image, settings, social"],
            ["ProfileVoteController", "Poll voting"],
            ["NotificationController", "FCM token registration"],
            ["SearchController", "Site search"],
        ],
        col_widths=[6 * cm, 10.5 * cm],
    ))
    story.append(Spacer(1, 8))
    add_screenshot(story, "05-ajax-controller.png",
                   "Figure: Ajax MessageController after split — thin controller, service delegation.")

    # -------- 7 --------
    section(story, "7", "Service Layer")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Domain services encapsulate Eloquent queries and business rules shared by AJAX and API controllers.",
        S["Body"],
    ))
    story.append(make_table(
        ["Service", "Role"],
        [
            ["EmailAvailabilityService", "Email uniqueness checks"],
            ["MessageService", "Message reply/edit/delete"],
            ["QuestionService", "Poll/question creation"],
            ["UserAccountService", "Profile &amp; account updates"],
            ["PollVoteService", "Voting logic"],
            ["SiteSearchService", "Search queries"],
            ["NotificationTokenService", "Device token persistence"],
            ["FirebaseCloudMessaging", "FCM HTTP v1 send"],
            ["ProfileImageStorage", "Profile image Storage disk ops"],
        ],
        col_widths=[6.5 * cm, 10 * cm],
    ))
    story.append(Spacer(1, 8))
    add_screenshot(story, "04-services.png",
                   "Figure: MessageService — Eloquent-based business logic.")

    # -------- 8 --------
    section(story, "8", "Database Query Optimization")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Query execution was optimized at the application level. Raw Query Builder usage was reduced in "
        "favor of Eloquent models, <font face='DejaVuMono'>exists()</font> instead of unnecessary "
        "<font face='DejaVuMono'>count()</font> checks, and targeted selects where appropriate.",
        S["Body"],
    ))
    story.append(Paragraph(
        "Production response-time improvement requires production benchmarking. "
        "No percentage speed claim is made in this report.",
        S["Body"],
    ))

    # -------- 9 --------
    section(story, "9", "Eloquent Conversion")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Approximately 25 Query Builder usages were converted to Eloquent across services and providers "
        "(for example unread message counts, vote lookups, answer creation, and email checks).",
        S["Body"],
    ))
    story.append(Paragraph(
        "Commit: <font face='DejaVuMono'>be6f62e</font> — refactor: split AJAX monolith, add services, API v1, and Eloquent queries",
        S["Body"],
    ))

    # -------- 10 --------
    section(story, "10", "N+1 Query Fix")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Profile poll pages previously risked N+1 queries when checking viewer votes per poll. "
        "Votes are now batch-loaded once and passed to the view as "
        "<font face='DejaVuMono'>$viewerAnswersByPostId</font>.",
        S["Body"],
    ))
    add_screenshot(story, "07-n1-fix.png",
                   "Figure: ProfileController N+1 fix — batch Answer lookup keyed by post_id.")

    # -------- 11 --------
    section(story, "11", "Database Index Optimization")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Additive performance indexes were defined in migration "
        "<font face='DejaVuMono'>2026_08_08_040705_add_performance_indexes_to_posts_and_answers_tables</font>:",
        S["Body"],
    ))
    story.append(make_table(
        ["Table", "Index", "Supports"],
        [
            ["posts", "user_id, is_read, type", "Unread counts / dashboard updates"],
            ["answers", "user_id, post_id", "Vote/reply duplicate checks"],
        ],
        col_widths=[3.5 * cm, 5.5 * cm, 7.5 * cm],
    ))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Applied successfully on the test database. Production application of this migration: "
        "PENDING PRODUCTION DEPLOYMENT / REQUIRES SERVER VERIFICATION.",
        S["Body"],
    ))
    add_screenshot(story, "08-indexes.png",
                   "Figure: Performance index migration source.")

    # -------- 12 --------
    section(story, "12", "API v1")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "A versioned JSON API was added under <font face='DejaVuMono'>/api/v1</font>, reusing the same "
        "service layer as AJAX endpoints.",
        S["Body"],
    ))
    story.append(make_table(
        ["Method", "Endpoint", "Auth"],
        [
            ["GET", "/api/v1/search", "Public"],
            ["POST", "/api/v1/messages/reply", "auth"],
            ["POST", "/api/v1/polls/vote", "auth"],
            ["POST", "/api/v1/notifications/token", "auth"],
            ["PUT", "/api/v1/users/profile", "auth"],
        ],
        col_widths=[3 * cm, 8.5 * cm, 5 * cm],
    ))
    story.append(Spacer(1, 8))
    add_screenshot(story, "03-routes-api.png",
                   "Figure: API v1 routes definition.")
    add_screenshot(story, "06-api-controller.png",
                   "Figure: API SearchController example.")

    # -------- 13 --------
    section(story, "13", "Legacy AJAX Compatibility")
    story.append(status_badge("VERIFIED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "All 14 legacy AJAX endpoints were preserved with unchanged URLs, HTTP methods, and route names. "
        "33/33 API contract tests pass against these contracts.",
        S["Body"],
    ))
    add_screenshot(story, "02-routes-web.png",
                   "Figure: Web + AJAX routes after refactor (same contracts).")

    # -------- 14 --------
    section(story, "14", "Laravel Cache")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Static informational pages (contact, privacy policy, terms) are served through "
        "<font face='DejaVuMono'>PageController</font> with a 24-hour Cache::remember TTL. "
        "User-specific data (messages, votes, auth state, notifications) is intentionally not cached.",
        S["Body"],
    ))
    story.append(Paragraph(
        "Recommended production driver: <font face='DejaVuMono'>CACHE_STORE=file</font> "
        "(Redis not required). Production driver configuration: REQUIRES SERVER VERIFICATION.",
        S["Body"],
    ))
    story.append(Paragraph(
        "Screenshot unavailable for cache implementation UI/code capture under docs/screenshots/final/. "
        "Implementation is verified in source (<font face='DejaVuMono'>PageController</font>) and "
        "<font face='DejaVuMono'>StaticPageCacheTest</font>.",
        S["BodySmall"],
    ))

    # -------- 15 --------
    section(story, "15", "Laravel Queues / Jobs")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "FCM notifications are dispatched via <font face='DejaVuMono'>SendFcmNotificationJob</font> "
        "after a message is saved. The HTTP response returns immediately; the worker sends the push.",
        S["Body"],
    ))
    story.append(make_table(
        ["Item", "Detail", "Status"],
        [
            ["Job class", "app/Jobs/SendFcmNotificationJob.php", "IMPLEMENTED"],
            ["Retries / backoff", "3 tries; 10s, 30s, 60s", "IMPLEMENTED"],
            ["Queue driver (example)", "database (no Redis required)", "IMPLEMENTED"],
            ["jobs table migration", "Exists in repository", "IMPLEMENTED"],
            ["failed_jobs migration", "Exists in repository", "IMPLEMENTED"],
            ["Queue worker on server", "Supervisor recommended", "REQUIRES SERVER VERIFICATION"],
            ["Tests", "QUEUE_CONNECTION=sync", "VERIFIED"],
        ],
        col_widths=[5 * cm, 7 * cm, 4.5 * cm],
    ))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Screenshot unavailable for Jobs directory under docs/screenshots/final/. "
        "Source and tests verify the job dispatch path.",
        S["BodySmall"],
    ))

    # -------- 16 --------
    section(story, "16", "Firebase FCM HTTP v1")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Push notifications use Firebase Cloud Messaging HTTP v1 with OAuth2 service-account JWT signing. "
        "Credentials are loaded from environment variables only (never committed).",
        S["Body"],
    ))
    story.append(make_table(
        ["Config key (env)", "Purpose"],
        [
            ["FIREBASE_PROJECT_ID", "Firebase project"],
            ["FIREBASE_CLIENT_EMAIL", "Service account email"],
            ["FIREBASE_PRIVATE_KEY", "Service account private key"],
            ["FIREBASE_REQUEST_TIMEOUT", "HTTP timeout (seconds)"],
        ],
        col_widths=[6 * cm, 10.5 * cm],
    ))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Automated tests use <font face='DejaVuMono'>Http::fake</font> — no real production notifications "
        "are sent during CI/local tests. FCM suite: 9/9 passing.",
        S["Body"],
    ))
    story.append(Paragraph(
        "Screenshot unavailable for FCM service source under docs/screenshots/final/. "
        "Verified via FcmNotificationTest and FirebaseCloudMessaging service.",
        S["BodySmall"],
    ))

    # -------- 17 --------
    section(story, "17", "Laravel Storage &amp; Image Handling")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Profile image upload/delete uses Laravel Storage with a dedicated "
        "<font face='DejaVuMono'>profile_images</font> disk rooted at "
        "<font face='DejaVuMono'>public/images/profile/</font>. Legacy public URLs "
        "(<font face='DejaVuMono'>/images/profile/{filename}</font>) are preserved so existing "
        "images remain accessible.",
        S["Body"],
    ))
    story.append(Paragraph(
        "Avatar fallback remains <font face='DejaVuMono'>img/avatar2.png</font>. "
        "Commit: <font face='DejaVuMono'>c71c085</font> — refactor(storage): modernize file and image handling.",
        S["Body"],
    ))
    story.append(Paragraph(
        "Screenshot unavailable for Storage configuration under docs/screenshots/final/. "
        "Verified via ProfileImageStorageTest.",
        S["BodySmall"],
    ))

    # -------- 18 --------
    section(story, "18", "Rate Limiting")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Laravel RateLimiter definitions protect public and sensitive endpoints without blocking normal use.",
        S["Body"],
    ))
    story.append(make_table(
        ["Limiter", "Limit", "Applied to"],
        [
            ["login", "10 / min / IP", "LoginController"],
            ["register", "5 / min / IP", "RegisterController"],
            ["password-reset", "5 / min / IP", "Forgot/Reset password"],
            ["messages", "20 / min / user|IP", "Message AJAX + profile send"],
            ["votes", "30 / min / user|IP", "Poll vote AJAX"],
            ["search", "60 / min / IP", "Site search"],
            ["notification-token", "10 / min / user|IP", "Token save"],
            ["api", "120 / min / user|IP", "API middleware"],
        ],
        col_widths=[4.5 * cm, 5 * cm, 7 * cm],
    ))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Screenshot unavailable for rate-limiter source under docs/screenshots/final/. "
        "Verified via RateLimitTest (429 after limit).",
        S["BodySmall"],
    ))

    # -------- 19 --------
    section(story, "19", "Frontend Build Verification")
    story.append(status_badge("VERIFIED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "The project uses Laravel Mix 4 (not Vite). Commands "
        "<font face='DejaVuMono'>npm install</font> and <font face='DejaVuMono'>npm run production</font> "
        "completed successfully locally, producing <font face='DejaVuMono'>public/js/app.js</font> and "
        "<font face='DejaVuMono'>public/css/app.css</font>.",
        S["Body"],
    ))
    story.append(Paragraph(
        "Important: Blade layouts continue to load UI assets from <font face='DejaVuMono'>public/style/</font>. "
        "Mix outputs are not referenced via <font face='DejaVuMono'>mix()</font> and are gitignored as build artifacts. "
        "Primary production UI assets remain the existing Bootstrap/style stack.",
        S["Body"],
    ))

    # -------- 20 --------
    section(story, "20", "Test Server Deployment")
    story.append(status_badge("VERIFIED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "The application was deployed successfully to a test environment running PHP 8.3.33 with a "
        "database separate from production. Laravel migrations were applied on the test database only.",
        S["Body"],
    ))
    story.append(Paragraph(
        "Screenshot unavailable for test-server console/UI under docs/screenshots/final/. "
        "Deployment success is recorded from verified project status at report time.",
        S["BodySmall"],
    ))

    # -------- 21 --------
    section(story, "21", "Database / Test Database")
    story.append(status_badge("VERIFIED"))
    story.append(Spacer(1, 6))
    story.append(make_table(
        ["Item", "Detail", "Status"],
        [
            ["Test database", "akbrny2026_akbrny_test", "VERIFIED"],
            ["Migrations on test DB", "Applied successfully", "VERIFIED"],
            ["Production database", "NOT modified", "VERIFIED"],
            ["Destructive commands", "migrate:fresh / db:wipe not used", "VERIFIED"],
            ["MySQL 8 on production host", "Confirm with SELECT VERSION()", "REQUIRES SERVER VERIFICATION"],
        ],
        col_widths=[5 * cm, 7 * cm, 4.5 * cm],
    ))

    # -------- 22 --------
    section(story, "22", "Testing Results")
    story.append(status_badge("VERIFIED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Full suite result (local verification for this report): <b>OK (51 tests, 211 assertions)</b>.",
        S["Body"],
    ))
    story.append(make_table(
        ["Suite", "Count", "Result"],
        [
            ["Total", "51", "PASS"],
            ["API contract", "33 / 33", "PASS"],
            ["FCM (Http::fake)", "9 / 9", "PASS"],
            ["API v1 feature", "4 / 4", "PASS"],
            ["Rate limiting", "2", "PASS"],
            ["Static page cache", "1", "PASS"],
            ["Profile Storage", "2", "PASS"],
        ],
        col_widths=[6 * cm, 5 * cm, 5.5 * cm],
    ))
    story.append(Spacer(1, 8))
    add_screenshot(story, "09-tests.png",
                   "Figure: API v1 / related automated test evidence screenshot.")

    # -------- 23 --------
    section(story, "23", "Security Improvements")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(make_table(
        ["Control", "Status", "Notes"],
        [
            ["CSRF on web/AJAX", "IMPLEMENTED", "Laravel web middleware"],
            ["Password hashing", "IMPLEMENTED", "bcrypt"],
            ["Rate limiting", "IMPLEMENTED", "Auth, messages, votes, search, API"],
            ["FCM credentials", "IMPLEMENTED", "Environment variables only"],
            ["APP_DEBUG=false (prod)", "PENDING PRODUCTION DEPLOYMENT", "Must be set on server"],
            ["Guest AJAX null handling", "PRE-EXISTING", "Documented; not silently changed"],
            ["Search HTML escaping", "PRE-EXISTING", "Documented future recommendation"],
        ],
        col_widths=[5 * cm, 5.5 * cm, 6 * cm],
    ))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "This PDF deliberately excludes .env contents, passwords, database credentials, API keys, "
        "Firebase private keys, tokens, and private user data.",
        S["BodySmall"],
    ))

    # -------- 24 --------
    section(story, "24", "Performance Improvements")
    story.append(status_badge("IMPLEMENTED"))
    story.append(Spacer(1, 6))
    story.append(Paragraph(
        "Query execution was optimized at the application level through Eloquent conversion, "
        "an N+1 fix on profile polls, additive database indexes, and static page caching.",
        S["Body"],
    ))
    story.append(Paragraph(
        "Production response-time improvement requires production benchmarking. "
        "This report does not claim a percentage performance gain.",
        S["Body"],
    ))
    story.append(make_table(
        ["Optimization", "Status"],
        [
            ["Eloquent / exists() conversions (~25)", "IMPLEMENTED"],
            ["N+1 profile poll votes", "IMPLEMENTED"],
            ["Composite indexes (posts, answers)", "IMPLEMENTED (applied on test DB)"],
            ["Static page cache (24h)", "IMPLEMENTED"],
            ["Async FCM via queue job", "IMPLEMENTED"],
            ["OPcache / PHP-FPM tuning", "REQUIRES SERVER VERIFICATION"],
        ],
        col_widths=[11 * cm, 5.5 * cm],
    ))

    # -------- 25 --------
    section(story, "25", "Git History / Major Commits")
    story.append(status_badge("VERIFIED"))
    story.append(Spacer(1, 6))
    story.append(make_table(
        ["Commit", "Summary"],
        [
            ["469d783", "Upgrade project to Laravel 13"],
            ["4e4a844", "Align dependencies with PHP 8.3"],
            ["be6f62e", "Split AJAX, services, API v1, Eloquent"],
            ["c71c085", "Modernize profile image Storage"],
            ["062164d", "Define application rate limiters"],
            ["108bdbc", "Cache static pages + route throttles"],
            ["c9683bd", "Queue FCM notifications via Jobs"],
            ["73dac5d", "Verify Laravel Mix production build"],
            ["16071c7", "Production readiness documentation"],
        ],
        col_widths=[3 * cm, 13.5 * cm],
    ))
    story.append(Spacer(1, 8))
    add_screenshot(story, "10-git-history.png",
                   "Figure: Git history evidence for architecture refactor commit(s).")
    story.append(Paragraph(
        "Branch <font face='DejaVuMono'>production-readiness</font> contains the production-readiness "
        "commits listed above. No automatic push to production was performed as part of this work.",
        S["Body"],
    ))

    # -------- 26 --------
    section(story, "26", "Before vs After")
    story.append(make_table(
        ["Area", "Before (Laravel 5.8 era)", "After (Laravel 13)"],
        [
            ["Framework", "Laravel 5.8", "Laravel 13.24.0"],
            ["PHP", "Legacy / mixed", "PHP ^8.3 (test: 8.3.33)"],
            ["AJAX", "Single AjaxController monolith", "7 focused Ajax controllers"],
            ["Business logic", "Inside controllers", "Service layer"],
            ["API", "No versioned API v1", "5 /api/v1 endpoints"],
            ["Queries", "Heavy Query Builder", "~25 Eloquent conversions"],
            ["N+1 (profile polls)", "Per-poll vote queries", "Batch load"],
            ["Indexes", "Missing composites", "Additive migration prepared"],
            ["FCM", "Legacy / blocking sync path", "HTTP v1 + queued Job"],
            ["Images", "Direct public filesystem writes", "Storage disk (legacy URLs kept)"],
            ["Cache", "None for static pages", "24h Cache::remember"],
            ["Rate limits", "Not configured", "Named RateLimiters"],
            ["Tests", "Limited / older baseline", "51 tests / 211 assertions"],
        ],
        col_widths=[3.8 * cm, 6.2 * cm, 6.5 * cm],
    ))

    # -------- 27 --------
    section(story, "27", "Production Readiness Status")
    story.append(make_table(
        ["Component", "Status"],
        [
            ["Laravel 13 + PHP 8.3 application code", "VERIFIED"],
            ["Automated test suite", "VERIFIED"],
            ["AJAX / API contracts preserved", "VERIFIED"],
            ["Cache / Queues / Storage / Rate limiting code", "IMPLEMENTED"],
            ["FCM HTTP v1 code + tests", "VERIFIED"],
            ["Frontend Mix build (local)", "VERIFIED"],
            ["Test DB migrations", "VERIFIED"],
            ["Production .env (APP_DEBUG=false, Firebase, DB)", "PENDING PRODUCTION DEPLOYMENT"],
            ["Production migrations (jobs, indexes, failed_jobs)", "PENDING PRODUCTION DEPLOYMENT"],
            ["Queue worker (Supervisor)", "REQUIRES SERVER VERIFICATION"],
            ["Nginx / PHP-FPM / OPcache / SSL", "REQUIRES SERVER VERIFICATION"],
            ["MySQL version on production", "REQUIRES SERVER VERIFICATION"],
        ],
        col_widths=[11 * cm, 5.5 * cm],
    ))

    # -------- 28 --------
    section(story, "28", "Remaining Server-Side Checks")
    story.append(Paragraph(
        "The following items cannot be completed from the application repository alone:",
        S["Body"],
    ))
    checks = [
        "Confirm production PHP is 8.3.x with required extensions.",
        "Confirm MySQL version (SELECT VERSION();) — MySQL 8 recommended.",
        "Set APP_ENV=production and APP_DEBUG=false.",
        "Configure Firebase env vars without committing secrets.",
        "Backup production DB, then run pending additive migrations only.",
        "Start queue worker for database driver (SendFcmNotificationJob).",
        "Ensure public/images/profile is writable by PHP-FPM user.",
        "Enable OPcache in PHP-FPM and tune pool size.",
        "Verify Nginx document root points to /public and SSL is valid.",
        "Run production smoke tests (login, message, vote, search, image upload).",
        "Benchmark production response times before claiming latency improvement.",
    ]
    for c in checks:
        story.append(Paragraph(f"• {c}", S["Body"]))

    # -------- 29 --------
    section(story, "29", "Final Summary")
    story.append(Paragraph(
        "The Akbrny platform has been successfully modernized from Laravel 5.8 to Laravel 13.24.0 "
        "with PHP 8.3 compatibility, a maintainable AJAX/service architecture, API v1, query "
        "optimizations, FCM HTTP v1, Storage-based image handling, caching, queues, and rate limiting.",
        S["Body"],
    ))
    story.append(Paragraph(
        "Application-level work is complete and covered by automated tests (51/51). "
        "Production cutover still requires controlled server configuration, additive migrations, "
        "queue workers, and production benchmarking.",
        S["Body"],
    ))
    story.append(Spacer(1, 10))
    story.append(make_table(
        ["Final checklist", "Status"],
        [
            ["Laravel 13.24.0", "VERIFIED"],
            ["PHP 8.3 compatibility", "VERIFIED"],
            ["51 tests / 211 assertions", "VERIFIED"],
            ["14/14 AJAX endpoints preserved", "VERIFIED"],
            ["Production DB unmodified", "VERIFIED"],
            ["Production deployment", "PENDING PRODUCTION DEPLOYMENT"],
            ["Server hardening (Nginx/FPM/OPcache)", "REQUIRES SERVER VERIFICATION"],
        ],
        col_widths=[11 * cm, 5.5 * cm],
    ))
    story.append(Spacer(1, 14))
    story.append(Paragraph("— End of report —", S["CoverMeta"]))

    doc = SimpleDocTemplate(
        str(OUT),
        pagesize=A4,
        leftMargin=MARGIN,
        rightMargin=MARGIN,
        topMargin=1.6 * cm,
        bottomMargin=1.8 * cm,
        title="Akbrny Final Client Report — Laravel 5.8 to 13.24.0",
        author="Akbrny Upgrade Team",
    )
    doc.build(story, onFirstPage=footer, onLaterPages=footer)
    return OUT


if __name__ == "__main__":
    path = build()
    size = path.stat().st_size
    import fitz
    pdf = fitz.open(path)
    pages = pdf.page_count
    # Count image XObjects roughly
    img_count = 0
    for i in range(pages):
        img_count += len(pdf[i].get_images(full=True))
    # Secret scan (basic)
    text = "".join(page.get_text() for page in pdf)
    banned = ["FIREBASE_PRIVATE_KEY=", "DB_PASSWORD=secret", "APP_KEY=base64:", "BEGIN PRIVATE KEY"]
    leaks = [b for b in banned if b in text]
    print(f"PDF={path}")
    print(f"PAGES={pages}")
    print(f"SIZE_BYTES={size}")
    print(f"SIZE_MB={size/1024/1024:.2f}")
    print(f"EMBEDDED_IMAGES={img_count}")
    print(f"SCREENSHOTS_USED={len(SCREENSHOTS_USED)}")
    for s in SCREENSHOTS_USED:
        print(f"  - {s}")
    print(f"SECRET_LEAKS={leaks if leaks else 'none'}")
    pdf.close()
