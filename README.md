# SBH — Social Engagement PWA for Small Business Owners

A fast, low-data, installable social platform. Laravel 12 API + Next.js 16 PWA, real-time via Laravel Reverb.

## Features

- **Rich composer** — 15 post types (text, photos, link, typewriter, magnifier, secret, check-in, video, audio, blog, poll, quiz, event, job, portfolio) with scheduling, drafts, visibility control and AI-assisted topic suggestions.
- **Feeds** — For You / Following / Nearby timelines, pull-to-refresh, cursor pagination, inline sponsored units and offline-readable cached feeds.
- **Profiles & social graph** — personal + business profiles, multi-profile switching, follows/requests for private accounts, verified badges.
- **Messaging** — 1:1 and group DMs, typing indicators, read receipts, reactions, reply threading and photo attachments over Reverb.
- **Notifications** — in-app + web push (VAPID), live toasts, follow/mention/reaction activity.
- **Gamification** — XP, ranks and a leaderboard.
- **Business tools** — business directory & categories, Ad Center (promote posts), Insights analytics dashboards.
- **Discovery** — topic tree, search, and a nearby map (approximate location only).
- **Safety & moderation** — report flow, block/mute, sensitive-content controls, Filament admin moderation.
- **Internationalization** — English, বাংলা, العربية, Español, Français with full RTL support (see below).
- **PWA** — installable, Serwist service worker, offline fallback page and runtime caching, web push.

## Internationalization & RTL

The web app uses **next-intl** in "without i18n routing" mode: the active locale
lives in a `NEXT_LOCALE` cookie (default `en`) read server-side in the root
layout, which also sets `<html lang dir>` (`ar` → `rtl`). Message catalogs are
in `apps/web/src/messages/{en,bn,ar,es,fr}.json`. Change languages from
**Settings → Language**; the picker updates the cookie, PATCHes `/me { locale }`
(so API error messages localize via `Accept-Language`) and refreshes. Layout
components use logical CSS properties (`ms-/me-`, `ps-/pe-`, `start-/end-`) so
they mirror correctly under RTL.

## Stack

| Layer | Tech |
|---|---|
| Backend API | Laravel 12 (PHP 8.2+), Sanctum, Socialite, Scout |
| Frontend | Next.js 16 (React 19, TypeScript), Tailwind CSS 4, shadcn/ui, Zustand |
| Real-time | Laravel Reverb (WebSockets) + Laravel Echo |
| Data | MySQL 8, Redis 7 |
| Media | Chunked uploads, FFmpeg compression, WebP conversion |
| Maps | Pigeon Maps (OpenStreetMap) + Nominatim reverse geocoding |
| Admin | Filament 4 at `/admin` |
| PWA | Serwist service worker, web push (VAPID), offline shell |

## Monorepo layout

```
apps/api   Laravel 12 backend (API, Reverb, queue, scheduler, Filament admin)
apps/web   Next.js 16 PWA
docker/    nginx reverse-proxy config
```

## Development

Requirements: Docker + Docker Compose (or PHP 8.2+/Node 22/pnpm locally).

```bash
docker compose up -d          # mysql, redis, api :8000, web :3000, reverb :8080, mailpit :8025
cd apps/api && php artisan migrate --seed
```

Without Docker: `pnpm install && pnpm dev:web` and `cd apps/api && composer install && php artisan serve` (SQLite works out of the box for a quick start).

Tests: `cd apps/api && php artisan test` (Pest).

## First run

After the containers are up (or a local install), bootstrap the app:

```bash
cd apps/api
php artisan migrate --seed            # schema + demo topics/categories/seed data
php artisan make:admin                # create the first admin (Filament /admin) login
php artisan webpush:vapid             # generate VAPID keys for web push, then copy
                                      # the printed keys into apps/api/.env and
                                      # NEXT_PUBLIC_VAPID_PUBLIC_KEY in apps/web/.env
```

Then open the web app at http://localhost:3000 and register your first account.

## Configuration

Backend (`apps/api/.env`):

| Variable | Purpose |
|---|---|
| `APP_URL` | Public API base URL |
| `FRONTEND_URL` | Web app origin (CORS, Sanctum stateful domains, links in emails) |
| `DB_*` / `REDIS_*` | MySQL + Redis connection |
| `FILESYSTEM_DISK` | `local` for dev, `s3` / R2 for prod media |
| `AI_DRIVER` | AI provider for topic suggestions (`null`/unset disables the feature and the composer hides the affordance) |
| `AI_API_KEY` | API key for the configured `AI_DRIVER` |
| `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` / `VAPID_SUBJECT` | Web push keys (from `php artisan webpush:vapid`) |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | Laravel Reverb credentials |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Socialite Google login |
| `FACEBOOK_CLIENT_ID` / `FACEBOOK_CLIENT_SECRET` | Socialite Facebook login |
| `TWITTER_CLIENT_ID` / `TWITTER_CLIENT_SECRET` | Socialite X/Twitter login |

Frontend (`apps/web/.env`):

| Variable | Purpose |
|---|---|
| `NEXT_PUBLIC_API_URL` | Laravel API base URL (browser-facing, no trailing slash) |
| `INTERNAL_API_URL` | Optional server-side API base for SEO fetches over the docker network (falls back to `NEXT_PUBLIC_API_URL`) |
| `NEXT_PUBLIC_APP_URL` | Canonical web origin — powers `metadataBase`, `robots.txt` and `sitemap.xml` |
| `NEXT_PUBLIC_VAPID_PUBLIC_KEY` | Public VAPID key for push subscriptions |
| `NEXT_PUBLIC_ENABLE_X` | Show the "Continue with X" social login button |
| `NEXT_PUBLIC_REVERB_APP_KEY` | Reverb key (leave blank to disable realtime — the app degrades gracefully) |
| `NEXT_PUBLIC_REVERB_HOST` / `NEXT_PUBLIC_REVERB_PORT` / `NEXT_PUBLIC_REVERB_SCHEME` | Reverb WebSocket endpoint (`https` scheme enables TLS) |

## Production (VPS, Ubuntu 22.04, aaPanel-friendly)

Minimum: 2 vCPU, 4 GB RAM, 40 GB SSD. Ports 80, 443, 8080.

```bash
cp .env.prod.example .env.prod   # fill in secrets
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec api php artisan migrate --force
```

TLS: edit `docker/nginx/prod.conf` with your domain, then issue certs with the bundled certbot service (command in the file's header comment).

One `api` image runs php-fpm, 2 queue workers, the scheduler, and Reverb under supervisord. Media persists in the `media-storage` volume; switch to S3/R2 with `FILESYSTEM_DISK` in `.env.prod`.
