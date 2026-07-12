#!/usr/bin/env bash
#
# SBH one-shot VPS deploy — fresh Ubuntu 22.04+ server.
#
#   curl -fsSL https://raw.githubusercontent.com/MarlonBHA81/sbh-app/claude/social-engagement-pwa-5yayus/scripts/deploy-vps.sh | bash
#
# Or clone the repo and run: bash scripts/deploy-vps.sh
#
# What it does: installs Docker + git, clones the repo (if needed), asks for
# your domain + email, generates all secrets, obtains a Let's Encrypt
# certificate, builds and starts the full stack, runs migrations + seeders,
# and creates your admin account.

set -euo pipefail

BRANCH="claude/social-engagement-pwa-5yayus"
REPO="https://github.com/MarlonBHA81/sbh-app"
DIR="/opt/sbh-app"

say() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
die() { printf '\n\033[1;31mERROR: %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "Run as root (or with sudo)."
[ -e /dev/tty ] || die "No terminal available for prompts — run: bash scripts/deploy-vps.sh"

# --- 1. Docker + git -------------------------------------------------------
if ! command -v docker >/dev/null 2>&1; then
  say "Installing Docker + git"
  apt-get update -qq
  apt-get install -y -qq ca-certificates curl git >/dev/null
  curl -fsSL https://get.docker.com | sh
fi
docker compose version >/dev/null 2>&1 || die "docker compose plugin missing — install docker-compose-plugin."

# --- 2. Repo ---------------------------------------------------------------
if [ ! -d "$DIR/.git" ]; then
  say "Cloning $REPO ($BRANCH) into $DIR"
  git clone -b "$BRANCH" "$REPO" "$DIR"
fi
cd "$DIR"

# --- 3. Configuration ------------------------------------------------------
if [ ! -f .env.prod ]; then
  say "Configuration"
  read -rp "Your domain (e.g. app.example.com, must already point to this server): " DOMAIN < /dev/tty
  # Forgive pasted URLs: strip scheme, path/trailing slash, and whitespace.
  DOMAIN="$(printf '%s' "$DOMAIN" | sed -e 's|^[a-zA-Z]*://||' -e 's|/.*$||' | tr -d '[:space:]')"
  [ -n "$DOMAIN" ] || die "Domain is required."
  read -rp "Email for SSL certificate + web push contact: " EMAIL < /dev/tty
  [ -n "$EMAIL" ] || die "Email is required."

  # No early-exiting readers here: under pipefail, `tr | head` dies on
  # SIGPIPE (exit 141) and set -e aborts the whole script silently.
  rand() { head -c 48 /dev/urandom | base64 | tr -dc 'a-zA-Z0-9' | cut -c1-32; }

  DB_PASSWORD="$(rand)"; DB_ROOT_PASSWORD="$(rand)"
  REVERB_KEY="$(rand)"; REVERB_SECRET="$(rand)"

  # Start from pristine templates so re-runs with a corrected domain work.
  git checkout -- docker/nginx/prod.conf 2>/dev/null || true
  cp .env.prod.example .env.prod
  sed -i \
    -e "s|^APP_KEY=.*|APP_KEY=base64:$(head -c 32 /dev/urandom | base64)|" \
    -e "s|example.com|$DOMAIN|g" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" \
    -e "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=$DB_ROOT_PASSWORD|" \
    -e "s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=$REVERB_KEY|" \
    -e "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=$REVERB_SECRET|" \
    -e "s|^MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=$EMAIL|" \
    -e "s|^VAPID_SUBJECT=.*|VAPID_SUBJECT=mailto:$EMAIL|" \
    .env.prod

  sed -i "s|example.com|$DOMAIN|g" docker/nginx/prod.conf
else
  DOMAIN="$(grep '^APP_URL=' .env.prod | sed -e 's|.*https://||' -e 's|/.*$||')"
  EMAIL="$(grep '^MAIL_FROM_ADDRESS=' .env.prod | cut -d= -f2)"
  say "Reusing existing .env.prod (domain: $DOMAIN)"
fi

# --- 4. TLS certificate (standalone first, then the stack takes over) ------
if [ ! -d "/var/lib/docker/volumes/sbh-app_certbot-certs" ] || \
   ! docker run --rm -v sbh-app_certbot-certs:/etc/letsencrypt certbot/certbot certificates 2>/dev/null | grep -q "$DOMAIN"; then
  say "Obtaining Let's Encrypt certificate for $DOMAIN"
  docker run --rm -p 80:80 \
    -v sbh-app_certbot-certs:/etc/letsencrypt \
    -v sbh-app_certbot-www:/var/www/certbot \
    certbot/certbot certonly --standalone --non-interactive --agree-tos \
    -m "$EMAIL" -d "$DOMAIN" \
    || die "Certificate failed — is $DOMAIN pointing at this server and port 80 free?"
fi

# --- 5. Build + start ------------------------------------------------------
say "Building and starting the stack (first build takes several minutes)"
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build

say "Waiting for the database"
sleep 15

# --- 6. App bootstrap ------------------------------------------------------
say "Running migrations + seeders"
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T api php artisan migrate --force --seed

say "Generating web push (VAPID) keys"
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T api php artisan webpush:vapid --show 2>/dev/null || true
echo "  ^ Copy VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY into .env.prod, set"
echo "    NEXT_PUBLIC_VAPID_PUBLIC_KEY as a web build arg, then re-run this script (idempotent)."

say "Create your admin account (Filament panel at https://$DOMAIN/admin)"
docker compose --env-file .env.prod -f docker-compose.prod.yml exec api php artisan make:admin < /dev/tty || true

say "Done! Your app is live at: https://$DOMAIN"
echo "  API health:  https://$DOMAIN/api/v1/status"
echo "  Admin panel: https://$DOMAIN/admin"
echo "  Logs:        docker compose --env-file .env.prod -f docker-compose.prod.yml logs -f"
