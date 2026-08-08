# API INVENTORY — complete endpoint contract (Laravel 5.8 baseline)

**Generated:** 2026-08-08 · **Source commit:** `2be148b` · **Method:** static analysis of `routes/web.php`, `routes/api.php`, `RouteServiceProvider`, and every controller.

This file is the **authoritative contract** for the migration. Every entry here must behave identically on Laravel 13. Phase 15 diffs old vs. new against this document.

---

## Summary

| Group | Count |
|---|---|
| Auth scaffolding (`Auth::routes()`) | 9 |
| Public pages & profile | 7 |
| Authenticated dashboard pages | 3 |
| AJAX / JSON endpoints | 13 |
| `routes/api.php` | 1 (stock stub, non-functional) |
| **Total registered routes** | **33** |

**There is no REST API.** `routes/api.php` contains only the unmodified Laravel stub. All 13 JSON endpoints live in `routes/web.php` inside the `web` middleware group and therefore require a **session cookie + CSRF token**. See `MIGRATION_BLOCKERS.md` BLOCKER-1.

### Global middleware applying to every route below

Global stack: `CheckForMaintenanceMode`, `ValidatePostSize`, `TrimStrings` (except `password`, `password_confirmation`), `ConvertEmptyStringsToNull`, `TrustProxies`.

`web` group: `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`, `ShareErrorsFromSession`, `VerifyCsrfToken`, `SubstituteBindings`.

`api` group: `throttle:60,1`, `bindings`.

> **`ConvertEmptyStringsToNull` is contract-relevant.** Empty form fields arrive as `null`, not `''`. This is why `addQuestion` stores `null` for omitted poll options 3 and 4, and why `$post->answer3 != null` is the render condition. Preserve it.

---

## A. Authentication routes

Registered by `Auth::routes()` at `routes/web.php:15` with **no arguments**, so `register` and `reset` are on and **`verify` is off**.

| # | Method | URL | Controller@method | Name | Middleware |
|---|---|---|---|---|---|
| A1 | GET | `/login` | `Auth\LoginController@showLoginForm` | `login` | web, guest |
| A2 | POST | `/login` | `Auth\LoginController@login` | — | web, guest |
| A3 | POST | `/logout` | `Auth\LoginController@logout` | `logout` | web |
| A4 | GET | `/register` | `Auth\RegisterController@showRegistrationForm` | `register` | web, guest |
| A5 | POST | `/register` | `Auth\RegisterController@register` | — | web, guest |
| A6 | GET | `/password/reset` | `Auth\ForgotPasswordController@showLinkRequestForm` | `password.request` | web, guest |
| A7 | POST | `/password/email` | `Auth\ForgotPasswordController@sendResetLinkEmail` | `password.email` | web, guest |
| A8 | GET | `/password/reset/{token}` | `Auth\ResetPasswordController@showResetForm` | `password.reset` | web, guest |
| A9 | POST | `/password/reset` | `Auth\ResetPasswordController@reset` | `password.update` | web, guest |

**Not registered:** `email/verify`, `email/resend`, `email/verify/{id}/{hash}`. `VerificationController` is dead code and `App\User` does not implement `MustVerifyEmail`.

### A2 — POST /login  *(highest-risk endpoint in the migration)*

**Request:** `email` (accepts an email address **or** a username), `password`, `remember` (optional), `_token`.

**Custom behaviour that must be carried over verbatim** — `LoginController::findUsername()` runs in the **constructor**:

```php
$login = request()->input('email');
$fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
request()->merge([$fieldType => $login]);
return $fieldType;
```

It inspects the `email` input, decides whether it looks like an email address, and merges the value back onto the request under the resolved key. `$this->username` then drives the credential lookup. **A user can log in with either their email or their username, both submitted in the field named `email`.** No standard upgrade tool preserves this. Test both paths explicitly.

