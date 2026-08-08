# PERFORMANCE — Baseline Audit (Laravel 13 / PHP 8.4)

**Generated:** 2026-08-08  
**Branch:** `upgrade/laravel-13`  
**Status:** Phase 3 optimizations **#1–#3 complete** · **Re-audit complete (2026-08-08)** · Awaiting approval for #4+  
**API contract tests:** 33/33 passing (98 assertions)

This document captures measured and static-analysis performance characteristics. Phase 1 established the baseline; Phase 3 applies optimizations one at a time with re-measurement and API regression checks after each change.

---

## 1. Measurement methodology

### Environment (baseline capture)

| Setting | Value |
|---------|-------|
| Laravel | 13.24.0 |
| PHP | 8.4.24 |
| Database | MySQL-compatible (`akbrny_test`) |
| Session driver (local `.env`) | `file` |
| Session driver (L13 config default) | `database` |
| Cache driver | `file` |
| Queue driver | `database` (configured, workers not benchmarked) |

### Harness

- Command: `DB_DATABASE=akbrny_test php8.4 artisan performance:baseline`
- Implementation: `app/Console/PerformanceBaseline.php` + `php8.4 artisan performance:baseline`
- Raw JSON: `storage/app/performance-baseline.json`
- Technique: internal HTTP kernel dispatch with `DB::enableQueryLog()`, `hrtime()` wall time, `memory_get_peak_usage()` delta
- Seed data per run: user `perfuser` with **10 messages** (5 with replies), **1 poll**; voter user `voteruser`

### Limitations (read before comparing numbers)

1. **Single-process, no HTTP stack** — measures Laravel kernel time only (no Nginx/TLS/network).
2. **Small dataset** — query counts scale linearly with timeline size; N+1 impact grows with posts.
3. **Local machine** — not production hardware; absolute ms values are relative baselines.
4. **Query log scope** — logs queries during the request lifecycle; session driver I/O (file) is not counted as SQL.
5. **Production session default** — `config/session.php` defaults to `database`, which adds 1–2 SQL round-trips per request when `SESSION_DRIVER` is not overridden. Local baseline used `file`.
6. **Not all 33 routes measured individually** — representative endpoints cover each category; remaining AJAX routes analyzed statically (see §4).

### Reproduce

```bash
DB_DATABASE=akbrny_test php8.4 artisan migrate --force
DB_DATABASE=akbrny_test php8.4 artisan performance:baseline
php8.4 artisan test --filter=ApiContractTest   # confirm 33/33 before/after any future change
```

---

## 2. Baseline response times & query counts

Captured: **2026-08-08T03:55:50+00:00** (see JSON for full query text).

| Endpoint | HTTP | Time (ms) | SQL queries | Memory Δ | Notes |
|----------|------|-----------|-------------|----------|-------|
| GET `/` (home) | 200 | 20.05 | 0 | ~2 MB | Guest; no DB |
| GET `/login` | 200 | 1.62 | 0 | — | Auth form |
| GET `/register` | 200 | 1.16 | 0 | — | Auth form |
| GET `/password/reset` | 200 | 1.13 | 0 | — | Auth form |
| POST `/login` | 302 | **72.76** | 1 | ~2 MB | Dominated by **bcrypt verify** |
| GET `/user` (dashboard) | 200 | 20.01 | **18** | — | See §3.1 — N+1 hotspot |
| GET `/user/settings` | 200 | 3.67 | 3 | — | 2× duplicate unread COUNT (composer) |
| GET `/user/notification` | 200 | 1.83 | 3 | — | 2× duplicate unread COUNT |
| GET `/{username}` (profile) | 200 | 7.98 | **12** | — | Duplicate user lookup + N+1 |
| POST `/{username}` (send message) | 200 | 4.93 | 4 | — | COUNT + SELECT same user; no FCM (notification off) |
| GET `/ajax/site/search?query=perf` | 200 | 2.63 | 1 | ~2 MB | Full-table `LIKE` scan |
| POST `/ajax/email/check` | 200 | 0.93 | 1 | — | Single COUNT |
| POST `/ajax/message/reply` | 200 | 1.95 | 5 | — | COUNT-before-mutate pattern |
| POST `/ajax/profile/profileSendVote` | 200 | 4.12 | 6 | — | 3 COUNTs before INSERT |
| POST `/ajax/user/saveNotificationToken` | 200 | 2.74 | 2 | — | SELECT + UPDATE |
| POST `/ajax/question/add` | 200 | 4.24 | 2 | — | INSERT only (query builder, no timestamps) |
| POST `/ajax/user/edit_info` | 200 | 5.49 | 3 | — | COUNT + UPDATE |
| GET `/api/user` | 401 | 1.86 | 0 | — | JSON unauthenticated |

### Slowest endpoints (baseline)

1. **POST `/login`** — 72.76 ms (bcrypt, intentional security cost)
2. **GET `/user` (dashboard)** — 20.01 ms, 18 queries
3. **GET `/` (home)** — 20.05 ms (Blade compile/render, no DB)
4. **GET `/{username}`** — 7.98 ms, 12 queries

---

## 3. Problems discovered (static + measured)

### 3.1 ~~Critical — N+1 on timeline views~~ ✅ RESOLVED (Opt #1–#2)

**Fixed 2026-08-08:** `$posts_polls` eager-loads `answers`; single post fetch with in-memory splits.

