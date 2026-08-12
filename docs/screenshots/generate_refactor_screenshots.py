#!/usr/bin/env python3
"""Generate after-refactor screenshots for architecture report."""

from __future__ import annotations

import subprocess
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(__file__).resolve().parent

BG = (30, 30, 30)
HEADER_BG = (45, 45, 48)
TEXT = (212, 212, 212)
LINE_NUM = (100, 100, 100)
ACCENT = (86, 156, 214)
HIGHLIGHT_BG = (60, 50, 20)

MONO = [
    "/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf",
]


def font(size: int):
    for path in MONO:
        if Path(path).exists():
            return ImageFont.truetype(path, size)
    return ImageFont.load_default()


def render_code(filename: str, content: str, title: str, highlight: set[int] | None = None, max_lines: int = 55):
    highlight = highlight or set()
    lines = content.splitlines()[:max_lines]
    f, f_t = font(13), font(13)
    lh = 20
    w, h = 1200, 40 + len(lines) * lh + 16
    img = Image.new("RGB", (w, h), BG)
    draw = ImageDraw.Draw(img)
    draw.rectangle([0, 0, w, 32], fill=HEADER_BG)
    draw.text((16, 8), title, fill=ACCENT, font=f_t)
    y = 40
    for i, line in enumerate(lines, 1):
        if i in highlight:
            draw.rectangle([0, y - 2, w, y + lh - 2], fill=HIGHLIGHT_BG)
        draw.text((16, y), f"{i:>4}", fill=LINE_NUM, font=f)
        draw.text((64, y), line[:110], fill=TEXT, font=f)
        y += lh
    (OUT / filename).write_bytes(b"")
    img.save(OUT / filename, "PNG")
    print(f"Created {filename}")


def render_tree(filename: str, title: str):
    tree = """app/Http/Controllers/
├── Ajax/
│   ├── EmailCheckController.php
│   ├── MessageController.php
│   ├── QuestionController.php
│   ├── UserAccountController.php
│   ├── ProfileVoteController.php
│   ├── NotificationController.php
│   └── SearchController.php
├── Api/V1/
│   ├── SearchController.php
│   ├── MessageController.php
│   ├── PollController.php
│   ├── NotificationController.php
│   └── UserController.php
├── User/
│   ├── ProfileController.php
│   └── UsersController.php
└── Auth/ ...

app/Services/
├── EmailAvailabilityService.php
├── MessageService.php
├── QuestionService.php
├── UserAccountService.php
├── PollVoteService.php
├── SiteSearchService.php
├── NotificationTokenService.php
└── FirebaseCloudMessaging.php"""
    render_code(filename, tree, title, max_lines=30)


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8", errors="replace")


def main():
    render_tree("refactor-01-structure-after.png", "Project structure AFTER refactor")
    render_code("refactor-02-routes-web-after.png", read("routes/web.php"), "routes/web.php — grouped AJAX routes")
    render_code("refactor-03-routes-api-after.png", read("routes/api.php"), "routes/api.php — v1 JSON API")
    render_code(
        "refactor-04-message-service.png",
        read("app/Services/MessageService.php"),
        "MessageService — Eloquent + exists()",
        highlight={11, 12, 15, 16, 19, 20},
    )
    render_code(
        "refactor-05-ajax-message-controller.png",
        read("app/Http/Controllers/Ajax/MessageController.php"),
        "Ajax\\MessageController",
    )
    render_code(
        "refactor-06-api-search-controller.png",
        read("app/Http/Controllers/Api/V1/SearchController.php"),
        "Api\\V1\\SearchController",
    )
    render_code(
        "refactor-07-profile-n1-fix.png",
        "\n".join(read("app/Http/Controllers/User/ProfileController.php").splitlines()[28:48]),
        "ProfileController — batch viewer answers (N+1 fix)",
        highlight={5, 6, 7, 8, 9, 10, 11},
    )
    render_code(
        "refactor-08-performance-index-migration.png",
        read("database/migrations/2026_08_08_040705_add_performance_indexes_to_posts_and_answers_tables.php"),
        "Performance indexes migration",
    )
    render_code(
        "refactor-09-api-v1-tests.png",
        read("tests/Feature/ApiV1Test.php"),
        "ApiV1Test — new API coverage",
    )
    git_log = subprocess.run(
        ["git", "-C", str(ROOT), "log", "--oneline", "-5"],
        capture_output=True,
        text=True,
    ).stdout
    render_code("refactor-10-git-log.png", git_log, "Recent Git history", max_lines=10)


if __name__ == "__main__":
    main()
