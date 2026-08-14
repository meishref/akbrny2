# Real Database Compatibility Report

**Source of truth:** `/home/roony/Downloads/localh202ost.sql`  
**Database name in dump:** `akbrny2_home2`  
**Server metadata:** MariaDB 10.11.18  
**Report date:** 14 August 2026  
**Laravel version:** 13.24.0  
**Test database (separate):** `akbrny2026_akbrny_test`  
**Production database:** NOT modified during this work  

Status legend: **MATCH** | **VERIFIED** | **MODEL ADJUSTMENT REQUIRED** | **LEGACY** | **DO NOT RUN ON PRODUCTION** | **REQUIRES REVIEW**

---

## 1. Existing database structure

The client SQL file contains **schema only** (CREATE TABLE + indexes). No INSERT data.

### Tables found (11)

| Table | PK | Auto increment | Status |
|-------|-----|----------------|--------|
| `users` | `id` int(11) | Yes | MATCH |
| `posts` | `id` int(11) | Yes | MATCH |
| `answers` | `id` int(11) | Yes | MATCH |
| `notifications` | `id` int unsigned | Yes | MATCH |
| `password_resets` | — (email index) | No | MATCH |
| `migrations` | `id` | No | MATCH |
| `oauth_access_tokens` | `id` varchar(100) | No | LEGACY |
| `oauth_auth_codes` | `id` varchar(100) | No | LEGACY |
| `oauth_clients` | `id` int unsigned | Yes | LEGACY |
| `oauth_personal_access_clients` | `id` int unsigned | Yes | LEGACY |
| `oauth_refresh_tokens` | `id` varchar(100) | No | LEGACY |

**Not in client dump:** `jobs`, `sessions`, `failed_jobs` (Laravel queue/session additions).

---

## 2. Column reference

### users (29 columns)

| Column | Type | Nullable | Default |
|--------|------|----------|---------|
| id | int(11) | NO | — |
| name | varchar(255) | NO | — |
| email | varchar(255) | NO | — |
| email_verified_at | timestamp | YES | NULL |
| password | varchar(255) | NO | — |
| user_pass | varchar(255) | YES | NULL |
| username | varchar(255) | NO | — |
| image | varchar(255) | YES | NULL |
| text_profile | varchar(255) | YES | NULL |
| ip_address | varchar(45) | YES | NULL |
| visitors | int(11) | NO | 0 |
| active | int(11) | NO | 1 |
| is_public | tinyint(1) | NO | 1 |
| accept_posts | tinyint(1) | NO | 1 |
| show_zwar | tinyint(1) | NO | 1 |
| active_notification | tinyint(1) | NO | 1 |
| token_notification | varchar(255) | YES | NULL |
| android_token | text | YES | NULL |
| web … linkedin | varchar(255) | YES | NULL |
| tiktok | varchar(250) | YES | NULL |
| words_block | mediumtext | YES | NULL |
| remember_token | varchar(100) | YES | NULL |
| created_at | timestamp | YES | current_timestamp |
| updated_at | timestamp | NO | current_timestamp |

**Indexes:** PRIMARY KEY (`id`) only. No UNIQUE on `email` or `username` in dump.

### posts (16 columns)

Includes legacy fields **`post_is_fav`**, **`post_time`** (present in production, now in Laravel `Post::$fillable`).

### answers (6 columns)

`id`, `body`, `user_id`, `post_id`, `created_at`, `updated_at` — **MATCH**

### notifications (6 columns)

`id`, `user_id`, `token`, `device`, `created_at`, `updated_at` — **MATCH**

---

## 3. Indexes and relationships

### Production indexes (from dump)

| Table | Indexes |
|-------|---------|
| answers | PK(id), KEY(post_id) |
| posts | PK(id), KEY(user_id) |
| users | PK(id) |
| notifications | PK(id), KEY(user_id) |
| password_resets | KEY(email prefix 191) |
| oauth_* | PK + client/user indexes |