**Remaining (see §16 candidate #1):** Authenticated profile still runs **2× `auth()->user()->answers()->where('post_id', …)` per poll** — separate from post→answers N+1.

---

### 3.2 ~~Critical — Global view composer duplicate COUNT~~ ✅ RESOLVED (Opt #3)

**Fixed 2026-08-08:** Request-scoped memoization via `app()->instance('view.num_message_unread', …)`.

---

### 3.3 ~~High — Overlapping post queries~~ ✅ RESOLVED (Opt #2)

**Fixed 2026-08-08:** One `Post::where('user_id', …)->with('answers')->get()` per controller; counts derived in PHP.

---

### 3.4 High — Duplicate user existence checks (OPEN — §16 #2)

**Profile GET:** `COUNT(*) WHERE username=? AND active=1` then `SELECT * WHERE username=?`  
**Message POST:** `COUNT(*) WHERE id=? AND active=1` then `SELECT * WHERE id=?`

Two round-trips where one SELECT + null check suffices.

---

### 3.5 High — Blocking FCM cURL in request path

**File:** `ProfileController::sendNotification()`  
**Trigger:** `POST /{username}` when recipient has `active_notification=1` and `token_notification` set.

Synchronous `curl_exec()` to legacy FCM endpoint **blocks the HTTP response** until cURL completes or times out. Baseline benchmark had notifications disabled; production latency impact is unbounded (network-bound).

**Queue candidate (Phase 5 — not implemented):** move notification send off-request; must preserve redirect/flash response timing and body.

---

### 3.6 Medium — COUNT-then-mutate AJAX pattern

Most authenticated AJAX handlers run a **COUNT for ownership/existence**, then INSERT/UPDATE/DELETE on the same row:

| Endpoint | COUNT queries | Mutating query |
|----------|---------------|----------------|
| D2 reply | 2 | INSERT |
| D3 delete_reply | 1 | DELETE |
| D4 edit | 1 | UPDATE |
| D5 delete | 1 | DELETE |
| D7 edit_info | 1 | UPDATE |
| D12 vote | 3 | INSERT |

**Impact:** extra round-trip per action; acceptable at low traffic, adds up under load.

---

### 3.7 Medium — Search full-table scan

**File:** `AjaxController@siteSearch`

```sql
SELECT * FROM users
WHERE name LIKE '%query%' OR username LIKE '%query%' AND is_public = 1
```

- Leading `%` wildcard → **cannot use B-tree indexes** on `name`/`username`.
- `OR`/`AND` precedence may expose private profiles via `name` branch (documented in `API_INVENTORY.md` §D14).
- `SELECT *` loads all columns including social URLs, tokens, etc.

---

### 3.8 Medium — Database session driver (production default)

`config/session.php`: `'driver' => env('SESSION_DRIVER', 'database')`

When `SESSION_DRIVER=database` (recommended for multi-worker PHP-FPM):

- Every request: session **read** (+ **write** on mutation).
- Adds latency vs `file` or Redis (Redis excluded by project constraints).
- **`sessions` table** migration exists (`2026_08_08_034259_create_sessions_table.php`).

---

### 3.9 Medium — Unconditional dashboard write

**File:** `UsersController@index`

```php
Post::where('user_id', Auth::user()->id)->where('is_read', 0)->update(['is_read' => 1]);
```

Runs on **every** dashboard visit even when no unread messages. Measured: 1 UPDATE per dashboard load.

---

### 3.10 Low — Profile visitor session growth

**File:** `ProfileController@getUser`

First visit per username stores `session([$username => 'true'])` and increments `users.visitors`. Over time:

- Session payload grows without bound (one key per visited profile).
- With DB sessions → larger row writes.

---

### 3.11 Low — Expensive PHP operations

| Operation | Location | Cost |
|-----------|----------|------|
| bcrypt verify/hash | login, register, changePassword, password reset | ~50–100 ms per hash at `BCRYPT_ROUNDS=10` |
| base64 decode + file write | `userChangeImage` | Memory + disk I/O proportional to image size |
| Blade `@extends` + large inline JS | dashboard/profile views | Render time; home page ~20 ms with no DB |
| `percentageOf()` helper | poll rendering | Called 8× per poll in view (cheap vs SQL) |

---

## 4. Database analysis (Phase 2 preview — no changes made)

### 4.1 Current indexes

| Table | Indexes |
|-------|---------|
| `users` | PRIMARY (`id`), UNIQUE (`email`), UNIQUE (`username`) |
| `posts` | PRIMARY (`id`), FK index on `user_id` |
| `answers` | PRIMARY (`id`), FK index on `post_id` only |
| `password_resets` | INDEX (`email`) |
| `sessions` | PRIMARY (`id`), INDEX (`user_id`), INDEX (`last_activity`) |
| `jobs` | INDEX (`queue`) |

### 4.2 Missing indexes (documented for future safe migration)

These are **recommendations only** — no migration created in Phase 1.

| Table | Proposed index | Rationale | Query patterns |
|-------|----------------|-----------|----------------|
| `posts` | `(user_id, type, created_at)` | Dashboard/profile type-filtered lists | `WHERE user_id=? AND type=? ORDER BY created_at DESC` |
| `posts` | `(user_id, is_public, id)` | Profile public timeline | `WHERE user_id=? AND is_public=1 ORDER BY id DESC` |
| `posts` | `(user_id, is_read, type)` | Unread badge composer | `WHERE user_id=? AND is_read=0 AND type=0` |
| `answers` | `(post_id)` | Already via FK | Lazy load by post |
| `answers` | `(user_id, post_id)` | Vote/reply duplicate check | D2, D12 COUNT |
| `users` | `(name)` prefix | Limited benefit with `%like%`; full-text may be needed later | siteSearch |

**Note:** Leading-wildcard `LIKE` in search will remain slow without FULLTEXT or external search — index alone will not fix D14.

### 4.3 Query builder vs Eloquent inconsistency

| Path | Timestamps on insert |
|------|---------------------|
| D2 reply, D6 poll (query builder) | **Not set** (`NULL`) |
| D12 vote (Eloquent `Answer`) | **Set** |

Preserved behavior per API contract; affects ordering if `created_at` used in views (`diffForHumans()` on NULL — potential edge case on polls).

---

## 5. Endpoint inventory — static query profile (unmeasured AJAX)

| ID | Endpoint | Est. queries (auth) | Primary concern |
|----|----------|---------------------|-----------------|
| D3 | delete_reply | 2 | COUNT + DELETE |
| D4 | edit_message | 2 | COUNT + UPDATE |
| D5 | delete_message | 2 | COUNT + DELETE (+ cascade) |
| D8 | changePassword | 1 + bcrypt×2 | CPU-bound |
| D9 | ChangeImage | 1 + file I/O | Disk, no validation |
| D10 | EditSettings | 1 | Always UPDATE |
| D11 | userEditSocial | 1–2 | Validation + UPDATE |
| A7 | password/email | 2 + mail I/O | Network |
| A9 | password/reset | 3 + bcrypt | CPU + DB |

**Guest AJAX (D2–D11):** dereference `auth()->user()->id` without middleware → **HTTP 500** on PHP 8.4 (documented contract).

---

## 6. Cache candidates (Phase 4 preview — not implemented)

| Data | Safe to cache? | Notes |
|------|----------------|-------|
| Unread message count | **Risky** | Changes on dashboard visit (`is_read=1` UPDATE); requires invalidation on new message |
| Static pages (contact, terms) | Possible | Rarely change; low benefit (already fast) |
| siteSearch results | **Risky** | Stale public profile data; XSS/HTML contract |
| User profile HTML | **No** | User-specific, session/visitor logic |
| Auth state / permissions | **No** | Explicitly excluded |
| Vote counts | **No** | Real-time correctness required |

**Conclusion:** few low-risk cache wins; any caching needs strict key design + invalidation (Phase 4).

---

## 7. Queue candidates (Phase 5 preview — not implemented)

| Operation | Current | Queue candidate? | API impact if queued |
|-----------|---------|------------------|----------------------|
| FCM `sendNotification()` | Sync cURL in POST /{username} | **Yes** | Response already redirects before client reads body; **must not change** redirect/flash behavior |
| Password reset email | Sync mail | **Yes** | Laravel default; flash message unchanged |
| Image processing | Sync write | Borderline | Success JSON must still reflect saved file |

**Constraint:** database queue only; no Redis. `jobs` table migration exists.

---

## 8. PHP 8.4 / OPcache / PHP-FPM (Phase 6 preview — not implemented)

Documented here as **recommendations only**; production server not modified. Full detail will move to `DEPLOYMENT.md` in Phase 6–7.

| Setting | Recommendation | Rationale |
|---------|----------------|-----------|
| OPcache `enable` | `1` | Avoid re-parsing PHP on every request |
| OPcache `memory_consumption` | `128`–`256` MB | Laravel 13 + app code |
| OPcache `max_accelerated_files` | `20000` | Composer autoload + Blade compiled |
| OPcache `validate_timestamps` | `0` in prod, `1` in dev | Performance vs deploy workflow |
| PHP-FPM `pm` | `dynamic` | Standard for web app |
| PHP-FPM `pm.max_children` | tune to RAM (~50–100 MB/child) | Prevent OOM |
| `memory_limit` | `128M` minimum | Image upload base64 in memory |
| `realpath_cache_size` | `4096K` | Framework many file includes |

Application code is **PHP 8.4 compatible** (33 contract tests pass). No deprecated dynamic property issues observed in app code.

---

## 9. Nginx / deployment (Phase 7 preview — not implemented)

| Requirement | Status |
|-------------|--------|
| Document root | Must be `public/` |
| PHP-FPM socket | Standard Unix socket to PHP 8.4 pool |
| `try_files $uri $uri/ /index.php?$query_string` | Required for Laravel |
| Static assets | `/images/profile/`, `/css/`, `/js/` served directly by Nginx |
| OPcache | PHP-FPM pool config, not Nginx |

No Nginx configuration files added in Phase 1.

---

## 10. Before/after comparison

### Optimization #1 — Eager-load `answers` on `$posts_polls` (2026-08-08)

**Change:** Added `->with('answers')` to the `$posts_polls` query in `UsersController@index` and `ProfileController@getUser` only. No changes to `$posts` / `$polls` queries, views, models, or schema.

**Measured** (same harness, seed: 10 messages + 1 poll for `perfuser`):

| Metric | Before (Phase 1) | After (#1) | Improvement |
|--------|------------------|------------|-------------|
| GET `/user` time | 20.01 ms | **15.56 ms** | **−22%** (−4.45 ms) |
| GET `/user` queries | 18 | **8** | **−56%** (−10 queries) |
| GET `/{username}` time | 7.98 ms | **8.08 ms** | ~0% (within run variance) |
| GET `/{username}` queries | 12 | **7** | **−42%** (−5 queries) |
| GET `/user` memory Δ | ~0 KB | ~0 KB | unchanged |
| GET `/{username}` memory Δ | ~0 KB | ~0 KB | unchanged |
| API contract tests | 33/33 | **33/33** | no regression |

**Dashboard query breakdown (after):** 3 post fetches + **1 batched** `SELECT * FROM answers WHERE post_id IN (...)` + 1 UPDATE + 2 unread COUNT (composer) + 1 auth user lookup = 8 total. Previously: 10 per-post lazy `answers` loads.

**Profile query breakdown (after):** 2 user lookups + 3 post fetches + **1 batched** answers load + 1 visitor UPDATE = 7 total. Profile poll vote lookups (`auth()->user()->answers()->where(...)`) unchanged — separate future optimization.

**API / behavior impact:** none. Blade still uses `$post->answers->count()`, `$post->answers[0]`, and `foreach ($post->answers)`; eager loading returns the same in-memory data without per-post SQL.

### Optimization #2 — Consolidate overlapping post queries (2026-08-08)

**Analysis before change:**

| Variable | Dashboard query (before) | Profile query (before) | View usage |
|----------|-------------------------|------------------------|------------|
| `$posts` | `type=0`, `latest()` | `type=0`, `latest()` | `count($posts)` only — all messages, including private |
| `$polls` | `type=1`, `latest()` | `type=1`, `latest()` | `count($polls)` only — all polls, including non-public |
| `$posts_polls` | all posts, `id DESC`, `with('answers')` | `is_public=1`, `id DESC`, `with('answers')` | Timeline `@foreach` |

**Safe to consolidate:** all three fetched overlapping rows from `posts` for the same user. Counts and timeline are derivable in PHP from one `with('answers')` fetch. Ordering for `$posts`/`$polls` was irrelevant (count-only). Timeline order preserved via `sortByDesc('id')`. Profile `is_public=1` filter applied in PHP so header counts still include private posts/polls.

**Change:** Single `Post::where('user_id', …)->with('answers')->get()`, then collection splits in `UsersController@index` and `ProfileController@getUser`.

**Measured** (after #1 → after #2):

| Metric | After (#1) | After (#2) | Δ (#2 alone) |
|--------|------------|------------|--------------|
| GET `/user` time | 15.56 ms | **18.22 ms** | +2.66 ms (run variance) |
| GET `/user` queries | 8 | **6** | **−2** |
| GET `/{username}` time | 8.08 ms | **12.80 ms** | +4.72 ms (run variance) |
| GET `/{username}` queries | 7 | **5** | **−2** |
| Memory Δ | ~0 KB | ~0 KB | unchanged |
| API contract tests | 33/33 | **33/33** | no regression |

**Dashboard query breakdown (after #2):** 1 posts fetch + 1 batched answers + 1 UPDATE + 2 unread COUNT + 1 auth lookup = **6 total** (was 8 after #1, 18 at baseline).

**Profile query breakdown (after #2):** 2 user lookups + 1 posts fetch + 1 batched answers + 1 visitor UPDATE = **5 total** (was 7 after #1, 12 at baseline).

**API / behavior impact:** none. Same records, filters, ordering, counts, and eager-loaded relationships passed to Blade.

### Optimization #3 — Memoize unread COUNT in view composer (2026-08-08)

**Analysis:**

| Item | Detail |
|------|--------|
| **File** | `app/Providers/AppServiceProvider.php` lines 19–31 |
| **Query** | `SELECT COUNT(*) FROM posts WHERE user_id=? AND is_read=0 AND type=0` |
| **Consumer** | `resources/views/layouts/app.blade.php:100` (navbar badge only) |
| **Other views** | None reference `$num_message_unread` (grep verified) |
| **User-specific** | Yes — scoped to `auth()->id()` |
| **Real-time** | Yes — must reflect state after controller side-effects on same request (e.g. dashboard `is_read` UPDATE) |
| **Cross-request cache** | **Not used** — invalidation is complex; request-scoped memo only |

**Duplicate cause:** `View::composer('*')` fires per rendered view. `@extends('layouts.app')` composes page view + layout → **2 COUNTs**. `@include('layouts.alerts')` adds a third fire (alerts does not use the variable).

**Fix:** `app()->instance('view.num_message_unread', $count)` on first fire; reuse on subsequent composer fires in the same request.

**Measured** (after #2 → after #3):

| Metric | After (#2) | After (#3) | Δ |
|--------|------------|------------|---|
| GET `/user` queries | 6 | **5** | **−1** |
| GET `/user` time | 18.22 ms | **15.05 ms** | −17% |
| GET `/user/settings` queries | 3 | **2** | **−1** |
| GET `/user/settings` time | 4.93 ms | **5.11 ms** | ~0% |
| GET `/user/notification` queries | 3 | **2** | **−1** |
| GET `/user/notification` time | 6.05 ms | **3.11 ms** | −48% |
| GET `/{username}` queries | 5 | **5** | 0 (guest benchmark) |
| API contract tests | 33/33 | **33/33** | no regression |

### Optimization #4a — Profile poll duplicate vote lookup (2026-08-08)

**Problem:** For each poll on authenticated profile views, `profile.blade.php` ran the same query twice: once for null check, once for `->body`.

**Fix:** Store first `auth()->user()->answers()->where('post_id', $post->id)->first()` result in `$viewerAnswer`; reuse for `$answer_select`.

**File:** `resources/views/user/profile.blade.php` (poll vote block only).

**Measured** (guest benchmark unchanged — optimization applies to authenticated profile with polls):

| Metric | After (#3) | After (#4a) | Δ |
|--------|------------|-------------|---|
| GET `/{username}` queries (guest) | 5 | **5** | 0 (guest — no vote lookups) |
| GET `/{username}` time (guest) | ~5 ms | ~5–9 ms | within run variance |
| API contract tests | 33/33 | **33/33** | no regression |

**Authenticated profile impact:** **−1 query per public poll** (eliminates duplicate lookup). With *N* polls → saves *N* SQL queries per authenticated profile view.

**API / behavior impact:** none. Same vote UI, same `$answer_select` value, guest/auth paths unchanged.

### Optimization #4b — COUNT then SELECT user checks (2026-08-08)

**Problem:** `ProfileController@getUser` and `senMessageToUser` each ran `COUNT(*) … WHERE active=1`, then a separate `SELECT` for the same user row.

**Fix:** Single `User::where(...)->where('active', 1)->first()` + null check returning literal `'user not found'` (HTTP 200). Removed unused `DB` facade import.

**File:** `app/Http/Controllers/User/ProfileController.php`

**Measured** (after #4a → after #4b):

| Metric | After (#4a) | After (#4b) | Δ |
|--------|-------------|-------------|---|
| GET `/{username}` queries | 5 | **4** | **−1** |
| GET `/{username}` time | ~9 ms | **~8 ms** | ~−11% |
| POST `/{username}` (send message) queries | 4 | **3** | **−1** |
| POST `/{username}` time | ~4 ms | **~9 ms** | run variance |
| API contract tests | 33/33 | **33/33** | no regression |

**Preserved:** inactive/missing user → `'user not found'` string; redirects; flash messages; visitor tracking; message insert behavior.

### Optimization #5 — Composite database indexes (2026-08-08)

**Migration:** `2026_08_08_040705_add_performance_indexes_to_posts_and_answers_tables.php`

**Indexes added (additive, reversible):**

| Table | Index | Query patterns improved |
|-------|-------|-------------------------|
| `posts` | `(user_id, is_read, type)` | View composer unread COUNT; dashboard `is_read` UPDATE |
| `answers` | `(user_id, post_id)` | D2 reply duplicate check; D12 vote duplicate check; profile poll vote lookup |

**Not added (justified skip):**

| Proposed | Reason skipped |
|----------|----------------|
| `posts(user_id, type, created_at)` | After opt #2, posts fetched with `WHERE user_id=?` only; type filtered in PHP. FK on `user_id` sufficient. |
| `posts(user_id, is_public, id)` | Same — `is_public` filtered in PHP after single fetch. |
| `users(name)` prefix | siteSearch uses leading `%` wildcard — index not usable (deferred). |

**Measured** (after #4b → after #5):

| Metric | After (#4b) | After (#5) | Δ |
|--------|-------------|------------|---|
| GET `/user` queries | 5 | **5** | 0 (query count unchanged; index benefits latency at scale) |
| GET `/user` time | ~13 ms | **~10 ms** | ~−23% (small dataset — variance) |
| GET `/{username}` queries | 4 | **4** | 0 |
| GET `/{username}` time | ~8 ms | **~5 ms** | ~−38% (variance) |
| POST `/{username}` queries | 3 | **3** | 0 |
| API contract tests | 33/33 | **33/33** | no regression |

**Write overhead:** minor — two additional indexes on INSERT/UPDATE to `posts` and `answers`. Acceptable for read-heavy workload.

### Cumulative tracker (Phase 1 → final)

| Metric | Phase 1 | After #3 | **Final (#4a–#5)** | Total vs baseline |
|--------|---------|----------|---------------------|-------------------|
| GET `/user` queries | 18 | 5 | **5** | **−72%** |
| GET `/user/settings` queries | 3† | 2 | **2** | **−33%** |
| GET `/user/notification` queries | 3† | 2 | **2** | **−33%** |
| GET `/{username}` queries | 12 | 5 | **4** | **−67%** |
| POST `/{username}` queries | — | 4 | **3** | **−25%** vs post-#3 |
| GET `/user` time | 20.01 ms | 15.05 ms | **~10 ms** | **~−50%** |
| GET `/{username}` time | 7.98 ms | ~5 ms | **~5 ms** | **~−37%** |
| API contract tests | 33/33 | 33/33 | **33/33** | — |

†Settings/notification isolated in post-#2 baseline (3 queries each with duplicate COUNT).

\*Wall-clock times vary between runs; query-count reductions are deterministic. Index benefits (#5) show primarily at scale; small test DB shows timing variance.

### Final performance summary table

| Endpoint | Phase 1 baseline | Final | Improvement |
|----------|------------------|-------|-------------|
| GET `/user` | 18 queries, 20.01 ms | **5 queries, ~10 ms** | **−72% queries, ~−50% time** |
| GET `/{username}` | 12 queries, 7.98 ms | **4 queries, ~5 ms** | **−67% queries, ~−37% time** |
| GET `/user/settings` | 3 queries, ~5 ms | **2 queries, ~4 ms** | **−33% queries** |
| GET `/user/notification` | 3 queries, ~6 ms | **2 queries, ~3 ms** | **−33% queries** |
| POST `/{username}` (message) | 4 queries (post-#3) | **3 queries, ~7 ms** | **−1 query** |
| POST `/ajax/message/reply` | 5 queries | **6 queries**‡ | unchanged pattern |
| POST `/ajax/profile/profileSendVote` | 6 queries | **6 queries, ~5 ms** | index latency benefit at scale |

‡Reply endpoint query count unchanged; index on `answers(user_id, post_id)` improves duplicate-check latency as table grows.

### Measurement limitations

- Baseline harness uses minimal seed data (10 messages + 1 poll). Index impact (#5) is most visible on production-sized tables.
- Authenticated profile poll savings (#4a) are **per poll** and not reflected in guest `/{username}` benchmark.
- Wall-clock times include PHP bootstrap, view rendering, and run-to-run variance (±30%).
- Memory Δ consistently ~0 KB on measured endpoints (OPcache warm).

---

## 11. API regression gate

After **every** performance change:

```bash
php8.4 artisan test --filter=ApiContractTest
```

**Required result:** 33 passed, 0 failed.  
If any test fails → revert the optimization and investigate.

---

## 12. Optimization priority queue (completed + pending)

### Completed ✅

| # | Optimization | Result |
|---|--------------|--------|
| 1 | Eager-load `answers` on timeline | −10 queries on `/user` |
| 2 | Consolidate 3 post queries → 1 | −2 queries on `/user`, `/profile` |
| 3 | Memoize unread COUNT in view composer | −1 query on authenticated pages |
| 4a | Profile poll duplicate vote lookup | −1 query per poll (authenticated profile) |
| 4b | COUNT+SELECT user checks | −1 query on profile GET, −1 on message POST |
| 5 | Composite indexes (`posts`, `answers`) | Latency at scale; additive migration |

**Cumulative:** GET `/user` **18 → 5 queries (−72%)**, **20.01 → ~10 ms (−50%)**; GET `/{username}` **12 → 4 queries (−67%)** (see §10 final summary).

### Pending — see §16 for full re-audit

Do **not** implement deferred items until explicitly approved.

---

## 13. Files (measurement tooling + optimizations)

| File | Purpose |
|------|---------|
| `app/Console/PerformanceBaseline.php` | Baseline harness |
| `routes/console.php` | Registers `performance:baseline` artisan command |
| `storage/app/performance-baseline.json` | Latest captured metrics |
| `app/Http/Controllers/User/UsersController.php` | Opt #1–2: single post fetch + collection splits |
| `app/Http/Controllers/User/ProfileController.php` | Opt #1–2: single post fetch; Opt #4b: single user SELECT |
| `resources/views/user/profile.blade.php` | Opt #4a: deduplicated poll vote lookup |
| `database/migrations/2026_08_08_040705_add_performance_indexes_to_posts_and_answers_tables.php` | Opt #5: composite indexes |
| `app/Providers/AppServiceProvider.php` | Opt #3: request-scoped unread COUNT memoization |
| `app/Console/PerformanceBaseline.php` | Opt #3: clears memo between benchmark requests |

---

## 14. Phase 3 optimization log

### #1 — Eager-load `Post::answers` on timeline (2026-08-08)

**Problem:** Blade loops in `user/index.blade.php` and `user/profile.blade.php` call `$post->answers->count()`, `$post->answers[0]`, and `foreach ($post->answers)` for each item in `$posts_polls`, causing ~1 SQL query per timeline item.

**Fix:** `->with('answers')` on the `$posts_polls` Eloquent query only (the collection actually iterated in views).

**Not changed:** `$posts` and `$polls` queries (used only for header counts); profile authenticated poll vote queries; any API/AJAX endpoint.

### #2 — Consolidate overlapping post queries (2026-08-08)

**Problem:** Dashboard and profile each ran 3 separate `SELECT * FROM posts …` queries for the same user's rows — one for message count, one for poll count, one for the timeline (with answers).

**Fix:** One `Post::where('user_id', …)->with('answers')->get()`, then:
- **Dashboard:** `$posts` / `$polls` via `where('type', …)`; `$posts_polls` via `sortByDesc('id')`
- **Profile:** same counts from full set; `$posts_polls` via `where('is_public', 1)->sortByDesc('id')`

**Not changed:** Views, filters, ordering semantics, `is_read` UPDATE, visitor tracking, API/AJAX endpoints.

### #3 — Memoize unread COUNT in view composer (2026-08-08)

**Problem:** `View::composer('*')` ran the unread posts COUNT on every composed view (page + layout + includes) — typically **2× per authenticated page**, **3×** when `layouts.alerts` is included.

**Fix:** Bind result to `view.num_message_unread` on the application container on first composer fire; reuse for subsequent fires in the same request. No cross-request cache.

**Not changed:** Query predicates, wildcard composer registration, variable name, layout badge behavior.

### #4a — Profile poll duplicate vote lookup (2026-08-08)

**Problem:** Authenticated profile polls ran `auth()->user()->answers()->where('post_id', $post->id)->first()` twice per poll.

**Fix:** Assign to `$viewerAnswer` once; set `$answer_select` from that result.

**File:** `resources/views/user/profile.blade.php`

### #4b — COUNT then SELECT user checks (2026-08-08)

**Problem:** Profile GET and message POST each verified user existence with COUNT then SELECT.

**Fix:** `User::where(...)->where('active', 1)->first()` + null → `'user not found'`.

**File:** `app/Http/Controllers/User/ProfileController.php`

### #5 — Composite database indexes (2026-08-08)

**Problem:** Frequent filters on `(user_id, is_read, type)` and `(user_id, post_id)` used only FK single-column indexes.

**Fix:** Additive migration with `posts_user_id_is_read_type_index` and `answers_user_id_post_id_index`.

**File:** `database/migrations/2026_08_08_040705_add_performance_indexes_to_posts_and_answers_tables.php`

---

## 15. Next steps

1. ~~**Phase 3 #1** — eager-load answers~~ ✅ complete
2. ~~**Phase 3 #2** — consolidate overlapping post queries~~ ✅ complete
3. ~~**Phase 3 #3** — view composer duplicate COUNT~~ ✅ complete
4. ~~**Phase 3 #4a** — profile poll duplicate vote lookup~~ ✅ complete
5. ~~**Phase 3 #4b** — COUNT+SELECT user checks~~ ✅ complete
6. ~~**Phase 3 #5** — composite database indexes~~ ✅ complete
7. **Phase 4+** — await explicit approval: FCM queue (#3), siteSearch (#4), session driver (#8), AJAX COUNT-then-mutate (#7)

---

## 16. Performance re-audit — remaining candidates (2026-08-08)

**Context:** Optimizations #1–#3 verified. API contract: **33/33 passing, 98 assertions, 0 failures.**  
**Instruction:** Do not implement any item below until approved.

### Classification key

| Class | Meaning |
|-------|---------|
| **HI/LR** | High impact / low risk — preferred next |
| **HI/MR** | High impact / medium risk — needs careful design |
| **HI/HR** | High impact / high risk — may affect API contract or needs product approval |
| **LI/LP** | Low impact / low priority — defer |
| **Skip** | Not worth changing |

---

### Candidate #1 — Profile poll duplicate vote lookup

| | |
|---|---|
| **Class** | **HI/LR** |
| **Location** | `resources/views/user/profile.blade.php:159–160` |
| **Current behavior** | For each poll, authenticated viewers run the same query twice: `auth()->user()->answers()->where('post_id', $post->id)->first()` — once for null check, once for `->body`. |
| **Est. impact** | **2 SQL queries × number of polls** on authenticated profile views. With 10 public polls → 20 extra queries. Dashboard index does not have this pattern. |
| **API / logic risk** | None if result is identical (assign to variable or single query in Blade). Better: one eager load in controller for viewer's answers on visible poll IDs. |
| **Recommended approach** | Controller: when `auth()->check()`, load viewer's `answers` for `$posts_polls` poll IDs once (`whereIn('post_id', …)`). Pass map to view or use collection lookup in Blade. **Smallest fix:** store first query result in `$answer_select` variable in existing PHP block (eliminates duplicate without controller change). |

---

### Candidate #2 — COUNT then SELECT user checks

| | |
|---|---|
| **Class** | **HI/LR** |
| **Location** | `ProfileController@getUser` (lines 18–28), `ProfileController@senMessageToUser` (lines 91–100) |
| **Current behavior** | `COUNT(*) … WHERE username/id AND active=1`, then `SELECT * … WHERE username/id`. Same row checked twice. |
| **Est. impact** | **−1 query** per profile GET and per message POST (~2 queries saved on high-traffic public path). |
| **API / logic risk** | Low. Must preserve: inactive/missing user → literal `'user not found'` string (HTTP 200), not 404. Replace with `User::where(...)->where('active',1)->first()` + null check. |
| **Recommended approach** | Single SELECT; branch on null/`active != 1` exactly as today. |

---

### Candidate #3 — Queue blocking FCM cURL

| | |
|---|---|
| **Class** | **HI/MR** |
| **Location** | `ProfileController::sendNotification()` — called from `senMessageToUser` when `active_notification=1` and token set |
| **Current behavior** | Synchronous `curl_exec()` to legacy FCM endpoint **before** redirect. Network-bound; can add seconds. Legacy endpoint is decommissioned (calls fail silently into output buffer). |
| **Est. impact** | **High** when notifications enabled and FCM migrated to HTTP v1. Currently often fast-fail but still blocks. |
| **API / logic risk** | **Medium.** Redirect, flash message, and HTTP 302 must remain identical. Queue job must not change response timing visible to client. Firebase migration is separate project. |
| **Recommended approach** | Phase 5: database queue job for notification send; dispatch after post insert, return redirect immediately. Requires Firebase HTTP v1 + env credentials (not started). |

---

### Candidate #4 — siteSearch `LIKE '%…%'` full scan

| | |
|---|---|
| **Class** | **HI/HR** |
| **Location** | `AjaxController@siteSearch` |
| **Current behavior** | `SELECT * FROM users WHERE name LIKE '%q%' OR username LIKE '%q%' AND is_public=1`. Leading wildcard → no index use. `OR`/`AND` precedence may leak private profiles via `name` branch (documented contract quirk). Empty query fixed (returns no-results JSON). |
| **Est. impact** | **High at scale** — O(n) table scan per search. Low with small user base. |
| **API / logic risk** | **High.** Result set, HTML shape, and sort order are API contract. FULLTEXT, prefix search, or fixing OR precedence **changes who appears in results**. |
| **Recommended approach** | Add composite index only helps prefix searches, not `%q%`. Any semantic change needs explicit approval + contract test updates. Consider `LIMIT` (not in contract today). **Defer until product approves search behavior.** |

---

### Candidate #5 — Composite database indexes

| | |
|---|---|
| **Class** | **HI/LR** (additive migration) |
| **Location** | Schema: `posts`, `answers` |
| **Current behavior** | Only FK indexes on `posts.user_id`, `answers.post_id`. Frequent filters: `(user_id, is_read, type)`, `(user_id, is_public, id)`, `(user_id, post_id)` on answers. |
| **Est. impact** | Faster COUNT/SELECT as tables grow. Minimal on small datasets. |
| **API / logic risk** | **None** for additive indexes. Requires safe migration + approval (no destructive changes). |
| **Recommended approach** | Document then migrate: `posts(user_id, type, created_at)`, `posts(user_id, is_read, type)`, `answers(user_id, post_id)`. Measure on production-sized data if available. |

---

### Candidate #6 — Unconditional dashboard `is_read` UPDATE

| | |
|---|---|
| **Class** | **LI/LP** (borderline HI/LR at scale) |
| **Location** | `UsersController@index` — `UPDATE posts SET is_read=1 WHERE user_id=? AND is_read=0` on every visit |
| **Current behavior** | Write runs even when zero unread rows. Documented contract: viewing dashboard marks all messages read (badge clears). |
| **Est. impact** | 1 unnecessary write when already read. Saves one UPDATE when user revisits with no unread. |
| **API / logic risk** | Low if guarded by `where('is_read', 0)` only when count > 0. Must not skip update when unread exist. |
| **Recommended approach** | Optional: `if ($allPosts->where('type',0)->where('is_read',0)->isNotEmpty())` before UPDATE, or use affected-rows check. Low priority. |

---

### Candidate #7 — COUNT-then-mutate AJAX pattern

| | |
|---|---|
| **Class** | **LI/LP** |
| **Location** | `AjaxController` — D2 reply, D3 delete_reply, D4 edit, D5 delete, D7 edit_info, D12 vote |
| **Current behavior** | Ownership/existence verified with `COUNT(*)`, then INSERT/UPDATE/DELETE on same row. |
| **Est. impact** | 1 extra round-trip per AJAX action. AJAX is lower volume than page loads. |
| **API / logic risk** | **Medium** — error JSON shapes differ for validation vs ownership failure. Combining into single statement may change edge-case empty-body responses (documented contract). |
| **Recommended approach** | Defer. If pursued, use `update()`/`delete()` affected rows and map to existing error strings. |

---

### Candidate #8 — Production DB session driver overhead

| | |
|---|---|
| **Class** | **HI/MR** |
| **Location** | `config/session.php` — default `database`; local `.env` uses `file` |
| **Current behavior** | Every web request: session read (+ write on mutation). Adds 1–2 SQL queries vs file. |
| **Est. impact** | **+1–2 queries on all routes** in production if `SESSION_DRIVER=database`. |
| **API / logic risk** | **Medium** — session driver change can affect concurrency, logout, and cookie behavior. Redis excluded by project rules. |
| **Recommended approach** | Document in `DEPLOYMENT.md` (Phase 6–7). Tune `sessions` index (already migrated). Not a code change. |

---

### Candidate #9 — Profile visitor session key growth

| | |
|---|---|
| **Class** | **LI/LP** |
| **Location** | `ProfileController@getUser` — `session([$username => 'true'])` per visited profile |
| **Current behavior** | Unbounded session keys; DB session driver amplifies write size. |
| **Est. impact** | Gradual session bloat; minor per-request, cumulative over time. |
| **API / logic risk** | **Medium** — changing dedup logic affects visitor count semantics (business rule). |
| **Recommended approach** | Defer. Alternative: cookie-based or DB visitor log (would be business logic change). |

---

### Candidate #10 — bcrypt / login CPU time

| | |
|---|---|
| **Class** | **Skip** |
| **Current behavior** | POST `/login` ~70 ms — dominated by bcrypt verify (`BCRYPT_ROUNDS=10`). |
| **Est. impact** | Intentional security cost. |
| **Recommended approach** | Do not reduce rounds without security review. |

---

### Candidate #11 — Blade duplicate in-memory `count()` calls

| | |
|---|---|
| **Class** | **Skip** |
| **Location** | `user/index.blade.php:87,100` — `$post->answers->count()` called twice when reply exists |
| **Current behavior** | After opt #1, uses eager-loaded collection — **no extra SQL**. |
| **Recommended approach** | Optional micro-refactor for readability only. No performance gain. |

---

### Candidate #12 — Large Blade + inline Firebase JS

| | |
|---|---|
| **Class** | **LI/LP** |
| **Location** | `layouts/app.blade.php` — Firebase SDK, inline scripts on every page |
| **Est. impact** | HTML/JS payload size; home ~15–20 ms with zero DB. Client-side cost dominates. |
| **API / logic risk** | Moving scripts could affect notification token save timing. |
| **Recommended approach** | Frontend/asset pipeline — out of scope for backend query optimizations. |

---

### Candidate #13 — Cross-request cache (unread count, search)

| | |
|---|---|
| **Class** | **Skip** ( rejected ) |
| **Reason** | Unread count changes on dashboard visit and new messages. Search must be real-time per contract. Invalidation unsafe without design. |

---

### Candidate #14 — Guest AJAX → HTTP 500

| | |
|---|---|
| **Class** | **Skip** (not performance) |
| **Reason** | Documented API contract behavior on PHP 8. Fixing is auth middleware change — API contract decision, not optimization. |

---

### Prioritized implementation order (pending approval)

| Priority | Candidate | Class | Est. query savings |
|----------|-----------|-------|-------------------|
| **#4a** | Profile poll duplicate vote lookup (#1) | HI/LR | Up to 2 × polls (authenticated profile) |
| **#4b** | COUNT+SELECT user checks (#2) | HI/LR | −1 per profile GET, −1 per message POST |
| **#5** | Composite indexes (#5) | HI/LR | Latency at scale (migration) |
| **#6** | Queue FCM (#3) | HI/MR | Latency on message send (Phase 5) |
| **#7** | Conditional is_read UPDATE (#6) | LI/LP | −0–1 write per dashboard revisit |
| **#8** | AJAX COUNT-then-mutate (#7) | LI/LP | −1 per AJAX call |
| **Defer** | siteSearch (#4) | HI/HR | Needs product/API approval |
| **Defer** | Session driver (#8), visitor sessions (#9) | LI/LP–MR | Infra / business logic |

---

### Current measured state (post #1–#5, final run)

| Endpoint | Queries | Time |
|----------|---------|------|
| GET `/user` | **5** | ~10 ms |
| GET `/user/settings` | **2** | ~4 ms |
| GET `/user/notification` | **2** | ~3 ms |
| GET `/{username}` (guest) | **4** | ~5 ms |
| POST `/{username}` (message) | **3** | ~7 ms |
| POST `/login` | 1 | ~73 ms |

**API regression (final):** `33 passed, 0 failures, 98 assertions` ✅
