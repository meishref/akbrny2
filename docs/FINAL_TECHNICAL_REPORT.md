# Final Technical Report — Production Readiness

**Project:** اخبرني (akbrny)  
**Branch:** `production-readiness`  
**Baseline:** `be6f62e` (refactor + API v1)  
**Report date:** 12 August 2026

Status legend: **IMPLEMENTED** | **VERIFIED** | **REQUIRES SERVER ACCESS** | **NOT IMPLEMENTED** | **FUTURE RECOMMENDATION**

---

## Executive summary

The Laravel 13 upgrade and architecture refactor (AJAX controllers, services, API v1, Eloquent optimization) were completed on commit `be6f62e`. This production-readiness pass adds storage abstraction, static page caching, queued FCM notifications, rate limiting, failed-job support, tests, and deployment documentation — without changing AJAX URLs, HTTP methods, route names, or API contracts.

**Tests:** 51 passing (211 assertions) — up from 46 at baseline.

---

## 1. Laravel upgrade

| Item | Status |
|------|--------|
| Laravel 13.24.0 | VERIFIED |
| Symfony 7.4.x (PHP 8.3 compatible) | VERIFIED |
| No breaking AJAX/API route changes | VERIFIED |

---

## 2. PHP compatibility

| Item | Status |
|------|--------|
| `composer.json` requires `^8.3` | IMPLEMENTED |
| Platform pin prevents Symfony 8.x | IMPLEMENTED |
| `composer check-platform-reqs` | VERIFIED |

---

## 3. Architecture refactor (baseline `be6f62e`)

| Item | Status |
|------|--------|
| 7 AJAX controllers | IMPLEMENTED |
| 7 domain services + FCM service | IMPLEMENTED |
| Monolithic AjaxController removed | IMPLEMENTED |
| 14 legacy `/ajax/*` endpoints preserved | VERIFIED |

---

## 4. AJAX organization

All endpoints retain original URLs, methods, and route names. Controllers delegate to services. No frontend changes required.

---

## 5. API v1

| Route | Status |
|-------|--------|
| `GET /api/v1/search` | IMPLEMENTED |
| `POST /api/v1/messages/reply` | IMPLEMENTED |
| `POST /api/v1/polls/vote` | IMPLEMENTED |
| `POST /api/v1/notifications/token` | IMPLEMENTED |
| `PUT /api/v1/users/profile` | IMPLEMENTED |

33 API contract tests + 4 ApiV1 tests: **VERIFIED**

---

## 6. Query optimization

~25 Query Builder usages converted to Eloquent/`exists()`. **IMPLEMENTED** on baseline branch.

---

## 7. N+1 fix

Profile poll votes batch-loaded in `ProfileController`, passed to Blade as `$viewerAnswersByPostId`. **IMPLEMENTED**

---

## 8. Database indexes

Migration `2026_08_08_040705_add_performance_indexes_to_posts_and_answers_tables.php`:

- `posts (user_id, is_read, type)` — unread count, dashboard updates
- `answers (user_id, post_id)` — vote/reply duplicate checks

**IMPLEMENTED** (file). Application on production: **REQUIRES SERVER ACCESS** to run migration.

No new indexes added in this pass — existing migration covers verified query patterns.

---

## 9. Cache

| Item | Status |
|------|--------|
| Static pages (contact, privacy, terms) | IMPLEMENTED — 24h TTL |
| Site config / user private data | NOT IMPLEMENTED (intentional) |
| Cache driver on server | REQUIRES SERVER ACCESS (`file` recommended) |

---

## 10. Queues

| Item | Status |
|------|--------|
| `SendFcmNotificationJob` | IMPLEMENTED |
| Retries (3) with backoff [10, 30, 60]s | IMPLEMENTED |
| `failed_jobs` migration | IMPLEMENTED (file) |
| `jobs` migration | IMPLEMENTED (file, pre-existing) |
| Production worker | REQUIRES SERVER ACCESS |
| Tests (`QUEUE_CONNECTION=sync`) | VERIFIED |

Message save completes before HTTP response; FCM runs asynchronously when queue worker is active.

---

## 11. FCM (Firebase Cloud Messaging)

| Item | Status |
|------|--------|
| HTTP v1 API | IMPLEMENTED |
| OAuth2 service-account JWT | IMPLEMENTED |
| Env-based credentials | IMPLEMENTED |
| Failure logging (status + error body) | IMPLEMENTED |
| Real sends in tests | NOT IMPLEMENTED (Http::fake) |
| Invalid token auto-cleanup | FUTURE RECOMMENDATION |

9 FCM feature tests: **VERIFIED**

---

## 12. Storage

