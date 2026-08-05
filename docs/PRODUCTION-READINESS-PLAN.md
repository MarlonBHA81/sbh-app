# SBH — Production Readiness Plan

**Handoff document. Self-contained — you do not need the conversation that produced it.**

---

## Read this first: provenance

This plan came from a 43-dimension audit run on **2026-08-03** against
`github.com/MarlonBHA81/sbh-app`, branch `claude/social-engagement-pwa-5yayus`, commit **`6718601`**.

**Production runs the same repo and the same branch, 4 commits behind.** Confirmed on the server
(`/opt/sbh-app`, 2026-08-05):

```
6718601  Add Sentry error tracking to the web app            ← audited here
36c7552  Add signed alert-webhook → GitHub-issue triage
33f0094  Add Sentry error tracking + JSON logging to the API
161ff90  Add production-readiness audit findings (docs only)
87bf810  Restructure profile + lift feed/discover cards      ← PRODUCTION
```

Production is a strict ancestor of the audited commit, so **every finding here applies to the live
app**, and fixes written against `6718601` fast-forward cleanly onto it. (The server's
`M docker/nginx/prod.conf` is just `update-vps.sh` substituting the real domain; it restores that
file before each pull.)

### Important consequence: production currently has NO error tracking

Both Sentry commits are among the four not deployed. The audit describes Sentry as "present but
dormant until a DSN is set" — in production it is not dormant, it is **absent**. So every
observability finding (PII scrubber breadcrumb gaps, the web DSN build-arg problem, the JSON log
channel being unreachable) is **moot for production today** and becomes live the moment you deploy.

Related: **deploying any fix from this plan also deploys those four commits.** One deploy brings
Sentry (API + web), the alert webhook, *and* the P0 fixes. Review the Sentry DSN configuration
before pushing — more changes than just the fixes.

### How to work through this

Every item is written as:

> **Symptom** → **How to verify it in the tree** → **Fix** → **How to prove it's fixed**

Run the verification step first anyway. It is cheap, it confirms the defect is where the audit says
it is, and it catches anything changed since. If a verification comes back clean, skip the item and
say so rather than "fixing" something that isn't broken.

### Confidence tiers

- **[LIVE]** — measured against the running production server. True regardless of lineage.
- **[CODE]** — found by reading source at `6718601`. Very likely still true, but verify.
- **[INFERRED]** — deduced from config templates (`.env.prod.example`), not from production itself.
  Must be checked against the real `.env.prod` on the server.

### Reference implementations

Working implementations of items P0-1 through P0-8 exist at `~/sbh-app` on this machine (branch
`claude/social-engagement-pwa-5yayus`, uncommitted). Because production is a strict ancestor of that
commit, **these apply directly** — they are not "reference only". Paths are given per item.

**None of them have been executed.** The machine that produced them has no PHP, no Docker and no
pnpm, so nothing was linted, no test was run, and no migration was applied. Treat all provided code
as unverified until your CI proves otherwise.

---

## Priority 0 — Block launch

These are exploitable, lose money, lose data, or expose one user's data to another.

---

### P0-1 · No backups exist [CODE, and almost certainly still true]

**Symptom.** No database dump, snapshot or restore mechanism anywhere. `mysql-data`, `redis-data`
and `media-storage` are bare Docker volumes on a single VPS. `update-vps.sh` runs
`php artisan migrate --force` — and on `--reseed`, `demo:seed --fresh` — against production with
nothing to roll back to.

**Why it is first.** Everything else on this list is recoverable if you have a backup. Nothing on
this list is recoverable if you don't. It also makes P1-2 (an irreversible migration) survivable.

**Verify:**
```bash
grep -rniE "mysqldump|pg_dump|backup|snapshot|restic|borg" scripts/ docker-compose.prod.yml .env.prod.example
# No hits => defect confirmed.
```

**Fix.** Two scripts plus a hook. Reference implementations:
- `~/sbh-app/scripts/backup-vps.sh` — `mysqldump --single-transaction` piped to gzip, plus a tar of
  the media volume via the api container. **Verifies the dump contains mysqldump's "Dump completed"
  marker before pruning any older backup**, so a failed run can never delete your last good one.
- `~/sbh-app/scripts/restore-vps.sh` — drops/recreates the DB, loads the dump, optionally restores
  media, re-runs migrations, requires typing `RESTORE` to proceed.

Then wire into `scripts/update-vps.sh`, **before** `migrate --force`:
```bash
echo "==> Taking a pre-migration database backup"
./scripts/backup-vps.sh --db-only
```
and a full backup before the `--reseed` branch.

