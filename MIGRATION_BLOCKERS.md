# MIGRATION BLOCKERS

Issues that require a decision from the project owner before migration work proceeds. Per Rule 20 and Rule 30, nothing here has been changed unilaterally.

**Status key:** 🔴 blocking · 🟡 needs a decision, not blocking · 🟢 resolved

---

## 🔴 BLOCKER-1 — RESOLVED (scope confirmed)

**Update 2026-08-08:** Owner confirmed this repository is the project to upgrade. Migration proceeds on branch `upgrade/laravel-13`.

**Note:** `routes/api.php` still contains only the Laravel stub (`GET /api/user`). Machine-consumable JSON endpoints remain the 13 `/ajax/*` routes documented in `API_INVENTORY.md`. No separate REST API exists in this codebase.

### Current state

The brief states the application "contains APIs for the entire system", that "the API is already used in production", and that API backward compatibility is the highest priority. **That API does not exist in this codebase.**

`routes/api.php` in full:

```php
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
```

This is the unmodified Laravel 5.8 stub. It is also non-functional: the `api` guard is configured as `driver => token` (`config/auth.php:44-48`), which requires a `users.api_token` column. No such column exists in `database/migrations/2014_10_12_000000_create_users_table.php`. Every request to `GET /api/user` returns `401`.

### Evidence

- Searched the entire repository for `Route::get|post|put|patch|delete|any|match|resource|apiResource|group|prefix`. Matches occur in exactly two files: `routes/web.php` and `app/Providers/RouteServiceProvider.php`.
- `RouteServiceProvider::map()` loads only `routes/web.php` and `routes/api.php`. No custom route files, no additional route registration.
- Only three PHP files exist outside `app/`, `config/`, `database/`, `routes/`, `resources/`, and `tests/`: `bootstrap/app.php`, `public/index.php`, and `server.php`. All three are stock. There is no `public/api/` entrypoint.
- No API authentication package is installed: no Passport, no Sanctum, no JWT, no `api_token` column, no personal access tokens.
- No API Resources, no Form Requests, no versioning directory, no controller under an `Api` namespace.
- Checked git history across all commits (`git log --all --name-only`) — no API files were ever committed and later deleted.

### Why Laravel 13 creates a problem

It does not, directly. The problem is one of **scope, not compatibility**: I cannot guarantee "do not break the existing API" for an API I cannot see. If a live mobile client is calling a production backend, that backend is served by code outside this repository, and upgrading this repository could break it in ways invisible to me — most obviously if both share the same MySQL database and the mobile backend depends on schema, session, or `users.token_notification` semantics.

Corroborating signals that a mobile client exists: `resources/views/home.blade.php` advertises an Android app with a Google Play badge (its `href` is empty), and `public/manifest.json` contains `{"gcm_sender_id": "744234927044"}`.

### What *is* machine-consumable today

The 13 `/ajax/*` endpoints return JSON, but they are registered in `routes/web.php` inside the `web` middleware group. They require **a session cookie and a valid CSRF token**, so only the site's own jQuery can call them. They are fully documented in `API_INVENTORY.md` §D and I am treating them as the contract to preserve.

### Options

| # | Option | Effect |
|---|---|---|
| 1 | **Confirm there is no separate API.** Treat the `/ajax/*` + auth + profile routes as "the API". | Migration proceeds as scoped. Lowest risk. `API_INVENTORY.md` is already complete for this case. |
| 2 | **Provide the repository or server path of the real API**, and confirm whether it shares this MySQL database. | I audit it, extend `API_INVENTORY.md`, and add regression tests before touching anything shared. |
| 3 | **The Android app consumes the web routes directly** (e.g. a WebView, or HTML scraping). | Then the Blade output itself is a contract and the HTML structure must be frozen too — a materially stricter constraint than option 1. |
| 4 | Build a new REST API as part of this work. | **Out of scope.** Violates Rule 12 and Rule 18. Would need to be a separate project. |

### Recommendation

**Option 1 or 2, and confirm before Phase 3.** If a separate API shares this database, I need to see it before any schema change — even an additive index — because I cannot reason about its query plans or its assumptions.

### Compatibility impact

If option 1 is correct: none. If a hidden API exists and we proceed as if it does not, the risk is **high and unbounded**.

---

### 🔴 Related finding: Laravel 5.8 cannot boot on PHP 8.4 (resolved by upgrade)

**Status (2026-08-08):** Owner confirmed **this repository is the project to upgrade.** Laravel 13 migration is in progress on branch `upgrade/laravel-13`.

Laravel 5.8 cannot boot on PHP 8.4 (verified). The application now runs **Laravel 13.24.0 on PHP 8.4** via `php8.4 artisan about`.