**Validation:** `email` → `required|string`, `password` → `required|string` (from `AuthenticatesUsers::validateLogin`, keyed on `$this->username()`).

**Responses:**
- Success (browser): `302` → `/user` (`$redirectTo`), session regenerated.
- Success (`wantsJson`): `204` with an empty body.
- Failure: `302` back with a `ValidationException` on the `email` key, message from `trans('auth.failed')` (Arabic, `resources/lang/ar/auth.php`).
- Throttled: `ThrottlesLogins`, **5 attempts / 60 s**, then a `ValidationException` with `trans('auth.throttle')`. **This is the only rate limit that exists in the application today.**

**DB:** `SELECT` on `users` by `email` or `username`; bcrypt verify (10 rounds); `UPDATE users.remember_token` when `remember` is set.

### A5 — POST /register

**Request:** `name`, `email`, `password`, `password_confirmation`, `username`, `_token`.

**Validation** (`RegisterController::validator`):

| Field | Rules |
|---|---|
| `name` | `required, string, max:255` |
| `email` | `required, string, email, max:255, unique:users` |
| `password` | `required, string, min:6, confirmed` |
| `username` | `required, string, min:3, alpha_dash, max:50` |

> **`username` is NOT validated as unique** even though the column has a unique index. A duplicate username produces a `QueryException` → **HTTP 500**, not a validation error. This is current behaviour. Adding `unique:users` would be an improvement but changes the response from 500 to 302-with-errors, so it is logged as a documented deviation rather than applied silently.

**Creation:** `name`, `email`, `password` (`Hash::make`), `username` (**lowercased** via `strtolower`). All other columns take their schema defaults (`active=1`, `is_public=1`, `accept_posts=1`, `show_zwar=1`, `active_notification=1`, `visitors=0`).

**Responses:** `302` → `/user`, user logged in, `Registered` event fired. Validation failure → `302` back with errors.

### A3 — POST /logout
`302` → `/`, session invalidated and token regenerated. Triggered from `user/settings.blade.php` by a hidden form.

### A7 / A9 — password reset
Stock behaviour against the **`password_resets`** table (pinned in `config/auth.php`, 60-minute expiry). A9 validates `token`, `email` (`required|email`), `password` (`required|confirmed|min:8` — the framework default, **not** the min:6 used at registration). Success → `302` `/home`.

> `/home` **is not a registered route.** `ResetPasswordController::$redirectTo` and `VerificationController::$redirectTo` both point at `/home`, which falls through to the catch-all `GET /{username?}` and renders the literal string `user not found` (HTTP 200) unless a user named `home` exists. **Pre-existing bug — preserve unless you approve a fix.**

---

## B. Public pages and profile

| # | Method | URL | Controller@method | Name | Auth |
|---|---|---|---|---|---|
| B1 | GET | `/` | `HomeController@index` | `home` | public |
| B2 | GET | `/{username?}` | `User\ProfileController@getUser` | `user.getUser` | public |
| B3 | POST | `/{username?}` | `User\ProfileController@senMessageToUser` | `profile.senMessageToUser` | public |
| B4 | GET | `/pages/contact` | closure → `view('pages.contact')` | `pages.contact` | public |
| B5 | GET | `/pages/privacy-policy` | closure → `view('pages.privacy-policy')` | `pages.privacy-policy` | public |
| B6 | GET | `/pages/terms` | closure → `view('pages.terms')` | `pages.terms` | public |

> **Route-ordering is part of the contract.** `GET /{username?}` is a single-segment catch-all registered at line 43, *before* `ajax/site/search` and the `pages/*` routes. Those are all two-segment URLs so they do not collide. But `login`, `register`, `logout`, `user`, and `password` are registered *earlier*, so a user whose username matches one of those reserved words is permanently unreachable. **The registration order in `routes/web.php` must be preserved exactly.**

### B2 — GET /{username}