### Logical relationships (application)

```
users 1──* posts      (posts.user_id)
users 1──* answers    (answers.user_id)
posts 1──* answers    (answers.post_id)
users 1──* notifications (notifications.user_id)
```

Foreign keys exist in Laravel migrations for `posts.user_id` and `answers.post_id`; dump shows index on `post_id` / `user_id` without explicit FK ALTER in phpMyAdmin export.

---

## 4. Laravel models — mapping

| Model | Table | PK | Changes this pass |
|-------|-------|-----|-------------------|
| `App\User` | users | id | Added `deviceNotifications()` relationship |
| `App\Post` | posts | id | Already aligned (post_is_fav, post_time) |
| `App\Answer` | answers | id | Added integer casts |
| `App\Notification` | notifications | id | **NEW** model |

### User authentication

| Item | Status |
|------|--------|
| Login via email or username | VERIFIED |
| Password column `users.password` | VERIFIED |
| Legacy `user_pass` preserved (hidden) | MATCH |
| Hidden: password, user_pass, tokens | VERIFIED |
| All legacy columns in `$fillable` | MATCH |

### FCM / notifications dual path

| Path | Role | Status |
|------|------|--------|
| `users.token_notification` | Primary send + AJAX contract + frontend JS | VERIFIED (preserved) |
| `notifications` table | Device token rows synced on save | MODEL ADJUSTMENT REQUIRED → **IMPLEMENTED** |

`NotificationTokenService` now writes to **both** without changing AJAX/API response format.

---

## 5. Migration comparison

| Migration | Action | Production policy |
|-----------|--------|-------------------|
| `2014_10_12_000000_create_users_table` | CREATE users | **DO NOT RUN ON PRODUCTION** (exists) |
| `2014_10_12_100000_create_password_resets_table` | CREATE | **DO NOT RUN ON PRODUCTION** (exists) |
| `2020_04_12_193813_create_posts_table` | CREATE | **DO NOT RUN ON PRODUCTION** (exists) |
| `2020_04_13_085736_create_answers_table` | CREATE | **DO NOT RUN ON PRODUCTION** (exists) |
| `2026_08_08_034258_create_jobs_table` | CREATE jobs | **PRODUCTION QUEUE MIGRATION REQUIRES APPROVAL** |
| `2026_08_08_034259_create_sessions_table` | CREATE sessions | **REQUIRES REVIEW** (only if SESSION_DRIVER=database) |
| `2026_08_08_040705_add_performance_indexes` | ADD indexes | **REQUIRES REVIEW** (additive; verify not duplicate) |
| `2026_08_12_000001_create_failed_jobs_table` | CREATE | **PRODUCTION QUEUE MIGRATION REQUIRES APPROVAL** |
| `2026_08_13_020500_align_schema_with_production_metadata` | ADD columns IF missing | **DO NOT RUN ON PRODUCTION** without ops approval (no-op if aligned) |

**Rule:** Never run `migrate:fresh`, `db:wipe`, or `migrate:refresh` on production.

---

## 6. Query compatibility

| Optimization | Production-safe | Status |
|--------------|-----------------|--------|
| Eloquent on users/posts/answers | Yes | VERIFIED |
| `exists()` vs `count()` | Yes | VERIFIED |
| Profile N+1 batch load | Yes | VERIFIED |
| Unread count in AppServiceProvider | Yes | VERIFIED |
| Performance index migration | Additive only | REQUIRES REVIEW before prod |

Query execution was optimized at the application level. Production response-time improvement requires production benchmarking.

---

## 7. AJAX compatibility (14 endpoints)

| Item | Status |
|------|--------|
| URLs unchanged | VERIFIED |
| HTTP methods unchanged | VERIFIED |
| Route names unchanged | VERIFIED |
| Request/response JSON contracts | VERIFIED (33/33 contract tests) |

---

## 8. API v1 compatibility (5 endpoints)

