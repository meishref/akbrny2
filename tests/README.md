# Contract tests — native MySQL 8 setup

The 17 tests in `Feature/ApiContractTest.php` require **MySQL 8** (or MariaDB 10.6+ for local dev), **PHP 8.4**, and **Laravel 13**. They use `RefreshDatabase` and migrate all tables before each test class.

**Do not run tests against production data.**

---

## Current test credentials (`phpunit.xml`)

| Setting | Value |
|---------|-------|
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | `akbrny_test` |
| `DB_USERNAME` | `homestead` |
| `DB_PASSWORD` | `secret` |

If your local MySQL user differs, update `phpunit.xml` or create `.env.testing` with the same keys.

---

## Step 1 — Install MySQL 8 (Ubuntu 22.04+, no Docker)

If MySQL/MariaDB is not installed, or you need MySQL 8 specifically:

```bash
# Remove MariaDB if it conflicts (optional — only if you want MySQL 8 only)
# sudo apt remove --purge mariadb-server mariadb-client

# Add Oracle MySQL APT repository
wget https://dev.mysql.com/get/mysql-apt-config_0.8.36-1_all.deb
sudo dpkg -i mysql-apt-config_0.8.36-1_all.deb
# Select: MySQL Server 8.0 → OK → OK

sudo apt update
sudo apt install -y mysql-server mysql-client

# Secure installation (set root password, remove anonymous users)
sudo mysql_secure_installation
```

Verify:

```bash
mysql --version
# Expected: mysql  Ver 8.0.x

sudo systemctl enable mysql
sudo systemctl start mysql
sudo systemctl status mysql
```

---

## Step 2 — PHP 8.4 MySQL extension

Required extension: `pdo_mysql`

```bash
php8.4 -m | grep pdo_mysql
# If missing:
sudo apt install php8.4-mysql
sudo systemctl restart php8.4-fpm   # only if FPM is in use
```

---

## Step 3 — Create test database and user

**Option A — automated script (recommended):**

```bash
cd /path/to/akbrny2
sudo bash tests/setup-mysql-test-db.sh
```

**Option B — manual SQL (as MySQL root):**

```bash
sudo mysql
```

```sql
CREATE DATABASE IF NOT EXISTS akbrny_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'homestead'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON akbrny_test.* TO 'homestead'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**MySQL 8 note:** If `CREATE USER IF NOT EXISTS` fails on an older patch, use:

```sql
CREATE USER 'homestead'@'localhost' IDENTIFIED BY 'secret';
-- ignore error if user already exists
GRANT ALL PRIVILEGES ON akbrny_test.* TO 'homestead'@'localhost';
FLUSH PRIVILEGES;
```

---

## Step 4 — Verify connection

```bash
mysql -u homestead -psecret -h 127.0.0.1 -e "SHOW DATABASES LIKE 'akbrny_test';"
```

Expected output:

```
Database (akbrny_test)
akbrny_test
```

---

## Step 5 — Run the 17 contract tests

```bash
cd /path/to/akbrny2
php8.4 artisan config:clear
php8.4 artisan test --filter=ApiContractTest
```

Or run a single test:

```bash
php8.4 artisan test --filter=test_home_page_returns_success
```

---

## Troubleshooting

| Error | Fix |
|-------|-----|
| `Access denied for user 'homestead'@'localhost'` | Run Step 3; confirm password matches `phpunit.xml` |
| `Can't connect to local server through socket` | `sudo systemctl start mysql` |
| `SQLSTATE[HY000] [1049] Unknown database` | Create `akbrny_test` (Step 3) |
| `could not find driver` | Install `php8.4-mysql` (Step 2) |
| `Plugin caching_sha2_password` auth error | `ALTER USER 'homestead'@'localhost' IDENTIFIED WITH mysql_native_password BY 'secret';` |

---

## What the tests cover

17 contract tests in `ApiContractTest.php` — home, static pages, email check (plain text), login (email + username), register, profile, auth dashboard, AJAX reply/search/vote/notification, API stub. See `API_INVENTORY.md` for the full contract.