**Path param:** `username`. Declared optional (`{username?}`) but the controller signature is `getUser($username)` with no default — reaching it without a value would be an `ArgumentCountError`. Unreachable in practice because `/` is claimed by B1.

**Logic:**
1. `SELECT COUNT(*) FROM users WHERE username = ? AND active = 1`
2. If not exactly `1` → returns the **literal string `user not found` with HTTP 200**. Not a 404, not a view.
3. `User::where('username',…)->firstOrFail()`
4. `$posts` = `posts WHERE type=0 AND user_id=? ORDER BY created_at DESC` — used only for `count($posts)`
5. `$polls` = `posts WHERE type=1 AND user_id=? ORDER BY created_at DESC` — used only for `count($polls)`
6. `$posts_polls` = `posts WHERE user_id=? AND is_public=1 ORDER BY id DESC` — the rendered timeline
7. If `!session()->has($username)`: set `session([$username => 'true'])`, increment `users.visitors`, `save()`

**Response:** `200`, `text/html`, view `user.profile`.

> Step 6 filters on `is_public=1` only, **not** on `is_active`. Polls that were "stopped" via `is_active=0` still render on the public profile — `is_active` only gates vote acceptance. Preserve.
>
> Step 7 is the source of the unbounded-session growth in `UPGRADE_AUDIT.md` §10.4 and the lost-update race in §13 item 8.

### B3 — POST /{username}  *(anonymous message send — core feature)*

**Request:** `message`, `user_id`, `_token`. **`username` in the URL is ignored** — the recipient comes entirely from the `user_id` body field.

**Validation:** `message` → `required|min:2`, `user_id` → `required|numeric`. Custom Arabic messages: `required` and `min` both → `الرسالة قصيرة جدا !`.

**Responses:**
- Validation failure → `302` back with `withErrors($error->errors()->all())`.
- Recipient missing or `active != 1` → **literal string `user not found`, HTTP 200**.
- Success → `302` back with `session('msg') = 'تم إرسال الرسالة بنجاح , شكرا لك .'`, rendered by `layouts/alerts.blade.php`.

**DB:** `SELECT COUNT(*) FROM users WHERE id=? AND active=1`; `SELECT` the user; `INSERT INTO posts (user_id=<recipient>, body=<message>, is_public=0, type=0)` plus timestamps.

**Notification:** if `active_notification == 1` **and** `token_notification != null`, calls `sendNotification($token, "لديك رسالة جديدة", "لديك رسالة جديدة")` — a **blocking** cURL POST to the legacy FCM endpoint. That endpoint was decommissioned by Google in June 2024, so this call currently fails; the handler `echo`s the raw cURL error or response into the output buffer **before** the redirect. Notifications do not work in production today.

> `users.accept_posts` exists and is exposed in the settings UI but **is never checked here**. Users who disabled "accept posts" still receive messages. Pre-existing bug; preserve unless you approve a change.

---

## C. Authenticated dashboard pages

| # | Method | URL | Controller@method | Name | Middleware |
|---|---|---|---|---|---|
| C1 | GET | `/user` | `User\UsersController@index` | `user.index` | web, auth |
| C2 | GET | `/user/settings` | `User\UsersController@settings` | `user.settings` | web, auth |
| C3 | GET | `/user/notification` | `User\UsersController@notification` | `user.notification` | web, auth |

Registered by the `Route::group(['prefix'=>'user','middleware'=>'auth'], …)` block at lines 32–40. Unauthenticated → `302` `/login` via `Authenticate::redirectTo`.

> **Dead code at `routes/web.php:23-30`.** The preceding block calls `Route::prefix(['prefix'=>'user','middleware'=>'auth'], function () {…})`. `Route::prefix()` accepts a single string; the router's `__call` forwards only `$parameters[0]` to `RouteRegistrar::attribute()` and **silently discards the closure**. That block registers **zero routes** and its body never executes. Its three routes are duplicated in the working group below it, which is why nothing is broken. Removing it is a no-op — but it must be removed knowingly, not accidentally "fixed" into registering a second set of routes.

