# SBH — Production-Readiness Audit (read-only)

_Read-only audit. No application code was modified; no branches or commits were created.
Evidence was gathered from the implementation (file:line), from commands run against the
repository, and from live inspection of the app running on the demo dataset._

## 1. Executive summary

The consumer half of SBH is real and reasonably mature; the enterprise half that the
pitch leans on is not built. **Biggest estimate risk: the corporate / ESD (enterprise &
supplier-development) portal is ABSENT** — no routes, models, migrations, permissions or
UI (whole-repo search: zero hits) — and the **business-verification workflow that any
supplier/ESD programme depends on is ABSENT too**: `is_verified` is a manual admin toggle
with **no member submission, no ID/CIPC/B-BBEE document upload, and no review queue**. If
the launch thesis needs those, that is months of unstarted work, not polish.

Among what *is* built (feeds, messaging, marketplace, opportunities, AI coach, admin):
data is real (91 migrations, Eloquent, all 26 UI sections API-backed — nothing mocked),
authorization is enforced server-side, rate limiting and Pest tests are solid. The acute
*launch* risks are operational, not cosmetic: **no error tracking / APM** (prod runs
blind), **realtime is dark by default** (`BROADCAST_CONNECTION=null`, no polling fallback
— messaging/notifications silently non-live), **no MFA anywhere**, and media-security gaps
(no server-side EXIF/GPS stripping, no orientation fix, and **private product/course
uploads accept any file type up to 50 MB with no MIME check and no virus scan**). The
screenshot placeholders are an empty demo dataset + a dev `/storage` serving quirk + no
`<img onError>` fallback — **not** an absent pipeline.

## 2. Repository map & stack

- **Monorepo**, two apps. `apps/api` = **Laravel 12 / PHP 8.4**, Pest, Sanctum, **Filament
  v4 admin at `/admin`** (`php artisan route:list` → `filament.admin.*`), Reverb.
  `apps/web` = **Next.js 16 (App Router, Turbopack) / React 19 / TypeScript**, Tailwind 4,
  shadcn/ui, Zustand, next-intl, Serwist PWA. Package manager **pnpm** (workspace).
- **Run:** API `php artisan serve` (Docker `dev` stage) on :8000; web `pnpm dev` on :3000;
  production via `docker-compose.prod.yml` (mysql, redis, php-fpm+reverb, next, nginx,
  certbot).
- **Front vs back:** clean split — web calls the API over HTTP (`apps/web/src/lib/api/client.ts`);
  Sanctum **session-cookie** auth (not bearer for the SPA).
- **One product, two deployables.** ~223 API routes (`apps/api/routes/api_v1.php`).

## 3. Where the data comes from — answered plainly

**A real relational database via Eloquent, seeded by a script.** Default connection
`sqlite` (`apps/api/config/database.php:20`; `.env.example` `DB_CONNECTION=sqlite`),
**MySQL** supported and CI-tested on both (`.github/workflows/ci.yml`). **91 migrations**
(`apps/api/database/migrations/`) define real tables (users, posts, media, stores, orders,
masterclasses, moderation_actions, …). Demo content comes from **`php artisan demo:seed`**
(`Database\Seeders\DemoContentSeeder`) — run live during this audit: 14 users / 20
profiles / 30 posts. **No in-memory mock, no JSON fixtures, no hardcoded component data** —
the feature-reality sweep found **all 26 UI sections resolve to API endpoints** (§B14).
Media is real files on disk, e.g. `storage/app/public/media/01KYT…/01KYT…webp` — a genuine
1200×900 WebP (`file` → `RIFF … Web/P … VP8`) with a `_thumb.webp` sibling.

**Why the pack images render blank:** sparse seeded images **+** `php artisan serve` does
not serve the `/storage` symlink in dev (a live `curl` of a real webp returned nothing)
**+** no `<img onError>` fallback (§A10) — a working-but-imperfect pipeline behind an empty
dataset, **not** an absent one.

## 4. PART A — media & file handling (forensic)

