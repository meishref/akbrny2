# Code Architecture & Query Optimization

**Branch:** `refactor/code-organization-query-optimization`  
**Date:** 12 August 2026  
**Scope:** AJAX controller split, Service layer, API v1 routes, Query Builder → Eloquent

---

## Executive Summary

The client identified that AJAX logic was monolithic, API routes were absent, and many queries used Query Builder without structure. This refactor **implements** organizational and query improvements while **preserving all 14 legacy `/ajax/*` endpoints** exactly (URLs, methods, route names, request/response contracts).

| Metric | Before | After |
|--------|--------|-------|
| AJAX controller files | 1 (`AjaxController.php`, 708 lines) | 7 focused controllers |
| Service classes (domain) | 1 (`FirebaseCloudMessaging`) | 8 |
| API v1 JSON endpoints | 0 (stub only) | 5 |
| `DB::` in application code | ~25 calls in `AjaxController` | **0** (only `PerformanceBaseline` harness) |
| Tests | 42 pass | **46 pass** (+4 API v1 tests) |

---

## Problems Identified

1. **Monolithic `AjaxController`** — 14 endpoints, mixed responsibilities (messages, users, polls, search, notifications).
2. **No structured API** — `routes/api.php` contained only non-functional `GET /api/user` stub.
3. **Query Builder overuse** — Most AJAX handlers used `DB::table()` with `count()` instead of Eloquent/`exists()`.
4. **N+1 on profile polls** — One query per poll for authenticated viewer vote state.
5. **Empty search bug** — Fixed: empty `query` now returns `{'success': 'لاتوجد نتائج لبحثك'}` (test D14).

---

## AJAX Architecture Before

```
app/Http/Controllers/Ajax/
└── AjaxController.php  (708 lines, 14 methods)
```

All routes in `routes/web.php` pointed to `AjaxController::class`.

![Before AjaxController](../screenshots/before/AjaxController.php) — saved snapshot in repo.

---

## AJAX Architecture After

```
app/Http/Controllers/Ajax/
├── EmailCheckController.php      checkEmail
├── MessageController.php         reply, deleteReply, edit, delete
├── QuestionController.php        addQuestion
├── UserAccountController.php     editInfo, password, image, settings, social
├── ProfileVoteController.php     profileSendVote
├── NotificationController.php    saveNotificationToken
└── SearchController.php          siteSearch
```

![Structure after refactor](../screenshots/refactor-01-structure-after.png)

**Routes:** Grouped under `Route::prefix('ajax')` in `routes/web.php`. **Same URLs and route names.**

![routes/web.php after](../screenshots/refactor-02-routes-web-after.png)

---

## API Architecture Before

- `GET /api/user` — Laravel stub, `auth:api` (non-functional, no API tokens).

---

## API Architecture After

New **JSON API v1** (does not replace `/ajax/*`):

| Method | URL | Auth | Purpose |
|--------|-----|------|---------|
| GET | `/api/v1/search?query=` | guest | Structured user search JSON |
| POST | `/api/v1/messages/reply` | session (`auth`) | Reply to message |
| POST | `/api/v1/polls/vote` | session | Cast poll vote |
| POST | `/api/v1/notifications/token` | session | Save FCM token |
| PUT | `/api/v1/users/profile` | session | Update profile |

Legacy stub preserved: `GET /api/user` → 401 without API token.

![routes/api.php](../screenshots/refactor-03-routes-api-after.png)

---

## Database Query Audit

### Query Builder removed from application layer

All former `AjaxController` `DB::table()` calls moved to Services using **Eloquent**:

| Old pattern | New pattern | Service |
|-------------|-------------|---------|
| `DB::table('users')->where(...)->count()` | `User::where(...)->exists()` | EmailAvailabilityService |
| `DB::table('posts')->where(...)->count()` | `Post::where(...)->exists()` | MessageService, PollVoteService |
| `DB::table('answers')->insert(...)` | `Answer::create([...])` | MessageService, PollVoteService |
| `DB::table('posts')->insert(...)` | `Post::create([...])` | QuestionService |
| `DB::table('users')->update(...)` | `User::where(...)->update(...)` | UserAccountService |
| `DB::table('users')->where(...)->get()` | `User::select([...])->...->get()` | SiteSearchService |
| `DB::table('posts')->count()` (composer) | `Post::query()->...->count()` | AppServiceProvider |

**Remaining `DB::` usage:** `app/Console/PerformanceBaseline.php` only (diagnostic harness).

---

## Eloquent Improvements

![MessageService](../screenshots/refactor-04-message-service.png)

- **`count() > 0` → `exists()`** where only existence matters (reply duplicate check, vote duplicate, ownership).
- **`Answer::create()`** instead of raw insert (timestamps preserved when columns exist).
- **`User::select(['id','name','username','image'])`** in search — only required columns.

---

## N+1 Query Fixes

### Profile poll viewer votes

**Before:** `profile.blade.php` ran `auth()->user()->answers()->where('post_id', $id)->first()` per poll.

**After:** `ProfileController::getUser()` batch-loads viewer answers for all poll IDs in **one query**, passes `$viewerAnswersByPostId` to the view.

![N+1 fix](../screenshots/refactor-07-profile-n1-fix.png)

**Compatibility:** Same `$answer_select` UI behavior for authenticated users.

---

## Database Indexes

Existing migration unchanged (not modified):