### C1 — GET /user
Queries: `posts type=0 user_id=me ORDER BY created_at DESC`; `posts type=1 user_id=me ORDER BY created_at DESC`; `posts user_id=me ORDER BY id DESC` (no `is_public` filter — the owner sees everything); then an unconditional `UPDATE posts SET is_read=1 WHERE user_id=me AND is_read=0`.

**Side effect on read:** viewing the dashboard marks every message read. This is why the navbar badge clears. Preserve — including the fact that the `UPDATE` fires even when nothing is unread.

### C3 — GET /user/notification
Renders a static "no notifications" panel. No queries, no data.

### View composer (affects C1–C3, B1–B6, and A1–A9)
`AppServiceProvider::boot()` registers `View::composer('*', …)`, which for authenticated users runs `SELECT COUNT(*) FROM posts WHERE user_id=? AND is_read=0 AND type=0` and shares `$num_message_unread`. Because the wildcard matches partials, `@include('layouts.alerts')` triggers it a second time on pages that use it. Every Blade view **must** receive this variable — `layouts/app.blade.php:100` reads it unguarded.

---

## D. AJAX / JSON endpoints

All are `web`-group routes: **session cookie + `X-CSRF-TOKEN` header (or `_token` field) required**. All return **HTTP 200** regardless of outcome, except where a PHP fatal produces a 500.

> **None of these routes declare the `auth` middleware.** D2–D12 dereference `auth()->user()->id` directly, so an unauthenticated request throws `Error: Attempt to read property "id" on null` → **HTTP 500** on PHP 8. D1, D12 and D13 are the exceptions (D1 needs no auth; D12/D13 guard with `auth()->check()`).

| # | Method | URL | Method name | Name | Guest-safe |
|---|---|---|---|---|---|
| D1 | POST | `/ajax/email/check` | `checkEmail` | `email_available.check` | yes (by design) |
| D2 | POST | `/ajax/message/reply` | `replyMessage` | `ajax.reply_message` | no → 500 |
| D3 | POST | `/ajax/message/delete_reply` | `deleteReplyMessage` | `ajax.delete_reply_message` | no → 500 |
| D4 | POST | `/ajax/message/edit` | `editMessage` | `ajax.edit_message` | no → 500 |
| D5 | POST | `/ajax/message/delete` | `deleteMessage` | `ajax.delete_message` | no → 500 |
| D6 | POST | `/ajax/question/add` | `addQuestion` | `ajax.question_add` | no → 500 |
| D7 | POST | `/ajax/user/edit_info` | `userEditInfo` | `ajax.userEditInfo` | no → 500 |
| D8 | POST | `/ajax/user/changePassword` | `userChangePassword` | `ajax.userChangePassword` | no → 500 |
| D9 | POST | `/ajax/user/ChangeImage` | `userChangeImage` | `ajax.userChangeImage` | no → 500 |
| D10 | POST | `/ajax/user/EditSettings` | `userEditSettings` | `ajax.userEditSettings` | no → 500 |
| D11 | POST | `/ajax/user/userEditSocial` | `userEditSocial` | `ajax.userEditSocial` | no → 500 |
| D12 | POST | `/ajax/profile/profileSendVote` | `profileSendVote` | `ajax.profileSendVote` | yes |
| D13 | POST | `/ajax/user/saveNotificationToken` | `saveNotificationToken` | `ajax.saveNotificationToken` | yes |
| D14 | GET | `/ajax/site/search` | `siteSearch` | `ajax.siteSearch` | yes (but fatals on empty query) |

> URL casing is inconsistent (`edit_info`, `changePassword`, `ChangeImage`, `EditSettings`, `userEditSocial`). **All five spellings are frozen.** Renaming any of them breaks the frontend.

---

### D1 — POST /ajax/email/check