### Investigation plan — commands to run on the production server (native only)

Read-only. No Docker, no architecture changes.

**1. Confirm PHP 8.4 and deployed app roots:**

```bash
php8.4 -v   # or: php -v  — must report 8.4.x
nginx -T 2>/dev/null | grep -E 'server_name|root|fastcgi_pass'
ls -la /etc/nginx/sites-enabled/
```

**2. For each Laravel `root` (must end in `/public`):**

```bash
cd <app-root>/..
php8.4 artisan --version
grep -E '"(laravel/framework|php)"' composer.json
git log --oneline -5 ; git remote -v
```

If `php8.4 artisan --version` succeeds with Laravel ≠ 5.8, that is the live codebase.

**3. API routes:**

```bash
php8.4 artisan route:list --path=api
php8.4 artisan route:list   # full list — compare to API_INVENTORY.md
wc -l routes/*.php
```

**4. Shared database check:**

```bash
grep -E '^DB_(HOST|PORT|DATABASE|USERNAME)' .env
```

**5. PHP 8.4 extensions (OPcache required):**

```bash
php8.4 -m | grep -iE 'gd|imagick|bcmath|redis|opcache|intl|zip|pdo_mysql'
php8.4 -i | grep -E 'opcache.enable|opcache.memory'
```

**6. Error baseline:**

```bash
tail -200 storage/logs/laravel.log
tail -200 /var/log/nginx/error.log
tail -200 /var/log/php8.4-fpm.log
```

### What proceeds without resolving BLOCKER-1

Audit documents (done), contract tests from `API_INVENTORY.md`, and the Laravel 13 upgrade on **native PHP 8.4** can proceed on this repository. **Do not deploy to production** until BLOCKER-1 is resolved and every endpoint in `API_INVENTORY.md` is verified on PHP 8.4.

---

## 🟡 DECISION-1 — Adding `auth` middleware converts guest 500s into login redirects

**Current:** none of the 13 `/ajax/*` routes declare `auth`. Ownership is enforced inside each handler by scoping every query to `auth()->user()->id`, so **data isolation is correct** — a guest cannot read or modify another user's rows. But eleven handlers dereference `auth()->user()->id` with no null check, so a guest gets `Error: Attempt to read property "id" on null` → HTTP 500.

**Proposed:** add `auth` to those eleven routes. A guest would then get the framework's standard `302` → `/login` (via `Authenticate::redirectTo`), or `401 JSON` for `expectsJson()` requests.

**Why it needs approval:** it changes the guest response from `500` to `302`/`401`. No legitimate client depends on a 500 — but Rule 4 says I do not change a response shape without asking.

**Alternative if you prefer zero change:** add explicit `auth()->check()` guards returning the endpoint's own `{"errors": "…"}` shape with HTTP 200, matching how D12 and D13 already behave. Slightly uglier, but a strictly smaller diff.

**Recommendation:** add the `auth` middleware. It is the standard fix, it turns an unauthenticated crash into a defined response, and it removes a trivial log-spam/DoS vector.

---

## 🟡 DECISION-2 — Fixing the search privacy leak changes which rows are returned

**Current:** `AjaxController::siteSearch()` builds

```sql
WHERE name LIKE '%q%' OR username LIKE '%q%' AND is_public = 1
```

`AND` binds tighter than `OR`, so this is `(name LIKE …) OR (username LIKE … AND is_public = 1)`. **Users with `is_public = 0` are still returned when the match is on `name`.**

**Proposed:** `WHERE (name LIKE ? OR username LIKE ?) AND is_public = 1`, plus a `LIMIT` and a minimum query length.

**Why it needs approval:** this changes result sets. Private users currently appear in search and would stop appearing. That is almost certainly the intended behaviour — but it is a visible behavioural change, and some users may have come to rely on being findable.

**Recommendation:** apply the fix. The current behaviour contradicts the user-facing privacy setting.

---

## 🟡 DECISION-3 — Session driver change requires pinning the cookie name

**Current:** `config/session.php` hardcodes `'driver' => 'cookie'`, ignoring `SESSION_DRIVER`. Combined with `ProfileController::getUser()` writing `session([$username => 'true'])` for every profile viewed, session data grows without bound inside a ~4 KB cookie. Users who browse many profiles get silently logged out.

**Proposed:** switch to the `database` (or `file`) driver and replace the per-username session keys with a bounded structure, preserving the "count one visit per session per profile" semantics.

**Why it needs approval:** changing the session driver **invalidates every active session** — every logged-in user is logged out once, at deploy time. Additionally, Laravel 13 changed the default session cookie name from `foo_session` to `foo-session`, which would log everyone out a *second* time unless `SESSION_COOKIE` is pinned explicitly in `.env`.

