# UPGRADE AUDIT — Laravel 5.8 → Laravel 13

**Project:** `akbrny2` (اخبرني — anonymous messages / polls platform)
**Audit date:** 2026-08-08
**Audit scope:** read-only. No application code was modified during this phase.
**Repository state at audit:** branch `main`, HEAD `2be148b`, working tree clean.

---

## 1. Version summary

| Item | Current | Target | Notes |
|---|---|---|---|
| Laravel | 5.8.37 (`laravel/framework v5.8.37`) | 13.x | Released 2026-03-17 |
| PHP constraint in `composer.json` | `^7.1.3` | `^8.4` | Laravel 13 supports 8.3–8.5; **this project targets PHP 8.4 only** |
| PHP on this machine | 8.4.24 CLI (`php8.4`) | **8.4** | All migration dev, testing, and verification use PHP 8.4 natively |
| Composer | 2.2.6 | 2.7+ recommended | Upgrade Composer on the build/deploy host |
| Database (local) | MariaDB 10.6.23 | MySQL 8 | See §7 |
| Node (local) | 22.22.0 | 20+ | Current build toolchain cannot run on it, see §8 |
| Frontend build | laravel-mix 4 / webpack 4 | Vite (or removal) | Build output is **unused**, see §8 |
| HTTP auth model | Session + CSRF only | unchanged | No token/API auth exists, see §4 |

**Target production stack (fixed):** Laravel 13 · **PHP 8.4** · MySQL 8 · Nginx · PHP-FPM · OPcache. No Docker, no Sail, no containers. Do not downgrade PHP. Do not target PHP 8.3.

Laravel 13 officially supports PHP 8.3–8.5. This project **must run on PHP 8.4** per production requirements.

---

## 2. Repository inventory

The application is small and self-contained. Complete list of first-party PHP source:

- **Models (3):** `App\User`, `App\Post`, `App\Answer` — all in the legacy `app/` root namespace, not `app/Models`.
- **Controllers (9):** `HomeController`, `User\UsersController`, `User\ProfileController`, `Ajax\AjaxController`, and the five stock `Auth\*` scaffolding controllers.
- **Middleware (7):** all stock Laravel 5.8 scaffolding, only `TrustProxies` and `Authenticate` are customised.
- **Providers (6):** `App`, `Auth`, `Broadcast` (not registered), `Event`, `Helper`, `Route`.
- **Migrations (4):** `users`, `password_resets`, `posts`, `answers`.
- **Views (16):** Blade + Bootstrap 3 markup, RTL Arabic.
- **Route files:** `routes/web.php` (all real routes), `routes/api.php` (stock stub only), `routes/channels.php`, `routes/console.php`.
- **Helpers:** `app/helpers.php` (one function, `percentageOf`), autoloaded via `composer.json` `files`.
- **Tests:** only the two stock `ExampleTest` files. **There is no meaningful test coverage.**

Things that do **not** exist despite being implied by the brief: Form Requests, API Resources, Services, Jobs, Events, Listeners, Notifications, Policies, Console Commands, and an `app/Helpers/` directory.

---

## 3. Composer dependency analysis

### 3.1 Production requirements

| Package | Locked | Laravel 13 status | Action |
|---|---|---|---|
| `laravel/framework` | v5.8.37 | — | **Upgrade to `^13.0`** |
| `laravel/tinker` | v1.0.10 | Incompatible | **Upgrade to `^3.0`** (per the L13 upgrade guide) |
| `laravel/socialite` | v4.3.2 | Incompatible | **Remove.** Registered in `config/app.php` but there is not a single `Socialite::` call, route, or callback anywhere in the codebase. Dead dependency. |
| `fideloper/proxy` | 4.3.0 | Abandoned (2021) | **Remove.** Replaced by the framework's built-in `Illuminate\Http\Middleware\TrustProxies`. |

### 3.2 Development requirements

| Package | Locked | Laravel 13 status | Action |
|---|---|---|---|
| `phpunit/phpunit` | 7.5.20 | Incompatible | **Upgrade to `^12.0`** |
| `fzaninotto/faker` | v1.9.1 | **Abandoned** (author archived it in 2020) | **Replace with `fakerphp/faker ^1.23`** |
| `nunomaduro/collision` | v2.1.1 | Incompatible | **Upgrade to `^9.0`** |
| `mockery/mockery` | 1.3.1 | Incompatible | **Upgrade to `^1.6`** |
| `beyondcode/laravel-dump-server` | 1.3.0 | Obsolete | **Remove.** Superseded by `symfony/var-dumper`'s built-in server, which Laravel ships with. |
| `filp/whoops` | 2.7.1 | Obsolete as a direct dep | **Remove from `require-dev`.** Laravel's own error page handles this. |

### 3.3 Packages to add

| Package | Version | Why |
|---|---|---|
| `laravel/ui` | `^4.6.3` | **Critical.** `Auth::routes()` and the `AuthenticatesUsers` / `RegistersUsers` / `ResetsPasswords` / `SendsPasswordResetEmails` / `VerifiesEmails` traits were moved out of the framework into this package in Laravel 6. `laravel/ui` v4.6.3 declares `illuminate/support: ^13.0`. Adding it lets all five `Auth\*` controllers and all nine auth routes keep working **byte-for-byte identically**, which is the single lowest-risk path for authentication compatibility. |
| `google/apache-auth` *or* raw Guzzle + JWT | see §6 | Needed to mint OAuth2 access tokens for FCM HTTP v1. Final choice deferred to the Firebase phase. |

Nothing in this project depends on undocumented Laravel internals, and there are no third-party packages that lack a Laravel 13 equivalent. **Dependency-wise this upgrade is unusually clean.**

---

