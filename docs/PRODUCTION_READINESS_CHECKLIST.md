# Production Readiness Checklist

**Application:** اخبرني (akbrny) — Laravel 13  
**Branch:** `production-readiness`  
**Baseline commit:** `be6f62e`  
**Last updated:** 12 August 2026

Status legend: **IMPLEMENTED** | **VERIFIED** | **REQUIRES SERVER ACCESS** | **NOT IMPLEMENTED** | **FUTURE RECOMMENDATION**

---

## Application

| Item | Status | Notes |
|------|--------|-------|
| Laravel 13.24.0 | VERIFIED | `composer.lock` |
| PHP 8.3 compatible | VERIFIED | `composer.json` `^8.3`, platform pin 8.3.30 |
| Composer dependencies aligned | VERIFIED | Symfony 7.4.x |
| `APP_DEBUG=false` in production | REQUIRES SERVER ACCESS | Documented in `.env.example` |
| `APP_ENV=production` | REQUIRES SERVER ACCESS | |
| Application key | REQUIRES SERVER ACCESS | `php artisan key:generate` |

---

## Database

| Item | Status | Notes |
|------|--------|-------|
| MySQL 8 compatibility | IMPLEMENTED | Standard Laravel schema; **server version requires verification** |
| Migrations (additive) | IMPLEMENTED | jobs, sessions, indexes, failed_jobs — see `docs/PRODUCTION_MIGRATIONS.md` |
| Run migrations on production | REQUIRES SERVER ACCESS | Manual during maintenance window |
| Performance indexes | IMPLEMENTED | `posts_user_id_is_read_type_index`, `answers_user_id_post_id_index` |
| Database backup | REQUIRES SERVER ACCESS | Before any migration |
| Separate test database | VERIFIED | `akbrny_test` in `phpunit.xml` |

---

## Storage

| Item | Status | Notes |
|------|--------|-------|
| Profile images via Laravel Storage | IMPLEMENTED | `profile_images` disk → `public/images/profile/` |
| Legacy URL compatibility | IMPLEMENTED | `/images/profile/{filename}` unchanged |
| Upload / delete / fallback | VERIFIED | `ProfileImageStorageTest` |
| `storage:link` | REQUIRES SERVER ACCESS | For default `public` disk if used later |
| Directory permissions | REQUIRES SERVER ACCESS | `public/images/profile/` writable |

---

## Cache

| Item | Status | Notes |
|------|--------|-------|
| Static pages cached (24h) | IMPLEMENTED | contact, privacy-policy, terms |
| Cache driver | REQUIRES SERVER ACCESS | `CACHE_STORE=file` recommended; Redis optional |
| User-specific data cached | NOT IMPLEMENTED | Intentionally excluded |
| Cache invalidation | IMPLEMENTED | TTL-based; flush on deploy if content changes |

---

## Queue

| Item | Status | Notes |
|------|--------|-------|
| FCM notifications queued | IMPLEMENTED | `SendFcmNotificationJob` |
| Queue driver (production) | REQUIRES SERVER ACCESS | `QUEUE_CONNECTION=database` |
| Jobs table migration | IMPLEMENTED | Not applied to production yet |
| Failed jobs table | IMPLEMENTED | Migration file added |
| Queue worker (supervisor) | REQUIRES SERVER ACCESS | `php artisan queue:work database` |
| Tests use sync queue | VERIFIED | `phpunit.xml` `QUEUE_CONNECTION=sync` |

---

## Notifications (FCM)

| Item | Status | Notes |
|------|--------|-------|
| HTTP v1 API | IMPLEMENTED | `FirebaseCloudMessaging` service |
| Service account via env | IMPLEMENTED | `FIREBASE_PROJECT_ID`, `FIREBASE_CLIENT_EMAIL`, `FIREBASE_PRIVATE_KEY` |
| No credentials in Git | VERIFIED | `.env.example` placeholders only |
| Invalid token / failure logging | IMPLEMENTED | Warning logs with HTTP status |
| FCM tests (Http::fake) | VERIFIED | 9 tests, no real sends |
| Async dispatch | IMPLEMENTED | Job dispatched; HTTP response immediate |

