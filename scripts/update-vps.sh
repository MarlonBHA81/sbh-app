#!/usr/bin/env bash
#
# SBH Community — one-command production update.
#
# Run from the app directory on the server (the folder you cloned into):
#   ./scripts/update-vps.sh              # pull latest + rebuild + migrate
#   ./scripts/update-vps.sh --reseed     # ...and reseed demo content (wipes data!)
#   ./scripts/update-vps.sh --branch main
#
# It re-applies the domain to the nginx template automatically (read from
# APP_URL in .env.prod), so you never need the checkout/sed dance by hand.

set -euo pipefail

BRANCH="claude/social-engagement-pwa-5yayus"
RESEED=0

while [ $# -gt 0 ]; do
  case "$1" in
    --reseed) RESEED=1 ;;
    --branch) BRANCH="${2:?--branch needs a value}"; shift ;;
    *) echo "Unknown option: $1" >&2; exit 1 ;;
  esac
  shift
done

cd "$(dirname "$0")/.."

if [ ! -f .env.prod ]; then
  echo "ERROR: .env.prod not found — run scripts/deploy-vps.sh first." >&2
  exit 1
fi

DOMAIN="$(grep '^APP_URL=' .env.prod | sed -e 's|.*https\?://||' -e 's|/.*$||' -e 's|"||g')"
if [ -z "$DOMAIN" ]; then
  echo "ERROR: could not read the domain from APP_URL in .env.prod." >&2
  exit 1
fi

COMPOSE=(docker compose --env-file .env.prod -f docker-compose.prod.yml)

echo "==> Updating to origin/$BRANCH (domain: $DOMAIN)"

# The nginx config is templated with the real domain on the server; restore
# the pristine copy so the pull never conflicts, then re-template it.
git checkout -- docker/nginx/prod.conf
git fetch origin "$BRANCH"
git checkout "$BRANCH" 2>/dev/null || git checkout -b "$BRANCH" "origin/$BRANCH"
git pull origin "$BRANCH"
sed -i "s/example.com/$DOMAIN/g" docker/nginx/prod.conf

echo "==> Rebuilding and restarting containers"
"${COMPOSE[@]}" up -d --build --remove-orphans

# nginx's config is a bind mount — compose won't recreate the container
# when only the file's contents change, so always reload it explicitly.
echo "==> Restarting nginx to apply config"
"${COMPOSE[@]}" restart nginx

# Safety net: a schema change with no restore point is unrecoverable. Some
# migrations are not cleanly reversible (e.g. the encrypted-column widening —
# its down() would truncate ciphertext), so `migrate:rollback` is NOT a backup.
echo "==> Taking a pre-migration database backup"
./scripts/backup-vps.sh --db-only

echo "==> Running database migrations"
"${COMPOSE[@]}" exec -T api php artisan migrate --force

echo "==> Refreshing Laravel caches"
"${COMPOSE[@]}" exec -T api php artisan config:cache >/dev/null
"${COMPOSE[@]}" exec -T api php artisan storage:link >/dev/null 2>&1 || true

if [ "$RESEED" = "1" ]; then
  # --reseed drops and rebuilds the schema. Back up media too: the reseed can
  # orphan or replace uploaded files, and the pre-migration backup above was
  # database-only.
  echo "==> Taking a full backup before the destructive reseed"
  ./scripts/backup-vps.sh

  echo "==> Reseeding demo content (this wipes existing content)"
  "${COMPOSE[@]}" exec -T api php artisan demo:seed --fresh
fi

echo "==> Container status"
"${COMPOSE[@]}" ps

echo
echo "Update complete: https://$DOMAIN"
echo "(Browsers may need a hard refresh — Ctrl/Cmd+Shift+R — to pick up new assets.)"