## 4. API risk assessment

> This is the most important finding in the audit and it contradicts a core premise of the brief. It is documented in full in `MIGRATION_BLOCKERS.md` (BLOCKER-1) and summarised in `API_INVENTORY.md`.

**`routes/api.php` contains only the untouched Laravel 5.8 stub:**

```php
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
```

That route is also non-functional: the `api` guard is `driver => token`, which requires an `api_token` column on `users`. No such column exists in the `users` migration. Any call to `GET /api/user` returns `401`.

I searched the entire repository for route registrations (`Route::get|post|put|patch|delete|any|match|resource|apiResource|group|prefix`). They appear in exactly two files: `routes/web.php` and `RouteServiceProvider`. There is no second application, no versioned API directory, no `public/api/` PHP entrypoint, and nothing in git history.

**Therefore:**

- There is **no REST API, no token authentication, no mobile-app backend** in this repository.
- The 13 `/ajax/*` endpoints are the only JSON-producing routes. They are inside the `web` middleware group, so they require a **session cookie plus a valid CSRF token**. They are consumable only by the site's own jQuery, not by an external client.
- The home page advertises an Android app (`public/img/google-play-badge.png`, and `public/manifest.json` carries a GCM sender id), but its link `href` is empty and no backend for it exists here.

**Consequence for the migration:** the "do not break the existing API" requirement, as literally written, maps onto the `/ajax/*` + auth + profile routes. Those I will preserve exactly. But if a real mobile API is live in production, **it is served by code that is not in this repository**, and it must be located before deployment.

### 4.1 API-adjacent contracts that MUST be preserved byte-for-byte

These are unusual response shapes that a naive "modernisation" would silently break:

1. **`POST /ajax/email/check` returns `text/plain`, not JSON.** It `echo`es the bare strings `unique` / `not_unique` with no `Content-Type` header and no response object. If both `email` and `username` are posted it echoes twice, producing e.g. `uniquenot_unique`. If neither is posted it returns an empty body. The registration JS compares the raw response string.
2. **Error payloads are inconsistent by design.** Some handlers return `{"errors": ["msg1","msg2"]}` (array, from `$validator->errors()->all()`), others return `{"errors": "msg"}` (plain string, from `trans(...)`). The frontend handles both via `html += data.errors`. Both shapes must survive.
3. **Success/error key names are inconsistent.** Most handlers use `success` / `errors`; `userChangeImage`, `profileSendVote` and `siteSearch` use `error` (singular). This inconsistency is load-bearing — the JS checks `result.error` on the vote path and `data.errors` on the reply path.
4. **`GET /ajax/site/search` returns rendered HTML inside a JSON string** (`{"success": "<ul class=\"list-group\">…"}`), injected via `.html()`. The markup is part of the contract.
5. **Several handlers return HTTP 200 with an empty body** when an `if` falls through without a `return` (e.g. `replyMessage` when the insert returns falsy, `deleteMessage` on a failed delete, `addQuestion` on a failed insert). The frontend treats an empty response as a silent no-op. This is sloppy but it is the current behaviour.
6. **`GET /{username}` returns the literal string `user not found` with HTTP 200** for unknown or blocked users — not a 404. Same for `POST /{username}` with a bad `user_id`.
7. **All JSON error responses carry HTTP 200.** Nothing in this application returns 4xx from the AJAX layer.

---

## 5. Laravel breaking changes that affect this codebase

Ordered by the version that introduced them. Items marked **AFFECTS US** were confirmed against the actual source.

### 5.1 Laravel 6

- **AFFECTS US — Auth scaffolding extracted to `laravel/ui`.** `Auth::routes()` and all five `Illuminate\Foundation\Auth\*` traits are gone from the framework. Mitigation: add `laravel/ui ^4.6.3` (see §3.3).
- **AFFECTS US — String/array helpers deprecated then removed.** No usages found in first-party code, so no action, but worth re-checking after the framework bump.
- `Illuminate\Support\Facades\Input` removed — not used here.

### 5.2 Laravel 7

- **AFFECTS US — `Blade::component` / date-serialisation changes.** `Post::created_at` is rendered with `->diffForHumans()` in Blade rather than serialised to JSON, so the ISO-8601 serialisation change does **not** reach any response body. No impact confirmed.
- **AFFECTS US — `CheckForMaintenanceMode` renamed** to `PreventRequestsDuringMaintenance`. `app/Http/Middleware/CheckForMaintenanceMode.php` extends the old class.
- `fideloper/proxy` replaced by first-party `TrustProxies`; `Request::HEADER_X_FORWARDED_ALL` (used in `app/Http/Middleware/TrustProxies.php`) was **removed in Symfony 6**. Must become `Request::HEADER_X_FORWARDED_FOR | ... | HEADER_X_FORWARDED_AWS_ELB` or simply `'*'`.

### 5.3 Laravel 8

- **AFFECTS US — Model factories rewritten to classes.** `database/factories/UserFactory.php` uses the old `$factory->define()` global. Must become a `Database\Factories\UserFactory` class.
- **AFFECTS US — `database/seeds` renamed to `database/seeders`** and namespaced. `composer.json` classmaps the old paths.
- **AFFECTS US — Route strings vs. controller arrays.** The implicit `App\Http\Controllers` namespace prefix in `RouteServiceProvider` is gone. Every route in `routes/web.php` uses the `'User\UsersController@index'` string form and will 500 with "Class not found" unless the namespace is restored or the routes are converted to the `[Controller::class, 'method']` array form. **Route URLs and names are unaffected either way.**
- Pagination defaults to Tailwind. The app never paginates, so no impact today — but this matters when I add pagination in the performance phase.

### 5.4 Laravel 9

