# Staging Data Test Guide

This guide explains how to run the **StagingDataSeeder** on the **client staging server** after uploading the prepared files from this repository.

---

## ⚠️ CRITICAL WARNING

**DO NOT RUN THIS AGAINST PRODUCTION.**

- Never set `ALLOW_STAGING_SEED=true` on production.
- Never run the seeder when `APP_ENV=production`.
- Never point the seeder at the production database (`akbrny2_home2` or any production-named database).

The seeder **inserts only new prefixed rows** but still adds ~120k+ records. Production must not be touched.

---

## What gets created (insert-only)

All rows are identifiable by prefix:

| Entity | Identifier pattern |
|--------|-------------------|
| Users | `staging_test_2026_000001` … `@example.test` |
| Names | `STAGING TEST USER 000001` |
| Posts | `ip = staging-seed`, body `STAGING TEST …` |
| Answers | Vote body `1`–`4` on staging poll posts |
| Notifications | Token `STAGING_FAKE_FCM_*` (fake, not real FCM) |

**Target volumes:**

| Table | Count |
|-------|------:|
| users | 10,000 |
| posts | 30,000 |
| answers | 80,000 |
| notifications | 12,000 |

Existing client records are **not modified**. If `staging_test_2026_000001` already exists, the seeder **aborts** to prevent duplicates.

---

## Files to upload to staging

```
database/seeders/StagingDataSeeder.php
scripts/verify_staging_data.php
scripts/staging_performance_check.php
docs/STAGING_DATA_TEST_GUIDE.md
```

Ensure the full Laravel 13 application (including `vendor/`) is deployed as usual.

---

## Step 1 — Verify environment

On the **staging server**:

```bash
cd /path/to/akbrny
php artisan --version
php artisan env
```

Confirm:

```env
APP_ENV=staging
DB_DATABASE=<staging-database-name>   # NOT akbrny2_home2 / production
```

---

## Step 2 — Enable seed permission (temporary)

Add to staging `.env` **only for the seed window**:

```env
APP_ENV=staging
ALLOW_STAGING_SEED=true
```

Optional blocklist override (comma-separated exact database names blocked):

```env
STAGING_SEED_BLOCKED_DATABASES=akbrny2_home2,production,prod,akbrny_production,akbrny_prod
```

Clear config cache after editing:

```bash
php artisan config:clear
```

---

## Step 3 — Verify database connection

```bash
php artisan db:show
```

Confirm the database name is the **staging** database, not production.

---

## Step 4 — Syntax check (optional)

```bash
php -l database/seeders/StagingDataSeeder.php
php -l scripts/verify_staging_data.php
php -l scripts/staging_performance_check.php
```

---

## Step 5 — Run the seeder

```bash
php artisan db:seed --class=StagingDataSeeder
```

Expected: progress messages and a summary table (users/posts/answers/notifications inserted).

**Do not run:**

- `migrate:fresh`
- `db:wipe`
- `migrate:refresh`
- Any destructive migration on production

---

## Step 6 — Verify record counts & integrity

```bash
php scripts/verify_staging_data.php
```

Expected output: `Result: PASSED` with zero orphan references.

---

## Step 7 — Performance checks (measured, not estimated)

```bash
php scripts/staging_performance_check.php
```

Reports **actual query counts** and **milliseconds** for:

- Homepage/posts query
- Profile + batch poll answers (N+1-sensitive)
- Search (common, partial, empty)
- Poll vote exists checks
- Answers aggregate
- Notifications lookup

---

## Step 8 — Run Laravel tests safely

Tests use the **PHPUnit test database** (`phpunit.xml`), not staging data:

```bash
php artisan test
```

Expected baseline: **51 tests, 211 assertions**.

Do not run tests with `RefreshDatabase` against staging unless `phpunit.xml` explicitly points to an isolated test DB.

---

## Step 9 — Disable seed permission

After completion, **remove or set false** in staging `.env`:

```env
ALLOW_STAGING_SEED=false
```

```bash
php artisan config:clear
```

---

## Safety checks built into StagingDataSeeder

The seeder **ABORTs** unless **all** of the following are true:

1. `APP_ENV=staging`
2. `APP_ENV` is **not** `production`
3. `ALLOW_STAGING_SEED=true`
4. Database name is **not** on the production blocklist
5. `staging_test_2026_000001` does **not** already exist (duplicate guard)

---

## Staging test login (if needed)

All seeded users share the bcrypt password set at seed time:

```
Password: staging-test-2026-not-for-production
Username: staging_test_2026_000001
Email:    staging_test_2026_000001@example.test
```

Use only on staging.

---

## Re-seeding

The seeder refuses to run if staging prefix users already exist. To re-seed:

1. Manually delete **only** rows matching staging markers (`staging_test_2026_%`, `ip=staging-seed`, `STAGING_FAKE_FCM_%` tokens).
2. Re-run with `ALLOW_STAGING_SEED=true`.

Never delete production/client rows.

---

## What was NOT done from local development

- Staging server was **not** accessed (no SSH).
- Staging database was **not** seeded from this environment.
- Server performance numbers were **not** measured here.
- Production was **not** modified.

Upload these files and run the commands above on staging when ready.
