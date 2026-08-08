#!/usr/bin/env bash
# Native MySQL 8 test database setup for akbrny contract tests.
# Run once with sudo from the project root:
#   sudo bash tests/setup-mysql-test-db.sh
#
# Matches credentials in phpunit.xml:
#   DB_DATABASE=akbrny_test
#   DB_USERNAME=homestead
#   DB_PASSWORD=secret

set -euo pipefail

DB_NAME="${DB_NAME:-akbrny_test}"
DB_USER="${DB_USER:-homestead}"
DB_PASS="${DB_PASS:-secret}"
DB_HOST="${DB_HOST:-localhost}"

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "Run with sudo: sudo bash tests/setup-mysql-test-db.sh" >&2
  exit 1
fi

if ! command -v mysql >/dev/null 2>&1; then
  echo "mysql client not found. Install MySQL 8 server first (see tests/README.md)." >&2
  exit 1
fi

MYSQL_VERSION="$(mysql --version)"
echo "Using: ${MYSQL_VERSION}"

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${DB_HOST}';
FLUSH PRIVILEGES;
SQL

echo ""
echo "Test database ready."
echo "  Database : ${DB_NAME}"
echo "  User     : ${DB_USER}"
echo "  Password : ${DB_PASS}"
echo "  Host     : 127.0.0.1"
echo ""
echo "Verify:"
echo "  mysql -u ${DB_USER} -p${DB_PASS} -h 127.0.0.1 -e \"SHOW DATABASES LIKE '${DB_NAME}';\""
echo ""
echo "Run tests:"
echo "  cd $(dirname "$(dirname "$(realpath "$0")")") && php8.4 artisan test"