Add `backups/` to `.gitignore` — dumps contain live PII and encrypted secrets.

**Prove it:**
```bash
./scripts/backup-vps.sh --db-only          # writes backups/sbh-db-<stamp>.sql.gz
gzip -dc backups/sbh-db-*.sql.gz | tail -5 # must end with "Dump completed"
```
Then **actually test the restore on a staging box.** An untested restore path is not a restore path.

**Also do:** cron it daily, and copy `backups/` off-box (rsync/rclone/S3). An on-box backup does not
survive losing the box.

---

### P0-2 · PayFast silently drops confirmed payments [CODE]

**Symptom.** In the ITN handler, the outbound call that asks PayFast "is this notification genuine?"
catches all exceptions and returns `false`. A network timeout is therefore indistinguishable from
"invalid signature". The webhook then returns **200 unconditionally**, which tells PayFast the
notification was accepted — so **PayFast never retries**. Buyer is charged; order stays `pending`;
no entitlement, no receipt, nothing logged. It surfaces only as a support ticket.

**Verify:**
```bash
# 1. Does the validation call swallow transport failures?
grep -n "catch" -B12 app/Services/Payments/PayFastDriver.php | grep -A12 "validate"
# Look for: catch (\Throwable) { return false; }

# 2. Does the controller always 200?
grep -n "response('', 200)\|response(''," app/Http/Controllers/Api/V1/PayFastWebhookController.php

# 3. Is there any reconciliation for stuck pending orders?
ls app/Console/Commands/ | grep -i "reconcil\|order"
```

**Fix.** Separate "PayFast said no" from "we could not ask PayFast".

Add an exception type (`~/sbh-app/apps/api/app/Services/Payments/PaymentGatewayUnavailable.php`),
then in the driver:

```php
private function validatePostback(array $data): bool
{
    try {
        $response = Http::asForm()
            ->timeout($this->config['timeout'] ?? 15)
            ->post($this->host().'/eng/query/validate', $data);
    } catch (\Throwable $e) {
        // Not a verdict — we could not ask. Caller must 5xx so PayFast retries.
        throw new PaymentGatewayUnavailable('PayFast ITN validation unreachable.', previous: $e);
    }

    if ($response->serverError()) {
        throw new PaymentGatewayUnavailable('PayFast validation returned '.$response->status().'.');
    }

    return trim($response->body()) === 'VALID';   // definitive answer
}
```

In the controller:
```php
try {
    $order = $gateway->handleWebhook($request);
} catch (PaymentGatewayUnavailable $e) {
    report($e);
    return response('', 503);   // PayFast redelivers
}
```

Add a reconciliation command that reports orders left `pending` past a threshold
(`~/sbh-app/apps/api/app/Console/Commands/ReconcileOrders.php`). It deliberately does **not** guess
their status — PayFast has no query API wired up here — it surfaces them for manual dashboard review
and exits non-zero so cron can alert.

**Prove it.** Tests in `~/sbh-app/apps/api/tests/Feature/Shop/PayFastItnTest.php`:
- `Http::fake(fn () => throw new ConnectionException(...))` → assert **503**, order still `pending`.
- `Http::response('', 502)` → assert **503**.
- `Http::response('INVALID', 200)` → assert **200** (this must not regress — a genuine rejection
  should still stop PayFast retrying).

---

### P0-3 · Payment receipts lost permanently when the queue is down [CODE]

**Symptom.** The webhook records the payment (`markPaid()` commits), then fires the CRM webhook and
queues receipt emails. If that second step throws — queue unavailable, Redis down — the exception
escapes before `return response('', 200)`. PayFast gets a 500 and retries. On retry `markPaid()`
returns `false` (already paid), the whole block is skipped, and **the buyer's VAT receipt and the
vendor's sale notice are never sent**, on a payment that succeeded.

**Verify:** in the ITN controller, check whether the post-`markPaid()` side effects are inside a
`try/catch`. If they aren't, the defect is present.

**Fix.** Two parts — the payment is already committed, so nothing after it may block the 200:

1. Wrap the side effects; log failures rather than letting them escape.
2. Make the failure **recoverable**, not merely logged. Add `orders.receipts_sent_at` (nullable
   timestamp) written only after mailables are handed off. A paid order with a NULL stamp is exactly
   "payment recorded, buyer never told" — and the reconciliation command from P0-2 replays it.

Reference: `~/sbh-app/apps/api/app/Services/Shop/OrderFulfilment.php` (idempotent on the stamp),
migration `2026_08_04_090000_add_receipts_sent_at_to_orders.php`.

