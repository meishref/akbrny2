#!/usr/bin/env python3
"""Generate code/terminal screenshots for the technical report."""

from __future__ import annotations

import subprocess
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(__file__).resolve().parent

# Colors (dark IDE theme)
BG = (30, 30, 30)
HEADER_BG = (45, 45, 48)
TEXT = (212, 212, 212)
LINE_NUM = (100, 100, 100)
ACCENT = (86, 156, 214)
COMMENT = (106, 153, 85)
STRING = (206, 145, 120)
KEYWORD = (86, 156, 214)
HIGHLIGHT_BG = (60, 50, 20)

MONO = [
    "/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf",
    "/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf",
    "/usr/share/fonts/truetype/ubuntu/UbuntuMono-R.ttf",
]


def font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    for path in MONO:
        if Path(path).exists():
            return ImageFont.truetype(path, size)
    return ImageFont.load_default()


def wrap_lines(text: str, max_chars: int = 110) -> list[str]:
    lines: list[str] = []
    for raw in text.splitlines():
        if len(raw) <= max_chars:
            lines.append(raw)
            continue
        start = 0
        while start < len(raw):
            lines.append(raw[start : start + max_chars])
            start += max_chars
    return lines


def render_code(
    filename: str,
    content: str,
    title: str,
    highlight_lines: set[int] | None = None,
    max_lines: int = 55,
) -> None:
    highlight_lines = highlight_lines or set()
    lines = wrap_lines(content)[:max_lines]
    if len(wrap_lines(content)) > max_lines:
        lines.append("... (truncated)")

    f = font(14)
    f_title = font(13, bold=True)
    f_ln = font(13)

    line_h = 20
    pad_x = 16
    pad_y = 12
    ln_w = 48
    width = 1200
    height = pad_y * 2 + 28 + len(lines) * line_h + 8

    img = Image.new("RGB", (width, height), BG)
    draw = ImageDraw.Draw(img)

    # Header bar
    draw.rectangle([0, 0, width, 32], fill=HEADER_BG)
    draw.text((pad_x, 8), title, fill=ACCENT, font=f_title)
    draw.text((width - pad_x - 200, 8), filename, fill=LINE_NUM, font=f_ln)

    y = 40
    for i, line in enumerate(lines, start=1):
        if i in highlight_lines:
            draw.rectangle([0, y - 2, width, y + line_h - 2], fill=HIGHLIGHT_BG)
        draw.text((pad_x, y), f"{i:>4}", fill=LINE_NUM, font=f_ln)
        draw.text((pad_x + ln_w, y), line, fill=TEXT, font=f)
        y += line_h

    out_path = OUT / filename
    img.save(out_path, "PNG")
    print(f"Created {out_path}")


def render_terminal(filename: str, content: str, title: str) -> None:
    lines = content.splitlines()
    f = font(13)
    f_title = font(13, bold=True)
    line_h = 18
    pad_x = 16
    width = 1100
    height = 40 + len(lines) * line_h + 16

    img = Image.new("RGB", (width, height), BG)
    draw = ImageDraw.Draw(img)
    draw.rectangle([0, 0, width, 32], fill=HEADER_BG)
    draw.text((pad_x, 8), title, fill=ACCENT, font=f_title)

    y = 40
    for line in lines:
        color = STRING if line.startswith("*") or line.startswith("commit") else TEXT
        if "469d783" in line or "4e4a844" in line:
            color = (180, 220, 120)
        draw.text((pad_x, y), line, fill=color, font=f)
        y += line_h

    out_path = OUT / filename
    img.save(out_path, "PNG")
    print(f"Created {out_path}")


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8", errors="replace")


def tree_output() -> str:
    result = subprocess.run(
        [
            "find",
            str(ROOT / "app"),
            str(ROOT / "routes"),
            str(ROOT / "database" / "migrations"),
            str(ROOT / "tests"),
            str(ROOT / "config"),
            "-type",
            "f",
            "-o",
            "-type",
            "d",
        ],
        capture_output=True,
        text=True,
    )
    paths = sorted(set(result.stdout.splitlines()))
    # Build a readable tree manually for key dirs
    lines = [
        "akbrny2/",
        "├── app/",
        "│   ├── Answer.php, Post.php, User.php",
        "│   ├── Console/PerformanceBaseline.php",
        "│   ├── Http/Controllers/",
        "│   │   ├── Ajax/AjaxController.php",
        "│   │   ├── Auth/ (Login, Register, Reset, ...)",
        "│   │   ├── HomeController.php",
        "│   │   └── User/",
        "│   │       ├── ProfileController.php",
        "│   │       └── UsersController.php",
        "│   ├── Providers/AppServiceProvider.php",
        "│   └── Services/FirebaseCloudMessaging.php",
        "├── routes/web.php, api.php, console.php",
        "├── database/migrations/ (7 files)",
        "├── resources/views/ (user/, auth/, pages/, layouts/)",
        "├── tests/Feature/",
        "│   ├── ApiContractTest.php (33 tests)",
        "│   └── FcmNotificationTest.php (9 tests)",
        "├── config/ (14 files incl. firebase.php)",
        "└── composer.json, composer.lock",
    ]
    return "\n".join(lines)