| Item | Verdict | Evidence | Note |
|---|---|---|---|
| **A1** Upload endpoints | **WORKING (gaps)** | `POST media`→`MediaController@store`; chunked `POST/PUT uploads/*`; avatar/cover `ProfileImageController`; product `StoreProductController@uploadFile:67`; course `StoreCourseController@uploadAttachment:97` (`routes/api_v1.php:186-193,158-161,334,363`). Feed image rules `StoreMediaRequest.php:18-23` (`image,mimes:jpg,jpeg,png,webp,gif,max:10240`). | **Private product/course uploads validate `max:51200` ONLY — no MIME/type check.** Chunk init `mime/size_bytes` are never re-verified against assembled bytes (`UploadService@complete:87-134`). |
| **A2** Client resize before upload | **WORKING (image crop path)** | `composer/crop-image.ts:cropImageToWebp` → `<canvas>`, cap `MAX_DIMENSION=2048` (L15,28-40), `toBlob("image/webp",0.85)` (L52-60); used by `photo-picker.tsx`, `profile-image-upload.tsx`. | Feed photos/avatars re-encoded WebP + downscaled on device (good for metered data). **Video/audio NOT compressed client-side** (`chunked-upload.ts` sends raw). |
| **A3** Resumable / chunked / retry / progress | **PARTIAL** | Chunked 1 MiB PUTs + per-chunk XHR progress + abort→best-effort DELETE + post-complete transcode polling (`chunked-upload.ts:100-180`). | **No retry/backoff** (one failed chunk aborts all, L110-115). **No client resume after reload** (restarts at chunk 0) even though the **server is idempotent** and *supports* resume (`UploadService:69-87`). |
| **A4** On-ingest processing | **MIXED** | WebP+downscale `MediaService@storeImage:23-31` (q82, ≤1920w); avatar `cover(512²)`, cover `1500×500`; video H.264/AAC 720p `ProcessVideoUpload`. | **NOT done:** explicit **EXIF/GPS stripping** (relies on GD re-encode as a side-effect; installed `exif` ext never called), **orientation correction** (no `orient()` — rotated phone photos stay rotated), **AVIF**, **virus/content scanning** (none anywhere). |
| **A5** Derivatives | **PARTIAL** | Two per image on upload: full ≤1920w + one thumb (`thumb_width` 480w) `MediaService:25-36`; video poster thumb. | **No responsive size ladder**, no on-demand resizer; app uses raw `<img>` so `/_next/image` is not exercised for feed media. |
| **A6** Delivery | **PARTIAL** | `Media::url()`→`Storage::disk->url` (`Media.php:86-95`); prod nginx `location /storage/` `expires 7d; Cache-Control immutable; nosniff` (`docker/nginx/prod.conf`). Raw `<img loading="lazy">` + `aspect-ratio` reserved (`image-post.tsx:40-81`); low-data mode defers full-res. | **No CDN** (same-origin), **no `srcset`/`sizes`**, **no blurhash/LQIP**. Dev `php artisan serve` doesn't serve the `/storage` symlink (live curl empty). |
| **A7** Storage | **WORKING** | `config/filesystems.php`: `local`(private `app/private`), `public`(`app/public`, visibility public, url `APP_URL/storage`), `s3`(env-driven). Feed→public; product/course→local. | **s3 is dead code** — upload code hardcodes `'public'`/`'local'` and ignores `FILESYSTEM_DISK` (`MediaService:38`, `UploadService:113`). |
| **A8** Verification docs (ID/CIPC/B-BBEE) | **ABSENT** | Whole-`apps/api` search `verification\|cipc\|bbee\|id_document` → zero app-code hits (only seeders/badges). No table, route, controller or upload path. | Private-delivery *primitives* that exist are sound: `PurchaseController@download:43-55` streams from `local` after ownership check; private paths use ULIDs (non-enumerable). **But there is NO signed/expiring-URL mechanism anywhere** — public feed media is world-readable; if docs were added on the public disk they'd be exposed. |
| **A9** Auto image moderation / sensitive flag | **user/admin-set, NOT auto** | `sensitive` set by author (`StorePostRequest.php:38`) or admin toggle (`Filament/.../PostsTable.php:58-66`). Only AI in loop = `AiModerationAssist` (report-triggered, **text-only**, advisory; never sets `sensitive`). Web blur `post-card.tsx:264`. | No image classifier at upload. |
| **A10** Loading / failure UI | **PARTIAL** | Upload failure `toast.error`; progress bars; "Processing…/Processing failed" states (`media-upload.tsx:89-193`); muted-bg skeleton. | **No `<img onError>` fallback anywhere** (grep: none) — broken/expired URLs render as raw broken images. No blurhash/LQIP. |
| **A11** Retention / lifecycle | **PARTIAL** | `PruneUploadSessions` prunes stale upload sessions hourly (`routes/console.php:13`); avatar/cover deleted on replace (`MediaService:134-136`). | **No lifecycle on stored media/derivatives**: no orphan cleanup, no TTL, no disk-file deletion when a Post/Media row is deleted, no S3 lifecycle. |

