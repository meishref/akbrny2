# Production Readiness Audit (Phase 0)

**Branch baseline:** `be6f62e`  
**Audit date:** 12 August 2026  
**Status:** Read-only — no code changes in this document

---

## Already implemented (verified)

| Area | Status | Evidence |
|------|--------|----------|
| Laravel 13.24.0 | IMPLEMENTED | `composer.lock` |
| PHP ^8.3 | IMPLEMENTED | `composer.json`, platform pin 8.3.30 |
| AJAX split (7 controllers) | IMPLEMENTED | `app/Http/Controllers/Ajax/` |
| Services layer (7 + FCM) | IMPLEMENTED | `app/Services/` |
| API v1 (5 routes) | IMPLEMENTED | `routes/api.php` |
| FCM HTTP v1 | IMPLEMENTED | `FirebaseCloudMessaging.php`, `config/firebase.php` |
| Query optimization | IMPLEMENTED | Eloquent services, N+1 fix, indexes migration |
| Tests | VERIFIED | 46/46 passing at audit start |
| Jobs table migration | IMPLEMENTED (file) | `2026_08_08_034258_create_jobs_table.php` |
| Sessions migration | IMPLEMENTED (file) | `2026_08_08_034259_create_sessions_table.php` |
| Performance indexes | IMPLEMENTED (file) | `2026_08_08_040705_...` |

---

## Gaps identified (pre-work)

| Area | Status | Notes |
|------|--------|-------|
| Profile image storage | NOT IMPLEMENTED | Direct `file_put_contents` to `public/images/profile/` |
| Laravel Storage abstraction | NOT IMPLEMENTED | No custom disk for profile images |
| Cache for static pages | NOT IMPLEMENTED | Pages served directly from closures |
| FCM via Queue Job | NOT IMPLEMENTED | Synchronous in `ProfileController` |
| Rate limiting | NOT IMPLEMENTED | No custom `RateLimiter` on auth/AJAX/API |
| Vite | NOT APPLICABLE | Project uses **Laravel Mix** (`webpack.mix.js`) |
| Scheduler / cron tasks | NOT IMPLEMENTED | Only `performance:baseline` artisan command |
| OPcache / PHP-FPM / Nginx | REQUIRES SERVER ACCESS | Not in repository |
| MySQL version on server | REQUIRES SERVER ACCESS | App uses standard MySQL syntax |
| Production `.env` | REQUIRES SERVER ACCESS | `.env.example` documents keys |
| Queue worker on server | REQUIRES SERVER ACCESS | `QUEUE_CONNECTION=database` in example |

---

## Image / file handling audit

| Item | Location | Method |
|------|----------|--------|
| Profile upload | `UserAccountService::updateProfileImage` | Base64 → `public/images/profile/img_{time}{userId}.png` |
| Profile delete | Same service | `File::delete($path.$oldImage)` |
| Display URL | Blade views | `asset('images/profile/'.$user->image)` |
| Fallback | Views | `asset('img/avatar2.png')` |
| Post images | — | None |
| FCM icons | Hardcoded paths | `img/icon.png`, `img/d.png` in payload |

---

## Security audit snapshot

| Item | Status |
|------|--------|
| CSRF on web/AJAX | IMPLEMENTED (Laravel web middleware) |
| Password hashing | IMPLEMENTED (`bcrypt`) |
| FCM credentials in env | IMPLEMENTED |
| Guest AJAX null deref | PRE-EXISTING (11 endpoints → 500 for guests) |
| Mass assignment | Partial — `$fillable` on User/Post/Answer |
| XSS in search HTML | PRE-EXISTING (user `name` in search results HTML) |
| Rate limiting | NOT IMPLEMENTED |

---

## Frontend assets

| Item | Value |
|------|-------|
| Build tool | Laravel Mix 4 |
| Entry | `resources/js/app.js`, `resources/sass/app.scss` |
| Output | `public/js`, `public/css` |
| Primary UI assets | `public/style/` (pre-built, not Mix) |

---

## Recommended implementation order

1. Storage disk + ProfileImageStorage (preserve URLs)
2. SendFcmNotificationJob (sync in tests, database in prod example)
3. Rate limiters on auth, votes, search, API
4. Static page cache (contact, privacy, terms)
5. Documentation + screenshots
6. npm run production verification
