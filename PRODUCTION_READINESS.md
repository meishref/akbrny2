# Production Readiness Audit

**Date:** 2026-08-08  
**Scope:** Read-only audit — no deployment, no production server changes, no code changes  
**Application:** اخبرني (akbrny) — Laravel 13 upgrade branch  

---

## Executive summary

| Area | Status |
|------|--------|
| **Application code (Laravel 13 / PHP 8.4)** | **READY** — 42/42 tests pass, 33/33 API contracts preserved |
| **FCM HTTP v1 transport** | **READY** in code — requires production credentials |
| **Production deployment** | **NOT READY** — blockers remain in environment, credentials, and server configuration |

The codebase is functionally verified for Laravel 13. Production go-live requires owner actions (Firebase credentials, legacy key revocation, production `.env`, server hardening, migration execution, and backup plan) before traffic is switched.

---

## Verified test state (audit run)

```bash
php8.4 artisan test
# Tests: 42 passed (128 assertions), 0 failed

php8.4 artisan test --filter=ApiContractTest
# Tests: 33 passed (98 assertions), 0 failed
```

| Item | Value |
|------|-------|
| Laravel | 13.24.0 |
| PHP (audit environment) | 8.4.24 |
| Total tests | 42 |
| API contract tests | 33 |
| Assertions | 128 |
| FCM | HTTP v1 synchronous (`App\Services\FirebaseCloudMessaging`) |
| Queues / Redis / Docker | Not introduced |

---

## Findings by audit area

### 1. Laravel 13 configuration

| ID | Severity | Finding |
|----|----------|---------|
| L13-01 | **READY** | Laravel 13 skeleton: `bootstrap/app.php`, `bootstrap/providers.php`, `public/index.php`, health route `/up` |
| L13-02 | **READY** | Routing: `routes/web.php`, `routes/api.php`, `routes/console.php` (`performance:baseline`) |
| L13-03 | **READY** | Middleware: guest redirect to login; CSRF on web routes (Laravel 13 default web stack) |
| L13-04 | **MEDIUM** | `config/session.php` default driver is `database`; `.env.example` uses `file`. Production must explicitly choose and align migrations |
| L13-05 | **MEDIUM** | `config/cache.php` default is `database`; `.env.example` uses `file`. No `cache` table migration exists — use `file` or add migration |
| L13-06 | **LOW** | `config/database.php` default connection fallback is `sqlite`; production `.env` must set `DB_CONNECTION=mysql` |

### 2. PHP 8.4 requirements and extensions