| Endpoint | Uses existing tables | Status |
|----------|---------------------|--------|
| GET /api/v1/search | users | VERIFIED |
| POST /api/v1/messages/reply | posts, answers | VERIFIED |
| POST /api/v1/polls/vote | posts, answers | VERIFIED |
| POST /api/v1/notifications/token | users + notifications | VERIFIED |
| PUT /api/v1/users/profile | users | VERIFIED |

No new API tables introduced.

---

## 9. FCM compatibility

| Item | Status |
|------|--------|
| HTTP v1 via env credentials | VERIFIED |
| Send uses `users.token_notification` | VERIFIED |
| Token save syncs `notifications` table | IMPLEMENTED |
| No new notification table created | VERIFIED |
| 9/9 FCM tests | VERIFIED |

---

## 10. Storage compatibility

| Item | Status |
|------|--------|
| Profile images `users.image` filenames preserved | VERIFIED |
| URLs `/images/profile/{filename}` | VERIFIED |
| Storage disk `profile_images` → same directory | VERIFIED |

---

## 11. Queue compatibility

| Item | Status |
|------|--------|
| `jobs` table in production dump | **NOT PRESENT** |
| `SendFcmNotificationJob` | Uses queue when configured |
| Tests use `sync` driver | VERIFIED |
| Production worker + jobs migration | **PRODUCTION QUEUE MIGRATION REQUIRES APPROVAL** |

---

## 12. OAuth tables

| Item | Status |
|------|--------|
| Tables in production | LEGACY |
| `laravel/passport` in composer | NOT installed |
| API v1 auth | Session/web `auth` middleware |
| Recommendation | Preserve tables; do not delete |

---

## 13. Data preservation

| Item | Status |
|------|--------|
| Production data modified | **NO** |
| Schema changes executed on production | **NO** |
| New tables created on production | **NO** |
| Test DB separate from production | VERIFIED |

---

## 14. Model changes made (Laravel code only)

1. **`app/Notification.php`** — new Eloquent model for `notifications` table  
2. **`app/User.php`** — `deviceNotifications()` hasMany relationship  
3. **`app/Services/NotificationTokenService.php`** — sync token to `notifications` on save  
4. **`app/Answer.php`** — integer casts for FK columns  

Previously aligned (no change this pass): `User::$fillable`, `Post::$fillable` with legacy columns.

---

## 15. Mismatches found

| Mismatch | Resolution | Status |
|----------|------------|--------|
| `notifications` table unused by Laravel | Dual-write in NotificationTokenService | RESOLVED in code |
| No `App\Notification` model | Created model | RESOLVED |
| `jobs`/`sessions` not in client DB | Document only; no auto-create on prod | REQUIRES REVIEW |
| No UNIQUE on email/username in dump vs Laravel migration | Do not run create migration on prod | DOCUMENTED |
| OAuth tables unused | Mark LEGACY | DOCUMENTED |
| Performance indexes not in dump | Optional additive migration | REQUIRES REVIEW |

**No mismatch requires database schema changes.**

---

## 16. Test results

```
OK (51 tests, 211 assertions)
```

Verified after model alignment — **unchanged from baseline**.

---

## 17. Screenshots

Generated in `docs/screenshots/database/` (15 files):

01-database-tables through 15-tests.png

---

## 18. Final database compatibility status

| Area | Status |
|------|--------|
| Laravel models ↔ production schema | **VERIFIED** |
| Authentication ↔ users table | **VERIFIED** |
| AJAX/API contracts | **VERIFIED** |
| FCM + notifications table | **VERIFIED** |
| Production data safety | **VERIFIED** |
| Production migrations | **DO NOT RUN** without explicit approval |
| Queue on production | **PRODUCTION QUEUE MIGRATION REQUIRES APPROVAL** |

**Conclusion:** Laravel 13 is aligned to use the client's existing database as source of truth. All adjustments were made in Laravel application code only. Production database was not modified.