**Request:** `email` and/or `username`. Both branches are independent `if`s, not `elseif`.

**Response: `text/html` plain text, NOT JSON.** The handler `echo`es and returns nothing:
- `email` provided → `not_unique` if a row exists in `users`, else `unique`
- `username` provided → same, checked against `users.username`
- **both provided → both strings are echoed, concatenated** (e.g. `uniquenot_unique`)
- neither provided → **empty body**, HTTP 200

**DB:** `SELECT COUNT(*) FROM users WHERE email = ?` and/or `… WHERE username = ?`.

**Security:** unauthenticated, unthrottled email **and** username enumeration oracle.

**Migration note:** returning `response()->json(...)` here would break the caller, which compares the raw response text. The `echo`-and-return-null shape must survive. Laravel converts a null controller return into an empty `Response`, and the echoed bytes are already in the output buffer — this still works in Laravel 13, but it must be explicitly tested rather than assumed.

---

### D2 — POST /ajax/message/reply

**Request:** `reply`, `message_reply_id`, `_token`.
**Validation:** both `required`.

**Logic:** counts `posts WHERE user_id=me AND id=?` (ownership), then counts `answers WHERE user_id=me AND post_id=?` (already-replied).

**Responses:**

| Condition | Body |
|---|---|
| Validation fails | `{"errors": ["msg", …]}` — **array** |
| Post not owned / missing | `{"errors": "حدث خطاء الرجاء المحاولة مرة اخرى ."}` — **string** (`main.error_somethings`) |
| Already replied | `{"errors": "…"}` — string (`main.message_already_reply`) |
| Success | `{"success": "تم إرسال الرد بنجاح"}` (`main.message_done_reply`) |
| Insert returns falsy | **empty body, HTTP 200** — no `return` on that path |

**DB:** 2 × `SELECT COUNT(*)`, then `INSERT INTO answers (post_id, user_id, body)`.

> The insert uses the **query builder**, so `created_at` / `updated_at` are **NOT** set and are left at their column defaults. The Eloquent path in D12 *does* set them. This inconsistency exists in production data; preserve it.
>
> The already-replied guard shares the `answers` table with voting, so replying also blocks voting on the same post (`UPGRADE_AUDIT.md` §7.3).

---

### D3 — POST /ajax/message/delete_reply

**Request:** `reply_id`, `_token`. **No validation.**

**Responses:** success → `{"success": "تم إرسال الرد بنجاح"}` (note: reuses `main.message_done_reply`, the *reply-sent* string, for a *deletion*); ownership check fails → `{"errors": "حدث خطاء…"}`; count is 1 but the delete returns falsy → `{"errors": "…message_already_reply"}`.

**DB:** `SELECT COUNT(*) FROM answers WHERE user_id=me AND id=?`, then `DELETE FROM answers WHERE id=? AND user_id=me`.

---

### D4 — POST /ajax/message/edit  *(visibility toggle — the name is misleading)*

**Request:** `post_id`, `type`, `is_question`, `_token`. **No validation.**

**Semantics — note the inversion:** `type == 1` → sets the flag to `0` (hide/stop); anything else → `1` (show/start).

- `is_question == 1` → updates **`posts.is_active`** (poll accepting votes)
- otherwise → updates **`posts.is_public`** (message visible on profile)

**Responses** (four distinct success strings from `resources/lang/ar/main.php`): `question_done_hide`, `question_done_show`, `message_done_hide`, `message_done_show`. Ownership failure → `{"errors": "حدث خطاء…"}`.

> If the ownership count is `1` but the `UPDATE` affects 0 rows (value already equal), `$update` is `0` and **no response is returned** → empty body, HTTP 200. Toggling to the current value is a silent no-op. Preserve.

**DB:** `SELECT COUNT(*) FROM posts WHERE user_id=me AND id=?`, then `UPDATE posts SET is_active|is_public = ? WHERE id=? AND user_id=me`.