> **Migration trap — do not skip.** Backfill existing paid orders to `receipts_sent_at = now()` in
> the same migration. Otherwise the first reconciler run emails **every historical customer** a
> duplicate receipt.

**Prove it:** force `Mail::` to throw, POST a valid ITN, assert **200**, assert order is `paid` and
`receipts_sent_at` is NULL. Then run the reconciler and assert the mail is queued and the stamp set.
Run it twice — the second run must send nothing.

---

### P0-4 · Buyers can be charged twice for one entitlement [CODE]

**Symptom.** Checkout creates a new `Order` on every POST with no guard: no already-owned check, no
existing-pending-order check, no idempotency key. `purchases` has a unique constraint on
`(buyer_profile_id, product_id)`, so `markPaid()`'s `Purchase::firstOrCreate` silently no-ops the
second grant. Net: buyer pays twice, receives one entitlement, vendor is credited for a phantom sale.

**This is not a race.** It is reachable sequentially by any buyer purchasing the same product twice.
A double-click is merely the fastest trigger. The only mitigation present is a 12/min rate limit,
which is irrelevant at human click speed.

**Verify:**
```bash
grep -n "ownedBy\|idempot\|STATUS_PENDING" app/Http/Controllers/Api/V1/CheckoutController.php
# No ownership guard and no pending-order reuse => defect confirmed.
```

**Fix.** Two guards, reference `~/sbh-app/apps/api/app/Http/Controllers/Api/V1/CheckoutController.php`:

```php
// 1. Already owned — refuse outright.
abort_if(Purchase::ownedBy($buyer, $product), 409,
    'You already own this — check your purchases.');

// 2. Identical re-submission — hand back the existing pending order rather
//    than minting a second payable one. Matched on product + total + coupon,
//    so a genuinely different basket still gets a fresh order.
$order = $this->equivalentPendingOrder($buyer, $product, $quote)
    ?? DB::transaction(fn () => /* existing creation */);
```

> **SQL trap.** In the lookup, the no-coupon case must use `whereNull('coupon_id')`.
> `where('coupon_id', null)` compiles to `= NULL`, which never matches.

**Prove it:** three tests in `~/sbh-app/apps/api/tests/Feature/Shop/CheckoutTest.php` — owned product
returns 409 and creates no order; identical re-submit returns the *same* order ulid with
`Order::count() === 1`; adding a bump creates a second order.

---

### P0-5 · Account pre-hijacking via unverified email + OAuth auto-link [CODE]

**Symptom.** The `User` model does not implement `MustVerifyEmail`; registration never sets
`email_verified_at`; login never checks it. Separately, the social-login path matches an existing
account **by email address** and attaches the OAuth identity to it — then back-fills
`email_verified_at = now()`.

Chain:
1. Attacker registers with the victim's email and a password of their choosing. Usable immediately.
2. Victim later clicks "Sign in with Google" with that same email.
3. Email matches → the Google identity is attached to the **attacker's** account, which is
   simultaneously laundered into a verified account by the victim's own OAuth.
4. Victim is logged into the attacker's account. Attacker keeps password access to the victim's
   posts, DMs, purchases and business profiles.

**Verify:**
```bash
grep -rn "MustVerifyEmail" app/Models/User.php               # expect: absent
grep -rn "email_verified_at" app/Http/Controllers/Api/V1/Auth/  # expect: no login gate
grep -n "where('email'" -A15 app/Services/AuthService.php    # look for the auto-link
```

**⚠️ This one needs a product decision, and the obvious fix is wrong.**

Requiring email verification **alone does not close it** — it can make it worse. The squatted account
starts unverified so the attacker can't log in, which looks safe. But the victim's OAuth sign-in
matches on email, links, and sets `email_verified_at`. The account is now verified and **the
attacker's password starts working.** Verification alone launders the account.

The load-bearing change is the **linking rule**:

```php
$user = User::where('email', $email)->first();

if ($user !== null && $user->password !== null) {
    // Someone with this email set a password. We cannot prove the OAuth
    // holder is that person. Do NOT link and do NOT log in.
    throw new SocialLinkRequiresPassword();
}
// Safe: no existing account, or an OAuth-only account nobody claimed with a password.
```

Front-end: on that error, tell the user to sign in with their password and link the provider from
settings.

- **Closes:** account takeover. Auto-link still works for OAuth-created accounts, so most users see
  no change.
- **Residual:** someone can squat an email to *block* OAuth sign-in for its real owner. Annoying,
  not a takeover. Closing that needs email verification at registration — which adds signup friction
  and depends on transactional email actually working in production (**unverified — see Open
  Questions**).