| ID | Severity | Finding |
|----|----------|---------|
| PHP-01 | **READY** | `composer.json` requires `"php": "^8.4"` |
| PHP-02 | **READY** | Required extensions present on audit host: `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `json`, `intl`, `zip`, `bcmath` (via vendor), `sodium` |
| PHP-03 | **MEDIUM** | **`gd` and `imagick` not loaded** on audit host. `AjaxController::check_base64_image()` uses `imagecreatefromstring()` but is **dead code** (never called). Image upload uses `file_put_contents` without GD — works without GD but without validation |
| PHP-04 | **READY** | `openssl` required for FCM HTTP v1 JWT signing — present |
| PHP-05 | **HIGH** | Production PHP-FPM pool must run **PHP 8.4** (not 7.x/8.0). Mismatch causes immediate fatal errors |

### 3. Composer dependencies

| ID | Severity | Finding |
|----|----------|---------|
| COMP-01 | **READY** | `laravel/framework` v13.24.0 locked in `composer.lock` |
| COMP-02 | **READY** | Production deps minimal: `laravel/framework`, `laravel/tinker`, `laravel/ui` ^4.6 |
| COMP-03 | **READY** | No Redis, Horizon, Sail, or queue worker packages added |
| COMP-04 | **LOW** | Deploy must run `composer install --no-dev --optimize-autoloader` on production |

### 4. MySQL 8 compatibility

| ID | Severity | Finding |
|----|----------|---------|
| DB-01 | **READY** | Charset `utf8mb4`, collation `utf8mb4_unicode_ci`, `strict` mode enabled |
| DB-02 | **READY** | Schema uses `increments` PKs, FK cascades on `posts.user_id`, `answers.post_id` |
| DB-03 | **READY** | Arabic content supported via utf8mb4 |
| DB-04 | **MEDIUM** | `users.id`, `posts.user_id` use unsigned integers (legacy). Compatible with MySQL 8; not a migration blocker |
| DB-05 | **HIGH** | Production must use MySQL 8+ with backup before any migration |

### 5. Database migrations

| ID | Severity | Finding |
|----|----------|---------|
| MIG-01 | **READY** | Core tables: `users`, `posts`, `answers`, `password_resets` (legacy name, works with Laravel UI) |
| MIG-02 | **READY** | Laravel 13 additions: `sessions`, `jobs` (2026-08-08 migrations) |
| MIG-03 | **READY** | Performance indexes: `2026_08_08_040705_add_performance_indexes_to_posts_and_answers_tables.php` |
| MIG-04 | **BLOCKER** | **Production database must run `php artisan migrate` before go-live** if not already applied. New migrations include indexes + sessions/jobs tables |
| MIG-05 | **READY** | All migrations are additive/reversible; no column drops or destructive changes |

### 6. Performance indexes

| ID | Severity | Finding |
|----|----------|---------|
| IDX-01 | **READY** | `posts(user_id, is_read, type)` — unread badge COUNT, dashboard mark-read UPDATE |
| IDX-02 | **READY** | `answers(user_id, post_id)` — duplicate reply/vote checks |
| IDX-03 | **LOW** | Index creation on large tables may lock briefly — run during low-traffic window |
| IDX-04 | **READY** | No duplicate indexes conflict with existing FK indexes |

### 7. Nginx requirements

| ID | Severity | Finding |
|----|----------|---------|
| NGX-01 | **BLOCKER** | Document root **must** be `{app}/public`, not project root |
| NGX-02 | **READY** | `public/.htaccess` documents Apache rewrite; Nginx equivalent: `try_files $uri $uri/ /index.php?$query_string` |
| NGX-03 | **HIGH** | Pass PHP to PHP 8.4-FPM socket; `fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name` |
| NGX-04 | **READY** | Static assets under `public/style/`, `public/images/`, `public/img/` should be served directly by Nginx |
| NGX-05 | **MEDIUM** | Deny access to `.env`, `storage/`, `vendor/` at web server level (defense in depth) |
| NGX-06 | **LOW** | `public/web.config` exists for IIS; not used with Nginx |

### 8. PHP-FPM requirements

| ID | Severity | Finding |
|----|----------|---------|
| FPM-01 | **HIGH** | Dedicated pool for PHP 8.4; `pm` mode `dynamic` acceptable |
| FPM-02 | **MEDIUM** | Tune `pm.max_children` to available RAM (~50–100 MB per child) |
| FPM-03 | **MEDIUM** | `memory_limit` ≥ 128M (base64 image upload holds decoded bytes in memory) |
| FPM-04 | **READY** | No Docker/Sail — native PHP-FPM as specified |

### 9. OPcache

| ID | Severity | Finding |
|----|----------|---------|
| OPC-01 | **READY** | Zend OPcache enabled on audit host CLI (`opcache.enable=On`) |
| OPC-02 | **HIGH** | Production PHP-FPM: `opcache.enable=1`, `opcache.memory_consumption=128` (or higher), `opcache.max_accelerated_files=10000` |
| OPC-03 | **HIGH** | Production: `opcache.validate_timestamps=0` + reload strategy on deploy (current audit host has `validate_timestamps=On` — dev setting) |
| OPC-04 | **LOW** | `opcache.enable_cli=0` — correct for production |

### 10. Storage and filesystem permissions

| ID | Severity | Finding |
|----|----------|---------|
| FS-01 | **BLOCKER** | `storage/` and `bootstrap/cache/` must be writable by PHP-FPM user (`www-data` or equivalent) |
| FS-02 | **HIGH** | `public/images/profile/` must be writable for profile image uploads (`AjaxController::userChangeImage`) |
| FS-03 | **READY** | `.gitignore` excludes `.env`, `vendor/`, `/storage/*.key` |
| FS-04 | **MEDIUM** | Run `php artisan storage:link` if using `storage/app/public` URLs (profile images use `public/images/profile/` directly — current behavior preserved) |
| FS-05 | **LOW** | Log rotation for `storage/logs/laravel.log` |

### 11. public/ document root

| ID | Severity | Finding |
|----|----------|---------|
| PUB-01 | **READY** | `public/index.php` uses Laravel 13 bootstrap |
| PUB-02 | **READY** | Front controller pattern correct |
| PUB-03 | **HIGH** | Firebase service worker at `public/firebase-messaging-sw.js` must be served from site root scope |

### 12. .env production configuration

| ID | Severity | Finding |
|----|----------|---------|
| ENV-01 | **BLOCKER** | `APP_ENV=production` |
| ENV-02 | **BLOCKER** | `APP_DEBUG=false` — `.env.example` shows `true`; production misconfiguration exposes stack traces |
| ENV-03 | **BLOCKER** | `APP_KEY` must be unique (`php artisan key:generate`). Never use placeholder keys |
| ENV-04 | **HIGH** | `APP_URL=https://akbrny.com` (or actual production URL) — affects URL generation |
| ENV-05 | **HIGH** | `APP_URL_SITE` used by application helpers — set to production domain |
| ENV-06 | **HIGH** | Database credentials: `DB_*` for production MySQL 8 |
| ENV-07 | **HIGH** | Mail (`MAIL_*`) must be configured for password reset emails to work |
| ENV-08 | **MEDIUM** | `LOG_LEVEL=warning` or `error` in production (`.env.example` has `debug`) |
| ENV-09 | **MEDIUM** | `SESSION_SECURE_COOKIE=true` when serving over HTTPS |
| ENV-10 | **READY** | `.env` is gitignored |

### 13. Firebase HTTP v1 configuration

| ID | Severity | Finding |
|----|----------|---------|
| FCM-01 | **READY** | Implementation: `app/Services/FirebaseCloudMessaging.php`, `config/firebase.php` |
| FCM-02 | **BLOCKER** | Production requires all three: `FIREBASE_PROJECT_ID`, `FIREBASE_CLIENT_EMAIL`, `FIREBASE_PRIVATE_KEY` |
| FCM-03 | **READY** | Sync send preserved; 10s timeout default (`FIREBASE_REQUEST_TIMEOUT`) |
| FCM-04 | **READY** | Payload preserves `data.title`, `data.body`, `data.icon`, `data.image` |
| FCM-05 | **READY** | FCM failure does not block message save or redirect (9 FCM tests verify) |

### 14. Firebase credentials security

| ID | Severity | Finding |
|----|----------|---------|
| SEC-FCM-01 | **READY** | No server credentials in source code after HTTP v1 migration |
| SEC-FCM-02 | **READY** | Private key via env only; supports `\n` escaping |
| SEC-FCM-03 | **HIGH** | Store production Firebase service account JSON outside web root; inject via env or secrets manager |
| SEC-FCM-04 | **MEDIUM** | Firebase **client** API keys in `layouts/app.blade.php` and `firebase-messaging-sw.js` are public by design — restrict API key to domain in Firebase Console |

### 15. Legacy Firebase server key exposure in Git history

| ID | Severity | Finding |
|----|----------|---------|
| SEC-KEY-01 | **HIGH** | Legacy FCM server key (`AAAArUfbw8Q:…`) was committed in `ProfileController.php` (commit `2be148b` and history). **Removed from current code** but remains in Git history |
| SEC-KEY-02 | **HIGH** | **Owner action required:** Revoke/regenerate key in Firebase Console regardless of code removal |
| SEC-KEY-03 | **LOW** | Optional: Git history rewrite (BFG/filter-repo) — coordinate with team; not required if key is revoked |

### 16. Authentication

| ID | Severity | Finding |
|----|----------|---------|
| AUTH-01 | **READY** | Session guard `web`; login accepts email or username (`LoginController::username()`) |
| AUTH-02 | **READY** | Registration, logout, password reset via `laravel/ui` |
| AUTH-03 | **READY** | `bcrypt` rounds configurable (`BCRYPT_ROUNDS=10`) |
| AUTH-04 | **READY** | `/user/*` routes protected by `auth` middleware |
| AUTH-05 | **MEDIUM** | AJAX routes rely on in-controller `auth()->check()` — guests receive HTTP 500 (documented API contract; preserved intentionally) |
| AUTH-06 | **READY** | API route `GET /api/user` returns 401 without token (contract test E1) |

### 17. Session configuration

| ID | Severity | Finding |
|----|----------|---------|
| SESS-01 | **MEDIUM** | Choose `SESSION_DRIVER`: `file` (simpler, `.env.example`) vs `database` (config default, requires `sessions` migration) |
| SESS-02 | **READY** | `SESSION_LIFETIME=120` minutes |
| SESS-03 | **HIGH** | Profile visitor tracking stores `session([$username => 'true'])` per visited profile — can grow session payload over time (pre-existing behavior) |
| SESS-04 | **MEDIUM** | `SESSION_ENCRYPT=false` in example — acceptable; enable if threat model requires |

### 18. Cache configuration

| ID | Severity | Finding |
|----|----------|---------|
| CACHE-01 | **READY** | `CACHE_STORE=file` in `.env.example` — no DB table needed |
| CACHE-02 | **MEDIUM** | If `CACHE_STORE=database` is set without `cache` table migration, application will error on cache use |

### 19. Queue configuration

| ID | Severity | Finding |
|----|----------|---------|
| QUEUE-01 | **READY** | `QUEUE_CONNECTION=database` in `.env.example`; FCM **not** queued |
| QUEUE-02 | **LOW** | No queue workers required for current application behavior |
| QUEUE-03 | **LOW** | `jobs` table migration exists if database queue used in future — not a go-live requirement |

### 20. File uploads

| ID | Severity | Finding |
|----|----------|---------|
| UP-01 | **READY** | Profile images: base64 POST to `public/images/profile/{user_id}.png` (documented contract D9) |
| UP-02 | **MEDIUM** | No MIME/size validation; `check_base64_image()` exists but unused — pre-existing behavior |
| UP-03 | **MEDIUM** | Server-controlled `.png` extension prevents PHP execution; arbitrary bytes still written to webroot |
| UP-04 | **HIGH** | `public/images/profile/` writable and included in backups |

### 21. Error handling

| ID | Severity | Finding |
|----|----------|---------|
| ERR-01 | **READY** | Laravel 13 exception handler via `bootstrap/app.php` |
| ERR-02 | **BLOCKER** | `APP_DEBUG=false` in production prevents stack trace leakage |
| ERR-03 | **READY** | FCM errors logged internally, not exposed to users |

### 22. Logging

| ID | Severity | Finding |
|----|----------|---------|
| LOG-01 | **READY** | `LOG_CHANNEL=stack`, writes to `storage/logs/` |
| LOG-02 | **MEDIUM** | Set production `LOG_LEVEL=warning` or `error` |
| LOG-03 | **LOW** | Consider log rotation / external aggregation for production |

### 23. API routes

| ID | Severity | Finding |
|----|----------|---------|
| API-01 | **READY** | Single API route: `GET /api/user` (auth:api stub, returns 401 without credentials) |
| API-02 | **READY** | All application behavior is web + AJAX routes in `routes/web.php` |
| API-03 | **MEDIUM** | `auth:api` guard uses legacy `token` driver — no API tokens in schema; stub only |

### 24. Existing 33 API contracts

| ID | Severity | Finding |
|----|----------|---------|
| API-33 | **READY** | `tests/Feature/ApiContractTest.php` — 33/33 passing, 98 assertions |
| API-34 | **READY** | Documented in `API_INVENTORY.md` |

### 25. Security issues

| ID | Severity | Finding |
|----|----------|---------|
| SEC-01 | **HIGH** | Legacy FCM server key in Git history — revoke (see §15) |
| SEC-02 | **READY** | CSRF protection on web POST routes |
| SEC-03 | **MEDIUM** | AJAX guest → HTTP 500 (not 401) — documented contract |
| SEC-04 | **MEDIUM** | `siteSearch` SQL `LIKE '%query%'` — full table scan at scale; behavior frozen |
| SEC-05 | **LOW** | Firebase client config and API key public in JS (expected for web FCM) |
| SEC-06 | **MEDIUM** | No rate limiting on public message POST or AJAX endpoints (pre-existing) |

### 26. Production performance

| ID | Severity | Finding |
|----|----------|---------|
| PERF-01 | **READY** | Optimizations #1–#5 applied; GET `/user` 18→5 queries (~72% reduction) |
| PERF-02 | **READY** | Composite indexes migration ready for production |
| PERF-03 | **MEDIUM** | Sync FCM adds OAuth + send latency to message POST when notifications enabled |
| PERF-04 | **LOW** | `siteSearch` `%LIKE%` deferred — not a go-live blocker at current scale |
| PERF-05 | **READY** | Baseline harness: `php artisan performance:baseline` |

### 27. Database backup requirements

| ID | Severity | Finding |
|----|----------|---------|
| BAK-01 | **BLOCKER** | **No backup strategy documented or automated** — required before production migration |
| BAK-02 | **HIGH** | Full MySQL dump before running `artisan migrate` on production |
| BAK-03 | **HIGH** | Ongoing backup schedule: `users`, `posts`, `answers`, `sessions` (if database driver) |
| BAK-04 | **MEDIUM** | Include `public/images/profile/` in file backups |

### 28. Migration safety

| ID | Severity | Finding |
|----|----------|---------|
| SAFE-01 | **READY** | Index migration: additive only, `down()` drops indexes |
| SAFE-02 | **READY** | `sessions`/`jobs` tables: new tables only |
| SAFE-03 | **HIGH** | Run migrations during maintenance window; verify on staging clone first |
| SAFE-04 | **READY** | No data-destructive migrations in queue |

### 29. Required PHP extensions (production checklist)

| Extension | Required | Purpose |
|-----------|----------|---------|
| `ctype` | Yes | Laravel |
| `curl` | Yes | HTTP client, FCM |
| `dom` | Yes | Laravel |
| `fileinfo` | Yes | Laravel |
| `mbstring` | Yes | Laravel, Arabic text |
| `openssl` | Yes | FCM JWT, HTTPS |
| `pdo` + `pdo_mysql` | Yes | MySQL 8 |
| `tokenizer` | Yes | Laravel |
| `xml` | Yes | Laravel |
| `json` | Yes | AJAX/FCM |
| `intl` | Recommended | Locale |
| `zip` | Recommended | Composer/arbitrary |
| `gd` or `imagick` | Optional | Dead validation helper only |
| `opcache` | **Strongly recommended** | Production performance |

### 30. Required server packages

| Package | Required | Notes |
|---------|----------|-------|
| Nginx | Yes | Reverse proxy, static files |
| PHP 8.4-FPM | Yes | Application runtime |
| MySQL 8 | Yes | Primary database |
| Composer | Deploy-time | Not needed at runtime |
| Node/npm | No | No frontend build pipeline |
| Redis | **No** | Explicitly excluded |
| Docker | **No** | Explicitly excluded |
| Supervisor | No | No queue workers for go-live |

---

## BLOCKER / HIGH detail

### BLOCKER-1 — Production `.env` not configured

| | |
|---|---|
| **Problem** | `.env.example` defaults: `APP_DEBUG=true`, empty `APP_KEY`, empty Firebase vars |
| **Why it matters** | Debug mode leaks secrets; missing APP_KEY breaks encryption; missing Firebase disables push notifications |
| **Files** | `.env` (production server, not in Git), `.env.example` |
| **Fix** | Set `APP_ENV=production`, `APP_DEBUG=false`, generate `APP_KEY`, configure all production vars |
| **Server access** | Yes |
| **Downtime** | Brief restart after env change |
| **Behavior change** | No — correct production settings |

### BLOCKER-2 — Production migrations not applied

| | |
|---|---|
| **Problem** | New migrations (`sessions`, `jobs`, performance indexes) may not exist on production DB |
| **Why it matters** | Missing indexes reduce performance; database session driver fails without `sessions` table |
| **Files** | `database/migrations/2026_08_08_*` |
| **Fix** | Backup DB → `php artisan migrate --force` on staging first, then production |
| **Server access** | Yes |
| **Downtime** | Index creation: brief lock possible; plan low-traffic window |
| **Behavior change** | No |

### BLOCKER-3 — Nginx document root

| | |
|---|---|
| **Problem** | Laravel must be served from `public/` |
| **Why it matters** | Serving project root exposes `.env`, `vendor/`, source code |
| **Files** | Nginx vhost config |
| **Fix** | `root /path/to/akbrny2/public;` + standard Laravel try_files |
| **Server access** | Yes |
| **Downtime** | Minutes during vhost update |
| **Behavior change** | No |

### BLOCKER-4 — Writable storage directories

| | |
|---|---|
| **Problem** | Laravel requires write access to `storage/` and `bootstrap/cache/` |
| **Why it matters** | Sessions, logs, views, cache fail without writes |
| **Files** | `storage/`, `bootstrap/cache/`, `public/images/profile/` |
| **Fix** | `chown -R www-data:www-data storage bootstrap/cache public/images/profile` |
| **Server access** | Yes |
| **Downtime** | None |
| **Behavior change** | No |

### BLOCKER-5 — Database backup before migration

| | |
|---|---|
| **Problem** | No documented backup procedure |
| **Why it matters** | Migration rollback without backup risks data loss |
| **Fix** | `mysqldump` full backup before deploy; document restore procedure |
| **Server access** | Yes |
| **Downtime** | None for backup itself |
| **Behavior change** | No |

### BLOCKER-6 — Firebase credentials for notifications

| | |
|---|---|
| **Problem** | `FIREBASE_*` env vars empty in example; service skips send when unconfigured |
| **Why it matters** | Push notifications silently disabled |
| **Files** | `.env`, `config/firebase.php`, `app/Services/FirebaseCloudMessaging.php` |
| **Fix** | Create Firebase service account; set `FIREBASE_PROJECT_ID`, `FIREBASE_CLIENT_EMAIL`, `FIREBASE_PRIVATE_KEY` |
| **Server access** | Yes (env only) |
| **Downtime** | None |
| **Behavior change** | Restores notification delivery (was broken on legacy API) |

### HIGH-1 — Revoke legacy FCM server key

| | |
|---|---|
| **Problem** | Key committed in Git history (`2be148b`) |
| **Why it matters** | Anyone with repo access could use leaked key until revoked |
| **Fix** | Firebase Console → Project Settings → Cloud Messaging → revoke legacy server key |
| **Server access** | Firebase Console (owner) |
| **Downtime** | None (legacy API already dead) |
| **Behavior change** | No |

### HIGH-2 — Web push VAPID key not configured

| | |
|---|---|
| **Problem** | `messaging.usePublicVapidKey(...)` commented out in `resources/views/layouts/app.blade.php` |
| **Why it matters** | Browsers may not issue FCM tokens; `token_notification` stays null |
| **Files** | `resources/views/layouts/app.blade.php` |
| **Fix** | Generate Web Push certificate in Firebase Console; enable VAPID key (requires explicit approval — frontend change) |
| **Server access** | Firebase Console |
| **Downtime** | None |
| **Behavior change** | **Potentially yes** — token registration behavior; needs approval before change |

### HIGH-3 — Service worker notification icons use localhost

| | |
|---|---|
| **Problem** | `public/firebase-messaging-sw.js` lines 24–25: `http://localhost/gcm-push/img/...` |
| **Why it matters** | Background notifications show broken icons in production |
| **Files** | `public/firebase-messaging-sw.js` |
| **Fix** | Replace with production URLs (e.g. `/img/icon.png`) — requires approval (frontend asset path change) |
| **Server access** | No (code deploy) |
| **Downtime** | None |
| **Behavior change** | Visual only (icon/image URLs in notifications) |

### HIGH-4 — OPcache production tuning

| | |
|---|---|
| **Problem** | Audit host has `opcache.validate_timestamps=On` |
| **Why it matters** | Production performance; file stat overhead on every request |
| **Fix** | PHP-FPM pool ini: `opcache.validate_timestamps=0`, deploy reload procedure |
| **Server access** | Yes |
| **Downtime** | PHP-FPM reload |
| **Behavior change** | No |

### HIGH-5 — Mail configuration for password reset

| | |
|---|---|
| **Problem** | `.env.example` uses Mailtrap placeholders |
| **Why it matters** | Password reset emails fail silently or error |
| **Fix** | Configure production SMTP/transactional mail in `.env` |
| **Server access** | Yes |
| **Downtime** | None |
| **Behavior change** | No — enables existing feature |

### HIGH-6 — PHP 8.4-FPM on production

| | |
|---|---|
| **Problem** | Production may still run PHP 7.x from Laravel 5.8 era |
| **Why it matters** | Laravel 13 requires PHP 8.4 |
| **Fix** | Install/configure PHP 8.4-FPM; update Nginx fastcgi_pass |
| **Server access** | Yes |
| **Downtime** | Planned maintenance window |
| **Behavior change** | No (upgrade requirement) |

---

## Final production checklist

### Pre-deploy (owner / ops)

- [ ] Full MySQL backup verified restorable
- [ ] Staging environment matches production stack (Nginx, PHP 8.4-FPM, MySQL 8)
- [ ] Staging: `composer install --no-dev`, `php artisan migrate`, full test suite
- [ ] Revoke legacy FCM server key in Firebase Console
- [ ] Create Firebase service account; prepare `FIREBASE_*` env vars (never commit)
- [ ] Generate unique production `APP_KEY`
- [ ] Prepare production `.env` (`APP_ENV=production`, `APP_DEBUG=false`)
- [ ] Configure production mail (`MAIL_*`)
- [ ] Configure production database (`DB_*`)
- [ ] Decide session driver (`file` or `database`) and set `SESSION_DRIVER`
- [ ] Set `CACHE_STORE=file` (unless cache table added)
- [ ] Set `SESSION_SECURE_COOKIE=true` if HTTPS

### Server configuration

- [ ] Nginx document root → `{app}/public`
- [ ] Nginx `try_files` → `index.php`
- [ ] PHP 8.4-FPM pool active
- [ ] OPcache enabled; `validate_timestamps=0` in production
- [ ] `storage/`, `bootstrap/cache/`, `public/images/profile/` writable by FPM user
- [ ] Block web access to `.env`, `storage/`, `vendor/`

### Deploy steps (when approved — not executed in this audit)

- [ ] Put site in maintenance mode (`php artisan down`)
- [ ] Pull/deploy code
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] Reload PHP-FPM
- [ ] `php artisan up`
- [ ] Smoke test: login, dashboard, profile, send message, AJAX reply
- [ ] Verify `/up` health check returns 200

### Post-deploy verification

- [ ] `APP_DEBUG=false` — generic error pages only
- [ ] HTTPS redirect working
- [ ] Password reset email received
- [ ] FCM: send test message to user with notification enabled + token
- [ ] Monitor `storage/logs/laravel.log` for FCM warnings
- [ ] API contract spot-check or run tests against staging

### Explicitly out of scope (await approval)

- [ ] Queue workers / Redis
- [ ] Docker / Sail
- [ ] siteSearch optimization
- [ ] FCM queue migration
- [ ] VAPID key / service worker URL fixes (behavior/visual — needs approval)
- [ ] Production server changes (this audit only)

---

## Audit conclusion

| Question | Answer |
|----------|--------|
| Is Laravel 13 code ready? | **Yes** — tests pass, contracts preserved |
| Is FCM HTTP v1 code ready? | **Yes** — credentials required on server |
| Is production deployment ready? | **No** — environment, credentials, server config, and backup blockers remain |
| Were code changes made in this audit? | **No** |
| Was anything deployed? | **No** |

**STOP** — Awaiting approval for deployment phase.