- **AFFECTS US — Symfony 5 → 6, Flysystem 1 → 3.** No direct Flysystem or `Storage` usage exists (files are written with raw `file_put_contents`), so the Flysystem jump is a non-event here.
- `Exception` → `Throwable` in `app/Exceptions/Handler.php` signatures. Both overrides in this project are pure `parent::` pass-throughs and can simply be deleted.

### 5.5 Laravel 10

- Native PHP type declarations throughout the skeleton. Affects the scaffolding files being replaced anyway.
- `dispatchNow` removed — not used.

### 5.6 Laravel 11

- **AFFECTS US — the skeleton was restructured.** `app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`, and the whole `app/Http/Middleware/` directory are replaced by fluent configuration in `bootstrap/app.php`. All eleven of this project's Kernel/middleware/handler files are affected. Behaviour is preserved by porting the middleware groups and `$except` lists into `withMiddleware(...)`.
- **AFFECTS US — most `config/*.php` files are no longer published by default.** All 13 config files here are stock 5.8 copies. They can be kept (Laravel still reads published config), but they contain keys that no longer exist and are missing keys that now do. A `config:publish`-then-re-apply-our-deltas approach is safer than keeping 5.8 files verbatim.
- `password_resets` → `password_reset_tokens` in the default skeleton. **No impact:** `config/auth.php` pins `'table' => 'password_resets'` explicitly, so keeping that line preserves the existing table and its data.
- `bindings` / `throttle` middleware aliases still resolve.

### 5.7 Laravel 12

- Largely a maintenance release. No first-party code here is affected.

### 5.8 Laravel 13 (from the official 13.x upgrade guide)

- **High impact — CSRF middleware renamed** `VerifyCsrfToken` → `PreventRequestForgery`, and it now performs **request-origin verification using the `Sec-Fetch-Site` header**. `app/Http/Middleware/VerifyCsrfToken.php` must be ported. The origin check is the item most likely to affect real traffic on the `/ajax/*` routes; it needs explicit cross-origin testing.
- **Medium impact — `cache.serializable_classes` now defaults to `false`.** Only relevant once caching is introduced; plan to cache arrays/scalars, not objects.
- **Medium impact — `upsert()` behaviour on MySQL/MariaDB changed.** Not used in this codebase.
- **Low impact — `symfony/polyfill-php85` defines global `array_first()` / `array_last()`.** `app/helpers.php` defines only `percentageOf()`, so there is no collision.
- **Low impact — cache/session key prefixes changed from `foo_cache_` to `foo-cache-`.** `config/session.php` and `config/cache.php` are published here, so the app-level values win and **existing sessions will not be invalidated** — provided `SESSION_COOKIE` and `CACHE_PREFIX` are pinned explicitly in `.env`. This must be in the deployment checklist or every logged-in user gets logged out.
- **Low impact — Bootstrap pagination view names changed.** No pagination in use yet.
- `laravel/tinker` must go to `^3.0`, `phpunit/phpunit` to `^12.0`.

---

## 6. PHP 8.4 compatibility

### 6.1 Laravel 5.8 cannot run on PHP 8.4 — this is expected, not a blocker

`composer.json` declares `"php": "^7.1.3"` and the lock file pins Symfony 4.4.7 (pre-dating PHP 8 support entirely).

**This was tested on the target runtime, not assumed.** With the locked dependency set installed, `php8.4 artisan --version` fatals during bootstrap:

```
PHP Fatal error: Uncaught ErrorException: Method ReflectionParameter::getClass()
is deprecated since 8.0, use ReflectionParameter::getType() instead
in vendor/laravel/framework/src/Illuminate/Container/Container.php:853
  → Illuminate\Container\Container->resolveDependencies()
  → Illuminate\Foundation\Bootstrap\HandleExceptions->getExceptionHandler()
```

(On PHP 8.3 the first fatal is `Collection::offsetExists` / `ArrayAccess` — same root cause, different symptom.)

Laravel 5.8's `HandleExceptions` promotes PHP errors into thrown `ErrorException`s. Under PHP 8.x the framework fatals before routing, middleware, or application code runs. **This codebase cannot serve a single request on PHP 8.4.**

**This does not change the migration target.** The correct response is to migrate the application to **Laravel 13**, which runs natively on PHP 8.4. Do **not** solve this by:

- downgrading PHP or targeting PHP 8.3,
- introducing Docker or containers,
- or patching Laravel 5.8 vendor code to boot on 8.4.

### 6.2 Laravel 13 on PHP 8.4 — verified compatible

Laravel 13 (released 2026-03-17) supports PHP 8.3, 8.4, and 8.5. PHP 8.4 is within the supported range and is the **required production target** for this project.

### 6.3 Development and testing toolchain (native PHP 8.4 only)

- Use **`php8.4`** for all Composer, Artisan, PHPUnit, and local parity work.
- Set `"php": "^8.4"` in `composer.json` after the framework upgrade (enforces 8.4+, excludes 8.3).
- **Do not change the production PHP version automatically.** If PHP 8.4 is already installed on the server, use it. Any server-level PHP change requires documentation and explicit approval before execution.

**PHP 8.4 extensions — local check (this machine):**

| Extension | Required for Laravel 13 | Present locally |
|---|---|---|
| ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, PDO, session, tokenizer, xml | Yes | Yes |
| pdo_mysql | MySQL 8 | Yes |
| OPcache | Production performance | Yes (Zend OPcache v8.4.24) |
| intl, zip | Recommended | Yes |
| gd or imagick | Image upload validation (planned hardening) | **No** — confirm on production |
| bcmath | Optional | No |
| redis | Not used (database queue/cache) | No |

