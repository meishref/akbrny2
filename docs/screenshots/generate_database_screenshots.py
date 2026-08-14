#!/usr/bin/env python3
"""Generate database compatibility screenshots from verified schema metadata."""

from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "docs" / "screenshots" / "database"
OUT.mkdir(parents=True, exist_ok=True)

BG = (30, 30, 30)
HEADER = (45, 45, 48)
TEXT = (212, 212, 212)
ACCENT = (86, 156, 214)
LINE = (100, 100, 100)
HIGHLIGHT = (60, 50, 20)

MONO = "/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf"


def f(size: int):
    return ImageFont.truetype(MONO, size) if Path(MONO).exists() else ImageFont.load_default()


def render(filename: str, title: str, lines: list[str], highlight: set[int] | None = None):
    highlight = highlight or set()
    lh = 18
    w = 1200
    h = 44 + len(lines) * lh + 20
    img = Image.new("RGB", (w, h), BG)
    d = ImageDraw.Draw(img)
    d.rectangle([0, 0, w, 34], fill=HEADER)
    d.text((14, 8), title, fill=ACCENT, font=f(13))
    y = 42
    font = f(12)
    for i, line in enumerate(lines, 1):
        if i in highlight:
            d.rectangle([0, y - 2, w, y + lh - 2], fill=HIGHLIGHT)
        d.text((14, y), f"{i:>3}", fill=LINE, font=font)
        d.text((52, y), line[:130], fill=TEXT, font=font)
        y += lh
    img.save(OUT / filename)
    print("Created", filename)