`2026_08_08_040705_add_performance_indexes_to_posts_and_answers_tables.php`

- `posts(user_id, is_read, type)`
- `answers(user_id, post_id)`

![Indexes](../screenshots/refactor-08-performance-index-migration.png)

**No new migration added** — audit did not identify additional indexes with clear benefit for existing query patterns (search uses leading `%` LIKE).

---

## Service Layer

| Service | Responsibility |
|---------|----------------|
| `EmailAvailabilityService` | Registration email/username uniqueness |
| `MessageService` | Reply, delete reply, visibility toggle, delete post |
| `QuestionService` | Create poll |
| `UserAccountService` | Profile, password, image, settings, social links |
| `PollVoteService` | Cast vote on profile poll |
| `SiteSearchService` | HTML search (AJAX) + JSON search (API v1) |
| `NotificationTokenService` | Save FCM device token |
| `FirebaseCloudMessaging` | (existing) Send FCM HTTP v1 |

Controllers are thin: validate → call service → return response.

---

## Backward Compatibility

All 14 AJAX endpoints verified by existing `ApiContractTest` (33 route tests total):

| Route name | URL | Status |
|------------|-----|--------|
| `email_available.check` | POST `/ajax/email/check` | ✅ unchanged (plain text) |
| `ajax.reply_message` | POST `/ajax/message/reply` | ✅ |
| `ajax.delete_reply_message` | POST `/ajax/message/delete_reply` | ✅ |
| `ajax.edit_message` | POST `/ajax/message/edit` | ✅ |
| `ajax.delete_message` | POST `/ajax/message/delete` | ✅ |
| `ajax.question_add` | POST `/ajax/question/add` | ✅ |
| `ajax.userEditInfo` | POST `/ajax/user/edit_info` | ✅ |
| `ajax.userChangePassword` | POST `/ajax/user/changePassword` | ✅ |
| `ajax.userChangeImage` | POST `/ajax/user/ChangeImage` | ✅ |
| `ajax.userEditSettings` | POST `/ajax/user/EditSettings` | ✅ |
| `ajax.userEditSocial` | POST `/ajax/user/userEditSocial` | ✅ |
| `ajax.profileSendVote` | POST `/ajax/profile/profileSendVote` | ✅ |
| `ajax.saveNotificationToken` | POST `/ajax/user/saveNotificationToken` | ✅ |
| `ajax.siteSearch` | GET `/ajax/site/search` | ✅ (empty query fixed) |

---

## Tests

| Suite | Before | After |
|-------|--------|-------|
| `ApiContractTest` | 33 pass | 33 pass |
| `FcmNotificationTest` | 9 pass | 9 pass |
| `ApiV1Test` | — | 4 pass (new) |
| **Total** | **42 / 128** | **46 / 137** |

![ApiV1Test](../screenshots/refactor-09-api-v1-tests.png)

---

## Performance Verification

Not re-benchmarked in this refactor pass. Expected improvements:

- **`exists()` vs `count()`** — fewer rows scanned on duplicate/ownership checks.
- **Profile N+1** — −N queries for N public polls (authenticated profile view).
- **Search `select()`** — smaller result set from DB.

Query counts for AJAX endpoints should be **≤ previous** (not measured numerically in this commit).

---

## Files Changed

### Created
- `app/Http/Controllers/Ajax/*` (7 controllers)
- `app/Http/Controllers/Api/V1/*` (5 controllers)
- `app/Services/*` (7 services)
- `tests/Feature/ApiV1Test.php`
- `docs/CODE_ARCHITECTURE_AND_QUERY_OPTIMIZATION.md`
- `docs/screenshots/refactor-*.png`

### Modified
- `routes/web.php` — grouped AJAX routes, new controller classes
- `routes/api.php` — v1 API groups
- `app/Answer.php` — `$fillable`
- `app/Providers/AppServiceProvider.php` — Eloquent count
- `app/Http/Controllers/User/ProfileController.php` — batch viewer answers
- `resources/views/user/profile.blade.php` — use preloaded answers

### Deleted
- `app/Http/Controllers/Ajax/AjaxController.php`

---

## Future Recommendations (NOT implemented)

1. Form Request classes for repeated validation blocks.
2. `auth` middleware on owner-scoped AJAX routes (currently returns 500 for guests — preserved behavior).
3. Pagination for dashboard post lists.
4. Full-text search index for `siteSearch`.
5. Laravel Sanctum for token-based mobile API (separate from session API v1).
6. Replace COUNT-then-DELETE with single authorized delete where safe.

---

## Evidence / Screenshots

| File | Description |
|------|-------------|
| `screenshots/before/AjaxController.php` | Monolith before refactor |
| `screenshots/refactor-01-structure-after.png` | Structure after |
| `screenshots/refactor-02-routes-web-after.png` | web.php |
| `screenshots/refactor-03-routes-api-after.png` | api.php |
| `screenshots/refactor-04-message-service.png` | Eloquent MessageService |
| `screenshots/refactor-05-ajax-message-controller.png` | Thin AJAX controller |
| `screenshots/refactor-06-api-search-controller.png` | API search |
| `screenshots/refactor-07-profile-n1-fix.png` | N+1 fix |
| `screenshots/refactor-08-performance-index-migration.png` | Indexes |
| `screenshots/refactor-09-api-v1-tests.png` | New tests |
| `screenshots/refactor-10-git-log.png` | Git log |