---

### D5 — POST /ajax/message/delete

**Request:** `post_id`, `_token`. No validation.
**Responses:** success → `{"success": "…message_deleted_done"}`; not owned → `{"errors": "حدث خطاء…"}`; owned but delete returns falsy → **empty body**.
**DB:** `SELECT COUNT(*)`, then `DELETE FROM posts WHERE id=? AND user_id=me`. Cascades to `answers` via the FK.

---

### D6 — POST /ajax/question/add  *(create poll)*

**Request:** `question`, `option1`, `option2`, `option3` (optional), `option4` (optional), `_token`.
**Validation:** `question`, `option1`, `option2` → `required`.

**Insert into `posts`:** `body=question`, `user_id=me`, `answer1..answer4`, `type=1`, `is_active=1`, `is_public=1`. Query builder → **no timestamps**. Omitted options arrive as `null` (via `ConvertEmptyStringsToNull`) and are stored as `NULL`, which is what `@if($post->answer3 != null)` keys off in the views.

**Responses:** validation → `{"errors": [...]}` array; success → `{"success": "…question_done_add"}`; insert falsy → **empty body**.

> `posts.created_at` is `NULL` for polls created this way, but both views call `$post->created_at->diffForHumans()`. Whether that fatals depends on how the driver returns the column default. **Flag for explicit testing** — Laravel 13 + MySQL 8 may surface this differently than 5.8 + MariaDB.

---

### D7 — POST /ajax/user/edit_info

**Request:** `name`, `email`, `text_profile`, `_token`.
**Validation:** `name` → `required|min:1`; `email` → `required|email ` *(note the trailing space in the rule string — harmless, but reproduce it or verify equivalence)*; `text_profile` → `max:50`.

**Responses:** validation → `{"errors": [...]}`; email taken by another user → `{"errors": "…user_email_token"}`; success → `{"success": "…user_done_edit_info"}`; update affects 0 rows (nothing changed) → **empty body**.

**DB:** `SELECT COUNT(*) FROM users WHERE id != me AND email = ?`, then `UPDATE users SET name, email, text_profile WHERE id = me`.

> Changing `email` does **not** reset `email_verified_at`. Verification is dead anyway (§A), so this is inert — but do not "fix" it into invalidating verification.

---

### D8 — POST /ajax/user/changePassword

**Request:** `current-password` (**hyphen, not underscore**), `password`, `password_confirmation`, `_token`.

**Order of operations is contract-relevant** — the two custom checks run **before** validation:
1. `Hash::check($request->get('current-password'), Auth::user()->password)` fails → `{"errors": "كلمة المرور الحالية غير صحيحة ."}` (hardcoded Arabic, not a translation key)
2. `strcmp(current, new) === 0` → `{"errors": "لايمكن تعديل كلمة المرور لانها مستخدمة من قبل"}`
3. Then validates `current-password` → `required`, `password` → `required|string|min:6|confirmed` → `{"errors": [...]}` array
4. Success → `{"success": "تم تغيير كلمة المرور بنجاح"}`

**DB:** `UPDATE users SET password = bcrypt(...)` via `$user->save()`.

> The session is **not** invalidated and `remember_token` is **not** rotated after a password change. Other sessions stay live. Security weakness, but changing it would log users out — documented, not silently changed.

---

### D9 — POST /ajax/user/ChangeImage

**Request:** `image` — a data URI (`data:image/png;base64,AAAA…`), `_token`.

**Parsing:** `explode(";", $image)[1]` then `explode(",", …)[1]`, then `base64_decode`. **No validation, no MIME check, no size limit.** Missing array keys are unguarded (see `UPGRADE_AUDIT.md` §6.2).

**Write:** `file_put_contents(public_path().'/images/profile/'."img_".time().$user_id.".png", $bytes)`.
**Persist:** `users.image = <filename>` (bare filename, no path).
**Cleanup:** `File::delete(public_path().'/images/profile/'.$old_image)`. When `$old_image` is `null` this resolves to the directory itself; `File::delete()` returns false on a directory, so it is currently harmless.