If production lacks `gd`/`imagick`, image validation must use an available alternative or be documented as a pre-deploy requirement — **do not change server architecture without approval**.

### 6.4 First-party code defects that PHP 8 turns from warnings into fatals

These are real bugs today, not hypothetical:

1. **`AjaxController::siteSearch()` — guaranteed fatal on empty query.** `$data` is only assigned inside `if ($query != '')`. The subsequent `count($data)` therefore runs on an undefined variable. On PHP 5.x/7.x this was a warning that evaluated to `0`; on PHP 8 it throws `TypeError: count(): Argument #1 ($value) must be of type Countable|array, null given` → **HTTP 500**. Reachable by any visitor via `GET /ajax/site/search?query=`.
2. **All 11 owner-scoped `/ajax/*` handlers fatal for guests.** None of these routes carry the `auth` middleware, yet they dereference `auth()->user()->id` directly. For an unauthenticated request `auth()->user()` is `null`, so PHP 8 throws `Error: Attempt to read property "id" on null` → **HTTP 500**. Under PHP 7 this was a warning. (`saveNotificationToken` and `profileSendVote` are the two that correctly guard with `auth()->check()`.)
3. **`AjaxController::userChangeImage()` — fatal on malformed input.** `explode(";", $image)[1]` and `explode(",", …)[1]` are accessed without checking they exist; `$image` is unvalidated request input. Missing keys are warnings, but `base64_decode(null)` is a PHP 8.1+ deprecation and the subsequent `file_put_contents` with a null payload fails opaquely.
4. **`LoginController::findUsername()` — deprecation on every GET `/login`.** `request()->input('email')` is `null` on the form page, and `filter_var(null, FILTER_VALIDATE_EMAIL)` raises "Passing null to parameter #1 ($value) of type string is deprecated" in PHP 8.1+.
5. **Implicitly-nullable parameters.** PHP 8.4 deprecates `function f(Foo $x = null)`. Present throughout the 5.8 scaffolding being replaced; needs a sweep of first-party signatures on PHP 8.4.
6. **`config/app.php` alias `'Helper' => App\Helpers\Helper::class`** points at a class that does not exist (there is no `app/Helpers/` directory). Aliases are lazy, so this only fatals if something references `Helper`. Nothing does — but it should be removed.
7. **`AjaxController` imports `Faker\Provider\Image`** (a dev-only class) in production code. Unused, but it would fatal if ever referenced with `--no-dev` installed.
8. **`UsersController` imports `http\Message\Body`** — from the pecl_http extension, which is not installed. Unused import; harmless but must go.

---

## 7. Database risks

Schema per the migrations (4 tables). **No destructive change is proposed. No column, table, or row will be dropped, renamed, or retyped.**

### 7.1 Structural observations

- `users.id`, `posts.id`, `answers.id` are `increments()` → `INT UNSIGNED`, not `bigIncrements`. Foreign keys match (`integer()->unsigned()`). **Leave as-is.** Widening to `BIGINT` is a full-table rebuild with zero current benefit.
- `posts.user_id` → FK to `users.id` with `ON DELETE CASCADE`. Indexed as a side effect of the FK.
- `answers.post_id` → FK to `posts.id` with `ON DELETE CASCADE`. Indexed via the FK.
- **`answers.user_id` has neither a foreign key nor an index**, despite being in the WHERE clause of five different queries. This is the single biggest indexing gap.
- `users.username` and `users.email` are unique (indexed). `users.active` is not indexed.
- `posts.ip` and `users.ip_address` are declared but never written to by any code path.
- Charset `utf8mb4` / collation `utf8mb4_unicode_ci`. **Do not change** — the newer `utf8mb4_0900_ai_ci` default would force a rebuild of every table and index.

### 7.2 Missing indexes (all additive, all online-safe)

| Table | Proposed index | Serves |
|---|---|---|
| `answers` | `(user_id, post_id)` | Vote-already-cast check, reply-exists check, reply deletion — 5 call sites |
| `posts` | `(user_id, type, created_at)` | Message list, poll list, both profile and dashboard |
| `posts` | `(user_id, is_read, type)` | The unread badge count that runs on **every** page render |
| `posts` | `(user_id, is_public, id)` | Public profile timeline |

### 7.3 Data-integrity notes (behaviour to preserve, not fix)

- The `answers` table stores **two different things** in `body`: free-text replies to messages (`posts.type = 0`) and the selected option number `1`–`4` for polls (`posts.type = 1`). This is the existing design and must be preserved.
- Because both live in `answers`, the vote-duplication check in `profileSendVote()` (`answers WHERE user_id AND post_id`) will also match a *reply*. A user who replied to a post can therefore never vote on it. This is pre-existing behaviour; **I will not change it during a framework migration.**
- `posts` rows for anonymous messages are created with `user_id = <recipient>`, i.e. `user_id` is the *recipient*, not the author. Sender identity is deliberately not stored. Preserve.

### 7.4 Environment mismatch

The local database is **MariaDB 10.6**, the target is **MySQL 8**. These diverge on JSON functions, CTE behaviour, and index hints. This codebase uses none of those, so the risk is low — but migrations should be validated against the actual production engine, not the local one.

---

## 8. Frontend / build risks

**Finding: the entire npm build pipeline is dead code.**

- `webpack.mix.js` compiles `resources/js/app.js` → `public/js` and `resources/sass/app.scss` → `public/css`.
- **Neither `public/js/` nor `public/css/` exists on disk.**
- No Blade template references `js/app.js`, `css/app.css`, or `mix()`. I grepped all 16 views.
- Every asset actually loaded is a pre-built static file under `public/style/` (Bootstrap 3, jQuery from a Google CDN, Font Awesome, alertify, owl.carousel), plus Firebase 7.14.3 from `gstatic.com`.
- `resources/js/app.js` boots a Vue 2 instance against `#app`, an element that appears in **no** template.

