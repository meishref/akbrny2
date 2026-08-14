# Schema alignment with production metadata

Source: `/home/roony/Downloads/localh202ost.sql` (`akbrny2_home2`)

## Goal

Make Laravel models + migrations match the real server columns so the app can bind to production safely.

## `users` columns (full)

| Column | In app now |
|--------|------------|
| id, name, email, email_verified_at, password | yes |
| user_pass | yes (added) |
| username, image, text_profile, ip_address | yes |
| visitors, active, is_public, accept_posts, show_zwar, active_notification | yes |
| token_notification, android_token | yes (android_token added) |
| web, twitter, instagram, youtube, snapchat, telegram, facebook, linkedin | yes |
| tiktok, words_block | yes (added) |
| remember_token, created_at, updated_at | yes |

## `posts` columns (full)

| Column | In app now |
|--------|------------|
| id, type, body, answer1–4 | yes |
| is_public, is_read | yes |
| post_is_fav, post_time | yes (added) |
| ip, is_active, user_id, timestamps | yes |

## `answers`

Matches dump: `id`, `body`, `user_id`, `post_id`, timestamps.

## `notifications` (custom FCM device table on server)

Migration creates the table only if missing. Structure: `user_id`, `token`, `device`, timestamps.

> Note: this is **not** Laravel's default morph `notifications` table.

## OAuth tables in dump

Present on server (`oauth_*`) but Passport is not in this Composer project. Not recreated here to avoid unused schema. Document only.

## Files changed

- `database/migrations/2014_10_12_000000_create_users_table.php`
- `database/migrations/2020_04_12_193813_create_posts_table.php`
- `database/migrations/2026_08_13_020500_align_schema_with_production_metadata.php` (safe `hasColumn` / `hasTable`)
- `app/User.php`, `app/Post.php`
- Seeders / factory updated for new columns

## Local / test DB

```bash
php artisan migrate
php artisan db:seed --class=DemoDataSeeder
```

## Production

- Do **not** use `migrate:fresh` / `db:wipe`.
- The align migration skips columns that already exist.
- Backup first, then `php artisan migrate --force` only after review.