def main() -> None:
    render_terminal(
        "01-project-structure.png",
        tree_output(),
        "Project Structure — akbrny2 (Laravel 13)",
    )

    render_code(
        "02-routes-web.png",
        read("routes/web.php"),
        "Routes — routes/web.php",
    )

    render_code(
        "03-users-controller-queries.png",
        read("app/Http/Controllers/User/UsersController.php"),
        "UsersController — consolidated post query + eager loading",
        highlight_lines={17, 18, 19, 21, 22, 23, 25, 26, 27},
    )

    render_code(
        "04-profile-controller-queries.png",
        read("app/Http/Controllers/User/ProfileController.php"),
        "ProfileController — optimized user lookup + single post fetch",
        highlight_lines={16, 17, 18, 24, 25, 26, 69, 70, 71},
    )

    ajax = read("app/Http/Controllers/Ajax/AjaxController.php")
    render_code(
        "05-ajax-controller-queries.png",
        "\n".join(ajax.splitlines()[:120]),
        "AjaxController — replyMessage() query pattern (D2)",
        highlight_lines={61, 62, 63, 64, 66, 67, 68, 69},
        max_lines=120,
    )

    models = (
        "=== app/User.php ===\n"
        + read("app/User.php")
        + "\n\n=== app/Post.php ===\n"
        + read("app/Post.php")
        + "\n\n=== app/Answer.php ===\n"
        + read("app/Answer.php")
    )
    render_code("06-models.png", models, "Eloquent Models — User, Post, Answer", max_lines=70)

    render_code(
        "07-app-service-provider.png",
        read("app/Providers/AppServiceProvider.php"),
        "AppServiceProvider — memoized unread COUNT",
        highlight_lines={20, 21, 22, 23, 24, 25, 26, 27, 28, 31, 34},
    )

    render_code(
        "08-performance-index-migration.png",
        read("database/migrations/2026_08_08_040705_add_performance_indexes_to_posts_and_answers_tables.php"),
        "Migration — composite performance indexes (Opt #5)",
        highlight_lines={23, 24, 27, 28},
    )

    migrations = "\n".join(
        sorted(p.name for p in (ROOT / "database/migrations").glob("*.php"))
    )
    render_code(
        "09-database-migrations.png",
        migrations,
        "Database Migrations (7 files)",
        max_lines=20,
    )

    render_code(
        "10-api-contract-tests.png",
        "\n".join(read("tests/Feature/ApiContractTest.php").splitlines()[:45]),
        "ApiContractTest — 33 route contract tests",
        highlight_lines={12, 13, 15, 16, 17},
        max_lines=45,
    )

    render_code(
        "11-composer-json.png",
        read("composer.json"),
        "composer.json — PHP 8.3 + Laravel 13",
        highlight_lines={9, 10, 66, 67, 68},
    )

    lock_snippet = "\n".join(
        line
        for line in read("composer.lock").splitlines()
        if any(
            k in line
            for k in [
                '"name": "laravel/framework"',
                '"name": "symfony/console"',
                '"platform"',
                '"php"',
            ]
        )
    )[:80]
    # Better: extract specific sections
    lock_lines = read("composer.lock").splitlines()
    sections = []
    for i, line in enumerate(lock_lines):
        if '"name": "laravel/framework"' in line:
            sections.extend(lock_lines[i : i + 8])
        if '"name": "symfony/console"' in line:
            sections.extend(lock_lines[i : i + 8])
    sections.extend(lock_lines[-15:])
    render_code(
        "12-composer-lock-php83.png",
        "\n".join(sections),
        "composer.lock — Laravel 13.24 + Symfony 7.4 (PHP 8.3)",
        max_lines=40,
    )

    git_log = subprocess.run(
        ["git", "-C", str(ROOT), "log", "--all", "--oneline", "--decorate", "--graph"],
        capture_output=True,
        text=True,
    ).stdout

    render_terminal(
        "13-git-history.png",
        git_log.strip(),
        "Git History — upgrade/laravel-13 branch",
    )

    diff = subprocess.run(
        [
            "git",
            "-C",
            str(ROOT),
            "show",
            "469d783",
            "--",
            "app/Http/Controllers/User/UsersController.php",
        ],
        capture_output=True,
        text=True,
    ).stdout
    render_code(
        "14-before-after-users-controller-diff.png",
        diff,
        "Git diff — UsersController query optimization (commit 469d783)",
        highlight_lines={25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38},
        max_lines=50,
    )

    fcm_test = read("tests/Feature/FcmNotificationTest.php")
    render_code(
        "15-fcm-tests.png",
        "\n".join(fcm_test.splitlines()[:40]),
        "FcmNotificationTest — 9 FCM HTTP v1 tests",
        max_lines=40,
    )


if __name__ == "__main__":
    main()
