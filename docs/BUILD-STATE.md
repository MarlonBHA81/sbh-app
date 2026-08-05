# SBH — Build State & Handoff

Dense reference to continue building **SBH (Small Business Helpdesk)** — a social-engagement PWA for African small-business owners. Read this first; it replaces reading the whole history.

_Last updated at commit `6718601` (2026-08-03)._

## Stack & layout
- Monorepo. **`apps/api`** = Laravel 12 (PHP 8.3, Pest, Sanctum cookie+bearer, Filament v4 admin at `/admin`, Reverb realtime, spatie/permission installed but inert). **`apps/web`** = Next.js 16 App Router, React 19, TS, Tailwind 4 (`@theme`), shadcn/ui, Zustand (per-request store via providers), next-intl (en/es/fr/bn/ar[RTL]), Serwist PWA, recharts, hls.js, react-easy-crop.
- Realtime: Reverb (`BROADCAST_CONNECTION=reverb`); web client `apps/web/src/lib/echo.ts` (`useEchoPresence`/`useEchoPrivate...`), event keys prefixed `.`.
- Error tracking: Sentry on both apps (error-only, no session replay), off until a DSN is set. See `apps/api/docs/observability.md`.

## Conventions (do these)
- **Branch:** `claude/social-engagement-pwa-5yayus`. Push `git push -u origin <branch>` with 2/4/8/16s retry.
- **Every commit trailer:**
  `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`
  `Claude-Session: https://claude.ai/code/session_01B96DGrz14rCNu2YotmfSbQ`
  Never put a model identifier in committed artifacts. No PR unless asked.
- **Model id** when asked: `claude-opus-4-8` (chat only).
- **CI env quirk:** `env('X=null')` parses to PHP `null`. Run the full suite locally with `PAYMENTS_DRIVER=null STREAM_DRIVER=null php artisan test`. When a Filament required-Select maps to a config that may be null, coalesce `?: 'null'` in the page `mount()`.
- **Agent-proxy blocks outbound to external hosts** → all external-API tests use `Http::fake()`.
- **Driver pattern** (AiGateway/PaymentGateway/StreamProvider/IssueTracker): bound NOT singleton in `AppServiceProvider::register()` so config resolves at resolution time; super-admin **Integrations** Filament page (`app/Filament/Pages/Integrations.php`) writes `settings` rows that `IntegrationSettingsProvider` layers over config at boot. To add an integration: config file + contract + Null/Real driver + AppServiceProvider bind + Integrations page section (mount fill / form / save) + `IntegrationSettingsProvider::applyXSettings()` + `.env.example`.
- **Config that must survive `config:cache`:** use a `[class, method]` callable, never a closure (see `config/sentry.php` `before_send`).
- **Feature flags:** to add one — entry in `config/features.php`, gate the server with `Features::enabled('<key>')` or the `feature:<key>` route middleware, gate the client with `useFeature('<key>')`.
- **Design system:** `apps/web/design.md` is the locked spec; `src/app/design-tokens.css` + `globals.css` are the value source of truth. Read design.md before any screen refresh — do not redefine colours in components.
- **User-supplied URLs** never go straight into an `href`. Use `<ExternalLink>` / `safeExternalHref` (`src/lib/links.ts`) — scheme allowlist blocks `javascript:`/`data:`.
- **Pest gotchas:** global helper fn names must be unique across files. `withHeader('X-Profile-Id', $p->ulid)` persists on `$this` across requests → call `$this->flushHeaders()` before switching users. Active profile = `$request->attributes->get('activeProfile')` (defaults to `personalProfile` when no header). Broadcast events dispatched via `broadcast(new E)` are caught by `Event::fake([E::class])` + `Event::assertDispatched`.
- **Formatting:** `./vendor/bin/pint <files>` (API) always before commit; web `pnpm tsc --noEmit && pnpm lint && pnpm build`.
- **Lint rule** `react-hooks/set-state-in-effect`: don't `setState` synchronously in an effect body; inline the fetch with a `cancelled` flag and set state in `.then`; rely on `key` for reset-on-prop-change.

