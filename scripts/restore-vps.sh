#!/usr/bin/env bash
#
# SBH Community — restore a backup produced by scripts/backup-vps.sh.
#
#   ./scripts/restore-vps.sh --db backups/sbh-db-2026-08-04T03-00-00.sql.gz
#   ./scripts/restore-vps.sh --db <file> --media backups/sbh-media-<stamp>.tar.gz
#   ./scripts/restore-vps.sh --db <file> --yes        # skip the confirmation prompt
#
# THIS IS DESTRUCTIVE. It drops and recreates the application database, and (with
# --media) replaces the contents of the media volume. Read the plan it prints
# before confirming.
#
# Test this on a staging box BEFORE you need it in an incident. An untested
# restore path is not a restore path.

set -euo pipefail

DB_FILE=""
MEDIA_FILE=""
ASSUME_YES=0

while [ $# -gt 0 ]; do
  case "$1" in
    --db) DB_FILE="${2:?--db needs a file}"; shift ;;
    --media) MEDIA_FILE="${2:?--media needs a file}"; shift ;;
    --yes) ASSUME_YES=1 ;;
    -h|--help) sed -n '2,15p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unknown option: $1" >&2; exit 1 ;;
  esac
  shift
done

cd "$(dirname "$0")/.."

[ -n "$DB_FILE" ] || { echo "ERROR: --db is required." >&2; exit 1; }
[ -f "$DB_FILE" ] || { echo "ERROR: no such file: $DB_FILE" >&2; exit 1; }
if [ -n "$MEDIA_FILE" ] && [ ! -f "$MEDIA_FILE" ]; then
  echo "ERROR: no such file: $MEDIA_FILE" >&2; exit 1
fi

if [ ! -f .env.prod ]; then
  echo "ERROR: .env.prod not found." >&2
  exit 1
fi

env_val() { grep -m1 "^$1=" .env.prod | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

DB_NAME="$(env_val DB_DATABASE)"; DB_NAME="${DB_NAME:-sbh}"
DB_USER="$(env_val DB_USERNAME)"; DB_USER="${DB_USER:-sbh}"
DB_ROOT_PASS="$(env_val DB_ROOT_PASSWORD)"

[ -n "$DB_ROOT_PASS" ] || { echo "ERROR: DB_ROOT_PASSWORD is empty in .env.prod." >&2; exit 1; }

# Verify the dump is intact before we destroy anything.
gzip -dc "$DB_FILE" | tail -c 200 | grep -q "Dump completed" || {
  echo "ERROR: $DB_FILE does not look like a complete mysqldump. Refusing to restore." >&2
  exit 1
}

COMPOSE=(docker compose --env-file .env.prod -f docker-compose.prod.yml)

echo "Restore plan:"
echo "  database   : DROP + CREATE '$DB_NAME', then load $DB_FILE"
[ -n "$MEDIA_FILE" ] && echo "  media      : replace /app/storage/app contents from $MEDIA_FILE"
echo "  downtime   : the api container is stopped during the restore"
echo

if [ "$ASSUME_YES" != "1" ]; then
  printf 'Type RESTORE to proceed: '
  read -r reply
  [ "$reply" = "RESTORE" ] || { echo "Aborted."; exit 1; }
fi

echo "==> Stopping application containers (keeping mysql up)"
"${COMPOSE[@]}" stop api web >/dev/null

echo "==> Recreating database '$DB_NAME'"
"${COMPOSE[@]}" exec -T -e MYSQL_PWD="$DB_ROOT_PASS" mysql \
  mysql -uroot -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON \`$DB_NAME\`.* TO '$DB_USER'@'%'; FLUSH PRIVILEGES;"

echo "==> Loading dump"
gzip -dc "$DB_FILE" | "${COMPOSE[@]}" exec -T -e MYSQL_PWD="$DB_ROOT_PASS" mysql \
  mysql -uroot --default-character-set=utf8mb4 "$DB_NAME"

echo "==> Starting api"
"${COMPOSE[@]}" up -d api >/dev/null

if [ -n "$MEDIA_FILE" ]; then
  echo "==> Restoring media"
  # Clear then extract, so files deleted since the backup don't linger.
  "${COMPOSE[@]}" exec -T api sh -c 'rm -rf /app/storage/app/* /app/storage/app/.[!.]* 2>/dev/null || true'
  "${COMPOSE[@]}" exec -T api tar xzf - -C /app/storage/app < "$MEDIA_FILE"
  "${COMPOSE[@]}" exec -T api chown -R www-data:www-data /app/storage/app
fi

echo "==> Running migrations (in case the dump predates current code)"
"${COMPOSE[@]}" exec -T api php artisan migrate --force

echo "==> Refreshing caches and starting web"
"${COMPOSE[@]}" exec -T api php artisan config:cache >/dev/null
"${COMPOSE[@]}" exec -T api php artisan storage:link >/dev/null 2>&1 || true
"${COMPOSE[@]}" up -d web >/dev/null

echo
echo "Restore complete. Verify before declaring the incident over:"
echo "  - log in as a known user"
echo "  - open a recent order and confirm its status"
echo "  - load a profile with an avatar (checks the media volume)"
