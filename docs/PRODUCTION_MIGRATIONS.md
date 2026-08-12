# Production Database Migrations (Manual)

**Do not run `migrate:fresh` or `db:wipe` on production.**

These migrations exist in the repository but require **manual verification and execution** on the production server during a maintenance window.

---

## Pending migrations (as of production-readiness branch)

| Migration | Purpose |
|-----------|---------|
| `2026_08_08_034258_create_jobs_table.php` | Queue worker (`QUEUE_CONNECTION=database`) |
| `2026_08_08_034259_create_sessions_table.php` | Optional DB session driver |
| `2026_08_08_040705_add_performance_indexes_to_posts_and_answers_tables.php` | Query performance indexes |
| `2026_08_12_000001_create_failed_jobs_table.php` | Failed job inspection / retry |

---

## Recommended production steps

```bash
# 1. Backup database first
mysqldump -u USER -p DATABASE > backup_$(date +%Y%m%d).sql

# 2. Review pending migrations
php artisan migrate:status

# 3. Run only pending migrations (additive only)
php artisan migrate --force

# 4. Verify indexes
php artisan db:show
```

---

## After jobs migration

Start a queue worker (supervisor recommended):

```bash
php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
```

Set in production `.env`:

```
QUEUE_CONNECTION=database
CACHE_STORE=file
APP_ENV=production
APP_DEBUG=false
```