**Recommendation:** make the change, pin `SESSION_COOKIE` to the current value, and schedule the deploy during a low-traffic window. Accept the one-time logout; the current behaviour is an intermittent unexplained-logout bug that is strictly worse.

---

## 🟡 DECISION-4 — Firebase credentials must be rotated by the owner

`app/Http/Controllers/User/ProfileController.php:149` contains a hardcoded FCM **legacy server key**. It is present in the git history, so deleting the line does not revoke it.

**This requires owner action that I cannot perform:** revoke the key in the Firebase console and issue a service-account credential for FCM HTTP v1.

Note that push notifications are **already non-functional** — the code posts to `https://fcm.googleapis.com/fcm/send`, which Google decommissioned in June 2024. So the FCM HTTP v1 migration restores a broken feature rather than changing a working one, which makes it unusually low-risk.

**Also required from you:**
- The Firebase project ID (`akbrnyapp`, per the client config — please confirm).
- A service-account JSON with the `cloudmessaging` scope, delivered out-of-band, never committed.
- Confirmation that the notification payload shape (`data.title`, `data.body`, `data.icon`, `data.image`) must stay identical — `public/firebase-messaging-sw.js` reads those exact keys, and changing them would break notification rendering on already-installed clients.

---

## 🟢 DECISION-5 — Redis (resolved)

**No Redis.** Queue driver: **`database`**. Cache store: **`database`** (or `file` where appropriate). No new infrastructure.

---

## 🟢 Fixed production constraints (non-negotiable)

| Constraint | Value |
|---|---|
| Laravel | **13** |
| PHP | **8.4 only** (do not target 8.3; do not downgrade) |
| Database | MySQL 8 |
| Web server | Nginx → PHP-FPM |
| Performance | OPcache enabled |
| Containers | **None** — no Docker, Sail, or compose files |
| Architecture | Unchanged — native deploy only; server changes need approval |
| Deploy gate | **All 33 routes verified** per `API_INVENTORY.md` before production deploy |

## 🟢 Non-blocking items already decided

These were identified during the audit and resolved without needing your input, because each has a clearly correct answer that preserves behaviour:

| Item | Decision |
|---|---|
| `laravel/socialite` is registered but never called | Remove. Zero usages: no `Socialite::` call, no OAuth route, no callback handler. |
| `fideloper/proxy` is abandoned | Replace with the framework's built-in `TrustProxies`. Note `Request::HEADER_X_FORWARDED_ALL` was removed in Symfony 6 and needs an explicit header bitmask. |
| `fzaninotto/faker` is abandoned | Replace with `fakerphp/faker`, the maintained fork. Dev-only. |
| `laravel/ui` for auth scaffolding | **Add `^4.6.3`.** Restores `Auth::routes()` and all five `Illuminate\Foundation\Auth\*` traits, so all nine auth routes and all five `Auth\*` controllers keep working unchanged. Verified: `laravel/ui` v4.6.3 declares `illuminate/support: ^13.0`. |
| Dead route block at `routes/web.php:23-30` | Remove. `Route::prefix(array, closure)` discards the closure, so it registers zero routes. Its three routes are already duplicated in the working group below it. Removing it is verifiably a no-op. |
| The npm / laravel-mix / Vue 2 pipeline | Remove. `public/js` and `public/css` do not exist, no Blade template references `mix()`, `js/app.js`, or `css/app.css`, and the Vue instance mounts on `#app`, which appears in no template. The build output has never been used. |
| Storage location | **Do not migrate to the `public` disk.** It would change every image URL from `/images/profile/x.png` to `/storage/x.png` and break existing images and hotlinks. Keep the path and URL; harden only how bytes are written. |
| `password_resets` table | Keep the name. `config/auth.php` pins `'table' => 'password_resets'`, so the Laravel 11+ rename to `password_reset_tokens` does not apply and existing reset tokens survive. |
| `GET /api/user` stub | Leave registered and unchanged. It has never served a client; removing it is the only way it could break one. |
| Email verification | Leave dormant. `VerificationController` exists but its routes were never registered and `App\User` does not implement `MustVerifyEmail`. Enabling it would lock out every existing user. |
| bcrypt hashing | Keep, at 10 rounds. Switching to argon2 would invalidate every stored password. |
| `utf8mb4_unicode_ci` collation | Keep. Moving to `utf8mb4_0900_ai_ci` would rebuild every table and index for no functional gain. |
| `increments()` / `INT UNSIGNED` primary keys | Keep. Widening to `BIGINT` is a full-table rebuild with no current benefit. |