**Responses:** success → `{"success": " تم تحديث الصورة بنجاح"}` *(leading space is intentional — `' تم…'.$sizee` where `$sizee` is always `''`)*; failure → `{"error": "حدث خطاء , الرجاء المحاولة لاحقا"}` — **`error`, singular.**

**Public URL contract:** `asset('images/profile/'.$user->image)` → `/images/profile/img_<ts><uid>.png`. **This URL must not change.** Two files exist today. See `UPGRADE_AUDIT.md` §12 for why the `public` disk migration is explicitly rejected.

**Dead code:** `check_base64_image()` is defined in this controller and never called.

---

### D10 — POST /ajax/user/EditSettings

**Request:** `is_public`, `accept_posts`, `show_zwar`, `active_notification` (checkboxes), `_token`.

**Logic:** each field is set to `$request->has(...)` — **presence, not value.** An unchecked box is absent from the POST body → `false`. Sending `is_public=0` sets it to **`true`**, because the key is present. This is a genuine footgun and it is the current contract.

**No validation.** Always returns `{"success": "تم حفظ الاعدادات بنجاح"}` (hardcoded Arabic).
**DB:** `UPDATE users SET is_public, accept_posts, show_zwar, active_notification WHERE id = me`.

---

### D11 — POST /ajax/user/userEditSocial

**Request:** `web`, `twitter`, `instagram`, `youtube`, `snapchat`, `telegram`, `facebook`, `linkedin`, `_token`.
**Validation:** every field `nullable|url`.
**Responses:** validation → `{"errors": [...]}`; success → `{"success": "تم حفظ الاعدادات بنجاح"}`.
**DB:** single `UPDATE users` over all eight columns.

> Values are stored raw and rendered in `user/profile.blade.php` inside `href="{{ $user->twitter }}"`. Blade escapes the attribute, and `nullable|url` blocks `javascript:` URIs. Confirm Laravel 13's `url` rule is not *more* permissive here.

---

### D12 — POST /ajax/profile/profileSendVote  *(voting)*

**Request:** `post_id`, `select_id` (the chosen option, `1`–`4`), `_token`. **No validation rules.**

**Guest handling:** `auth()->check()` is false → `{"error": "يجب عليك التسجيل اولا للتصويت  ... "}` — **`error` singular**, note the double space and trailing spaces. The frontend renders this string directly.

**Checks:** `users WHERE id=me AND active=1` must be `1`, **and** `posts WHERE id=? AND type=1` must be `1`.

> The poll check does **not** test `is_active`. A poll "stopped" via D4 still accepts votes through a direct POST — the stop is enforced only in the UI. Pre-existing; preserve.
>
> `select_id` is not range-checked. Any value is stored in `answers.body`; the tally loops only count `1`–`4`, so out-of-range votes inflate the total but match no option.

**Responses:** already voted (`answers WHERE user_id=me AND post_id=?` > 0) → `{"error": "لقد قمت بالتصويت مسبقا"}`; success → `{"success": "تم التصويت بنجاح"}`; checks fail → `{"error": "حدث خطاء , حاول لاحقا"}`.

**DB:** 3 × `SELECT COUNT(*)`, then an **Eloquent** `Answer` insert — so unlike D2/D6 this path **does** write `created_at`/`updated_at`.

---

### D13 — POST /ajax/user/saveNotificationToken

**Request:** `currentToken` (FCM registration token), `_token`.

**Responses:** guest → `{"errors": "not login"}`; token differs from stored → saves and returns `{"success": "done save token"}`; token unchanged → `{"success": "token already saved"}`. (A trailing `{"success2": …}` return exists but is unreachable.)

**DB:** `UPDATE users SET token_notification = ? WHERE id = me`.

