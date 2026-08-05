#!/usr/bin/env bash
#
# SBH Community — production backup (database + uploaded media).
#
# Run from the app directory on the server:
#   ./scripts/backup-vps.sh                 # write a timestamped backup, prune old ones
#   ./scripts/backup-vps.sh --db-only       # skip the (large) media archive
#   ./scripts/backup-vps.sh --keep 30       # override retention (default 7)
#   ./scripts/backup-vps.sh --out /mnt/vol  # write somewhere other than ./backups
#
# Restore with scripts/restore-vps.sh — see that script before you need it.
#
# Cron it. Once a day is the minimum for a system that takes payments:
#   0 3 * * * cd /opt/sbh-app && ./scripts/backup-vps.sh >> /var/log/sbh-backup.log 2>&1
#
# IMPORTANT: this writes to the same host by default, which protects against a bad
# migration or an accidental `demo:seed --fresh` — NOT against losing the VPS. Copy
# the backups/ directory off-box (rsync/rclone/S3) for real disaster recovery.

set -euo pipefail

KEEP=7
DB_ONLY=0
OUT_DIR=""

while [ $# -gt 0 ]; do
  case "$1" in
    --db-only) DB_ONLY=1 ;;
    --keep) KEEP="${2:?--keep needs a value}"; shift ;;
    --out) OUT_DIR="${2:?--out needs a value}"; shift ;;
    -h|--help) sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unknown option: $1" >&2; exit 1 ;;
  esac
  shift
done

cd "$(dirname "$0")/.."

if [ ! -f .env.prod ]; then
  echo "ERROR: .env.prod not found — run scripts/deploy-vps.sh first." >&2
  exit 1
fi

# Read the DB credentials without sourcing the file (it contains values with
# characters that would break `source`, and we don't want the rest in our env).
env_val() { grep -m1 "^$1=" .env.prod | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

DB_NAME="$(env_val DB_DATABASE)"; DB_NAME="${DB_NAME:-sbh}"
DB_ROOT_PASS="$(env_val DB_ROOT_PASSWORD)"

if [ -z "$DB_ROOT_PASS" ]; then
  echo "ERROR: DB_ROOT_PASSWORD is empty in .env.prod — cannot dump the database." >&2
  exit 1
fi

OUT_DIR="${OUT_DIR:-./backups}"
mkdir -p "$OUT_DIR"

STAMP="$(date +%Y-%m-%dT%H-%M-%S)"
DB_FILE="$OUT_DIR/sbh-db-$STAMP.sql.gz"
MEDIA_FILE="$OUT_DIR/sbh-media-$STAMP.tar.gz"

COMPOSE=(docker compose --env-file .env.prod -f docker-compose.prod.yml)

echo "==> Backing up database '$DB_NAME'"
# --single-transaction gives a consistent InnoDB snapshot without locking writes.
# --routines/--triggers/--events so a restore is genuinely complete.
# MYSQL_PWD avoids the password appearing in the container's process list.
"${COMPOSE[@]}" exec -T -e MYSQL_PWD="$DB_ROOT_PASS" mysql \
  mysqldump -uroot --single-transaction --quick --routines --triggers --events \
            --default-character-set=utf8mb4 "$DB_NAME" \
  | gzip -9 > "$DB_FILE"

# A dump that failed part-way still leaves a small, valid gzip. Check the
# uncompressed tail for mysqldump's own completion marker before trusting it —
# and before pruning any older (good) backup.
if ! gzip -dc "$DB_FILE" | tail -c 200 | grep -q "Dump completed"; then
  echo "ERROR: database dump is incomplete — mysqldump completion marker missing." >&2
  echo "       Kept the partial file for inspection: $DB_FILE" >&2
  echo "       NOT pruning older backups." >&2
  exit 1
fi

DB_SIZE="$(du -h "$DB_FILE" | cut -f1)"
echo "    $DB_FILE ($DB_SIZE)"

if [ "$DB_ONLY" = "0" ]; then
  echo "==> Backing up uploaded media"
  # The api container mounts the media-storage volume at /app/storage/app.
  "${COMPOSE[@]}" exec -T api tar czf - -C /app/storage/app . > "$MEDIA_FILE"

  if ! tar tzf "$MEDIA_FILE" >/dev/null 2>&1; then
    echo "ERROR: media archive is corrupt. Kept for inspection: $MEDIA_FILE" >&2
    echo "       NOT pruning older backups." >&2
    exit 1
  fi

  MEDIA_SIZE="$(du -h "$MEDIA_FILE" | cut -f1)"
  echo "    $MEDIA_FILE ($MEDIA_SIZE)"
fi

# Only prune once we know this run produced a verified-good backup.
echo "==> Pruning backups older than the newest $KEEP"
prune() {
  local pattern="$1"
  # shellcheck disable=SC2012  # filenames are timestamped and shell-safe by construction
  ls -1t "$OUT_DIR"/$pattern 2>/dev/null | tail -n +"$((KEEP + 1))" | while read -r old; do
    echo "    removing $old"
    rm -f -- "$old"
  done
}
prune 'sbh-db-*.sql.gz'
[ "$DB_ONLY" = "0" ] && prune 'sbh-media-*.tar.gz'

echo
echo "Backup complete. Restore with:"
echo "  ./scripts/restore-vps.sh --db $DB_FILE${DB_ONLY:+}"
[ "$DB_ONLY" = "0" ] && echo "  ./scripts/restore-vps.sh --db $DB_FILE --media $MEDIA_FILE"
echo
echo "Reminder: copy $OUT_DIR off this host — an on-box backup does not survive losing the box."
