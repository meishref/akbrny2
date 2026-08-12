# Production Server Configuration

**Status:** Documentation only — **REQUIRES SERVER ACCESS** to apply.

This document describes recommended server settings for the اخبرني (akbrny) Laravel 13 application. Nothing in this file modifies production infrastructure.

---

## PHP

| Requirement | Value |
|-------------|-------|
| PHP version | **8.3.x** (minimum per `composer.json`) |
| SAPI | PHP-FPM recommended |

### Required extensions (from Laravel / composer)

Verify with `php -m` on the server:

- `bcmath`
- `ctype`
- `curl`
- `dom`
- `fileinfo`
- `json`
- `mbstring`
- `openssl`
- `pdo`
- `pdo_mysql`
- `tokenizer`
- `xml`

Run locally: `composer check-platform-reqs`

---

## PHP-FPM (recommended starting points)

```ini
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500
request_terminate_timeout = 60s
```

Adjust based on available RAM and traffic. Monitor slow requests and queue latency.

---

## OPcache

**Local verification:** OPcache was **disabled** in the development PHP CLI used for this audit.

**Production:** Enable OPcache in PHP-FPM (not just CLI):

```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
```

Set `opcache.validate_timestamps=1` during active development only. After deployment, set to `0` and reload PHP-FPM.

**Status:** REQUIRES SERVER ACCESS — do not claim OPcache is enabled unless verified on the production host.

---

## Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name example.com;
    root /var/www/akbrny/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

- Document root must be **`/public`**, not the project root.
- Ensure `public/images/profile/` is writable by the PHP-FPM user.

---

## MySQL

| Item | Status |
|------|--------|
| Minimum version | **MySQL 8.0+ recommended** |
| Server version on production | **REQUIRES SERVER ACCESS — verify with `SELECT VERSION();`** |
| JSON columns | Not used in current schema |
| Collation | utf8mb4 (Laravel default) |
| Foreign keys | Limited use in legacy schema |

The application uses standard Laravel migrations and Eloquent. No MySQL 8-only syntax was identified, but **MySQL 8 is recommended** for long-term support.

---

## Storage permissions

```bash
chown -R www-data:www-data storage bootstrap/cache public/images/profile
chmod -R ug+rwx storage bootstrap/cache
chmod -R ug+rwx public/images/profile
php artisan storage:link   # if using storage/app/public later
```

---

## Queue worker

Required when `QUEUE_CONNECTION=database` (FCM notifications):

**Supervisor example:**

```ini
[program:akbrny-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/akbrny/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/akbrny/storage/logs/worker.log
stopwaitsecs=3600
```

Apply `jobs` and `failed_jobs` migrations before starting the worker. See `docs/PRODUCTION_MIGRATIONS.md`.

---

## Scheduler

No application scheduled tasks are required at this time (`routes/console.php` only defines `performance:baseline` for manual benchmarking).

If scheduled tasks are added later:

```cron
* * * * * cd /var/www/akbrny && php artisan schedule:run >> /dev/null 2>&1
```

**Status:** NOT IMPLEMENTED (no cron required currently)

---

## Production environment

```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
QUEUE_CONNECTION=database
CACHE_STORE=file
SESSION_DRIVER=file
```

Never commit `.env` to Git. Firebase credentials via:

```env
FIREBASE_PROJECT_ID=
FIREBASE_CLIENT_EMAIL=
FIREBASE_PRIVATE_KEY=
```

---

## SSL

HTTPS required for secure cookies and Firebase web push. **REQUIRES SERVER ACCESS** (Let's Encrypt or provider certificate).

---

## Logging

- Application: `storage/logs/laravel.log`
- Queue worker: supervisor log path above
- Rotate logs via `logrotate` — **REQUIRES SERVER ACCESS**

Never expose stack traces to users when `APP_DEBUG=false`.