**Live test run (read-only, on demo data):** a seeded upload = real WebP `1200×900`
(2,936 B) + `_thumb.webp` (1,426 B) at ULID paths on the public disk. **Content-type /
cache-control / EXIF-survival / delivered-vs-source could not be measured live** because
dev `php artisan serve` returned nothing for `/storage` (see §7). The full "create a post
with an image and read the headers" test needs the app run with real static serving
(nginx or `storage:link` + a static server).

## 5. PART B — the rest of production readiness

| Item | Verdict | Evidence / note |
|---|---|---|
| **B1** Real API layer | **WORKING** | ~223 REST-ish routes `routes/api_v1.php`; controllers real (`Services/Feed/FeedService.php` 387 lines, `SearchController:44-107`, `AccountController:22-58`). |
| **B2** DB schema + migrations | **WORKING** | 91 migrations; SQLite default, MySQL supported/CI-tested. Real tables (`create_users_table:14-21`, etc.). |
| **B3** N+1 / unbounded | **WORKING** | Feed/directory/search eager-load (`PostService::EAGER`) + `cursorPaginate(20)` + bounded limits (`FeedService`, `DirectoryService:26-61`, `SearchController:96-101`). Viewer reactions bulk-hydrated. |
| **B4** Authentication | **WORKING** | Sanctum **session-cookie** (`LoginController:34 Auth::guard('web')->login`), **bcrypt** `Hash::make/check`, Socialite (google/fb/twitter). **No OTP (email/phone).** |
| **B5** Server-side authorization | **WORKING** | Enforced server-side, not UI-only: `admin` middleware (`EnsureUserIsAdmin:20`), policies (`MasterclassPolicy`, `Gate::authorize`), pervasive ownership `abort_unless(...profile_id===...)`. `User::canAccessPanel()` gates Filament. spatie/permission installed but **inert** (boolean flags/policies used instead). *Not exhaustively swept across all 223 routes.* |
| **B6** MFA | **ABSENT** | No 2FA/TOTP/Fortify anywhere. |
| **B7** Realtime | **PARTIAL** | Reverb/Echo real & wired (10 `ShouldBroadcast` events; `MessagingService:216` broadcasts; web `lib/echo.ts` subscribes via private channels). **BUT `BROADCAST_CONNECTION=null` default (`.env.example`=`log`) and NO polling fallback** — live updates are dark unless Reverb is configured; initial load is one-shot, then manual refresh. |
| **B8** Search | **WORKING (DB `LIKE`)** | SQL `LIKE`/`whereRaw LOWER(...) LIKE` (`SearchController`, `ProfileSearchController`, `DirectoryService:38-47`). **No engine**; laravel/scout installed but **unused** (no `Searchable` models, no `config/scout.php`). No relevance ranking / typo tolerance. |
| **B9** Payments | **PARTIAL** | PayFast end-to-end real: `CheckoutController::store` → `PayFastDriver::checkout` → ITN `PayFastWebhookController` (verifies signature + server postback + `COMPLETE` + exact amount) → `Order::markPaid()` (lock-based, idempotent, platform-fee split). **Payouts ABSENT** (`vendor_amount_cents` recorded, never disbursed), **Refunds ABSENT**, lifecycle only pending→paid. Driver `null` by default. |
| **B10** AI coach | **WORKING** | Real Anthropic/OpenAI drivers (`AnthropicAiDriver:30-70`, default `claude-haiku-4-5`), DB-persisted conversations (`CoachConversation`/`CoachMessage`), `throttle:coach` 15/min, canned fallback with no key (`NullAiDriver`). **No per-user token/$ budget cap; minimal prompt-injection defense.** |
| **B11** Corporate / ESD portal | **ABSENT** | No routes/models/migrations/permissions/UI (searched corporate/enterprise/ESD/supplier-development/procurement). The tenders/opportunities feed is member-facing, not an enterprise/ESD portal. |
| **B12** Admin / moderation console | **WORKING** | Filament `/admin`, 27 resources; real report queue (`Filament/Resources/Reports/ReportActions.php`: startReview/resolve/dismiss/deleteContent/banAuthor → `moderation_actions`). |
| **B13** Business verification workflow | **PARTIAL** | `is_verified` column (`create_profiles_table:30`) + admin toggle (`ProfilesTable:47-69`) + verification badge only. **No member submission, no document upload, no review queue.** |
| **B14** 26 sections real vs mock | **WORKING (all real)** | Every section calls a backing API endpoint; none static/mock (see table below). |
| **B15** Committed secrets | **WORKING (none)** | No `.env` tracked (`git ls-files` clean); `.gitignore` covers it; `.env.example` blank placeholders. Only a **public PayFast *sandbox*** value in a comment (not a live secret). 143 commits scanned. |
| **B16** Validation / XSS | **WORKING/PARTIAL** | 17 FormRequests + inline `validate()` (min/max rules). React escapes by default; single `dangerouslySetInnerHTML` = SEO JSON-LD in `layout.tsx:123` (dev-controlled), not user content. No markdown→HTML of user bodies. |
| **B17** Rate limiting | **WORKING** | 9 named limiters (`AppServiceProvider:69-107`: auth 5/min, coach 15, checkout 12, messages 60, comments 30, reports 10, uploads 30, engagement 60, suggest-topics 20), broadly applied. |
| **B18** POPIA basics | **PARTIAL** | Export (`GET me/export`→`AccountController::export`) & delete (`DELETE me/account`, password-gated) **WORKING**. **Consent is client-side localStorage only (`consent-store.ts`) — no server-side, auditable consent record.** Retention: only upload-session pruning. Audit log: **moderation actions only** (`moderation_actions`), no general activity log. |
| **B19** Tests | **WORKING (backend); web ABSENT** | Pest: ~46 Feature areas (Auth, Security, Roles, Feeds, Shop, Safety, Webhooks, …) + Unit; CI runs on SQLite **and** MySQL. **No web/E2E tests** (web CI = lint/tsc/build only). |
| **B20** CI / Docker / IaC | **WORKING (no IaC)** | `.github/workflows/ci.yml` — 3 jobs (dual-DB API + web build), least-privilege token. Dockerfiles (api/web) + compose (dev/prod) + nginx. **No Terraform/K8s.** |
| **B21** Logging / error tracking / monitoring | **PARTIAL/ABSENT** | Stock Laravel/Monolog only. **No Sentry, no APM, no structured/JSON log shipping, no health metrics.** |
| **B22** FE bundle / low-end Android perf | **UNKNOWN/PARTIAL** | Not measured (no prod bundle analysis run). Signals: PWA + Turbopack; raw `<img loading=lazy>` + `aspect-ratio` (good); **no `srcset`/CDN/blurhash** (poor on slow Android); heavy client feature set. Needs `next build` analyze + Lighthouse on a throttled device. |

