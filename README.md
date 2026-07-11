# SBH — Social Engagement PWA for Small Business Owners

A fast, low-data, installable social platform. Laravel 12 API + Next.js 16 PWA, real-time via Laravel Reverb.

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

## Production (VPS, Ubuntu 22.04, aaPanel-friendly)

Minimum: 2 vCPU, 4 GB RAM, 40 GB SSD. Ports 80, 443, 8080.

```bash
cp .env.prod.example .env.prod   # fill in secrets
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec api php artisan migrate --force
```

TLS: edit `docker/nginx/prod.conf` with your domain, then issue certs with the bundled certbot service (command in the file's header comment).

One `api` image runs php-fpm, 2 queue workers, the scheduler, and Reverb under supervisord. Media persists in the `media-storage` volume; switch to S3/R2 with `FILESYSTEM_DISK` in `.env.prod`.
