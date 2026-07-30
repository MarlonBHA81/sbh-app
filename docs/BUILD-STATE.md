# SBH — Build State & Handoff

Dense reference to continue building **SBH (Small Business Helpdesk)** — a social-engagement PWA for African small-business owners. Read this first; it replaces reading the whole history.

## Stack & layout
- Monorepo. **`apps/api`** = Laravel 12 (PHP 8.3, Pest, Sanctum cookie+bearer, Filament v4 admin at `/admin`, Reverb realtime, spatie/permission installed but inert). **`apps/web`** = Next.js 16 App Router, React 19, TS, Tailwind 4 (`@theme`), shadcn/ui, Zustand (per-request store via providers), next-intl (en/es/fr/bn/ar[RTL]), Serwist PWA, recharts, hls.js, react-easy-crop.
- Realtime: Reverb (`BROADCAST_CONNECTION=reverb`); web client `apps/web/src/lib/echo.ts` (`useEchoPresence`/`useEchoPrivate...`), event keys prefixed `.`.

## Conventions (do these)
- **Branch:** `claude/social-engagement-pwa-5yayus`. Push `git push -u origin <branch>` with 2/4/8/16s retry.
- **Every commit trailer:**
  `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`
  `Claude-Session: https://claude.ai/code/session_01B96DGrz14rCNu2YotmfSbQ`
  Never put a model identifier in committed artifacts. No PR unless asked.
- **Model id** when asked: `claude-opus-4-8` (chat only).
- **CI env quirk:** `env('X=null')` parses to PHP `null`. Run the full suite locally with `PAYMENTS_DRIVER=null STREAM_DRIVER=null php artisan test`. When a Filament required-Select maps to a config that may be null, coalesce `?: 'null'` in the page `mount()`.
- **Agent-proxy blocks outbound to external hosts** → all external-API tests use `Http::fake()`.
- **Driver pattern** (AiGateway/PaymentGateway/StreamProvider): bound NOT singleton in `AppServiceProvider::register()` so config resolves at resolution time; super-admin **Integrations** Filament page (`app/Filament/Pages/Integrations.php`) writes `settings` rows that `IntegrationSettingsProvider` layers over config at boot. To add an integration: config file + contract + Null/Real driver + AppServiceProvider bind + Integrations page section (mount fill / form / save) + `IntegrationSettingsProvider::applyXSettings()` + `.env.example`.
- **Pest gotchas:** global helper fn names must be unique across files. `withHeader('X-Profile-Id', $p->ulid)` persists on `$this` across requests → call `$this->flushHeaders()` before switching users. Active profile = `$request->attributes->get('activeProfile')` (defaults to `personalProfile` when no header). Broadcast events dispatched via `broadcast(new E)` are caught by `Event::fake([E::class])` + `Event::assertDispatched`.
- **Formatting:** `./vendor/bin/pint <files>` (API) always before commit; web `pnpm tsc --noEmit && pnpm lint && pnpm build`.
- **Lint rule** `react-hooks/set-state-in-effect`: don't `setState` synchronously in an effect body; inline the fetch with a `cancelled` flag and set state in `.then`; rely on `key` for reset-on-prop-change.