Consequences:

- laravel-mix 4 / webpack 4 / node-sass-era `sass-loader@7` will not install or build on Node 22. This would normally be a blocking problem — here it is irrelevant, because **removing the pipeline changes nothing a user can observe.**
- Rule 14 ("do not introduce a frontend framework") is satisfied trivially: Vue 2 is listed in `package.json` but is not shipped to a single page.
- **Recommendation:** drop `webpack.mix.js`, `resources/js/`, `resources/sass/`, and the npm devDependencies. Add Vite only if we later decide to bundle the `public/style/` assets. This is the lowest-risk option and reduces the supply-chain surface to zero.
- Separately: jQuery 3.4.1 and Firebase 7.14.3 are loaded from third-party CDNs with **no Subresource Integrity hashes**, and `layouts/app.blade.php` loads jQuery **twice** (lines 167 and 172).

---

## 9. Authentication risks

Current model: **stock Laravel 5.8 session auth. No Passport, no Sanctum, no JWT, no tokens, no roles, no permissions, no policies.**

| Aspect | Current behaviour | Risk | Plan |
|---|---|---|---|
| Guard | `web` (session), driver `session`, provider `eloquent` → `App\User` | None | Preserve; only the model FQCN may move |
| Hashing | `bcrypt`, 10 rounds | None | **Keep bcrypt.** Switching to argon2 would silently invalidate every stored password |
| Login identity | `LoginController::findUsername()` accepts the `email` field and resolves it as either email or username via `filter_var` | Medium | Custom logic that no upgrade tool will preserve. Must be carried over verbatim and explicitly tested with both an email and a username |
| Login throttling | `ThrottlesLogins` from the auth trait, 5 attempts / 1 min lockout | None | Preserved automatically by keeping `laravel/ui` |
| Registration | `name`, `email` (unique), `password` (min:6, confirmed), `username` (min:3, alpha_dash, max:50) | Low | Preserve rules exactly. Note `username` is **not** validated as unique in the form request — it relies on the DB unique index, so a collision surfaces as a 500, not a validation error. Pre-existing; document, don't silently change |
| Password reset | Stock, `password_resets` table pinned in `config/auth.php`, 60-minute expiry | Low | Preserve. Requires working mail — currently `smtp.mailtrap.io` in `.env.example` |
| Email verification | `VerificationController` exists and `EventServiceProvider` wires `SendEmailVerificationNotification`, **but** `Auth::routes()` is called with no arguments so verification routes are never registered, and `App\User` does not implement `MustVerifyEmail` | None | Entirely dead. Verification has never run. Leave dormant — enabling it would lock out every existing user |
| Session storage | `config/session.php` hardcodes `'driver' => 'cookie'` (not `env()`) | **High** | See §10.4 |
| `api` guard | `driver => token`, requires a non-existent `api_token` column | None | Dead. Leave the config entry so nothing that references it breaks |

---

## 10. Security risks

Ordered by severity. Items 1–3 are live production exposures independent of the upgrade.

### 10.1 CRITICAL — Debug mode is hardcoded on

```php
// config/app.php
'env'   => 'debug',   // line 29 — a literal string, not env('APP_ENV')
'debug' => true,      // line 42 — a literal bool, not env('APP_DEBUG')
```

`.env` cannot override these. Every unhandled exception in production renders a full stack trace including source excerpts and, on Laravel's error page, the resolved environment. Combined with the guest-reachable fatals in §6.2, an anonymous visitor can trigger a stack trace by requesting `GET /ajax/site/search?query=`.

Additionally, `'env' => 'debug'` means `app()->environment('production')` is **false**, so every production guard in the framework and its packages is bypassed.

**Action:** restore `env('APP_ENV', 'production')` / `env('APP_DEBUG', false)` and set them in `.env`. Verify with `php artisan about`.

### 10.2 CRITICAL — Firebase legacy server key committed to git

`app/Http/Controllers/User/ProfileController.php:149` contains a hardcoded FCM legacy server key (`AAAArUfbw8Q:APA91b…`). It is in the git history, so removing the line is not sufficient.

**Action:** the key must be **revoked and rotated in the Firebase console** by the project owner. The replacement credential (a service-account JSON for FCM HTTP v1) must live outside the repo and be referenced via `.env`. This is owner-action, not something I can do.

### 10.3 CRITICAL — Stored XSS in site search

`AjaxController::siteSearch()` concatenates `$row->name` and `$row->username` into an HTML string with **no escaping**, returns it as JSON, and `layouts/app.blade.php` injects it with `$('#search_result').html(data.success)`.

`name` is fully user-controlled — it is set at registration (`required|string|max:255`, no sanitisation) and editable via `POST /ajax/user/edit_info`. Any user can set their display name to `<img src=x onerror=…>` and execute script in the browser of anyone who searches. Session cookies are `http_only`, which limits cookie theft, but CSRF-token exfiltration and full same-origin action forgery remain possible.

**Action:** escape with `e()` inside the generated markup. The HTML structure and JSON shape stay identical, so this is not an API change — it is a bug fix within the existing contract.

### 10.4 HIGH — Session driver is `cookie`, and profile views write unbounded session data

Two problems that compound each other:

- `config/session.php` hardcodes `'driver' => 'cookie'`, ignoring `SESSION_DRIVER`. All session state lives in an encrypted cookie, capped at ~4 KB by browsers.
- `ProfileController::getUser()` does `session([$username => 'true'])` for **every distinct profile a visitor views**, purely to debounce the visitor counter. The session therefore grows by one key per profile visited, forever.