### B14 — 26 sections → real-data map (all API-backed)

| Section | Real? | Endpoint (evidence) |
|---|---|---|
| home | REAL | `/me/brief`, `/me/streak`, feeds |
| feeds | REAL | `/feeds/*` (`FeedController`) |
| discover | REAL | `discover-view.tsx` → `/topics/*` |
| search | REAL | `/search` (`SearchController`, DB LIKE) |
| notifications | REAL | `/notifications` + Echo |
| messages | REAL | `/conversations` + Echo |
| shop | REAL | `/shop/stores/*`, `/me/store/products` |
| masterclasses | REAL | `/me/masterclasses`, `/masterclasses/*/live` |
| opportunities | REAL | `/opportunities/*` |
| coach | REAL | `/coach/messages` |
| wellness | REAL | `/wellness/resources` |
| leaderboard | REAL | `/gamification/leaderboard` |
| resources | REAL | `/resources/*` |
| learn | REAL | `/learn/lessons`, `/me/learn/progress` |
| mentors | REAL | `/mentors` |
| questions | REAL | `/feeds/questions` |
| wins | REAL | `/feeds/wins` |
| business | REAL | `/business/directory`, `/business/events`, matches |
| events | REAL | `/business/events?filter=upcoming` |
| map | REAL | `/geo/nearby-users` |
| dashboard | REAL | `/me/goals`, `/me/streak` |
| insights | REAL | `/analytics/overview`, `/analytics/posts` |
| drafts | REAL | `/me/posts?status=draft` (server drafts) |
| settings | REAL | `/me`, `/me/profiles`, muted/blocked |
| profile (`[handle]`) | REAL | `profile-client.tsx` → `/profiles/*` + Echo |
| post-detail (`p/[ulid]`) | REAL | `post-detail-client.tsx` → `/posts/*` + Echo |