## Domain model essentials
- **Profile** = identity; `kind` personal|business; a User has many; business Profile = the vendor/team owner (`ProfileMember`, `canManageMembers($user)`, `isBusiness()`). `avatarUrl()`/`coverUrl()` = `Storage::disk('public')->url(path)` + `?v=updated_at` cachebust.
- **Shop:** Store (1 per business profile, branded) → Product (`digital_download`|`course`|`service`; `visible()` scope; `offers()` cross_sell/bump/upsell; `isFree()`, `isHtmlTool()`, `isCourse()`). Order/OrderItem/Purchase (Purchase = entitlement, `ownedBy($profile,$product)`). `Order::markPaid()` idempotent, splits platform fee, grants Purchases.
- **Courses (P3):** CourseModule→CourseLesson (+`course_lesson_progress`). Gated by Purchase or store owner; `is_preview` lessons open. Free enrol grants Purchase w/o payment.
- **Masterclass = the "room":** brandable + sponsorable (P4: brand/accent colour, logo/banner, `is_sponsored`+sponsor_name/url/blurb; ad_events `masterclass_id`, `trackSponsoredRoom`). Live (ask#4): `masterclass_live_sessions` (RTMP stream_key/ingest host-only, HLS playback_url, recording_playback_url, status idle|active|ended). Chat: `conversation_id` → group Conversation (`ensureRoomConversation`), enrol joins via `MessagingService::ensureMember`. `LiveReaction` ephemeral broadcast.
- **Analytics:** `post_stats_daily` (existing) + P4 `store_stats_daily`/`product_stats_daily` (`ShopStatsService::bump*`, `POST /shop/seen`, `StoreAnalyticsService`).
- **CRM webhooks:** WebhookEndpoint (generic HMAC `X-SBH-Signature` or Brevo), `WebhookDispatcher` (CONTACT_CREATED/UPDATED, PURCHASE_COMPLETED) → queued `DeliverWebhook`.

## What's shipped (all green: API 845 tests, web build)
Original 12 milestones + 6 UX-pattern commits; V2 (Daily Brief) + super-admin AI model control; admin-roles (Super/Admin/Facilitator/Manager + multi-user business profiles); live tenders (OpenProcurement); V3 (deep Coach, personalised Home, masterclasses/cohorts). **4 marketplace asks:** (1) Shop P1–P4 done. (2) native iOS/Play — **NOT started**. (3) sponsored/branded rooms = masterclasses, done. (4) RTMP live masterclasses + chat + Zoom reactions + recordings, done.

## External integration config (all via Integrations page or env; drivers default to disabled)
- **Payments/PayFast** (`config/payments.php`): `PAYMENTS_DRIVER=payfast`, merchant_id/key/passphrase, sandbox, `PLATFORM_FEE_PERCENT`, `FRONTEND_URL`. ITN webhook `POST /api/v1/shop/payfast/itn` (authoritative). Sandbox creds 10000100 / 46f0cd694581a.
- **Streaming/Mux** (`config/streaming.php`): `STREAM_DRIVER=mux`, MUX_TOKEN_ID/SECRET/WEBHOOK_SECRET. Webhook `POST /api/v1/streaming/webhook` flips live status + stores recording. Host: OBS → RTMP server+key from "Go live". Player = `HlsPlayer` (native HLS / hls.js).
- **Reverb** must run for chat/live-reactions.

## Deployment (VPS, Docker)
- Repo on server: **`/opt/sbh-app`**. Fresh install: `curl -fsSL .../scripts/deploy-vps.sh | bash`. Update: `cd /opt/sbh-app && bash scripts/update-vps.sh` (pulls branch, rebuilds, restarts nginx, migrates, config:cache, storage:link).
- Stack `docker-compose.prod.yml`: mysql, redis, api (php-fpm 9000 + reverb 8080, WORKDIR `/app`, runs supervisord), web (Next, `NEXT_PUBLIC_API_URL=${APP_URL}` baked at build), nginx (443 + 8080 wss), certbot. `.env.prod` holds secrets.
- **Media serving:** nginx `location ^~ /storage/ { alias /app/storage/app/public/; }` from the shared `media-storage` volume (mounted `media-storage:/app/storage/app` in both api & nginx). NOT via the `public/storage` symlink (that's dev only; gitignored → dev container links it on boot). PHP runs as **www-data**; the volume is root-owned, so the prod Dockerfile CMD now `chown -R www-data:www-data storage bootstrap/cache` on startup.
- Host has NO php/git — run everything via `docker compose --env-file .env.prod -f docker-compose.prod.yml exec api php artisan ...`.

## OPEN ISSUE — profile avatar not loading on prod (in progress)
Symptom: uploaded avatar never displays (initials shown). Confirmed via server diagnostics: only demo-seed PNGs exist in `storage/app/public/media/avatars/` (root-owned); DB newest avatar = a seed png; user's upload not written. **Root cause = media volume root-owned, www-data can't write → `Storage::disk('public')->put()` fails silently (`'throw'=>false`) while DB path updates → 404.** Fixes committed: `8ee159b` (startup chown), `1d83347` (unique upload filename `media/avatars/{ulid}-{rand}.webp` so replace busts cache), `9f2dfa1` (dev storage:link). **Immediate unblock:** `... exec -T api chown -R www-data:www-data storage/app` then re-upload. **Still unconfirmed as of handoff** — user reported "still not working" after; next step = have them upload then run the targeted diagnostic (newest-file-by-mtime + connect_admin `avatar_path`/exists + `storage/logs/laravel.log` grep for `permission denied|UnableToWrite|WebP`), or get the exact broken image Request URL+status from DevTools. If write now succeeds but browser 404s → serving/URL mismatch; if write still fails → chown didn't take / another disk-write error.
- Note code smell to consider: `MediaService::replaceProfileImage` updates the DB `avatar_path` even when `Storage::put` returns false — consider making avatar writes check the return / use a throwing disk so failures surface instead of yielding broken images.

## Admin ops
- **Bulk-upload opportunities via CSV:** Admin → Opportunities → *Import CSV* (Filament `ImportAction` + `App\Filament\Imports\OpportunityImporter`, upserts on `(source, source_ref)`, queued). Column reference + example: `docs/opportunities-csv-upload.md`.

## Remaining / next
- **Ask #2 native iOS + Google Play**: Capacitor wrap of the PWA + bearer-token auth path + native push; needs Apple/Google dev accounts. Not started.
- Optional: post-purchase upsell carousel (P2 left it out); Filament course relation-manager (P3 skipped); QA acceptance checklist + Sentry error monitoring (recommended before calling it production-ready).
- "Production ready" = deployed + each external service verified with one real transaction + a human QA pass. Automated bar (tests/CI) already met.