A user who browses ~100 profiles overflows the cookie. The observable failure is silent: the browser drops the oversized `Set-Cookie`, and the user is logged out or the visitor counter stops working.

**Action:** move to the `database` or `file` session driver and pin `SESSION_COOKIE` so existing sessions survive. Replace the unbounded per-username keys with a single bounded structure or a short-TTL cache entry. Behaviour (one visit counted per session per profile) is preserved.

### 10.5 HIGH — Unauthenticated write endpoints

None of the 13 `/ajax/*` routes declare the `auth` middleware. Ownership is enforced inside each handler by scoping queries to `auth()->user()->id` — which is correct for data isolation (a guest cannot read or modify another user's rows) but means guests hit a null-dereference fatal instead of a redirect (§6.2, item 2).

Separately, three endpoints are **intentionally** guest-accessible and unthrottled:
- `POST /{username}` — anonymous message send. Open spam vector.
- `POST /ajax/email/check` — email **and** username enumeration oracle.
- `GET /ajax/site/search` — unbounded `LIKE '%…%'` scan on `users`, no minimum query length.

**Action:** add the `auth` middleware to the 11 owner-scoped routes (converting a 500 into the existing login redirect — an improvement, but a **behaviour change to document**), and apply conservative rate limits to the three public ones, sized above realistic legitimate traffic.

### 10.6 MEDIUM — Unvalidated base64 image upload

`userChangeImage()` base64-decodes request input and writes it straight to `public/images/profile/*.png` with `file_put_contents`. There is no MIME check, no size limit, and no image re-encode. A `check_base64_image()` helper exists in the same class but **is never called**. The `.png` extension is server-controlled, which prevents direct PHP execution, but arbitrary bytes are still written into the webroot.

Also: `File::delete($path . $old_image)` runs with `$old_image === null` for a first-time upload, resolving to the directory path. `File::delete()` returns false on a directory rather than deleting it, so this is currently harmless — but it is fragile.

### 10.7 MEDIUM — Search query is a leading-wildcard `LIKE` with broken operator precedence

```php
->where('name', 'like', '%'.$query.'%')
->orWhere('username', 'like', '%'.$query.'%')
->where('is_public', '=', 1)
```

Bindings are parameterised, so there is **no SQL injection**. But the missing parentheses mean this evaluates as `(name LIKE …) OR (username LIKE … AND is_public = 1)` — so **users who set their profile to private are still returned when the match is on `name`**. That is a privacy leak, and fixing it changes which rows appear in search results.

Additionally there is no `LIMIT`, so a one-character query returns every matching user in one response.

### 10.8 LOW

- `SESSION_SECURE_COOKIE` defaults to `false`; `same_site` is `null` (Laravel 13 defaults to `lax`).
- `VerifyCsrfToken::$except` is empty — good, keep it that way through the `PreventRequestForgery` port.
- `app/helpers.php` divides by `$everything` in `percentageOf()` with only a `$number == 0` guard. When a poll has options but zero votes, `$number` is `0` so it short-circuits — safe today, but only by accident.
- `config/app.php:56` sets `'url_site' => 'http://akbrny.com55/'`. The `55` is a typo. This value builds the profile URL shown in the "copy link" and social-share buttons on the dashboard, so **every share link the site produces is currently broken**. It is also hardcoded rather than derived from `APP_URL`.

---

## 11. Notification / Firebase risks

Current implementation: `ProfileController::sendNotification()`, invoked synchronously from `senMessageToUser()`.

| Aspect | Current | Problem |
|---|---|---|
| Endpoint | `POST https://fcm.googleapis.com/fcm/send` | **Legacy FCM HTTP API. Google shut it down in June 2024.** Push notifications have been broken in production since then |
| Auth | `Authorization: Key=<legacy server key>` | Wrong header format even for the legacy API (`key=` is the documented spelling), and the key is hardcoded (§10.2) |
| Payload | `{registration_ids: [...], data: {...}}` | HTTP v1 requires `{message: {token, data}}` |
| Execution | Blocking cURL inside the HTTP request | Adds full FCM round-trip latency to every anonymous message send; a network hang stalls the user's request |
| Error handling | `echo "cURL Error…"` / `echo $response` | Writes raw FCM output into the HTTP response body ahead of the redirect. Nothing is logged |
| Failure isolation | None | An exception here would abort the message send. The message is saved first, so a failure does not lose data — but the user sees a broken page |
| Token storage | `users.token_notification`, single token per user | No multi-device support, no token-invalid cleanup, no `updated_at` tracking. Overwritten wholesale by `POST /ajax/user/saveNotificationToken` |
| Client SDK | Firebase JS 7.14.3 (2020), `messaging.setBackgroundMessageHandler`, `messaging.getToken()` with no VAPID key | Deprecated compat API. The commented-out `usePublicVapidKey` line suggests web push was never fully working |
| Opt-out | `users.active_notification` + non-null `token_notification` | Correct, preserve |

**Plan:** migrate to FCM HTTP v1 (`POST https://fcm.googleapis.com/v1/projects/{project}/messages:send`) with OAuth2 service-account credentials from `.env`, move the send into a queued job, wrap it in try/catch with proper logging, and keep the `data`-only payload shape (`title`, `body`, `icon`, `image`) so the existing service worker keeps rendering notifications unchanged. **Because notifications are already non-functional, there is no working behaviour to regress here** — this is strictly a restoration.

---

## 12. Storage risks

- **No `Storage` facade usage anywhere.** Profile images are written with `file_put_contents(public_path().'/images/profile/'.$name)` and read with `asset('images/profile/'.$user->image)`.
- Existing files: `public/images/profile/img_15880195141.png`, `img_15917911791.png`. The naming scheme is `img_<unixtime><user_id>.png`, stored as a bare filename in `users.image`.
- `public/storage` is gitignored and no symlink exists; `storage/app/public` is empty. `php artisan storage:link` has never been meaningfully used.
- **Migrating to the `public` disk would change every image URL from `/images/profile/x.png` to `/storage/x.png`, breaking every existing image and any external hotlink.**

**Decision: do not migrate the storage location.** I will keep the exact same on-disk path and the exact same public URL, and only harden *how* bytes get written (validation, atomic write, safe deletion). This satisfies "modernise where appropriate" without breaking a single existing asset. Recorded as a deliberate non-change.

---

## 13. Performance risks

| # | Issue | Location | Impact |
|---|---|---|---|
| 1 | **N+1 on `$post->answers`** — lazy-loaded once per post in the render loop | `user/index.blade.php:87,100,137,142`; `user/profile.blade.php:133,172,177` | One query per post. A profile with 200 posts issues 200+ queries |
| 2 | **N+1 on the vote lookup, twice per poll** — `auth()->user()->answers()->where('post_id',…)->first()` is called on two consecutive lines | `user/profile.blade.php:159-160` | 2 extra queries per poll, and the identical query is run twice |
| 3 | **Unread-count composer runs on every view** — `View::composer('*')` fires for partials too, so `@include('layouts.alerts')` triggers a second `COUNT(*)` | `AppServiceProvider.php:32` | ≥2 identical COUNT queries per page load, on an unindexed predicate |
| 4 | **No pagination anywhere** — `Post::where(...)->get()` loads a user's entire history into memory | `UsersController@index`, `ProfileController@getUser` | Unbounded memory and payload growth |
| 5 | **Three overlapping queries where one would do** — `$posts` (type 0), `$polls` (type 1), and `$posts_polls` (all) are three separate full scans of the same rows, and `$posts`/`$polls` are used **only** for `count()` | `UsersController@index:20-25`, `ProfileController@getUser:30-35` | 3 queries + 3 hydrations; 2 of the 3 result sets are discarded after counting |
| 6 | **Unconditional write on read** — `Post::where(...)->update(['is_read' => 1])` runs on every dashboard load even when nothing is unread | `UsersController@index:30` | A write transaction on every page view |
| 7 | **Blocking FCM cURL in-request** | `ProfileController@sendNotification` | Full external round-trip added to message send latency |
| 8 | **Visitor counter is a read-modify-write race** — `$user->visitors = $user->visitors + 1; $user->save();` | `ProfileController@getUser:44-45` | Lost updates under concurrency; also writes the *entire* user row |
| 9 | **Poll tallying in PHP** — every answer row is fetched and counted in a Blade `foreach` | both index and profile views | Should be a grouped aggregate; scales linearly with vote count |
| 10 | **Missing indexes** | §7.2 | Full table scans on the hottest predicates |
| 11 | **`QUEUE_CONNECTION=sync`** | `.env.example` | No background processing at all |
| 12 | **`CACHE_DRIVER=file`, and nothing is cached** | `.env.example` | Every request recomputes everything |
| 13 | **jQuery loaded twice; ~12 render-blocking CSS/JS files; no bundling, minification, or SRI** | `layouts/app.blade.php` | Slow first paint |

Items 1, 2, 3, 5, and 10 are the high-yield fixes and are all achievable **without changing a single byte of rendered output**.

---

## 14. Recommended migration strategy

**Approach: incremental in-place upgrade, not a rewrite.** The codebase is small (3 models, 9 controllers, 4 migrations), has no exotic dependencies, and has a clean upgrade path once `laravel/ui` restores the auth scaffolding. A fresh-skeleton-plus-port would also be viable, but in-place keeps the diff reviewable and the git history honest.

**Prerequisite — contract-driven regression tests on PHP 8.4.** There are currently zero meaningful tests, and Laravel 5.8 **cannot boot on PHP 8.4** (§6.1), so a live golden-master capture of the old runtime on the target PHP version is impossible. The safety net is therefore:

1. **`API_INVENTORY.md`** — the authoritative behavioural contract (33 routes, response shapes, status codes, edge cases).
2. **Feature tests written during/after the upgrade** and executed with **`php8.4 artisan test`** against a local MySQL 8 database seeded with representative (never production) data.
3. **Optional:** if a staging server or production snapshot of the *current live* app is available, capture responses there for diffing — without Docker and without changing server architecture.

**Do not deploy until every endpoint in `API_INVENTORY.md` has been tested and verified on PHP 8.4.**

All migration work, CI, and verification run **natively on PHP 8.4**. No Dockerfile, no docker-compose, no containers, no Laravel Sail.

> ⚠️ **Phases 3 onward remain gated on BLOCKER-1** until it is confirmed this repository is the code being migrated. The audit documents and the contract test suite transfer to whichever codebase is live.

### Phase order

| Phase | Work | Commit prefix |
|---|---|---|
| 0 | Audit documents (this phase — no code changes) | `chore: audit …` |
| 1 | Resolve BLOCKER-1 (missing API / wrong repo) with the owner | — |
| 2 | Contract regression tests from `API_INVENTORY.md`; run on **PHP 8.4 + MySQL 8** | `test: …` |
| 3 | Create `upgrade/laravel-13`; set `"php": "^8.4"`; remove dead deps; port routes/factories | `chore: prepare …` |
| 4 | Framework upgrade to Laravel 13 + `laravel/ui`; L11+ skeleton on **PHP 8.4** | `upgrade: …` |
| 5 | Fix deprecated APIs: middleware renames, `Throwable`, `TrustProxies` headers, `PreventRequestForgery` | `fix: …` |
| 6 | Verify auth end-to-end, especially the email-or-username login path | `fix: …` |
| 7 | Storage hardening — same paths, same URLs, safer writes | `fix: …` |
| 8 | FCM HTTP v1 + queued job + logging | `fix: …` |
| 9 | Queue driver (`database`) + worker; **no Redis** unless it is already provisioned | `fix: …` |
| 10 | Additive indexes; fix N+1s; collapse redundant queries; add pagination | `perf: …` |
| 11 | Targeted caching with explicit invalidation; nothing on votes/messages/auth | `perf: …` |
| 12 | Rate limiting sized from real traffic data | `feat: …` |
| 13 | Security fixes: debug off, XSS escape, session driver, `auth` middleware | `fix: …` |
| 14 | Frontend: remove the dead Mix/Vue pipeline; SRI on CDN assets | `chore: …` |
| 15 | Full contract test pass on **PHP 8.4**; verify all 33 routes vs `API_INVENTORY.md` | `test: …` |
| 16 | `DEPLOYMENT.md`: PHP 8.4, Nginx, PHP-FPM, OPcache, MySQL 8, pre-deploy checklist, rollback | `docs: …` |

Branch: `upgrade/laravel-13`, one logical commit per phase, `main` untouched and always deployable.

**On Redis: decided — no Redis.** The owner has confirmed it is not provisioned. Per Rule 17 no new infrastructure will be introduced: the queue will use the **`database`** driver (with a `jobs` and `failed_jobs` table) and the cache will use the **`database`** store. Both are additive, require no new service, and are sufficient at this application's scale.

**Behaviour-change decisions (DECISION-1, -2, -3) are currently unapproved** and are on hold pending discussion. Nothing in phases 10–13 that alters an observable response will be implemented until each is individually agreed. The purely internal work in those phases — indexes, N+1 elimination, query consolidation — does not change any response and proceeds normally.

---

## 15. Blockers

Two items require your decision before Phase 3 begins. Both are detailed in `MIGRATION_BLOCKERS.md`:

- **BLOCKER-1** — The production REST API described in the brief does not exist in this repository.
- **BLOCKER-1 (continued)** — If production already runs on PHP 8.4, it cannot be running *this* Laravel 5.8 tree (§6.1). Confirm which codebase is live before deploying.

Laravel 5.8 not booting on PHP 8.4 is **expected** and is resolved by migrating to Laravel 13 — not by downgrading PHP, targeting 8.3, or adding Docker.

---

## 16. Pre-deployment verification checklist (PHP 8.4)

**Do not deploy until every item below passes on the target environment.** No server-level changes without documentation and approval.

### Platform

- [ ] `php8.4 -v` reports 8.4.x on the server (do not change PHP version without approval)
- [ ] Laravel 13: `php8.4 artisan about` shows framework 13.x
- [ ] OPcache enabled in PHP-FPM (`opcache.enable=1`, adequate memory)
- [ ] Required extensions present: ctype, curl, dom, fileinfo, mbstring, openssl, pdo, pdo_mysql, tokenizer, xml, intl, zip; gd or imagick for image uploads

### Composer and packages

- [ ] `composer validate` passes
- [ ] `composer install --no-dev` on PHP 8.4 succeeds
- [ ] `laravel/framework ^13.0`, `laravel/ui ^4.6`, `laravel/tinker ^3.0` installed
- [ ] Abandoned packages removed: `fideloper/proxy`, `fzaninotto/faker`, `laravel/socialite` (unused)
- [ ] No incompatible third-party packages remain

### Database (MySQL 8)

- [ ] Connectivity from PHP 8.4-FPM to MySQL 8
- [ ] Charset `utf8mb4` / collation `utf8mb4_unicode_ci` unchanged
- [ ] No destructive migrations; existing data intact
- [ ] Additive indexes applied without downtime issues

### Server (existing architecture — document only, do not change without approval)

- [ ] Nginx `root` points to `/public`
- [ ] PHP-FPM pool uses PHP 8.4 socket
- [ ] `storage/` and `bootstrap/cache/` writable by PHP-FPM user
- [ ] `public/images/profile/` writable; existing uploads still load

### Queue and cache

- [ ] `QUEUE_CONNECTION=database`; `jobs` / `failed_jobs` tables exist; worker running
- [ ] Cache driver configured (database or file); no Redis unless already provisioned
- [ ] Session driver not cookie-only (see DECISION-3); `SESSION_COOKIE` pinned if changed

### Routes and API contract (`API_INVENTORY.md` — all 33 endpoints)

- [ ] All 9 auth routes: login (email **or** username), register, logout, password reset
- [ ] All 13 `/ajax/*` endpoints: URLs, HTTP methods, request params, response JSON shapes unchanged
- [ ] `POST /ajax/email/check` still returns plain text (`unique` / `not_unique`)
- [ ] Profile routes: `GET/POST /{username}`, pages, dashboard
- [ ] No endpoint removed; no URL or method changed

### Features

- [ ] Messages: anonymous send, list, hide/show, delete
- [ ] Replies: add, delete; one reply per message preserved
- [ ] Voting: poll create, vote, duplicate-vote rejection
- [ ] Notifications: FCM HTTP v1 with service-account auth; queued; failure does not break message send
- [ ] File/image upload: same paths (`/images/profile/`), same URLs
- [ ] Authentication: session + CSRF; bcrypt passwords unchanged

### Final commands

```bash
php8.4 artisan about
php8.4 artisan route:list
php8.4 artisan config:cache && php8.4 artisan route:cache && php8.4 artisan view:cache
php8.4 artisan test
php8.4 artisan queue:work --once   # verify worker
```

Review `storage/logs/laravel.log`, PHP-FPM log, and Nginx error log after smoke testing.