---

## Security

| Item | Status | Notes |
|------|--------|-------|
| Rate limiting — login | IMPLEMENTED | 10/min per IP |
| Rate limiting — register | IMPLEMENTED | 5/min per IP |
| Rate limiting — password reset | IMPLEMENTED | 5/min per IP |
| Rate limiting — messages | IMPLEMENTED | 20/min per user/IP |
| Rate limiting — votes | IMPLEMENTED | 30/min per user/IP |
| Rate limiting — search | IMPLEMENTED | 60/min per IP |
| Rate limiting — API | IMPLEMENTED | 120/min per user/IP |
| CSRF (web/AJAX) | IMPLEMENTED | Laravel default |
| Password hashing | IMPLEMENTED | bcrypt |
| Guest AJAX null deref | PRE-EXISTING | Documented; not changed |
| XSS in search HTML | PRE-EXISTING | Documented; not changed |

---

## Performance

| Item | Status | Notes |
|------|--------|-------|
| Eloquent refactor | IMPLEMENTED | ~25 Query Builder conversions |
| N+1 fix (profile polls) | IMPLEMENTED | Batch vote lookup |
| Database indexes | IMPLEMENTED | Migration file exists |
| OPcache | REQUIRES SERVER ACCESS | See `docs/PRODUCTION_SERVER_CONFIGURATION.md` |
| PHP-FPM tuning | REQUIRES SERVER ACCESS | |
| Production response-time benchmark | FUTURE RECOMMENDATION | Query-level optimizations only locally measured |

---

## Frontend

| Item | Status | Notes |
|------|--------|-------|
| Build tool | VERIFIED | **Laravel Mix 4** (not Vite) |
| `npm install` + `npm run production` | VERIFIED | Compiled successfully locally |
| Primary UI assets | IMPLEMENTED | `public/style/` (pre-built Bootstrap) |
| Mix output | IMPLEMENTED | `public/js/app.js`, `public/css/app.css` |
| Blade + Bootstrap preserved | VERIFIED | No framework replacement |

---

## Server

| Item | Status | Notes |
|------|--------|-------|
| Nginx document root | REQUIRES SERVER ACCESS | `/public` |
| PHP 8.3 + extensions | REQUIRES SERVER ACCESS | See server config doc |
| SSL / HTTPS | REQUIRES SERVER ACCESS | |
| Cron scheduler | NOT IMPLEMENTED | No scheduled tasks required currently |
| Queue worker process | REQUIRES SERVER ACCESS | Required for async FCM |
| Log rotation | REQUIRES SERVER ACCESS | `storage/logs/` |

---

## Monitoring

| Item | Status | Notes |
|------|--------|-------|
| Application logs | IMPLEMENTED | Laravel `storage/logs` |
| Failed jobs inspection | IMPLEMENTED | After migration + worker |
| Database backups | REQUIRES SERVER ACCESS | |
| Health check | IMPLEMENTED | `/up` route |

---

## Testing

| Item | Status | Notes |
|------|--------|-------|
| Full test suite | VERIFIED | **51 tests, 211 assertions** |
| API contract tests | VERIFIED | 33 tests |
| FCM tests | VERIFIED | 9 tests |
| Rate limit tests | VERIFIED | 2 tests |
| `composer validate` | VERIFIED | Pass |
| `composer check-platform-reqs` | VERIFIED | Pass |

---

## Pre-deploy sign-off

- [ ] Production `.env` configured (`APP_DEBUG=false`)
- [ ] Firebase credentials set
- [ ] Database backup taken
- [ ] Pending migrations reviewed and applied
- [ ] Queue worker running
- [ ] `public/images/profile/` writable
- [ ] SSL certificate valid
- [ ] Smoke test: login, message send, vote, search, profile image upload