**This will break a currently-passing test.** `tests/Feature/Auth/SocialAuthTest.php` has
`test('social callback links an existing user by email')` which asserts the auto-link as *correct*.
That test encodes the current product intent. Updating it is part of the fix, and the change should
be a deliberate decision, not a silent one.

**Prove it:** attacker registers `victim@x` with a password → OAuth callback for `victim@x` must
**not** authenticate as that user. Existing OAuth-only accounts must still link cleanly.

---

### P0-6 · Bearer tokens never expire and cannot be revoked [CODE]

**Symptom.** `config/sanctum.php` has `'expiration' => null`, and `createToken()` passes no
`expiresAt`. There are **no token-management routes at all** — no list, no revoke. A leaked bearer
token is valid forever; only an admin in Filament or full account deletion clears it.

**Verify:**
```bash
grep -n "'expiration'" config/sanctum.php          # expect: null
grep -n "me/tokens\|tokens" routes/api_v1.php      # expect: no management routes
```

**Fix.**
1. `'expiration' => (int) env('SANCTUM_EXPIRATION_MINUTES', 60 * 24 * 90)` — 90 days.
2. Add `GET /me/tokens`, `DELETE /me/tokens/{id}`, `DELETE /me/tokens` (revoke all, including the
   caller's own — the "I lost my phone" button must not spare the compromised device).
3. Schedule `sanctum:prune-expired --hours=168` daily.

Reference: `~/sbh-app/apps/api/app/Http/Controllers/Api/V1/AccessTokenController.php`.

> **Deploy note.** Setting an expiration applies to **already-issued** tokens. Any token older than
> the window stops working on deploy and its client must re-authenticate. If a mobile client or
> integration is live, communicate this before shipping.

**Prove it:** issued token has non-null `expires_at`; a user can list their own tokens and see which
is current; revoking another user's token id returns **404**, not success.

---

### P0-7 · Unrestricted file upload on vendor deliverables [CODE]

**Symptom.** Product download files and course lesson attachments validate **size only**
(`['required','file','max:51200']`) — no MIME check, no extension allow-list, no scan. Any
business-profile owner or manager can upload arbitrary bytes (executables, scripts, polyglots) as a
paid product, served to buyers. Contrast the *image* upload path, which correctly uses
`image` + `mimes:`.

**Verify:**
```bash
grep -n "max:51200\|'file'" app/Http/Controllers/Api/V1/StoreProductController.php \
                            app/Http/Controllers/Api/V1/StoreCourseController.php
# Size rule with no mimes: => defect confirmed.
```

**Fix.** Add an explicit `mimes:` allow-list to both endpoints, driven from config so it is tunable
without a code change:
```php
'file' => ['required', 'file',
           'mimes:'.config('media.deliverable_mimes'),
           'max:'.config('media.deliverable_max_kb')],
```
Suggested list: `pdf,epub,zip,doc,docx,xls,xlsx,ppt,pptx,csv,txt,rtf,odt,ods,png,jpg,jpeg,webp,gif,mp3,m4a,wav,mp4,mov,webm`

**Exclude `svg`** — it can carry script and offers nothing here that png/webp/pdf don't.
**`zip` is deliberately included** — it is the primary legitimate deliverable format, and an archive
can contain anything. The allow-list bounds the obvious cases; it is not a substitute for content
scanning, which stays on the P2 list.

> **Size mismatch worth fixing here.** nginx sets `client_max_body_size 25m` while these endpoints
> allowed 50 MB. Anything between 25 and 50 MB was rejected by nginx with a bare 413 before Laravel
> ever validated it — so the documented ceiling was already unreachable. Either set
> `deliverable_max_kb` to 25600 to match, or raise nginx. Do not leave them disagreeing.

**Prove it:** uploading `payload.exe` (`application/x-msdownload`) returns 422 and leaves
`download_path` NULL. A `.pdf`-named file whose detected type is `application/x-sh` is also rejected
— `mimes:` checks the detected type, so renaming must not help.

---

### P0-8 · Service worker serves one user's private DMs to the next [CODE]

**Symptom.** `apps/web/src/sw.ts` spreads `...defaultCache` from `@serwist/next`. That default set
ends with a broad rule matching any same-origin `/api/` GET → `NetworkFirst`, `cacheName: "apis"`.
The app's logout purge lists only three cache names and **does not include `"apis"`**.

Profile scoping travels in the `X-Profile-Id` **header**, which Workbox does not include in the
cache key. So conversations, DM bodies, unread counts and business matches are cached in origin-wide
storage under keys identical across users. On a shared device: user A logs out, user B logs in,
network is slow (`NetworkFirst` has a 10s timeout) → **user B is served user A's private DMs.**

`switchProfile()` compounds it — it never purges at all, so the same leak occurs across profiles
within one account.

**Verify:**
```bash
grep -n "defaultCache\|USER_CACHES" apps/web/src/sw.ts
grep -n "purgeUserCaches" apps/web/src/lib/stores/auth-store.ts
# USER_CACHES missing "apis", and/or switchProfile not calling purge => confirmed.
```

**Fix.** Two layers — prevent the write, then make cleanup drift-proof:

1. **Don't cache it at all.** Add a `NetworkOnly` rule for `/api/` *immediately above*
   `...defaultCache`. `runtimeCaching` is first-match-wins, so the three deliberately-offline
   endpoints (matched by earlier rules) still win:
   ```ts
   {
     matcher: ({ url, sameOrigin }) => sameOrigin && url.pathname.startsWith("/api/"),
     handler: new NetworkOnly(),
   },
   ...defaultCache,
   ```
2. **Invert the purge to an allow-list.** The deny-list is what failed — it named three caches and
   silently missed a bucket a dependency added. Delete everything except static assets and the
   build precache, so a future dependency upgrade cannot reintroduce this:
   ```ts
   const survivesPurge = (name: string) =>
     name === "sbh-static-assets" || name.includes("precache");
   ```
3. Call the purge from **`switchProfile()`** as well as `logout()`.

Reference: `~/sbh-app/apps/web/src/sw.ts`, `~/sbh-app/apps/web/src/lib/stores/auth-store.ts`.

**Prove it:** manual is fine and more convincing than a unit test — log in as A, load DMs, log out,
log in as B, throttle the network in devtools, open DMs. B must never see A's threads. Check
Application → Cache Storage: no `"apis"` bucket should exist after logout.

---

### P0-9 · Cloudflare defeats every IP-based rate limit [LIVE — verified on production]

**Symptom.** Production sits behind Cloudflare (`server: cloudflare`, `cf-ray: …-JNB`,
`cf-cache-status: DYNAMIC`). The nginx config has **no `real_ip` handling** and Laravel has **no
`trustProxies()`** configuration. So `$request->ip()` returns a **Cloudflare edge IP** for every
request.

Consequence: every IP-keyed rate limiter is a **single shared bucket** for all users behind that
edge. `throttle:auth` at 5/min is not 5 attempts per user — it is 5 attempts for everyone.

- Brute-force protection is weakened (attacker attempts are pooled with legitimate ones).
- Worse, it is a **self-DoS**: one abuser exhausts the shared bucket and locks real users out of
  login.

This was rated "latent" in the original audit on the assumption that nginx proxied directly to
php-fpm. **That assumption was wrong** — the CDN is not visible anywhere in the repo. It is active
in production today.

**Verify:**
```bash
curl -sSI https://sbhapp.getstoryadvantage.com/api/v1/status | grep -iE "server|cf-ray"
grep -rnE "real_ip|CF-Connecting-IP|set_real_ip_from" docker/nginx/
grep -rn "trustProxies" bootstrap/app.php app/Http/Middleware/
```

**Fix.** Either layer works; doing both is belt-and-braces.

*nginx* (`docker/nginx/prod.conf`, inside the `server` block) — restore the real client IP from
Cloudflare's header, trusting only Cloudflare's published ranges:
```nginx
# Cloudflare IPv4/IPv6 ranges — refresh from https://www.cloudflare.com/ips/
set_real_ip_from 173.245.48.0/20;
set_real_ip_from 103.21.244.0/22;
# ... full list ...
real_ip_header CF-Connecting-IP;
real_ip_recursive on;
```

*Laravel* (`bootstrap/app.php`):
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO);
})
```

> **`at: '*'` is only safe because php-fpm is not reachable from the internet** — it is `expose`d,
> not `ports`-published, so only nginx can reach it. If that ever changes, `'*'` becomes a spoofing
> hole and must be narrowed to real ranges.

**Prove it:** log the resolved IP on a request and confirm it is your client IP, not a Cloudflare
range. Then hammer `/api/v1/auth/login` from one machine and confirm a *second* machine on a
different connection can still attempt a login.

---

### P0-10 · Every deploy SIGKILLs in-flight jobs [CODE]

**Symptom.** No `stopwaitsecs` on any supervisord program and no `stop_grace_period` on any compose
service — both default to **10 seconds**. The api container runs php-fpm + queue workers + Reverb +
scheduler under one PID 1, and video processing shells out to ffmpeg. Laravel's SIGTERM handling is
present and correct, but Docker kills the container before a worker can drain. Every
`update-vps.sh` run kills in-flight transcodes.

Compounding: no job declares `$timeout`, so Laravel's 60s default applies — shorter than a real
transcode — and `retry_after` (90s) is *lower* than a long job's runtime, so a still-running job gets
redelivered to a second worker and processed twice.

**Verify:**
```bash
grep -nE "stopwaitsecs|stopsignal|stopasgroup" apps/api/docker/supervisord.conf
grep -n "stop_grace_period" docker-compose.prod.yml
grep -n "retry_after" apps/api/config/queue.php
grep -rn "public.*timeout" apps/api/app/Jobs/
```

**Fix.** Two budgets that must hold:
```
compose stop_grace_period (150s) > supervisord stopwaitsecs (120s) > common job runtime
queue retry_after (660s)         > queue:work --timeout (600s)
```
The second line prevents duplicate processing — a job must never be redelivered while still running.
The first is best-effort: a very long transcode is still killed, but it is **not lost**, because an
unacked job is redelivered after `retry_after`.

Per-program in `supervisord.conf`:
- `[program:queue]` — `stopsignal=TERM`, `stopwaitsecs=120`, **`stopasgroup=true` + `killasgroup=true`**
  (without these the signal reaches only PHP and **ffmpeg is orphaned**, burning CPU after the
  container is meant to be gone), and add `--timeout=600` to the `queue:work` command.
- `[program:php-fpm]` — **`stopsignal=QUIT`**. php-fpm treats TERM as an immediate stop and QUIT as
  a graceful drain; supervisord's default is TERM, so this must be set explicitly.
- `[program:reverb]`, `[program:scheduler]` — `stopwaitsecs=30`.

Compose: `stop_grace_period: 150s` on `api`.

Reference: `~/sbh-app/apps/api/docker/supervisord.conf`, `~/sbh-app/docker-compose.prod.yml`.

**Prove it:** start a long job, `docker compose stop api`, confirm the container takes >10s to exit
and the job either completes or reappears in the queue. `ps aux | grep ffmpeg` on the host after
shutdown must show nothing.

---

## Priority 1 — Before meaningful traffic

---

### P1-1 · Redis is an unguarded SPOF, and the kill switch depends on it [CODE]

Production runs `SESSION_DRIVER`, `CACHE_STORE` and `QUEUE_CONNECTION` all on one non-clustered
Redis. An outage is therefore **total** outage — nobody can authenticate — and every write path that
calls `broadcast()`/`dispatch()` in-request 500s *after* its DB transaction committed, so users see
failures for actions that actually succeeded, and retry them.

Worse: `Setting::get()` reads through `Cache::rememberForever` with **no try/catch**, and
`Features::all()` is called on nearly every app bootstrap. So the super-admin feature-flag kill
switch — the thing you would reach for during an incident — fails *in the same incident*.

**Fix:** wrap `Setting::get()` to fall back to the `config/features.php` defaults on cache failure;
wrap in-request `broadcast()`/`dispatch()` calls so a queue outage degrades instead of 500-ing a
committed write.

### P1-2 · A migration's `down()` destroys encrypted secrets [CODE]

The migration that widened `settings.value` `json→text` (because ciphertext is not valid JSON) has a
`down()` that naively narrows it back. Rollback after data is written either throws — blocking
rollback of everything after it — or truncates `webhook_endpoints.secret` to 255 bytes, destroying
outbound webhook HMAC secrets.

**Fix:** make that `down()` a documented no-op. `text` is a safe superset; never narrow.
**Do P0-1 first** — without a backup this is unrecoverable.

### P1-3 · N+1 on the main feed [CODE]

`ProfileResource::toArray()` calls `relationshipStateFor()` — an **uncached** Block query plus a
Follow query — for every profile it renders, and it is nested inside `PostResource`. A 20-post feed
page issues ~40–60 extra queries. Same for every comment author and every directory row.
`SafetyService` already memoizes `blockedProfileIds()` per request, but `viewerBlocked()` bypasses
that cache.

**Fix:** add a bulk-hydrate helper mirroring the existing (correct) `ViewerReactions` pattern — one
`whereIn` for all Follows — and use the already-cached `isBlockedBetween()`. Then enable
`Model::preventLazyLoading(!app()->isProduction())` so this class of bug fails loudly in dev. Note
dev/CI run SQLite where the extra round-trips are cheap enough to go unnoticed.

### P1-4 · No readiness probe; nothing monitors container health [CODE]

Only Laravel's stock `/up` exists — it returns 200 whenever PHP boots, regardless of MySQL or Redis
state. And **no compose service defines a `healthcheck:`** except mysql, so nothing ever calls it.
A hung php-fpm or a dead Next.js process runs "successfully" forever.

**Fix:** add `/api/v1/ready` checking DB + Redis (+ queue depth), returning 503 on failure; add
`healthcheck:` blocks for `api`, `web`, `nginx`; give redis a healthcheck and gate api's
`depends_on` on `service_healthy` rather than `service_started`.

### P1-5 · Silent job failure [CODE]

No job defines `failed()` and there is no `Queue::failing()` listener, so a permanently-failing job
lands in `failed_jobs` with **zero alerting**. Combined with P0-10's missing `$timeout`, a video
upload can fail end-to-end silently: transcode killed at 60s, retried, exhausted, media left in
`STATUS_PROCESSING` forever — and `ensureReadyForPublish()` then blocks the post from ever
publishing, with no error shown to the user.

**Fix:** register a single `Queue::failing()` listener reporting to Sentry; add `failed()` to the
media jobs to flip media out of `PROCESSING` into a failed state the UI can surface.

### P1-6 · Protected routes return 500 instead of 401 [LIVE — verified on production]

Unauthenticated requests to `auth:sanctum` routes return **500** unless the client sends
`Accept: application/json` — Laravel is trying to redirect to a `login` route that does not exist in
an API-only app. Confirmed live: `500` without the header, `401` with it.

This pollutes error tracking with fake 500s and returns a server error where an auth error belongs.

**Fix:** in `bootstrap/app.php`, force JSON for API routes, or define the `login` named route to
return 401 JSON.

### P1-7 · Rate limiting covers ~20% of routes [CODE]

**~179 of 224 routes carry no throttle at all**, and there is no global `throttle:api` on the group,
so unlisted routes are genuinely unlimited. Includes `POST posts` and `POST media` (CPU-bound image
re-encode) — while *comment* creation is capped at 30/min. The search endpoints, which run unindexed
leading-wildcard `LIKE` scans, have no limit whatsoever: the cheapest DoS surface in the app.

Also: the AI coach has a 15/min cap but **no daily ceiling**, so one account can sustain ~21,600
calls/day indefinitely against your API budget.

**Fix:** apply a sane default limiter to the whole authenticated group; throttle search specifically;
add a per-account daily cap on AI spend. Fix P0-9 first or the keys are meaningless.

### P1-8 · Concurrency defects with money and integrity impact [CODE]

- **Coupon over-redemption.** `max_redemptions` is checked with an unlocked read *outside* the
  transaction; `increment()` is atomic but the cap is never re-checked under lock. Two buyers at
  99/100 both pass. Per-buyer reuse *is* correctly blocked by a unique constraint — only the
  aggregate cap leaks. Fix: re-check under `lockForUpdate()`, or use a conditional
  `UPDATE ... WHERE redeemed_count < max_redemptions` and abort on 0 rows affected.
- **XP double-award.** `xp_ledger` has **no unique index** on
  `(profile_id, action_key, subject_type, subject_id)`, so the idempotency guard is unbacked.
  Affects all award call sites. Fix: add the index and catch the violation.
- **Duplicate DM conversations.** `findOrCreateDm` is check-then-create with no constraint possible
  on the participant pair — two tabs create two permanent parallel threads and messages fragment.
  Fix: a sorted `pair_key` column with a unique index, or a cache lock.
- **`markHelpful` has no transaction at all** — double-click double-increments the counter and
  double-awards XP.

### P1-9 · No CSP, and headers dropped on user media [CODE]

No Content-Security-Policy anywhere — not nginx, not `next.config.ts`, no middleware. For a social
app rendering user content this is the biggest single header gap.

Separately, the `/storage/` location re-asserts only `nosniff` and therefore **silently drops HSTS,
X-Frame-Options and Referrer-Policy** for every user-uploaded file — nginx `add_header` does not
inherit once a location declares its own.

**Fix:** add a CSP tuned for Next.js; re-declare the three dropped headers inside the `/storage/`
block.

### P1-10 · Secrets stored in plaintext [CODE]

`masterclass_live_sessions.stream_key` (the RTMP ingest secret — its own migration comment calls it
"host-only") and `push_subscriptions.auth_token` are plaintext, inconsistent with the
`EncryptedString` cast already used for `webhook_endpoints.secret`. The stream key is also not in
`$hidden`, so it stays host-only purely by manual field-picking in one controller method — any future
Resource, log line or debug trace leaks it and lets someone hijack a broadcast.

**Fix:** add `$hidden` and the encryption cast to both.

---

## Priority 2 — Before scaling / next quarter

- **OpenAPI spec.** No machine-readable contract for 224 routes. `dedoc/scramble` infers from
  existing FormRequests and Resources with near-zero annotation burden. **This is a hard blocker for
  the planned mobile app**, whose bearer-token auth path is currently undocumented entirely.
- **Static analysis + format in CI.** PHPStan/Larastan absent; **Pint is installed but never
  invoked** — no CI step, no script. PHP style and types are unenforced across ~465 files.
- **Dependency scanning.** No `composer audit`, no `pnpm audit`, no Dependabot/Renovate anywhere.
- **CI never builds either Dockerfile.** Tests run on the runner; the VPS builds prod images from
  source at deploy time — so the artifact that ships is never validated before it ships.
- **`.dockerignore` missing entirely**, and the web build context is the repo root, so `.git`,
  `docs/` and all of `apps/api` are shipped into the build stage.
- **Connection timeouts.** Redis has no `timeout`/`read_timeout`; MySQL has no `PDO::ATTR_TIMEOUT`.
  A hung dependency holds php-fpm workers until nginx's 60s timeout.
- **Cache invalidation.** `business:categories` and `topics:tree` have no write-side invalidation —
  an admin edit takes up to 1h/10min to appear.
- **Dead dependencies.** `spatie/laravel-permission` and `laravel/scout` are installed and entirely
  unused — attack surface and upgrade burden for zero benefit.
- **Resource leaks.** `UploadService::complete()` has no `try/finally` around assembly, orphaning a
  partially-written file on the **public** disk that nothing cleans up. And a `tempnam()` call
  appends `.jpg` to the returned path, so the file `tempnam()` actually created is orphaned on every
  video upload.
- **Broken dev onboarding.** `docker compose up -d` cannot boot a clean checkout — the dev image
  never installs `vendor/`, and the bind mount would mask it anyway. `.env` creation and
  `key:generate` are undocumented. `composer run setup` exists and does most of this, but is
  referenced nowhere.

---

## Verified against production — true regardless of lineage

These were measured on `sbhapp.getstoryadvantage.com`, not read from source:

| Finding | Evidence |
|---|---|
| Cloudflare fronts the app; no real-IP handling (**P0-9**) | `server: cloudflare`, `cf-ray: …-JNB` |
| Protected routes 500 instead of 401 (**P1-6**) | 500 without `Accept: application/json`, 401 with |
| PHP version leaked | `x-powered-by: PHP/8.3.33` — set `expose_php=Off` |
| No CSP in production (**P1-9**) | absent from response headers |
| HSTS + X-Frame-Options **are** live | present and correct — credit where due |
| `observability/alert` not deployed | 404 while sibling POST-only routes return 405 — confirmed on the server as production being 4 commits behind |

---

## Open questions — answer before or during the work

1. ~~What commit is actually deployed?~~ **ANSWERED 2026-08-05: `87bf810`**, same repo and branch,
   4 commits behind the audited `6718601`. Still worth **surfacing a build SHA in `/api/v1/status`**
   so this never needs a server login to answer again.
2. **Does transactional email work in production?** Unverified. P0-5's stronger variant and P0-3's
   receipts both depend on it. Send one real test email before relying on either.
3. **Is PayFast in sandbox or live?** The audit found a boot-order bug where the "sandbox in
   production" warning reads config *before* DB overrides are applied — so a super-admin toggling
   sandbox on routes real checkouts to sandbox **with no warning ever logged**. Verify the live value
   directly, not via the warning.
4. **Is Reverb actually running?** If not, realtime is silently dead — the client degrades with no
   user-visible indication and there is no polling fallback for messaging.
5. **Backup destination.** On-box backups don't survive losing the box. Where do they go?

---

## Suggested execution order

1. **P0-1 backups** — alone, first, and *test the restore*. Nothing else is safe until this exists.
2. **P0-9 Cloudflare real-IP** — one config change, actively biting today, and it makes every rate
   limit meaningful (a prerequisite for P1-7).
3. **P0-2, P0-3, P0-4** — the money path, together. They share files and tests.
4. **P0-7, P0-6, P0-8** — independent, small, parallelisable.
5. **P0-5** — after the product decision on the linking rule.
6. **P0-10** — with the next deploy window, since verifying it requires a restart.
7. Then P1 in listed order.

**Ship P0-1 through P0-4 as separate commits, not one batch.** They touch payment code; if something
regresses you want to bisect cleanly.
