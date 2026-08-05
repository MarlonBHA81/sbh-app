# Production Readiness Audit Report

**Date:** 2026-08-03T22:25:16
**Codebase:** SBH (Small Business Helpdesk) — `~/sbh-app` @ `6718601`
**Auditor:** Claude Code (Production Readiness Skill v3.0)
**Status:** COMPLETE — all 43 active dimensions audited

## Audit Configuration

| Property | Value |
|----------|-------|
| **Detected Stack** | Mixed — PHP 8.3 / Laravel 12 (`apps/api`) + TypeScript / Next.js 16 / React 19 (`apps/web`) |
| **Standards Loaded** | `php.md`, `typescript.md`, `frontend.md`, `database.md`, `sre.md` |
| **Standards Unavailable** | `devops.md` — **HTTP 404, not present in the standards repo.** Dimensions 24 (Container Security), 26 (CI/CD) and 32 (Makefile & Dev Tooling) fall back to generic patterns. Recorded per Step 0.5 fallback rule. |
| **Active Dimensions** | 43 (44 minus conditional #33) |
| **Max Possible Score** | 430 |
| **Conditional: Multi-Tenant** | Inactive — no `MULTI_TENANT` indicators found |
| **Docker** | Yes (`apps/api/Dockerfile`, `apps/web/Dockerfile`) |
| **Makefile** | Not present |
| **LICENSE** | Not present (note: `php.md` § License Headers is MANDATORY) |

### Stack Adaptation Note

The skill's explorer prompts carry Go/Fiber/lib-commons reference implementations. This codebase is
Laravel + Next.js, so each dimension was audited against its **Laravel/Next equivalent**, using the
stack-matched Bee standards (`php.md`, `typescript.md`, `frontend.md`, `database.md`, `sre.md`) as the
source of truth rather than the Go examples. Findings that would only be meaningful for a Go service
(e.g. "lib-commons missing from go.mod") are reported as **N/A — stack mismatch**, not as violations.

---

## Dashboard

| Overall Score | Classification | Critical | High | Medium | Low |
|:-------------:|:--------------:|:--------:|:----:|:------:|:---:|
| **199.5 / 430 (46.4%)** | **Not Production Ready** | **13** | **69** | **103** | **98** |

> **Read this before the score.** 46.4% substantially *understates* this codebase. A large share of the
> deducted points come from conformance with the Bee standards — a third-party convention set this project
> never adopted (see "Two classes of finding" below). The genuine defects are concentrated and fixable; the
> conformance gaps are a choice, not a fault. **The blockers below are the deliverable, not the number.**

### Category Scoreboard

| Category | Score | % | Status |
|:---------|------:|--:|:------:|
| A: Code Structure & Patterns | 64.75/110 | 59% | NEEDS WORK |
| B: Security & Access Control | 41.5/90 | 46% | NEEDS WORK |
| C: Operational Readiness | 25.0/70 | 36% | FAIL |
| D: Quality & Maintainability | 42.0/100 | 42% | NEEDS WORK |
| E: Infrastructure & Hardening | 26.25/60 | 44% | NEEDS WORK |
| **TOTAL** | **199.5/430** | **46.4%** | — |

Operational Readiness is the weakest category and the honest headline: this application is **built** far
better than it is **operable**.

---

## Two classes of finding — do not collapse these

**1. Real defects** — true regardless of any standard. These need fixing whatever conventions you use:
the account-takeover chain, double-charge, silent payment loss, cross-user cache leak, no backups, no
graceful shutdown, unrestricted uploads, the feed N+1. **13 CRITICAL, and they are the deliverable.**

**2. Standards-conformance gaps** — only actionable if you *choose* to adopt Bee standards: `declare(strict_types=1)`,
PHPStan level 8, TanStack Query, camelCase JSON, per-file licence headers, Makefile targets. Each is real
against the standard and each was scored, but none means "your app is broken." Roughly a third of the
deducted points sit here.

The skill's own dimension list is Go/Fiber/lib-commons-shaped. Where a check only makes sense for a Go
service it was marked **N/A — stack mismatch** rather than scored as a violation; where the intent transfers
(pagination, error contract, health checks, idempotency) it was audited against the Laravel/Next equivalent
using the stack-matched standards (`php.md`, `typescript.md`, `frontend.md`, `database.md`, `sre.md`).

---

## The 13 Critical Blockers

Every one was independently re-verified against source by the lead auditor before being recorded.

| # | Blocker | Dim | Why it matters |
|---|---------|:---:|----------------|
| **CB-1** | Account pre-hijacking via unverified email + OAuth auto-link | 6 | Register with a victim's email; when they "Sign in with Google" they land in **your** account, which their OAuth marks verified. You keep password access. |
| **CB-2** | Sanctum tokens never expire, no revocation route | 6 | A leaked bearer token is valid forever. Only an admin or full account deletion clears it. |
| **CB-3/4** | Unrestricted file upload (product deliverable, course attachment) | 9 | `['required','file','max:51200']` and nothing else — no MIME check, no scan. Arbitrary bytes served to buyers. |
| **CB-5** | No graceful shutdown — every deploy SIGKILLs in-flight jobs | 4 | No `stopwaitsecs`/`stop_grace_period`; both default to 10s. Laravel traps SIGTERM correctly but Docker kills first. |
| **CB-6** | Buyers can be charged twice for one entitlement | 16 | No ownership or pending-order guard in checkout; `purchases` unique constraint silently no-ops the second grant. **Deterministic, not a race.** |
| **CB-7** | N+1 on the main feed | 21 | `ProfileResource` costs 2–3 uncached queries *per profile*; a 20-post page issues ~40–60 extra. `preventLazyLoading` absent. |
| **CB-8** | Migration `down()` destroys encrypted secrets | 23 | Narrows `text`→`json` back over ciphertext; rollback either throws or truncates webhook HMAC secrets. |
| **CB-9** | PayFast ITN silently drops confirmed payments | 36 | Transport failure is indistinguishable from bad signature; always returns 200 so PayFast never retries. Buyer charged, nothing delivered, no alert, no reconciliation job. |
| **CB-10** | Service worker serves one user's private DMs to the next | 40 | `...defaultCache` adds an `"apis"` bucket keyed by URL only; not in the logout purge list. |
| **CB-11** | **No backups exist anywhere** | 41 | Bare Docker volumes, one VPS, `migrate --force` against prod with nothing to restore from. |
| **CB-12** | Redis is an unguarded SPOF for session + cache + queue | 39 | Outage = total outage, *and* the feature-flag kill switch reads Redis too, so it fails in the incident it exists for. |
| **CB-13** | PayFast receipts permanently lost on queue failure | 39 | `markPaid()` commits, then queue dispatch throws before the 200; retry finds it already paid and skips receipts forever. |

**Three of thirteen touch money directly** (CB-6, CB-9, CB-13) and **two are POPIA-relevant privacy exposures**
(CB-10, and CB-11's absence of loss protection).

---

## What is genuinely strong

This is not a weak codebase, and the score shouldn't imply otherwise:

- **IDOR (9.0)** — the `X-Profile-Id` header *is* properly ownership-verified via owner-or-`ProfileMember`; ~35
  controllers follow a consistent scope-then-verify idiom; no mass-assignment escalation path exists.
- **SQL Safety (9.5)** — all 42 raw-SQL sites use bindings; the single interpolated identifier is allow-listed
  immediately before use; migrations are 100% Schema-builder.
- **Secret Scanning (9.25)** — zero secrets in tracked files *or* git history, verified including deleted-file history.
- **Null Safety (8.75)**, **CORS (9.0)**, **API Versioning (8.25)**, **Naming (8.0)**, **Routes (8.0)** — all clean.
- `Order::markPaid()` is textbook-correct (transaction + `lockForUpdate` + re-check + idempotent grant) with a
  real concurrency test. Payments/checkout is the **best-tested domain in the repo**.
- **Technical debt is unusually low**: 10 TODOs, zero FIXME/HACK/XXX, zero commented-out blocks, zero
  `@ts-ignore`/`any` in authored web code, zero phpstan suppressions across ~1,100 files.
- All 63 foreign keys declare explicit cascade behaviour; money is integer cents throughout.

The pattern across the whole audit: **application logic is careful and well-tested; the operational envelope
around it is thin.** Nine of the thirteen blockers are deploy/runtime/infrastructure concerns, not code defects.

---

## Remediation Roadmap

**Phase 1 — before any further production traffic**
1. CB-11 backups (nothing else matters until a restore path exists) · CB-9/CB-13 payment loss · CB-6 double-charge
2. CB-1 account takeover · CB-2 token expiry · CB-3/4 upload MIME allow-lists
3. CB-10 SW cache purge (one-line `USER_CACHES` addition + `switchProfile` hook)

**Phase 2 — this sprint**
CB-5 `stopwaitsecs`/`stop_grace_period` · CB-8 make `down()` a no-op · CB-12 guard `Setting::get()` and in-request
`broadcast()` · CB-7 bulk-hydrate `ProfileResource` + enable `preventLazyLoading` · readiness probe + compose
healthchecks · job `$timeout`/`failed()` hooks · throttle the search endpoints.

**Phase 3 — this quarter**
CSP · `.dockerignore` · OpenAPI spec (blocker for the mobile app) · PHPStan + Pint in CI · `composer audit`/Dependabot
· coupon cap under lock · `xp_ledger` unique index · DM conversation uniqueness · Redis/PDO timeouts.

**Phase 4 — decide, don't default**
Adopt or formally waive the Bee conformance items (strict_types, TanStack Query, camelCase JSON). Document the
snake_case decision rather than remediating it — a rename would touch ~327 files and create a dangerous
half-migrated window for no user benefit.

---

## Audit integrity notes

- **Every CRITICAL was independently re-verified** by the lead auditor against source. Three explorer findings
  were **rejected or downgraded** on verification: the `streaming/webhook` "unauthenticated" claim (it verifies
  an HMAC and fails closed), the `BROADCAST_CONNECTION=log` PII CRITICAL (prod correctly sets `reverb`; dev-only),
  and the `APP_PREVIOUS_KEYS` claim (it *is* configured).
- **Three hypotheses deliberately planted in explorer prompts were disproven**, not confirmed: root ffmpeg
  worker, DB/Redis published in prod, and CI not running on the working branch. All three are fine.
- **Duplicate findings were reconciled, not double-counted** — the deploy-before-migrate window, the coupon
  over-redemption cap, and the `PublishScheduledPost` double-publish were each found by 2–3 independent
  dimensions and are recorded once.
- `devops.md` **does not exist** in the standards repo (404, directory listing confirms). Dimensions 24/26/32
  fall back to generic practice.
- **Not verifiable statically, and not claimed:** actual test pass rate and coverage % (no PHP on this machine;
  CI also disables coverage), live HTTP header behaviour, real CVE status, and production runtime behaviour.

---
## Batch 1 (Dimensions 1–11) — Results

**Batch score: 46.0 / 100.** Findings: **5 CRITICAL · 16 HIGH · 21 MEDIUM · 25 LOW**.
Every CRITICAL below was independently re-verified by the lead auditor against source before being recorded.

| # | Dimension | Score | C | H | M | L | Status |
|---|-----------|:-----:|:-:|:-:|:-:|:-:|:------:|
| 1 | Pagination Standards | 6.0/10 | 0 | 1 | 3 | 4 | WARN |
| 2 | Error Framework | 5.0/10 | 0 | 2 | 3 | 2 | WARN |
| 3 | Route Organization | 8.0/10 | 0 | 0 | 2 | 3 | PASS |
| 4 | Bootstrap & Initialization | 2.0/10 | 1 | 2 | 3 | 2 | FAIL |
| 5 | Runtime Safety | 5.5/10 | 0 | 2 | 2 | 2 | WARN |
| 6 | Auth Protection | 1.0/10 | 2 | 1 | 2 | 2 | FAIL |
| 7 | IDOR & Access Control | 9.0/10 | 0 | 0 | 0 | 3 | PASS |
| 8 | SQL Safety | 9.5/10 | 0 | 0 | 0 | 2 | PASS |
| 9 | Input Validation | 0.0/10 | 2 | 3 | 1 | 3 | FAIL |
| 11 | Telemetry & Observability | 0.0/10 | 0 | 5 | 5 | 2 | FAIL |

> Scoring note: dimensions 9 and 11 hit the rubric's zero floor. That understates both. Dimension 9
> found **zero** unvalidated write endpoints across ~55 body-accepting routes and consistent array/length
> bounds — its score is driven entirely by two upload endpoints and three unbounded arrays. Dimension 11
> reflects a stack that was wired up *today* and is not yet switched on. Read the findings, not the number.

---

### CRITICAL BLOCKERS

#### CB-1 — Account pre-hijacking via unverified email + auto-linked social login
**Dimension 6 (Auth) · VERIFIED · `app/Services/AuthService.php:37-73`**

`User` does not implement `MustVerifyEmail`. `AuthService::register()` never sets `email_verified_at`, and
`LoginController::login()` never checks it — a password account is fully usable the instant it is created.
Separately, `findOrCreateSocialUser()` matches an existing account **by email address** and attaches the new
OAuth identity to it. The `elseif` branch then back-fills `email_verified_at = now()`.

Attack chain:
1. Attacker registers with the victim's email and a password of their choosing. No verification required.
2. Victim later clicks "Sign in with Google" using that same email.
3. Email matches → the Google identity is attached to the **attacker's** account, which is simultaneously
   laundered into a "verified" account by the victim's own OAuth.
4. Victim is logged into the attacker's account. Attacker retains permanent password access to the victim's
   posts, DMs, purchases and business profiles.

**Fix:** require email verification before an account can log in (or at minimum before it is eligible for
social auto-linking); never auto-link an OAuth identity to an existing password-holding account without an
explicit password challenge or verified-email confirmation step.

#### CB-2 — Sanctum bearer tokens never expire and cannot be revoked by the user
**Dimension 6 (Auth) · VERIFIED · `config/sanctum.php:53`, `Auth/TokenController.php:32`**

`'expiration' => null` globally; `createToken($device_name)` passes no `expiresAt`. There are **no token
management routes at all** in `routes/api_v1.php` — no list, no revoke. The only paths that clear tokens are
an admin action in Filament and full account deletion.

**Fix:** set a finite `expiration` (30–90 days) and add self-service `GET`/`DELETE /me/tokens`.

#### CB-3 — Unrestricted file upload: digital product deliverable
**Dimension 9 (Input Validation) · VERIFIED · `StoreProductController.php:67-69`**

`$request->validate(['file' => ['required', 'file', 'max:51200']])` — size only. No `mimes:`, no `image`, no
extension allow-list, no content sniffing, no malware scan. Any business-profile owner or manager can upload
arbitrary bytes (executables, scripts, polyglots) as a paid product's deliverable, served to buyers via
`PurchaseController::download`. Contrast `StoreMediaRequest.php:14-24`, which does this correctly.

**Fix:** add an explicit `mimes:` allow-list scoped to the product types actually sold.

#### CB-4 — Unrestricted file upload: course lesson attachment
**Dimension 9 (Input Validation) · VERIFIED · `StoreCourseController.php:97`**

Identical gap: `['required', 'file', 'max:51200']` and nothing else. Served to enrolled buyers via
`CourseController::attachment`.

**Fix:** same as CB-3.

#### CB-5 — No graceful shutdown: every deploy SIGKILLs in-flight jobs
**Dimension 4 (Bootstrap) · VERIFIED · `apps/api/docker/supervisord.conf`, `docker-compose.prod.yml:38-54`**

No `stopwaitsecs` on any supervisord program and no `stop_grace_period` on any compose service — both default
to **10 seconds**. The `api` container runs php-fpm + 2 `queue:work` workers + Reverb + scheduler under one
PID 1, and `ProcessVideoUpload` shells out to ffmpeg. Laravel's own SIGTERM handling is present and correct
(pcntl installed, `queue:work` traps it) but is defeated: Docker SIGKILLs the container before the worker can
drain. Every `scripts/update-vps.sh` run kills in-flight transcodes and drops Reverb sockets uncleanly.

**Fix:** set `stopwaitsecs` (+ explicit `stopsignal=TERM`) per supervisord program and a matching
`stop_grace_period` on the `api` service, sized to the longest expected job.

---

### HIGH PRIORITY (16)

**Auth & access**
- No MFA anywhere in either app — raises blast radius of CB-1/CB-2. (`dim 6`)

**Uploads & input**
- `UploadService.php:87-134` — chunked upload never re-verifies assembled bytes against the client-declared
  `mime`/`size_bytes`; per-chunk size is unvalidated, so declaring `size_bytes=1` and sending one huge chunk
  bypasses `MEDIA_MAX_VIDEO_MB` entirely. Content-confusion + storage-exhaustion. (`dim 9`)
- `StoreCourseController.php:110-115` (`reorder`) — unbounded `modules`/`lessons` arrays, one UPDATE per entry. (`dim 9`)
- `PostInteractionController.php:37-40` (`quizAttempt`) — unbounded `answers` array. (`dim 9`)

**Observability** (all `dim 11`)
- OpenTelemetry entirely absent — direct violation of `sre.md`'s MANDATORY PHP requirement. Sentry does not
  satisfy it: `traces_sample_rate` is `0.0`, so there is no distributed tracing of any kind.
- `.env.prod.example:12` sets `LOG_CHANNEL=stderr`, which bypasses the `stack` channel — so `LOG_STACK` is
  **inert in production** and `LOG_STDERR_FORMATTER` is set nowhere, falling back to plain-text `LineFormatter`.
  JSON logging is unreachable in the documented prod config, not merely opt-in.
- `config/logging.php:58` — `json` channel opt-in and off by default; `/app/storage/logs` is not a volume, so
  file-channel logs are ephemeral and invisible to `docker logs`.
- `SentryScrubber.php:58-87` — **breadcrumbs are never scrubbed**, despite the class docblock claiming they are.
  Sentry defaults turn every `Log::` call, SQL query and outbound HTTP request into a breadcrumb. `query_string`,
  `url`, exception messages and `getUser()` are also unredacted. The unit test covers none of these gaps.
- `apps/web` has **no `beforeSend` scrubber at all** across all three `Sentry.init()` calls; browser UI-click
  breadcrumbs capture clicked element text, which on a social app is user content.

**Runtime & bootstrap**
- No `failed()` handler on any of 7 jobs and no `Queue::failing()` listener — a permanently-failing job lands
  in `failed_jobs` silently. A stuck `PublishScheduledPost` never publishes and nobody is told. (`dim 5`)
- No segment-level `error.tsx` anywhere; only root `global-error.tsx`. One crashing widget white-screens the
  entire app shell. (`dim 5`)
- `update-vps.sh:53,61` — new container serves traffic **before** `migrate --force` runs; a failed migration
  aborts the script with no rollback, leaving prod half-migrated. (`dim 4`)
- `IntegrationSettingsProvider.php:24-38` + stock `/up` — DB unavailability at boot is swallowed and `/up` has
  no dependency probe, so an unreachable database reports healthy. (`dim 4`)

**API contract**
- `bootstrap/app.php:38-45` — no centralised API error envelope; shape varies by exception class. (`dim 2`)
- Banned-user response hand-duplicated in 3 places, with a 4th inconsistent variant in `SocialAuthController`. (`dim 2`)
- `LessonController.php:32-44` — `learn/lessons` returns the entire table with an eager-loaded relation on
  every authenticated request, no pagination, no cap. (`dim 1`)

---

### NOTABLE PASSES

- **IDOR (9.0/10) — the `X-Profile-Id` header is correctly verified.** `SetActiveProfile.php:19-27` resolves the
  ULID then `abort(403)` unless `isAccessibleBy()` — owner (`user_id`) or a `ProfileMember` pivot row. Personal
  profiles have no pivot rows so it degrades to strict ownership. ~35 controllers follow a consistent
  scope-then-verify idiom. No mass-assignment escalation: `is_admin`/`is_super_admin` are not `$fillable` and no
  controller passes `$request->all()` into a write.
- **SQL Safety (9.5/10).** All 42 raw-SQL sites inventoried; every dynamic value reaches the query as a `?`
  binding. The one interpolated identifier (`PostStatsService::bump`, `$metric`) is allow-listed immediately
  before use. Migrations are 100% Schema-builder DDL. No dynamic `orderBy` from request input.
- **Pagination.** No client-controlled page size exists anywhere in the codebase — structurally eliminates the
  `?per_page=100000` class. Zero mixed offset/cursor endpoints. Consistent `Paginated<T>` envelope, codified in
  the web client's types.
- **Route Organization (8.0/10).** Single registration point, 21/224 routes public, each with a documented
  rationale and a defense (throttle, HMAC, or privacy-aware resource).

### STRUCK FINDING (false positive)

Dimension 3 flagged `streaming/webhook` as possibly unauthenticated. **Rejected on verification:**
`MuxStreamDriver::parseWebhook` verifies the `Mux-Signature` HMAC and returns `null` on failure; the null driver
parses nothing. It fails closed outside local/testing and logs when the secret is missing. Not carried forward.

---

## Batch 2 (Dimensions 12–20) — Results

**Batch score: 49.75 / 90.** Findings: **1 CRITICAL · 13 HIGH · 24 MEDIUM · 22 LOW**.

| # | Dimension | Score | C | H | M | L | Status |
|---|-----------|:-----:|:-:|:-:|:-:|:-:|:------:|
| 12 | Health Checks | 5.5/10 | 0 | 2 | 2 | 1 | WARN |
| 13 | Configuration Management | 6.0/10 | 0 | 1 | 3 | 3 | WARN |
| 14 | Connection Management | 4.0/10 | 0 | 3 | 2 | 3 | WARN |
| 15 | Logging & PII Safety | 6.0/10 | 0 | 1 | 4 | 2 | WARN |
| 16 | Idempotency | 2.0/10 | 1 | 2 | 3 | 2 | FAIL |
| 17 | API Documentation | 6.0/10 | 0 | 2 | 2 | 0 | WARN |
| 18 | Technical Debt | 7.5/10 | 0 | 0 | 3 | 4 | PASS |
| 19 | Testing Coverage | 8.0/10 | 0 | 0 | 2 | 4 | PASS |
| 20 | Dependency Management | 4.75/10 | 0 | 2 | 3 | 3 | WARN |

---

### CRITICAL BLOCKER

#### CB-6 — Buyers can be charged twice for one entitlement
**Dimension 16 (Idempotency) · VERIFIED · `CheckoutController.php:30-90`, migration `2026_07_19_160000_create_orders_tables.php:52`**

`CheckoutController::store()` creates a new `Order` on **every** POST with no guard whatsoever: no
existing-pending-order check, no `Purchase::ownedBy()` check, no idempotency key. `resolveProduct()`
validates visibility only. The `purchases` table has `unique(['buyer_profile_id','product_id'])`, so
`Order::markPaid()`'s `Purchase::firstOrCreate()` silently no-ops the second time.

Net effect: a buyer who already owns a product can buy it again — **charged in full, entitlement granted
once**, vendor credited `vendor_amount_cents` for a sale that delivers nothing. Requires manual refund.

> **Correction to the explorer's framing:** this was reported as a concurrency race on double-click. It is
> not a race — it is a missing guard, reachable sequentially by any buyer purchasing the same product twice.
> Double-clicking is merely the fastest trigger. The only mitigation present is `throttle:checkout` at 12/min,
> which is irrelevant at human click speed. This makes it both more likely to have already occurred in
> production and simpler to fix than a locking problem would be.

**Fix:** before creating the order, abort if `Purchase::ownedBy($buyer, $product)` or if an unexpired
pending order exists for the same buyer+product. Add `orders.idempotency_key` (unique) fed by a client-supplied
`Idempotency-Key` header and return the existing order on replay.

---

### HIGH PRIORITY (13)

**Money & correctness**
- `CheckoutPricing.php:80-94` + `CheckoutController.php:43-49,84` — coupon `max_redemptions` is checked with
  an **unlocked read outside the transaction**; `increment()` is atomic but the cap is never re-checked under
  lock. Two buyers at `redeemed_count=99/100` both pass, ending at 101. Per-buyer reuse *is* correctly blocked
  by the `unique(coupon_id, buyer_profile_id)` constraint — only the aggregate cap leaks. (`dim 16`)
- `PayFastDriver.php:53-83` — no `pf_payment_id` dedup ledger. Replay-safety rests entirely on `Order.status`
  under `markPaid()`'s lock, with no independent second guard. Currently correct; no defence in depth. (`dim 16`)
- `AppServiceProvider.php:122-144` vs `bootstrap/providers.php:8-10` — `warnOnRiskyPaymentConfig()` runs before
  `IntegrationSettingsProvider` applies DB overrides, so a super-admin enabling PayFast **sandbox in production**
  via the Integrations page routes real checkouts to sandbox with no warning ever logged. (`dim 13`)
  *(Independently found by dim 4 as a provider-ordering bug — same root cause, two dimensions.)*

**Operational**
- `bootstrap/app.php:20` — only the stock `/up` liveness route exists. **No readiness probe**; it returns 200
  while MySQL is unreachable. (`dim 12`)
- `docker-compose.prod.yml:38-87` — **no `healthcheck:` block on `api`, `web`, `nginx`, or `redis`**. Nothing
  ever calls `/up`. A hung php-fpm or dead Next.js process runs "successfully" forever. (`dim 12`)
- `config/database.php:156-180` — Redis has **no `timeout`/`read_timeout`**. Redis backs cache, session, queue
  and broadcast, so a hung Redis blocks nearly every request until nginx's 60s timeout. (`dim 14`)
- `config/database.php:62-64,82-84` — no `PDO::ATTR_TIMEOUT`; a slow/unreachable MySQL holds php-fpm workers
  for the OS TCP timeout. With only ~5 `pm.max_children` (unconfigured image default), a handful of stalls
  starves the pool. (`dim 14`)
- `ProcessVideoUpload.php` / `ProcessAudioUpload.php` — **no `$timeout` on any job**, so Laravel's 60s default
  kills ffmpeg transcodes; `retry_after=90` then re-dispatches. Combined with dim 5's missing `failed()` hooks,
  **video upload failure is silent end-to-end.** (`dim 14`) — verified directly.

**Supply chain & contract**
- No `composer audit` / `pnpm audit` / `roave/security-advisories` anywhere in CI. (`dim 20`)
- No Dependabot or Renovate config. (`dim 20`)
- No OpenAPI spec for any of 224 routes — `php.md` § OpenAPI Documentation is MANDATORY. (`dim 17`)
- The bearer-token path (`POST /auth/token`) that the planned mobile app requires is **entirely undocumented** —
  no docblock, no README, no schema, and the required `X-Profile-Id` header is discoverable only by reading
  the web client's source. Concrete blocker for ask #2. (`dim 17`)
- `config/logging.php` — no secret-redaction Monolog processor exists, contrary to `php.md` § Secret Redaction
  Patterns (MANDATORY). No current call site abuses it, but there is no backstop. (`dim 15`)

---

### DOWNGRADED FINDING

Dimension 15 reported a CRITICAL: private DM bodies written to logs via `BROADCAST_CONNECTION=log` and
Laravel's `LogBroadcaster`. **Downgraded to MEDIUM on verification.** `.env.prod.example:31` sets
`BROADCAST_CONNECTION=reverb` and `config/broadcasting.php` defaults to `null` — only `apps/api/.env.example:73`
(the **dev** template) uses `log`. The real risk is narrower: local dev logs contain full message bodies and
sender identity, and anyone bootstrapping a staging box from `.env.example` inherits it silently. One-line fix,
but not the shipped production config.

### NOTABLE PASSES

- **`Order::markPaid()` (`Order.php:110-153`) is textbook-correct** — `DB::transaction` + `lockForUpdate` +
  re-check under lock + `firstOrCreate` for entitlements, with a real concurrency-shaped test. The CB-6 defect
  is upstream of it, not in it.
- **PayFast ITN verification is layered properly** — `hash_equals` signature check, mandatory server postback
  to PayFast, `COMPLETE` status check, and amount-to-the-cent match, all before `markPaid()`.
- **Technical debt is unusually low** — 10 TODOs, **zero** FIXME/HACK/XXX, zero commented-out blocks, zero
  `@ts-ignore`/`any` in authored web code, zero phpstan suppressions across ~1,100 source files.
- **Payments/checkout is the best-tested domain** — ITN valid/duplicate/bad-signature/amount-mismatch paths,
  coupon rules, VAT math to the cent. And `ActiveProfileTest.php` explicitly asserts `X-Profile-Id` spoofing
  is forbidden, corroborating dim 7.
- **`MasterResetService` is well-guarded** — typed "RESET" confirmation plus `is_super_admin` gating on both
  `canAccess()` and navigation registration.

### NEW FINDINGS NOT IN THE PRIOR AUDIT

- `apps/web/src/lib/api/public-feed.ts:17` calls `GET /api/v1/public/feed` and `/public/business/directory`,
  **neither of which exists** in `routes/api_v1.php`. The guest `/explore` acquisition page therefore always
  renders its empty state for every logged-out visitor. (`dim 18`)
- `apps/api/.github/workflows/tests.yml` is stray Laravel-skeleton boilerplate targeting `master`/`*.x`
  branches — dead CI config that never runs and duplicates root `ci.yml`. (`dim 20`)

---

## Batch 3 (Dimensions 21–30) — Results

**Batch score: 33.0 / 100.** Findings after dedup: **2 CRITICAL · 28 HIGH · 26 MEDIUM · 24 LOW**.

| # | Dimension | Score | C | H | M | L | Status |
|---|-----------|:-----:|:-:|:-:|:-:|:-:|:------:|
| 21 | Performance Patterns | 2.5/10 | 1 | 2 | 2 | 2 | FAIL |
| 22 | Concurrency Safety | 2.0/10 | 0 | 4 | 2 | 4 | FAIL |
| 23 | Migration Safety | 2.0/10 | 1 | 2 | 3 | 2 | FAIL |
| 24 | Container Security | 3.75/10 | 0 | 3 | 2 | 3 | FAIL |
| 25 | HTTP Hardening | 1.75/10 | 0 | 3 | 6 | 3 | FAIL |
| 26 | CI/CD Pipeline | 5.0/10 | 0 | 2 | 3 | 2 | WARN |
| 27 | Async Reliability | 0.5/10 | 0 | 5 | 3 | 2 | FAIL |
| 28 | Core Dependencies | 1.25/10 | 0 | 5 | 2 | 1 | FAIL |
| 29 | Naming Conventions | 8.0/10 | 0 | 1 | 0 | 2 | PASS |
| 30 | Domain Modeling | 6.25/10 | 0 | 1 | 3 | 3 | WARN |

---

### CRITICAL BLOCKERS

#### CB-7 — N+1 on the main feed: every profile costs 2–3 extra queries
**Dimension 21 · VERIFIED · `ProfileResource.php:20-21`, `Profile.php:362-386`, `SafetyService.php:90-98`**

`ProfileResource::toArray()` calls `relationshipStateFor($viewer)` (an **uncached** `Block` query via `SafetyService::viewerBlocked()` + a `Follow` query) and `isViewableBy($viewer)` for **every profile it renders**. It is nested at `PostResource.php:50`, so a 20-post feed page with distinct authors issues ~40–60 extra queries. Same for every comment/reply author and every row of the business directory.

`SafetyService` already memoizes `blockedProfileIds()`/`mutedProfileIds()` per request — but `viewerBlocked()` bypasses that cache and hits the DB every call. And `Model::preventLazyLoading()` is absent repo-wide, so nothing catches this in dev; dev/CI run SQLite where the round-trips are cheap enough to go unnoticed.

**Fix:** add a `ViewerRelationship::hydrate()` bulk helper mirroring the existing, correct `App\Support\ViewerReactions` (one `whereIn` for all Follows), and replace `viewerBlocked()` with the already-cached `isBlockedBetween()`. Enable `Model::preventLazyLoading(!app()->isProduction())`.

#### CB-8 — Rollback trap: `down()` destroys encrypted secrets
**Dimension 23 · `2026_07_30_110000_widen_encrypted_columns.php:29-39`**

`up()` widens `settings.value` `json→text` and `webhook_endpoints.secret`/`header_value` `varchar(255)→text` **because ciphertext is longer than plaintext and is not valid JSON** (the migration says so itself). `down()` naively narrows them back.

Any `migrate:rollback` after data has been written either throws on `settings.value` (MySQL validates JSON on `MODIFY ... JSON`), blocking rollback of every migration after it, or silently truncates `webhook_endpoints.secret` to 255 bytes under non-strict mode — destroying the HMAC secrets used to sign outbound CRM webhooks. `update-vps.sh` takes no backup before `migrate --force`, so there is nothing to restore from.

**Fix:** make `down()` a documented no-op — `text` is a safe superset; never narrow.

---

### HIGH PRIORITY — Batch 3 (28)

**Concurrency (dim 22)** — all verified as unbacked by DB constraints:
- `MessagingService::findOrCreateDm` — check-then-create with no possible unique constraint on the participant pair. Two tabs create two permanent parallel DM threads; messages fragment across them.
- `xp_ledger` has **no unique index** on `(profile_id, action_key, subject_type, subject_id)`, so `subjectAlreadyAwarded()` is an unbacked guard. Affects all 10 `award()` call sites — double XP on any double-tap.
- `MessagingService.php:205` — `messages_count` computed as `$conversation->messages_count + 1` in PHP memory, not `increment()`. Concurrent sends in a group chat permanently undercount.
- `CommentController.php:36-61` (`markHelpful`) — no transaction at all; double-click double-increments `helpful_count` and double-awards XP.

**Async (dim 27)**
- `after_commit => false` on every queue connection + `ProfileObserver` dispatching `DeliverWebhook` inside `AuthService::register()`'s transaction with no `->afterCommit()` → a rollback leaves the customer's CRM holding a **phantom contact**. `PostService.php:315` does use `->afterCommit()`, so the hazard is known — just applied inconsistently.
- `ProcessVideoUpload` has no `$timeout`, no `failed()` → media stuck in `STATUS_PROCESSING` forever, and `ensureReadyForPublish()` then blocks the post from ever publishing, with no error shown to the user.
- `DeliverWebhook` — no delivery-status tracking anywhere. `webhook_endpoints` has no `last_status`/`last_delivered_at`; support cannot answer "why didn't my CRM get this?" without deserializing `failed_jobs` by hand.
- `PublishScheduledPost` double-publish (dedup'd from dims 16/22) — unlocked `isPublished()` guard outside the transaction; duplicates `posts_count`, XP and notifications.
- Supervisord `queue` program has no `stopwaitsecs`, so SIGKILL at 10s is the realistic *trigger* for the double-processing above on every deploy.

**HTTP hardening (dim 25)**
- `/storage/` location re-asserts only `nosniff` and silently drops **HSTS, X-Frame-Options and Referrer-Policy** for every user-uploaded file (nginx `add_header` non-inheritance).
- **No CSP anywhere** — not in nginx, not in `next.config.ts`, no middleware. For a social app rendering user content this is the biggest single header gap.
- `SESSION_SECURE_COOKIE` never set in any env template; `config/session.php:172` has no fallback, so session and XSRF cookies ship without `Secure` unless an operator remembers.

**Container (dim 24)**
- No `USER` directive in `apps/api/Dockerfile`; supervisord runs `user=root`.
- **No `.dockerignore` anywhere** — and `apps/web`'s build context is the repo root, so `.git`, `docs/`, `AUDIT-FINDINGS.md` and all of `apps/api` are shipped into the build stage.
- `certbot/certbot:latest` — the only floating tag in the prod stack, on a privileged internet-facing cert tool.

**CI/CD (dim 26)**
- **CI never builds either Dockerfile.** Tests run directly on the runner; the VPS builds prod images from source at deploy time. The artifact that ships is never validated.
- `apps/api/.github/workflows/*.yml` (4 files) sit at a **nested** `.github/` path GitHub Actions never reads — dead config that never runs, carrying dormant `issues: write`/`contents: write` grants.

**Standards conformance (dim 28)** — see the framing note in the Executive Summary; these are Bee-standard gaps, not defects:
- `phpstan`/`larastan` absent (MANDATORY, level 8+ required) · `roave/security-advisories` absent (MANDATORY) · OpenTelemetry SDK absent (MANDATORY) · 7 of 9 mandated tsconfig strict flags unset, `skipLibCheck` inverted · TanStack Query absent while `useEffect` data-fetching (explicitly forbidden by `frontend.md`) is used in 20+ files.

**Other**
- `LessonController.php:32-44` — `learn/lessons` returns the entire table with an eager-loaded relation on every authenticated request (dim 21).
- `ad_events.kind` `enum→varchar` is an `ALGORITHM=COPY` full-table rebuild on the highest-write table in the schema, run via blanket `migrate --force` (dim 23).
- JSON responses are snake_case where `php.md` mandates camelCase — 26/26 Resources, ~350 fields, 224 endpoints (dim 29). **Recorded, not recommended for remediation** — see below.
- `MasterclassLiveSession` declares no `$hidden`, so RTMP `stream_key`/`ingest_url` stay host-only only via manual field-picking in one controller method (dim 30).

---

### PROPORTIONALITY NOTES

**snake_case JSON (dim 29) — recorded, remediation NOT recommended.** The deviation is total but *uniform*: zero mixed casing anywhere, and DB column, JSON key, WebSocket payload and TS property all agree. A big-bang rename would touch ~327 TS files and create a genuinely dangerous half-migrated window. Recommended: document the deviation, lint to block new camelCase, and adopt camelCase behind a `/v2` prefix only if a mobile client forces it.

**Makefile / License headers.** This is a pnpm+Docker monorepo, not a Make project, and a closed-source SaaS. "17 required Make targets missing" and "0/465 files lack a copyright header" are category errors against this stack and were scored accordingly. The *real* finding in that area is that `apps/api/composer.json` is still the unedited Laravel skeleton — `"name": "laravel/laravel"`, `"license": "MIT"` — which actively misstates the licensing posture of a proprietary product, and no LICENSE file exists to clarify it.

### REFUTED HYPOTHESES

Three suspicions I explicitly planted in explorer prompts were investigated and **disproven**:
- *"Queue worker runs ffmpeg as root"* — false. `supervisord.conf` pins `user=www-data` on queue, scheduler and reverb.
- *"MySQL/Redis published to the internet in prod"* — false. Only nginx publishes; `api`/`web` use `expose:`, DB/cache publish nothing.
- *"CI doesn't run on the working branch"* — false. `ci.yml` triggers on `claude/**`.

---

## Batches 4–5 (Dimensions 31–44) — Results

**Batch score: 70.75 / 130.** Findings after reconciliation: **4 CRITICAL · 12 HIGH · 32 MEDIUM · 27 LOW**.

| # | Dimension | Score | C | H | M | L | Status |
|---|-----------|:-----:|:-:|:-:|:-:|:-:|:------:|
| 31 | Linting & Code Quality | 4.75/10 | 0 | 2 | 3 | 3 | WARN |
| 32 | Makefile & Dev Tooling | 6.0/10 | 0 | 0 | 6 | 4 | WARN |
| 33 | Multi-Tenant Patterns | — | — | — | — | — | **N/A — inactive** |
| 34 | License Headers | 9.25/10 | 0 | 0 | 1 | 1 | PASS |
| 35 | Nil/Null Safety | 8.75/10 | 0 | 0 | 1 | 3 | PASS |
| 36 | Resilience Patterns | 3.5/10 | 0 | 2 | 6 | 2 | FAIL |
| 37 | Secret Scanning | 9.25/10 | 0 | 0 | 0 | 3 | PASS |
| 38 | API Versioning | 8.25/10 | 0 | 0 | 3 | 1 | PASS |
| 39 | Graceful Degradation | 0.0/10 | 2 | 2 | 2 | 2 | FAIL |
| 40 | Caching Patterns | 2.5/10 | 1 | 2 | 2 | 2 | FAIL |
| 41 | Data Encryption at Rest | 1.75/10 | 1 | 2 | 3 | 3 | FAIL |
| 42 | Resource Leak Prevention | 5.75/10 | 0 | 2 | 1 | 3 | WARN |
| 43 | Rate Limiting | 2.0/10 | 0 | 4 | 3 | 2 | FAIL |
| 44 | CORS Configuration | 9.0/10 | 0 | 0 | 1 | 2 | PASS |

---

### CRITICAL BLOCKERS

#### CB-9 — PayFast ITN silently drops confirmed payments
**Dimension 36 (Resilience) · VERIFIED · `PayFastDriver.php:139-150`, `PayFastWebhookController.php:22-40`**

`validatePostback()` catches `\Throwable` and returns `false` on **any** transport failure — timeout, DNS blip,
PayFast 5xx — indistinguishably from "signature invalid". `handleWebhook()` then returns `null`, `markPaid()`
never runs, and the controller unconditionally returns `200`.

That 200 tells PayFast the notification was accepted, so **PayFast's own ITN retry never fires**. The buyer has
been charged; the order stays `pending`; no entitlement, no receipt, nothing logged or alerted. There is no
reconciliation command for pending orders (verified against `app/Console/Commands/`), so nothing ever recovers it.
It surfaces only as a support ticket.

The file's own docblock says *"retrying; the order is only marked paid when the notification verifies"* — the
retry intent is there, but the always-200 defeats it.

**Fix:** distinguish transport failure from invalid signature — return non-2xx on the former so PayFast retries,
keep 200 for a genuinely bad signature. Add a scheduled reconciliation command that queries PayFast for orders
left `pending` beyond a threshold.

#### CB-10 — Service worker serves one user's private DMs to the next user
**Dimension 40 · VERIFIED (4-link chain) · `apps/web/src/sw.ts:103,123`, `auth-store.ts:143,154`**

`sw.ts:103` spreads `...defaultCache` from `@serwist/next`. That default set includes
(`@serwist/next/dist/index.worker.mjs:155-158`) a rule matching `sameOrigin && pathname.startsWith("/api/")`
→ `NetworkFirst`, `cacheName: "apis"`. `USER_CACHES` (`sw.ts:123`) lists only `sbh-api-me`, `sbh-api-feeds`,
`sbh-api-notifications` — **not `"apis"`** — so `logout()`'s `purgeUserCaches()` never clears it.

Every `/api/v1/*` GET not claimed by the three explicit rules — conversations, DM message bodies, unread
counts, business matches, business-needs — is cached in browser storage keyed by **URL alone**. Profile
scoping travels in the `X-Profile-Id` *header*, which Workbox does not include in the cache key.

On a shared device: user A logs out, user B logs in, network is slow (`NetworkFirst` has a 10s timeout) →
**user B is served user A's private DMs.** POPIA exposure.

`switchProfile()` (`auth-store.ts:154`) compounds it — it never calls `purgeUserCaches()` at all, so the same
leak occurs across profiles within one account.

> The mechanism to prevent this **already exists** — a purpose-built `sbh-purge-user-cache` message handler
> and a logout hook. The defect is coverage: a third-party default ruleset silently added a bucket the purge
> list doesn't know about.

**Fix:** add `"apis"` (and `"others"`, `"cross-origin"`) to `USER_CACHES`; add `NetworkOnly` overrides for
`/api/v1/conversations*`, `/api/v1/me/*`, `/api/v1/business/*` *ahead* of the `...defaultCache` spread; call
`purgeUserCaches()` from `switchProfile()`.

#### CB-11 — No backups exist anywhere
**Dimension 41 · VERIFIED · `scripts/`, `docker-compose.prod.yml`, `.env.prod.example`**

Zero references to `mysqldump`, backup, snapshot or any restore mechanism in the entire repo. `mysql-data`,
`redis-data` and `media-storage` are bare Docker named volumes on a single VPS. `update-vps.sh` runs
`migrate --force` — and on the reseed path `demo:seed --fresh` — against production with nothing to restore from.

Combined with **CB-8** (the `down()` rollback trap that destroys webhook HMAC secrets), there is no recovery
path from a bad migration. POPIA requires appropriate technical measures against accidental loss; there are none.

**This is the single most important operational finding in the audit.** Encryption-at-rest is moot when there
is no "rest" to restore.

#### CB-12 — Redis is an unguarded single point of failure for session + cache + queue
**Dimension 39 · `config/*.php`, `docker-compose.prod.yml`, `Setting.php:32`, `Features.php:56-59`**

Production runs `SESSION_DRIVER=redis`, `CACHE_STORE=redis` and `QUEUE_CONNECTION=redis` against one
non-clustered Redis container. A Redis outage is therefore **not** "lose realtime and caching" — it is total
application outage: nobody can authenticate, and every write path that calls `broadcast()`/`dispatch()`
in-request 500s *after* its DB transaction already committed (`MessagingService.php:216,226,268,432,453`,
`GamificationService.php:101`, `CommentService.php:92`, `ReactionService.php:208`, `PollVoteService.php:73`).
Users see failures for actions that actually succeeded — and retry them.

Worse, the **kill switch fails in the same incident it exists for**: `Setting::get()` reads through
`Cache::rememberForever` with no try/catch, so `Features::all()` — called from `MeController` on essentially
every app bootstrap — 500s instead of falling back to the `config/features.php` defaults that are sitting
right there.

**Fix:** wrap `Setting::get()` to fall back to config defaults on cache failure; wrap in-request `broadcast()`
/`dispatch()` calls so a queue outage degrades rather than 500s a committed write.

#### CB-13 — PayFast receipts permanently lost when the queue is unavailable
**Dimension 39 · VERIFIED · `PayFastWebhookController.php:22-40`**

```
$order = $gateway->handleWebhook($request);
if ($order !== null && $order->markPaid()) {   // ← MySQL write commits here
    $webhooks->contact(...);                    // ← throws if Redis is down
    $this->sendReceipts($order);                // ← never runs
}
return response('', 200);                       // ← never reached
```

PayFast receives a 500 and retries. On retry `markPaid()` returns `false` (already paid), so the entire block
is skipped. **The buyer's VAT receipt and the vendor's sale notice are lost forever**, on a payment that was
correctly recorded. The controller's own docblock states it must always return 200 "so PayFast stops retrying" —
the contract is broken by an unguarded queue dispatch.

**Fix:** wrap the post-`markPaid()` side effects in try/catch so the 200 is always returned; move receipt
dispatch to an idempotent, separately-retryable job keyed on order id.

---

### HIGH PRIORITY — Batches 4–5 (12)

**Rate limiting (dim 43)**
- **~179 of 224 routes carry no throttle at all**, and there is no global `throttle:api` on the group — unlisted
  routes are genuinely unlimited. Includes `POST posts` and `POST media` (CPU-bound WebP re-encode), while
  *comment* creation is capped at 30/min.
- **Search endpoints are unthrottled** while running unindexed leading-wildcard `LIKE` scans — the cheapest DoS
  surface in the app.
- **No `TrustProxies` configuration.** Currently correct *by accident* of topology (nginx → `fastcgi_pass` with
  no `X-Forwarded-For` forwarded; port 9000 `expose`d not published). Adding a CDN/LB later silently collapses
  every IP-keyed limit — including auth brute-force protection — into one shared bucket for all users.
- **No daily AI spend cap.** 15/min per user with no daily ceiling ⇒ ~21,600 calls/day sustainable per account.

**Resilience (dim 36)**
- Timeout cascade is inverted at the outer layers: nginx `fastcgi_read_timeout` unset (60s default),
  `request_terminate_timeout` and `max_execution_time` both **0/unlimited**, DB has no timeout. Only Laravel's
  per-call `Http::timeout()` bounds anything — accidental, not designed. A worker blocked on a non-HTTP
  operation has no kill switch.
- Only 1 of 9 outbound calls retries, with a flat 1000ms delay and no jitter. No circuit breaker anywhere.

**Encryption (dim 41)**
- `push_subscriptions.auth_token` and `masterclass_live_sessions.stream_key` are plaintext despite the latter's
  own migration comment calling it a "host-only secret" — inconsistent with the `EncryptedString` pattern
  already used for `WebhookEndpoint::secret`.
- MySQL connection TLS not enforced (`MYSQL_ATTR_SSL_CA` unset); mitigated only by ports not being published.

**Caching (dim 40)**
- `switchProfile()` never purges per-user SW caches → cross-profile data mixing (same mechanism as CB-10).
- `business:categories` and `topics:tree` have **no write-side invalidation** — an admin edit takes up to 1h /
  10min to appear, silently.

**Resource leaks (dim 42)**
- `UploadService::complete()` has no `try/finally` around `assemble()` — a mid-assembly failure orphans a
  partially-written file on the **public** disk that nothing ever cleans up (the hourly prune only handles chunks).
- `ProcessVideoUpload.php:85` — `tempnam(...)` creates a file, then `.'.jpg'` is appended, producing a *different*
  path. `finally` unlinks only the `.jpg`; the file `tempnam()` actually created is orphaned on **every** video
  upload. Downgraded from the explorer's CRITICAL: files are zero-byte (inode pressure, not disk) and `/tmp`
  clears on container restart, so deploy frequency bounds accumulation.

**Static analysis (dim 31)**
- No PHPStan/Larastan configured (`php.md` mandates level 8+), and **Pint is installed but never invoked** — no
  CI step, no script. PHP style and types are entirely unenforced across 465 files.

---

### NOTABLE PASSES — Batches 4–5

- **Secret Scanning (9.25) — clean.** 1,354 tracked files + full `git log --all` across 147 commits, including
  `--diff-filter=D` for secrets committed-then-deleted. Zero real secrets. Two false positives correctly
  rejected (`ASIA` inside base64 font data; `sk-ant-` in Pest fixtures that exist to *test* encryption-at-rest).
  `deploy-vps.sh` generates every prod secret from `/dev/urandom` and writes VAPID keys to a `chmod 600` file
  specifically so they never hit terminal scrollback.
- **Null Safety (8.75) — clean.** All 75 non-`findOrFail` call sites inspected; `abort_unless`/`?->`/`??` are
  house style. Notably, `noUncheckedIndexedAccess` is *off*, yet every array access in `apps/web` is manually
  length-guarded anyway.
- **CORS (9.0) — clean** on the check that matters: no wildcard origin, `allowed_origins_patterns` empty, so the
  forbidden wildcard-plus-credentials combination is absent. The `FRONTEND_URL` fallback **fails closed**.
- **API Versioning (8.25).** 224/224 routes under a single `/api/v1` prefix applied at one choke point.

---