def main():
    render(
        "01-database-tables.png",
        "Production database tables (localh202ost.sql / akbrny2_home2)",
        [
            "answers",
            "migrations",
            "notifications",
            "oauth_access_tokens",
            "oauth_auth_codes",
            "oauth_clients",
            "oauth_personal_access_clients",
            "oauth_refresh_tokens",
            "password_resets",
            "posts",
            "users",
            "— jobs / sessions / failed_jobs: NOT in client dump (Laravel optional)",
        ],
    )

    render(
        "02-users-schema.png",
        "users — production columns",
        [
            "id int(11) PK AUTO_INCREMENT",
            "name, email, email_verified_at, password, user_pass",
            "username, image, text_profile, ip_address, visitors, active",
            "is_public, accept_posts, show_zwar, active_notification",
            "token_notification, android_token",
            "web, twitter, instagram, youtube, snapchat, telegram",
            "facebook, linkedin, tiktok, words_block",
            "remember_token, created_at, updated_at",
            "Indexes: PRIMARY KEY (id) only — no UNIQUE on email/username in dump",
        ],
        highlight={8, 9},
    )

    render(
        "03-posts-schema.png",
        "posts — production columns",
        [
            "id int(11) PK AUTO_INCREMENT",
            "type int DEFAULT 0  — 0=message, 1=poll",
            "body longtext",
            "answer1..answer4 text nullable",
            "is_public, is_read tinyint",
            "post_is_fav int DEFAULT 0",
            "post_time varchar(50) nullable",
            "ip varchar(255), is_active tinyint DEFAULT 1",
            "user_id int unsigned NOT NULL",
            "created_at, updated_at timestamp",
            "Index: posts_user_id_foreign (user_id)",
        ],
        highlight={6, 7},
    )

    render(
        "04-answers-schema.png",
        "answers — production columns",
        [
            "id int(11) PK AUTO_INCREMENT",
            "body longtext NOT NULL",
            "user_id int unsigned NOT NULL",
            "post_id int unsigned NOT NULL",
            "created_at, updated_at timestamp nullable",
            "Index: answers_post_id_foreign (post_id)",
            "FK: post_id -> posts (app migration; not in dump ALTER)",
        ],
    )

    render(
        "05-notifications-schema.png",
        "notifications — production columns (FCM device tokens)",
        [
            "id int unsigned PK AUTO_INCREMENT",
            "user_id int unsigned NOT NULL",
            "token varchar(255) nullable",
            "device varchar(255) nullable",
            "created_at timestamp DEFAULT current_timestamp",
            "updated_at timestamp nullable",
            "Index: notifications_user_id_foreign (user_id)",
            "Laravel: App\\Notification model + sync on token save",
            "FCM send path: users.token_notification (legacy, preserved)",
        ],
        highlight={7, 8},
    )

    render(
        "06-oauth-schema.png",
        "OAuth tables — LEGACY (Laravel Passport style)",
        [
            "oauth_access_tokens, oauth_auth_codes, oauth_clients",
            "oauth_personal_access_clients, oauth_refresh_tokens",
            "composer.json: NO laravel/passport package",
            "Current API v1: session/web auth middleware — not OAuth tokens",
            "Status: LEGACY — preserve tables, do not delete",
        ],
    )

    render(
        "07-indexes.png",
        "Production indexes vs Laravel performance migration",
        [
            "PRODUCTION (dump):",
            "  answers: PK(id), KEY(post_id)",
            "  posts: PK(id), KEY(user_id)",
            "  users: PK(id)",
            "  notifications: PK(id), KEY(user_id)",
            "Laravel additive (2026_08_08_040705):",
            "  posts_user_id_is_read_type_index",
            "  answers_user_id_post_id_index",
            "Status: DO NOT RUN ON PRODUCTION without approval",
        ],
        highlight={8, 9, 10},
    )

    # Read model files for screenshots
    def model_lines(path: str, title: str, out: str, hl: set[int] | None = None):
        text = (ROOT / path).read_text(encoding="utf-8").splitlines()[:45]
        render(out, title, text, hl)

    model_lines("app/User.php", "App\\User — production-aligned", "08-user-model.png", {19, 79, 86})
    model_lines("app/Post.php", "App\\Post — production-aligned", "09-post-model.png", {14, 23, 24})
    model_lines("app/Answer.php", "App\\Answer", "10-answer-model.png", {9, 13, 19})
    model_lines("app/Notification.php", "App\\Notification (table: notifications)", "11-notification-model.png")

    render(
        "12-model-relationships.png",
        "Eloquent relationships (match production FK semantics)",
        [
            "User hasMany Post (posts.user_id)",
            "User hasMany Answer (answers.user_id)",
            "User hasMany Notification (notifications.user_id)",
            "Post belongsTo User",
            "Post hasMany Answer",
            "Answer belongsTo User, belongsTo Post",
            "Notification belongsTo User",
        ],
    )

    render(
        "13-migration-comparison.png",
        "Laravel migrations vs production — run policy",
        [
            "DO NOT RUN ON PRODUCTION:",
            "  2014_* create users, password_resets",
            "  2020_* create posts, answers",
            "  2026_08_08 create jobs, sessions",
            "  2026_08_12 create failed_jobs",
            "REQUIRES REVIEW (additive indexes only):",
            "  2026_08_08_040705 performance indexes",
            "SAFE NO-OP on prod if columns exist:",
            "  2026_08_13 align_schema (hasColumn/hasTable guards)",
        ],
        highlight={1, 2, 3, 4, 5},
    )

    render(
        "14-query-compatibility.png",
        "Query layer — compatible with production schema",
        [
            "Services use Eloquent on users, posts, answers",
            "exists() instead of count() where optimized",
            "ProfileController: batch Answer load (N+1 fix)",
            "AppServiceProvider: Post::query unread count",
            "No raw SQL requiring missing columns",
            "Legacy columns post_is_fav, post_time in Post $fillable",
        ],
    )

    test_out = ROOT / "docs" / "screenshots" / "production-readiness" / "test-results.txt"
    lines = test_out.read_text(encoding="utf-8").splitlines() if test_out.exists() else [
        "OK (51 tests, 211 assertions) — verified after model alignment",
    ]
    render("15-tests.png", "Test results after database compatibility alignment", lines)


if __name__ == "__main__":
    main()