| Item | Status |
|------|--------|
| `profile_images` disk | IMPLEMENTED |
| `ProfileImageStorage` service | IMPLEMENTED |
| Legacy URL `/images/profile/{file}` | IMPLEMENTED |
| Old images remain accessible | VERIFIED (same directory root) |
| Laravel `Storage` API for upload/delete | IMPLEMENTED |

---

## 13. Rate limiting

| Limiter | Limit | Status |
|---------|-------|--------|
| login | 10/min IP | IMPLEMENTED |
| register | 5/min IP | IMPLEMENTED |
| password-reset | 5/min IP | IMPLEMENTED |
| messages | 20/min user/IP | IMPLEMENTED |
| votes | 30/min user/IP | IMPLEMENTED |
| search | 60/min IP | IMPLEMENTED |
| notification-token | 10/min user/IP | IMPLEMENTED |
| api | 120/min user/IP | IMPLEMENTED |

Rate limit tests: **VERIFIED**

---

## 14. Security

| Item | Status |
|------|--------|
| CSRF on web routes | IMPLEMENTED |
| bcrypt passwords | IMPLEMENTED |
| Rate limiting | IMPLEMENTED |
| Guest AJAX null user (500) | PRE-EXISTING — documented, not changed |
| Search XSS in HTML output | PRE-EXISTING — documented, not changed |
| Mass assignment | Partial `$fillable` on models |

---

## 15. Frontend (Laravel Mix)

| Item | Status |
|------|--------|
| Build tool | Laravel Mix 4 (**not Vite**) |
| `npm install` | VERIFIED |
| `npm run production` | VERIFIED (compiled in ~7s) |
| Primary UI | `public/style/` pre-built assets |
| Blade + Bootstrap | Preserved |

---

## 16. Server requirements

Documented in `docs/PRODUCTION_SERVER_CONFIGURATION.md`:

- PHP 8.3 + extensions
- Nginx → `/public`
- PHP-FPM pool settings
- OPcache recommendations
- Queue worker (Supervisor)
- MySQL 8 verification

All deployment steps: **REQUIRES SERVER ACCESS**

---

## 17. Testing

| Suite | Count | Status |
|-------|-------|--------|
| Total | 51 | VERIFIED |
| API contract | 33 | VERIFIED |
| FCM | 9 | VERIFIED |
| ApiV1 | 4 | VERIFIED |
| Rate limit | 2 | VERIFIED |
| Static page cache | 1 | VERIFIED |
| Profile storage | 2 | VERIFIED |

`composer validate`: **VERIFIED**

---

## 18. Performance verification

Query-level optimizations (Eloquent, N+1 fix, indexes) were implemented. No production A/B benchmark was run.

**Status:** Query-level optimizations implemented; production response-time improvement requires production benchmarking.

---

## 19. Production deployment instructions

1. Deploy code from `production-readiness` branch.
2. Configure production `.env` (`APP_DEBUG=false`, Firebase, database).
3. Backup database.
4. Run pending migrations — see `docs/PRODUCTION_MIGRATIONS.md`.
5. Start queue worker (`queue:work database`).
6. Verify `public/images/profile/` permissions.
7. Run smoke tests (login, message, vote, search, image upload).
8. Monitor `storage/logs/` and failed jobs table.

**Do not** run `migrate:fresh`, `db:wipe`, or destructive operations.

---

## 20. Remaining manual server checks

| Check | Status |
|-------|--------|
| MySQL version ≥ 8 | REQUIRES SERVER ACCESS |
| OPcache enabled in PHP-FPM | REQUIRES SERVER ACCESS |
| Nginx/SSL configuration | REQUIRES SERVER ACCESS |
| Queue worker (Supervisor) | REQUIRES SERVER ACCESS |
| Apply jobs/indexes migrations | REQUIRES SERVER ACCESS |
| Production `.env` / Firebase keys | REQUIRES SERVER ACCESS |
| Log rotation / backups | REQUIRES SERVER ACCESS |
| Production response-time benchmark | FUTURE RECOMMENDATION |
| Invalid FCM token cleanup | FUTURE RECOMMENDATION |
| Migrate Laravel Mix → Vite | FUTURE RECOMMENDATION (not required) |
| Fix guest AJAX 500 responses | FUTURE RECOMMENDATION |
| Fix search XSS | FUTURE RECOMMENDATION |

---

## Remaining risks

1. **Pending production migrations** — FCM queue will not process until `jobs` table exists and worker runs.
2. **Guest AJAX endpoints** — unauthenticated requests may 500 (pre-existing).
3. **No production benchmark** — performance gains not measured under real load.
4. **Legacy Mix/npm dependencies** — deprecated packages; build works but upgrades are advisable long-term.
5. **Search XSS** — user names embedded in HTML without escaping (pre-existing).