## Domain model essentials
- **Profile** = identity; `kind` personal|business; a User has many; business Profile = the vendor/team owner (`ProfileMember`, `canManageMembers($user)`, `isBusiness()`). `avatarUrl()`/`coverUrl()` = `Storage::disk('public')->url(path)` + `?v=updated_at` cachebust.
- **Shop:** Store (1 per business profile, branded, `is_vat_registered`/`vat_rate_bp` default 1500bp/`vat_number`) → Product (`digital_download`|`course`|`service`; `price_cents` + optional `sale_price_cents`/`sale_ends_at` → `effectivePriceCents()`; `visible()` scope; `offers()` cross_sell/bump/upsell; `isFree()`, `isHtmlTool()`, `isCourse()`). Order/OrderItem/Purchase (Purchase = entitlement, `ownedBy($profile,$product)`). `Order::markPaid()` idempotent, splits platform fee, grants Purchases.
- **Pricing (single source of truth):** `App\Services\Shop\CheckoutPricing::quote()` returns items/subtotal/discount/total/vat/coupon — used by BOTH the dry-run quote endpoint and the real order build so they never diverge. VAT is **inclusive** (`vat_cents` is the portion of the total, not added on top). Orders store `discount_cents`/`vat_cents`/`vat_rate_bp`.
- **Coupons:** `Coupon` (`code` unique, `percent`|`fixed`, optional `store_id` scope, `min_spend_cents`, `max_redemptions`/`redeemed_count`, `starts_at`/`ends_at`, `is_active`) + `CouponRedemption` (unique per `(coupon, buyer_profile)` → one use per buyer).
- **Courses (P3):** CourseModule→CourseLesson (+`course_lesson_progress`). Gated by Purchase or store owner; `is_preview` lessons open. Free enrol grants Purchase w/o payment.
- **Masterclass = the "room":** brandable + sponsorable (P4: brand/accent colour, logo/banner, `is_sponsored`+sponsor_name/url/blurb; ad_events `masterclass_id`, `trackSponsoredRoom`). Live (ask#4): `masterclass_live_sessions` (RTMP stream_key/ingest host-only, HLS playback_url, recording_playback_url, status idle|active|ended). Chat: `conversation_id` → group Conversation (`ensureRoomConversation`), enrol joins via `MessagingService::ensureMember`. `LiveReaction` ephemeral broadcast. **Facilitator self-serve authoring:** `GET/POST/PATCH/DELETE me/masterclasses` (`MyMasterclassController`, authorised by `MasterclassPolicy`) — mirrors the store-owner tier; web UI at Settings → Profile (`masterclass-settings.tsx`).
- **Feature flags:** `config/features.php` registry (label/description/default/group) overlaid with `settings` rows `features.<key>` via `App\Support\Features`. Resolved map ships to the client on `/api/v1/me`. Nine flags: `daily_brief`, `ai_curation`, `coach`, `opportunities`, `shop`, `courses`, `masterclasses`, `gamification`, `wellness`. Server gate = `feature:<key>` middleware (`EnsureFeatureEnabled`, **404 when off** so the surface simply doesn't exist); client gate = `useFeature()` / `<FeatureGate>`.
- **Daily Brief:** `BriefService` + `StructuredJsonAiDriver` — AI curates each member's brief items to their activity when `ai_curation` is on and a key is set; falls back to industry matching otherwise. `daily_briefs` stores item ULIDs.
- **Bug reports:** `BugReport` (user/profile, summary, details, url, user_agent, app_version, status `open|triaged|resolved|dismissed`, handled_by, resolution_note). Member submits from the account switcher (`bug-report-dialog.tsx` → `POST bug-reports`, `throttle:reports`); admins triage in Filament.
- **Analytics:** `post_stats_daily` (existing) + P4 `store_stats_daily`/`product_stats_daily` (`ShopStatsService::bump*`, `POST /shop/seen`, `StoreAnalyticsService`).
- **CRM webhooks:** WebhookEndpoint (generic HMAC `X-SBH-Signature` or Brevo), `WebhookDispatcher` (CONTACT_CREATED/UPDATED, PURCHASE_COMPLETED) → queued `DeliverWebhook`. Secrets encrypted at rest (`EncryptedString` cast).

## What's shipped
Original 12 milestones + 6 UX-pattern commits; V2 (Daily Brief) + super-admin AI model control; admin-roles (Super/Admin/Facilitator/Manager + multi-user business profiles); live tenders (OpenProcurement); V3 (deep Coach, personalised Home, masterclasses/cohorts). **4 marketplace asks:** (1) Shop P1–P4 done. (2) native iOS/Play — **NOT started**. (3) sponsored/branded rooms = masterclasses, done. (4) RTMP live masterclasses + chat + Zoom reactions + recordings, done.

Since the previous handoff (`5ea5b06` → `6718601`):
- **Commerce depth** — sale prices, coupons, inclusive VAT, upsells through checkout + shop UI; VAT receipt to buyer and sale notice to vendor on paid order; Filament create/manage for stores, products, offers, coupons.
- **Feature flags** — registry + super-admin Filament page + server route gating + web surface gating.
- **AI-personalised daily brief** (`StructuredJsonAiDriver`).
- **Facilitator self-serve masterclass authoring.**
- **Member bug reporting** + admin triage inbox.
- **CSV bulk upload for opportunities** (Filament importer, upsert on `(source, source_ref)`, queued).
- **Security pass** — fail-closed webhooks, encrypted secrets at rest, extra rate limits, nginx TLS floor + security headers, SW cache purge on logout, Next image-host allowlist, MySQL container scoped to its own credentials.
- **Safe external links** — `safeExternalHref` scheme allowlist; social links handed to the native app, websites kept in-app.
- **Observability** — Sentry (API + web), JSON log channel, signed alert webhook → GitHub issue triage.
- **Production-readiness audit** — `AUDIT-FINDINGS.md` at repo root (read-only audit, no code changed).
- Fix: 500 on challenge leaderboard with soft-deleted participants.

**Test state:** ~865 Pest cases across 47 Feature areas + Unit (counted from source; the suite was **not** run in this session — no PHP on the working machine). CI runs the API on SQLite **and** MySQL plus a web lint/tsc/build job. Web has **no** unit or E2E tests.

## External integration config (all via Integrations page or env; drivers default to disabled)
- **Payments/PayFast** (`config/payments.php`): `PAYMENTS_DRIVER=payfast`, merchant_id/key/passphrase, sandbox, `PLATFORM_FEE_PERCENT`, `FRONTEND_URL`. ITN webhook `POST /api/v1/shop/payfast/itn` (authoritative). Sandbox creds 10000100 / 46f0cd694581a.
- **Streaming/Mux** (`config/streaming.php`): `STREAM_DRIVER=mux`, MUX_TOKEN_ID/SECRET/WEBHOOK_SECRET. Webhook `POST /api/v1/streaming/webhook` flips live status + stores recording. Host: OBS → RTMP server+key from "Go live". Player = `HlsPlayer` (native HLS / hls.js).
- **Observability** (`config/observability.php`, `config/sentry.php`): API DSN `SENTRY_LARAVEL_DSN`. PII scrubbed via `App\Observability\SentryScrubber`; `sendDefaultPii: false`; no session replay (POPIA).
  - ⚠️ **Web Sentry cannot currently be enabled in the documented deploy.** `NEXT_PUBLIC_SENTRY_DSN` is a build-time var, but `apps/web/Dockerfile` declares no `ARG` for it and `docker-compose.prod.yml` passes no such build arg — it needs a Dockerfile edit first.
  - ⚠️ **JSON logging is unreachable in production, not merely opt-in.** `.env.prod.example:12` sets `LOG_CHANNEL=stderr`, which bypasses the `stack` channel entirely, so `LOG_STACK=single,json` is inert there. The `stderr` channel's formatter comes from `LOG_STDERR_FORMATTER`, which is set nowhere in the repo → falls back to Monolog's plain-text `LineFormatter`. Fix: set `LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter` in the prod env, or switch prod to `LOG_CHANNEL=json`. Alert triage: `OBSERVABILITY_ISSUE_DRIVER=null|github`, `OBSERVABILITY_ALERT_SECRET`, `GITHUB_REPO`, `GITHUB_TOKEN`. Endpoint `POST /api/v1/observability/alert` verifies HMAC `X-SBH-Signature` / `Sentry-Hook-Signature` — **blank secret rejects everything (fail-closed)** — then queues `FileIssueForAlert`, one GitHub issue per fingerprint (recurrences comment), labelled `sentry`/`auto-triage`. Filing an issue is the only automated write; PRs are opened and merged by humans. Full detail: `apps/api/docs/observability.md`.
- **Reverb** must run for chat/live-reactions. Note `BROADCAST_CONNECTION` defaults to null/log — realtime is dark unless configured, and there is no polling fallback (audit B7).

## Deployment (VPS, Docker)
- Repo on server: **`/opt/sbh-app`**. Fresh install: `curl -fsSL .../scripts/deploy-vps.sh | bash`. Update: `cd /opt/sbh-app && bash scripts/update-vps.sh` (pulls branch, rebuilds, restarts nginx, migrates, config:cache, storage:link).
- Stack `docker-compose.prod.yml`: mysql, redis, api (php-fpm 9000 + reverb 8080, WORKDIR `/app`, runs supervisord), web (Next, `NEXT_PUBLIC_API_URL=${APP_URL}` baked at build), nginx (443 + 8080 wss), certbot. `.env.prod` holds secrets. The mysql container gets only its own DB credentials (no root password sharing).
- **nginx hardening:** TLS 1.2/1.3 floor with an explicit ECDHE cipher list; `Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` set with `always`. A `location` with its own `add_header` does **not** inherit server-level headers — `nosniff` is re-asserted inside `/storage/`.
- **Media serving:** nginx `location ^~ /storage/ { alias /app/storage/app/public/; }` from the shared `media-storage` volume (mounted `media-storage:/app/storage/app` in both api & nginx). NOT via the `public/storage` symlink (that's dev only; gitignored → dev container links it on boot). PHP runs as **www-data**; the volume is root-owned, so the prod Dockerfile CMD `chown -R www-data:www-data storage bootstrap/cache` on startup.
- Host has NO php/git — run everything via `docker compose --env-file .env.prod -f docker-compose.prod.yml exec api php artisan ...`.

## Profile avatar on prod — silent-failure path now closed
Original symptom: uploaded avatar never displayed (initials shown). Root cause = media volume root-owned, www-data couldn't write → `Storage::disk('public')->put()` failed silently (`'throw'=>false`) while the DB path updated → 404. Fixes in: `8ee159b` (startup chown), `1d83347` (unique upload filename `media/avatars/{ulid}-{rand}.webp` so replace busts cache), `9f2dfa1` (dev storage:link), `46f4619` — **`MediaService::replaceProfileImage` now throws a `RuntimeException` when `put()` returns false**, so a failed write surfaces as a 500 instead of persisting a path to a file that never landed. The DB column is only updated after the bytes are on disk.
- **If it recurs:** the upload now errors loudly rather than yielding a broken image. A 500 on upload ⇒ the disk is still unwritable → `... exec -T api chown -R www-data:www-data storage/app`. Upload succeeds but the image 404s ⇒ serving/URL mismatch, not a write failure — check the nginx `/storage/` alias and the request URL in DevTools.
- Not independently re-verified on prod since the fix; treat a clean upload + visible avatar as the confirmation.

## Admin ops
- **Feature flags:** Admin → Feature flags (super-admin only) — toggles the nine registry flags; off = 404 on the API route and hidden in the web UI.
- **Bulk-upload opportunities via CSV:** Admin → Opportunities → *Import CSV* (Filament `ImportAction` + `App\Filament\Imports\OpportunityImporter`, upserts on `(source, source_ref)`, queued). Column reference + example: `docs/opportunities-csv-upload.md`.
- **Commerce:** Filament resources for Stores, Products (+ Offers relation manager) and Coupons — create and manage without touching the member UI.
- **Bug reports:** Admin → Bug reports — triage inbox (open/triaged/resolved/dismissed, assignee, resolution note).
- **Integrations:** Admin → Settings → Integrations — payments, streaming, AI, observability; DB settings layer over env at boot.

## Known gaps — two audits, read both
- **`docs/audits/production-readiness-2026-08-03T22:25:16.md`** (+ `-dashboard.html`) — 43-dimension audit, 2026-08-03. **13 CRITICAL blockers**, every one verified against source. Top of the list: **no backups exist anywhere**; two payment defects (buyers chargeable twice; PayFast silently drops confirmed payments); account pre-hijacking via unverified-email + OAuth auto-link; the service worker serving one user's DMs to the next. Start there before shipping.
- **`AUDIT-FINDINGS.md`** (below) — earlier product/estimate-oriented audit. Still accurate on the enterprise-scope gaps; the newer one supersedes it on engineering detail.

## Known gaps (from `AUDIT-FINDINGS.md`, read that for evidence/file:line)
Ranked by how much they'd move a build estimate:
1. **Corporate / ESD portal — ABSENT (months).** No routes, models, migrations, permissions or UI; whole-repo search is zero hits. The opportunities/tenders feed is member-facing, not an enterprise portal. If the pitch leans on this, it is greenfield.
2. **Business verification (ID/CIPC/B-BBEE) — ABSENT (weeks→months).** `is_verified` is a manual admin toggle only: no member submission, no document upload, no review queue, no audit trail. Also note there is **no signed/expiring-URL mechanism anywhere** — private docs on the public disk would be world-readable.
3. **Media security & delivery (weeks).** No server-side EXIF/GPS stripping (relies on GD re-encode as a side-effect) or orientation fix; **private product/course uploads validate size only — no MIME check, no virus scan, 50 MB ceiling**; no `srcset`/CDN/blurhash; no `<img onError>` fallback; no media lifecycle/orphan cleanup; `s3` disk is dead code (upload paths hardcode `public`/`local`).
4. **Production operability (weeks).** Error tracking now exists (above) but is **off until a DSN is set**; still missing APM, health metrics, and — most acute — **realtime defaults off with no polling fallback**, so messaging/notifications are silently non-live.
5. **Compliance & account security (days→weeks).** **No MFA anywhere.** Consent is client-side localStorage only — no server-side auditable consent record. Audit log covers moderation actions only. Export/delete already work, so this is incremental.

Also worth knowing: payments have **no payouts and no refunds** (`vendor_amount_cents` is recorded, never disbursed; lifecycle is pending→paid only); search is DB `LIKE` with no engine (scout installed, unused); the AI coach has no per-user token/$ budget cap.

## Remaining / next
- **Ask #2 native iOS + Google Play**: Capacitor wrap of the PWA + bearer-token auth path + native push; needs Apple/Google dev accounts. Not started. (`links.ts` already routes social links out to the native app.)
- Optional: post-purchase upsell carousel (P2 left it out); Filament course relation-manager (P3 skipped); web unit/E2E tests; QA acceptance checklist.
- Verify-before-launch: turn Reverb on, set both Sentry DSNs, run one real PayFast transaction, one real Mux stream, one real email send.
- "Production ready" = deployed + each external service verified with one real transaction + a human QA pass. Automated bar (tests/CI) already met.
