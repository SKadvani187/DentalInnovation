#!/bin/sh
# Wait for the database to accept connections, apply schema migrations, then
# hand off to the Apache foreground process (the image's default CMD).
set -e

DB_HOST="${DB_HOST:-db}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-dentinno_crm}"

echo "[entrypoint] waiting for database ${DB_HOST}/${DB_NAME} ..."
i=0
until php -r '
  try { new PDO("mysql:host=".getenv("DB_HOST").";dbname=".getenv("DB_NAME"),
                getenv("DB_USER"), getenv("DB_PASS")); exit(0); }
  catch (Throwable $e) { exit(1); }' 2>/dev/null; do
  i=$((i+1))
  if [ "$i" -ge 60 ]; then
    echo "[entrypoint] database not reachable after 120s — giving up." >&2
    exit 1
  fi
  sleep 2
done
echo "[entrypoint] database is up."

# database.sql (core schema) is imported by the db container on first init.
# migrate.php applies the incremental database_*.sql files; it is idempotent
# (tracked in schema_migrations), so it is safe to run on every start.
echo "[entrypoint] applying migrations ..."
if ! php /var/www/html/dentinno/migrate.php; then
  echo "[entrypoint] WARNING: migrations reported an error — check logs." >&2
fi

exec "$@"