**Caller:** `layouts/app.blade.php` `saveToken()`, invoked from the Firebase `getToken()` promise on every page load for authenticated users. Single token per user; no multi-device support, no invalidation.

---

### D14 — GET /ajax/site/search

**Request:** `query` (query string).

> **Currently returns HTTP 500 when `query` is empty or absent.** `$data` is assigned only inside `if ($query != '')`, so `count($data)` runs on an undefined variable → `TypeError` on PHP 8. Reachable by anyone at `GET /ajax/site/search?query=`. This is a **bug to fix**, not behaviour to preserve — but the fix must return the existing "no results" shape, not a new error shape.

**Query:**
```sql
SELECT * FROM users
WHERE name LIKE '%q%' OR username LIKE '%q%' AND is_public = 1
```
Bindings are parameterised (no SQL injection), but the missing parentheses mean the `is_public` filter binds only to the `username` branch — **private profiles leak through the `name` branch.** No `LIMIT`. No column projection.

**Response:** always `{"success": "<html>"}`, HTTP 200.
- Matches → `<ul class="list-group">` of `<li class="list-group-item"><a href="{{route user.getUser}}"><img src="…" height="30" width="30"/> NAME</a></li>`
- No matches → the plain Arabic string `لاتوجد نتائج لبحثك`

**Security:** `$row->name` is interpolated **unescaped** and injected client-side via `.html()` → **stored XSS** (`UPGRADE_AUDIT.md` §10.3).

**Migration note:** the exact HTML (tags, classes, the `height="30" width="30"` attributes, the avatar fallback `img/avatar2.png`) is part of the contract. Escaping `$row->name` with `e()` changes only the rendered *text*, not the structure — that is a security fix inside the existing contract, not an API change.

---

## E. routes/api.php

| # | Method | URL | Handler | Middleware |
|---|---|---|---|---|
| E1 | GET | `/api/user` | closure → `$request->user()` | `api` (`throttle:60,1`, `bindings`), `auth:api` |

Unmodified Laravel 5.8 stub. The `api` guard uses `driver => token`, which requires a `users.api_token` column that **does not exist** in the schema. Every request returns `401 Unauthenticated`.

**Recommendation:** leave it registered and unchanged. It has never served a client, and removing it is the only way it could ever break one.

---

## F. Cross-cutting invariants for regression testing

Every one of these must hold identically after the migration:

1. **All 33 URLs and HTTP methods unchanged**, including the inconsistent casing in `/ajax/user/ChangeImage` and `/ajax/user/EditSettings`.
2. **All 24 route names unchanged** — `route()` is used throughout the Blade templates and in inline JS.
3. **Route registration order in `routes/web.php` unchanged**, because of the `GET /{username?}` catch-all.
4. **`checkEmail` returns plain text, not JSON.**
5. **`errors` is sometimes an array and sometimes a string.** Both shapes preserved per endpoint.
6. **`error` (singular) vs `errors` (plural)** preserved per endpoint: singular in D9, D12; plural everywhere else.
7. **All JSON responses are HTTP 200**, including errors.
8. **`user not found` is a literal 200 string**, not a 404, on B2 and B3.
9. **Empty-body 200 responses** on the fall-through paths in D2, D4, D5, D6, D7 preserved.
10. **Arabic strings byte-identical**, including the leading space in D9's success message and the trailing spaces in D12's guest message.
11. **Timestamp inconsistency preserved:** query-builder inserts (D2, D6) write no timestamps; the Eloquent insert (D12) does.
12. **Image URLs stay at `/images/profile/<filename>`.**
13. **CSRF still required** on all 13 POST AJAX endpoints — with Laravel 13's new `Sec-Fetch-Site` origin check verified against real browser traffic.
14. **Session cookie name pinned** via `SESSION_COOKIE` so the Laravel 13 prefix change does not log every user out.
15. **`$num_message_unread` shared with every view**, including partials.