## 6. The five findings that would most change a build estimate (ranked)

1. **Corporate / ESD portal is ABSENT — MONTHS.** The enterprise/supplier-development
   portal (the enterprise-revenue thesis) has no code at all. Data model, corporate
   roles/permissions, programme/cohort management, supplier onboarding, reporting/exports —
   greenfield. *Why: entire product surface unbuilt.*
2. **Business verification (ID/CIPC/B-BBEE) is ABSENT — WEEKS→MONTHS.** For an SMME/ESD
   platform this is foundational and legally sensitive: secure document upload (private,
   scanned, signed-URL delivery), a review queue, an audit trail, and the verified-badge
   state machine. Only a manual admin boolean exists today. *Why: no submission/upload/
   review/secure storage; overlaps §A8 media gaps and POPIA.*
3. **Media security & delivery hardening — WEEKS.** Server-side EXIF/GPS stripping +
   orientation fix; MIME allowlist + virus/content scan on the *unrestricted* private
   product/course uploads; signed/expiring URLs for any private/sensitive media; `srcset` +
   `onError` fallback + CDN/blurhash. *Why: privacy (GPS leakage), abuse (arbitrary 50 MB
   files unscanned), and mobile-data cost all sit here.*
4. **Production operability — WEEKS.** Error tracking/APM (e.g. Sentry), structured
   logging, health checks, and turning realtime on with a **polling fallback** so messaging
   isn't silently dark; plus media lifecycle/orphan cleanup. *Why: you cannot safely run or
   debug a launch without these, and realtime currently defaults off.*
5. **Compliance & account security — DAYS→WEEKS.** Server-side, auditable **consent
   records**; a general **audit log**; **MFA**; data-retention enforcement. *Why: POPIA
   posture and account-takeover risk; export/delete already exist so this is incremental.*

## 7. What I could not determine, and what would settle it

- **Live media HTTP semantics** (content-type, cache-control, EXIF-survival on a real
  round-trip, delivered-vs-source bytes, predictable derivative URLs) — **UNKNOWN**. Dev
  `php artisan serve` does not serve the `/storage` symlink (curl returned nothing).
  _Settle:_ run under nginx (prod compose) or `storage:link` + a static server, upload an
  EXIF/GPS-tagged JPEG via `POST /media`, then read the response and `curl -I` the result.
- **Prod cache-control on media** — nginx config declares `expires 7d; immutable`
  (`docker/nginx/prod.conf`) but was not observed live. _Settle:_ `curl -I` against a
  running prod nginx.
- **Front-end bundle size & low-end Android performance (B22)** — **UNKNOWN**. _Settle:_
  `next build` + bundle analyzer, and Lighthouse/WebPageTest on a throttled mid-range
  Android.
- **Vendor payout disbursement (B9)** — code shows none; **UNKNOWN** whether payouts run
  out-of-band (manual EFT/ops). _Settle:_ confirm with the operator; there is no payout
  code or scheduled job.
- **Exhaustive authorization coverage (B5)** — sampling shows consistent server-side
  enforcement; not proven across all 223 routes. _Settle:_ a route-by-route
  middleware/policy sweep.
- **Email deliverability (receipts / password reset)** — Mailables exist; SMTP/provider
  config not verified live. _Settle:_ check prod mail config + a live send.

---

_Verdict vocabulary: WORKING = implemented and reachable end-to-end from a user action ·
PARTIAL = implemented but incomplete/unverified (specifics named) · STUB = interface/route/
UI with no implementation · ABSENT = no trace found · UNKNOWN = not determinable from this
repository._
