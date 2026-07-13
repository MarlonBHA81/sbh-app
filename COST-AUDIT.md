# SBH Community App — Cost Audit

Read-only audit of the codebase as of branch `claude/social-engagement-pwa-5yayus` (2026-07-13).
Every claim cites the file and line where it was found. Anything that cannot be determined
from code alone is flagged **NEEDS PROD DATA**. No code was changed for this audit.

Measurement note: the one live measurement in this document (feed payload size) was taken
against a local instance seeded with `demo:seed`; everything else is static analysis.

---

## 1. Media pipeline

### 1.1 Image uploads

| Question | Answer | Citation |
|---|---|---|
| Stored output format | **WebP**, hard-coded | `apps/api/app/Services/MediaService.php:23-27,52` |
| Quality | **82** | `apps/api/config/media.php:16` (`MEDIA_WEBP_QUALITY=82`, `.env.example:88`) |
| Dimensions | `scaleDown` to **max width 1920px**, aspect preserved, never upscaled; no height cap | `MediaService.php:26`, `config/media.php:18` |
| Derived sizes | Exactly **one thumbnail**: WebP, quality 82, max width **480px**, stored `{ulid}_thumb.webp` | `MediaService.php:29-31,36,40`, `config/media.php:20` |
| Originals retained? | **No.** Only the encoded WebP + thumb are written; the uploaded original is never persisted | `MediaService.php:39-40` |
| Avatar / cover processing | **No upload pipeline exists.** `avatar_path`/`cover_path` are plain columns; only demo-seeded PNGs populate them. No settings UI uploads them | `apps/api/app/Models/Profile.php:110-125`; absent from `apps/web/src/app/(app)/settings/profile/page.tsx` |

### 1.2 Video

| Question | Answer | Citation |
|---|---|---|
| Codec / container | **H.264 (libx264) + AAC audio, MP4** | `apps/api/app/Jobs/ProcessVideoUpload.php:76,79,104` |
| Resolution | Capped at **720p height**; downscale only, width rounded to even; sources ≤720p pass through unresized | `ProcessVideoUpload.php:69-70`, `config/media.php:43` |
| Rate control | **CRF 26, preset medium**. The ffmpeg wrapper also injects a default `-b:v 1000k`, but with libx264 CRF governs when both are present, so output bitrate is content-dependent | `ProcessVideoUpload.php:77`, `config/media.php:44`; wrapper default at `vendor/.../Format/Video/DefaultVideo.php:30` |
| Audio | AAC **128 kbit/s** (library default; not set in app code) | `vendor/.../Format/Audio/DefaultAudio.php:28` |
| Poster | 1 frame at second 1 → WebP q82, 480px wide | `ProcessVideoUpload.php:83-93`, `config/media.php:45` |
| Original retained? | **Deleted on successful transcode**; **retained unchanged** (up to 500 MB, no thumbnail) if ffmpeg is missing or the transcode fails | `ProcessVideoUpload.php:98-100` (delete), `:38-53` (fallback) |

**Estimated MB per minute of output (arithmetic shown):**

- Nominal (if the injected 1000k bitrate bound): (1000 + 128) kbit/s × 60 s ÷ 8 ÷ 1024 ≈ **8.3 MB/min**
- Realistic CRF-26 720p mixed-motion (~1.5 Mbit/s video): (1500 + 128) × 60 ÷ 8 ÷ 1024 ≈ **11.9 MB/min**
- Range: talking-head ~5–8 MB/min; high motion higher. Exact per-title bitrate: **NEEDS PROD DATA**.

### 1.3 Audio

**No transcode at all.** The job only probes duration; the original file is kept verbatim in its
uploaded format — a WAV upload stays WAV (`apps/api/app/Jobs/ProcessAudioUpload.php:14,33-42`).
Stored bytes = uploaded bytes, up to the 50 MB cap.

### 1.4 Average stored bytes per post (from settings; content mix NEEDS PROD DATA)

| Post type | Estimate | Basis |
|---|---|---|
| Photo post (1 image) | **≈ 0.3–0.55 MB** (full ≤1920px WebP q82 ≈ 250–500 KB + 480px thumb ≈ 20–50 KB; no original kept) | §1.1 settings; assumes a real ~1920×1080 photograph |
| Photo post (4 images, max grid) | ≈ 1.2–2.2 MB | 4 × above |
| 60-second video post | **≈ 8–12 MB** (transcoded MP4 + ~10 KB poster) — **but up to 500 MB if the ffmpeg-missing fallback branch runs** | §1.2 arithmetic; fallback `ProcessVideoUpload.php:38-53` |
| Audio post | = uploaded size, ≤50 MB (no compression) | §1.3 |

---

## 2. Media serving

**Path:** direct static file from nginx off the local Docker volume. Not S3/R2, not signed, not
proxied through Laravel.

1. `Media::url()` → `Storage::disk('public')->url($path)` → `https://{APP_URL}/storage/media/{profileUlid}/{ulid}.webp` — a plain, unsigned, non-expiring URL (`apps/api/app/Models/Media.php:86-96`; disk hard-coded `'public'` at `MediaService.php:38,46` and `UploadService.php:113`; URL base `config/filesystems.php:41-47`).
2. nginx serves `/storage/` via `alias /app/storage/app/public/` with `expires 7d` + `add_header Cache-Control "public, immutable"` (`docker/nginx/prod.conf:35-39`). The `^~` prefix wins over the Laravel regex block, so PHP is never touched for media.
3. An `s3` disk is defined (`config/filesystems.php:50-63`) but **nothing writes media to it** — media is chained to the single VPS disk today.

**Cache headers:**

- Media: `Expires: +7d` and `Cache-Control: public, immutable` (`docker/nginx/prod.conf:37-38`). Exact merged header string on the wire is nginx-version dependent — **NEEDS PROD DATA** for the byte-exact header.
- API JSON: **no cache headers configured anywhere** — measured live: `Cache-Control: no-cache, private` (framework default). Nothing in `bootstrap/app.php:25-31` or any controller sets caching.

**Feed payload (measured against local instance with demo data):**
`GET /api/v1/feeds/for-you` → 14 posts, **23.0 KB raw JSON (~1.7 KB/post), 4.1 KB gzipped**.
A full 20-post page extrapolates to ~33 KB raw / ~6 KB gzipped. Whether gzip/brotli is applied
on the wire depends on nginx config — no `gzip on` directive exists in `docker/nginx/prod.conf`,
so **API responses are currently served uncompressed** unless the nginx image default enables it
(**NEEDS PROD DATA**: confirm with `curl --compressed` against prod).

---

## 3. AI usage

**Ships disabled.** Default driver is `null` (`apps/api/config/ai.php:19`, `.env.example:13`);
`NullAiDriver` makes zero network calls (`apps/api/app/Services/Ai/Drivers/NullAiDriver.php:12-28`).
The admin Integrations page can flip it to Anthropic at runtime via the settings table
(`apps/api/app/Providers/IntegrationSettingsProvider.php:37-54`). Whether prod has a key set:
**NEEDS PROD DATA**.

Exactly **two** call sites, both through `AnthropicAiDriver`:

| | Call site A: moderation assist | Call site B: topic suggestions |
|---|---|---|
| Trigger | **Automatic**, once per newly filed user report (deduped against open reports) | **Automatic during compose**: fires 2s after typing pauses, only when draft ≥50 chars AND no topics selected yet; may re-fire as the user edits. Not a button; **not** on every keystroke and not once-per-post |
| Citation | `apps/api/app/Services/ReportService.php:61-62`, `apps/api/app/Jobs/AiModerationAssist.php:25-50` | `apps/web/src/components/composer/suggested-topics.tsx:10-11,41-54,81`; `apps/api/app/Http/Controllers/Api/V1/PostController.php:88-97` |
| Model | `claude-haiku-4-5-20251001` (default) | same | 
| System prompt tokens | ≈ **124** (496 chars/4) + reported content | ≈ **66** (262 chars/4) + draft text (≤5000 chars ≈ ≤1250 tokens) |
| max_tokens | **300** (`AnthropicAiDriver.php:51,118`) | **150** (`AnthropicAiDriver.php:83`) |
| temperature | Not set (API default) | Not set |
| Retries | **None** — single HTTP call, 15s timeout, log-and-null on failure (`AnthropicAiDriver.php:109-142`) | None |
| Rate limit | Upstream trigger capped by `throttle:reports` 10/min | `throttle:suggest-topics` **20/min per user** (`routes/api_v1.php:143-144`) |
| Spend guard | **None anywhere** — no daily cap, no budget, no per-account limit | None |

**AI calls per post (direct answer):** posting itself makes **0** AI calls. A typical compose
session makes **0** (AI disabled) or **1–3** debounced suggest-topics calls (AI enabled, user
types ≥50 chars before picking a topic). Reports add ~1 call each. At Haiku pricing these are
sub-cent events; the absence of any spend guard is the notable risk, not the per-call cost.

---

## 4. Email

- **No Mailable classes exist** (`apps/api/app/Mail` does not exist).
- All 12 activity notifications route to `['database','broadcast']` + web push only — **zero use the mail channel** (`apps/api/app/Notifications/ActivityNotification.php:45-53`).
- The **only** user-facing email is Laravel's built-in **password reset** (`apps/api/app/Http/Controllers/Api/V1/Auth/PasswordResetController.php:23`), plus an admin-initiated "test email" button (`apps/api/app/Filament/Pages/Integrations.php:184`).
- No email verification (User doesn't implement `MustVerifyEmail`, `apps/api/app/Models/User.php:16`). No digests — the scheduler runs only posts:publish-due, posts:refresh-scores, uploads:prune, ads:settle (`apps/api/routes/console.php:11-14`). No suppression/preference system (none needed).
- Default mailer is **`log`** (`config/mail.php:17`, `.env.example:56`) — outbound email is zero unless prod sets SMTP/Brevo/Resend via env or admin Integrations. **NEEDS PROD DATA**: prod mailer value.

**Emails per active user per month: ≈ 0** (occasional password reset, well under 1/user/month).
Any email line in a cost model beyond a transactional-tier free plan is unsupported by this code.

---

## 5. Websockets

**Events (9, all queued `ShouldBroadcast` — none broadcast synchronously):**

| Event | Channel | Type |
|---|---|---|
| CommentAdded, ReactionUpdated, PollVoteTallied | `post.{ulid}` | Public (`apps/api/app/Events/CommentAdded.php:15,26` et al.) |
| MessageSent, MessageDeleted, MessageReacted, ReadReceipt | `conversation.{ulid}` | **Presence** (`routes/channels.php:23-56`) |
| ConversationBumped (fan-out: one channel per recipient), XpAwarded | `profile.{ulid}` | Private (`channels.php:14-19`) |
| — (`nearby.{geohash4}` presence channel has **no** server events; join/leave only) | `nearby.{geohash}` | Presence (`channels.php:62-83`) |

**Client connection strategy:** one WebSocket opens on **every authenticated page** — the app
shell mounts Notifications/Messages/Gamification providers globally
(`apps/web/src/app/(app)/layout.tsx:78-80`), each subscribing to `profile.{ulid}` over a shared
module-singleton Echo connection (`apps/web/src/lib/echo.ts:21,86-112`). Per-screen additions:
`post.{ulid}` on post detail, `conversation.{ulid}` in an open chat, `nearby.{geohash}` on the map.

**Connection multipliers:** each browser tab = its own connection (module singleton is per-tab);
realtime silently disables if Reverb env vars are missing (`echo.ts:87-88`).

**Heartbeat:** Reverb defaults — `ping_interval` 60s, `activity_timeout` 30s
(`apps/api/config/reverb.php:86-87`, not overridden in `.env.prod.example`). Client uses pusher-js
defaults (no override in `echo.ts`) — **NEEDS PROD DATA** for negotiated values.

**Caps:** `max_connections` has **no default and is not set** → effectively unlimited
(`config/reverb.php:88`). Reverb rate limiting disabled by default (`:91-96`). Horizontal scaling
(Redis pub/sub) exists but is off (`:41`).

**Config flag found (possible live defect):** the web build bakes `NEXT_PUBLIC_REVERB_PORT="443"`
(`docker-compose.prod.yml:56`) but nginx terminates Reverb TLS on **8080**
(`docker/nginx/prod.conf`, last server block; compose publishes `8080:8080` at
`docker-compose.prod.yml:67`). Unless something remaps 443→8080 externally, browser websockets
fail silently and the app degrades to REST-only. **NEEDS PROD DATA** — verify with a browser
network tab against prod. (Cost impact if broken: zero WS cost today, and a step-up when fixed.)

---

## 6. Queues and jobs

**Jobs (all on the single `default` Redis queue — no queue separation):**

| Job | Dispatched by | Notes |
|---|---|---|
| ProcessVideoUpload | upload complete (`apps/api/app/Services/UploadService.php:128`) | ffmpeg transcode, CPU-heavy |
| ProcessAudioUpload | upload complete (`UploadService.php:130`) | duration probe only |
| PublishScheduledPost | `posts:publish-due` command | |
| RecomputePostScore | **every** comment/reaction/publish (`CommentService.php:82,128`, `ReactionService.php:207`, `PostService.php:311`) | highest volume job |
| AiModerationAssist | report filed (`ReportService.php:62`) | no-op when AI disabled |

**Scheduled tasks** (`apps/api/routes/console.php:11-14`): posts:publish-due every minute;
posts:refresh-scores every 15 min; uploads:prune hourly; ads:settle every 15 min.

**Video transcoding executes** in the same `api` container as php-fpm, the scheduler and the
Reverb server — supervisord runs `queue:work --tries=3 --backoff=3 --max-time=3600` with
**`numprocs=2`** and no `--queue` split (`apps/api/docker/supervisord.conf:16-26`).
**Effective transcode concurrency: 2, shared with every other job type** — a video burst delays
score recomputes and vice versa. No worker `--timeout` is set, so the default 60s job timeout
applies — long transcodes will be killed (also flagged in the stability audit).
No worker replicas or resource limits exist in `docker-compose.prod.yml` (services: mysql, redis,
api, web, nginx, certbot — `docker-compose.prod.yml:5-84`).

---

## 7. Database and Redis

### 7.1 Row-size estimates (from migrations; averages assumed, shown per table)

**Per user cluster ≈ 1.25 KB in MySQL** — users row ~272 B incl. indexes
(`database/migrations/0001_01_01_000000_create_users_table.php:14-22` + profile-fields migration),
profiles ~570 B × 1.3 profiles ≈ 740 B (`2026_07_11_120002_create_profiles_table.php:11-37`;
heavily indexed ~1.5×), 1 personal access token ~235 B. Sessions live in Redis in prod, not MySQL
(`.env.prod.example:26`).

**Per engaged post cluster ≈ 5 KB** — posts row ~800 B incl. ~2× index overhead
(`2026_07_11_130000_create_posts_table.php:11-42`), one media row ~400 B, 5 reactions ~500 B,
2 comments ~680 B, 1.5 topic pivots ~75 B, ~3 XP ledger rows ~390 B, and — the dominant term —
**~7 notification rows ≈ 2.1 KB** (uuid PK + TEXT data blob,
`2026_07_11_150003_create_notifications_table.php:11-18`). Polls with many votes or promoted
posts (one `ad_events` row per viewer per 5-minute window, `AdTrackingService.php:40-48`) push a
single post cluster to **10–100+ KB**. At 100k posts this is ~0.5–1 GB of MySQL — storage cost is
dominated by media files, not the database.

### 7.2 Heaviest feed queries and index gaps

- **for-you** (`apps/api/app/Services/Feed/FeedService.php:63-113`): multi-query per request — global-top subquery (top 100 by score over 7 days), then a candidate query whose `OR`-of-`whereIn`/`whereHas` defeats single-index use and filesorts on `score`; plus a `whereNotIn('ulid', …)` seen-set that can inject a **500-element NOT IN** (~13 KB of SQL) on every cursor page (`:97`, cap at `:33`); plus loading **all servable campaigns** each page (`:125-175`).
- **nearby** (`FeedService.php:211-235`): geohash `LIKE 'prefix%'` × up to 9 prefixes + row-by-row haversine trig — and **`posts.geohash` has no index** (`create_posts_table.php:24`), so this is a full-table scan per request.
- **local** (`FeedService.php:242-255`): filters on `country_code`/`city` — **neither indexed** on posts (`create_posts_table.php:25-26`); in a single-country deployment this scans effectively the whole posts table every call.
- **following** (`FeedService.php:40-52`): well-supported by `(profile_id,status,published_at)` — the cheap one.

**N+1 / hot-path write amplification (all previously confirmed in the stability/perf audits):**

- Conversations unread: **one COUNT per conversation** per list page, and the global badge counts every conversation the user has (`apps/api/app/Http/Controllers/Api/V1/ConversationController.php:130-146,153-171`).
- Notifications: every list/unread/read-all call does a JSON-path filter on an **unindexed TEXT column** (`NotificationController.php:60`; column at `create_notifications_table.php:15`).
- Leaderboard viewer row: weekly branch hydrates every profile's grouped XP sum into PHP to count a rank, uncached, on every request (`GamificationController.php:152-163`).
- PostSeenController: up to **~80 write statements per scroll batch** (2–4 writes × 20 posts) on the hottest endpoint (`PostSeenController.php:51-59`, `PostStatsService.php:51-59`).

### 7.3 Redis contents

Prod uses Redis for **cache + queues + sessions** (`.env.prod.example:24-35`). Key inventory:
leaderboards (5 min), for-you seen-sets `seen:{ulid}` (24h, ≤13 KB each), daily view dedupe
`seen-day:*` (48h), ad-impression dedupe `ad-imp:{viewer}:{campaign}` (5 min, high cardinality
under promoted campaigns), geocode cache `geo:rev:*` (**30 days**), matchmaking (5 min), topics
tree (10 min), public sitemap (1h, can be 100s of KB), settings (**forever**), sessions (120 min,
0.5–4 KB per device), and job payloads. Presence rosters live in the Reverb process (scaling off).

**Eviction policy: none configured** — `redis-server --appendonly yes` with no `--maxmemory` /
`--maxmemory-policy` (`docker-compose.prod.yml:24-29`) = Redis default **noeviction**: if memory
fills, Redis starts rejecting writes (sessions, queues) instead of evicting cache. Latent
incident risk, not a direct cost. **NEEDS PROD DATA**: actual Redis memory usage.

### 7.4 Untuned infrastructure (largest hidden constraint)

- **php-fpm: no pool config shipped** — stock `pm.max_children=5` caps the entire API at ~5 concurrent PHP requests (`apps/api/docker/` contains only `opcache.ini` + `supervisord.conf`).
- **MySQL: zero tuning** — default 128 MB buffer pool, 151 max connections (`docker-compose.prod.yml:5-22`).
- OpCache is the only tuned layer (`docker/opcache.ini`: 192 MB + JIT, timestamps off).

---

## 8. Abuse controls

**Named limiters** (`apps/api/app/Providers/AppServiceProvider.php:42-64`):

| Limiter | Limit | Applied to |
|---|---|---|
| auth | **5/min per IP** | register, login, token, forgot/reset password (`routes/api_v1.php:59-66`) |
| engagement | 60/min | reactions, votes, poll/quiz/RSVP (`api_v1.php:191-207`) |
| comments | 30/min | comment creation (`api_v1.php:211`) |
| messages | 60/min | message send (`api_v1.php:242-243`) |
| reports | 10/min | report filing (`api_v1.php:130`) |
| suggest-topics | 20/min | AI topic suggestions (`api_v1.php:143-144`) |
| inline | 60/min public reads (`:82`); 60/min posts/seen (`:153`); 120/min ads/track (`:168`); 20/min geo/reverse (`:221`) | |

**Unthrottled writes (gap):** `POST /posts` (compose, `api_v1.php:146`), `POST /media` (image
upload, `:132`) and all chunk-upload endpoints (`:136-139`) carry **no rate limiter**. A hostile
authenticated client can create posts/uploads at line speed; combined with the deleted-post media
leak found in the stability audit, uploads are the cheapest storage-abuse vector.

**File caps:** image 10 MB (`StoreMediaRequest.php:22`, `config/media.php:22`); video 500 MB,
audio 50 MB (`InitUploadRequest.php:21-33`, `config/media.php:37-39`); chunk 1 MiB
(`config/media.php:35`); nginx request body 25 MB (`docker/nginx/prod.conf:31`). Chunk count is
bounded only by size÷1 MiB (≤500 video / ≤50 audio). Note: the declared `size_bytes` is validated
at init, but the assembled byte count is **not re-checked against the cap** after assembly
(`UploadService.php:106,163-168`).

---

## 9. Summary: model assumptions vs. what the code says

Your model's assumed values weren't provided with this request, so the first column states the
assumption *implied* by a typical cost model of this shape — replace it with your actual numbers;
the "code's answer" and corrected columns stand either way.

| Item | Typical model assumption | The code's answer | Corrected estimate — and what changes from 1–2k to 5–10k concurrent |
|---|---|---|---|
| **Storage per post** | Original + multiple renditions kept; ~2–5 MB per photo post | Originals **discarded**; 1 WebP + 1 thumb ≈ **0.3–0.55 MB/photo post**; video ≈ **8–12 MB/min** (original deleted on success) — §1 | Budget ~0.5 MB per photo post, ~12 MB per avg video post. Storage grows with content, not concurrency: at 10k users posting ~0.5 media posts/day at 80/20 photo/video ≈ **~25 GB/month new media**. Watch the two leaks: failed transcodes keep ≤500 MB originals, and deleted posts never free disk (stability audit finding #5). |
| **Media serving path** | R2/CDN egress billed per GB | **Local VPS disk via nginx**, unsigned URLs, 7-day immutable cache — zero egress fees but consumes VPS bandwidth/disk and pins the app to one machine (§2) | 1–2k: fine on the VPS (media egress ≈ feed views × ~150 KB visible media/page). 5–10k: move to R2 (no egress fees, ~$0.015/GB-mo storage — ~$0.40/mo per 25 GB) + Cloudflare in front. The `FILESYSTEM_DISK` abstraction exists but media is hard-coded to `public` disk — a small code change (~1 day) is required, not just config. |
| **AI calls per post** | 1+ LLM call per post (moderation and/or suggestions) | **0 by default** (null driver). Enabled: 0–3 debounced Haiku suggest calls per compose session (20/min cap) + 1 per report; max_tokens 150/300; **no spend guard** (§3) | At 10k users × ~1 compose/day × ~2 calls × ~1.5k tokens in / 150 out on Haiku ≈ **low tens of $/month** — negligible. The correction to make: add a daily spend cap before enabling, since none exists in code. |
| **Emails per user/month** | 5–30 (notifications + digests) | **≈ 0** — no notification uses email, no digests; password reset only, and default mailer is `log` (§4) | Your email line item is ~zero until digest/marketing features are *built*. A transactional free tier (Brevo/Resend 300/day) covers password resets at any scale discussed. |
| **Websocket concurrency drivers** | 1 connection per active user | 1 per **tab** per authenticated page (connection opens app-wide); 3 standing subs on `profile.{ulid}` + per-screen channels; ping 60s; **no max_connections cap**; scaling mode off; a 443-vs-8080 port mismatch may mean prod websockets are currently failing silently (§5) | 1–2k concurrent ≈ 1.2–1.5k sockets (×~1.3 tab factor) — one Reverb process handles this. 5–10k ≈ 6.5–13k sockets: raise ulimits, set `REVERB_APP_MAX_CONNECTIONS`, and turn on `REVERB_SCALING_ENABLED` with a second Reverb process behind nginx. Verify the port mismatch first — you may be paying for zero websockets today. |
| **Single VPS bottleneck** | "CPU from video transcoding" | **php-fpm's default 5 workers** is the first wall (`~5 concurrent requests`), then MySQL's 128 MB buffer pool + unindexed nearby/local feed scans, then the 2 shared queue workers being killed at the default 60s job timeout mid-transcode (§6–7) | 1–2k concurrent: fix config first — fpm pool 30–80 children, MySQL buffer pool 1–2 GB, split video onto its own queue with a long timeout, add the 3 missing posts indexes (geohash, country_code+city) and batch the post-seen writes. That alone should carry 1–2k on an 8 vCPU VPS. 5–10k: those fixes are table stakes; add a separate DB server/managed MySQL, 2–4 stateless app nodes behind a load balancer (requires the R2 media move above), Redis with `maxmemory` + `allkeys-lru` for the cache DB, and Reverb scaling on. |

### The three numbers most likely wrong in any assumption-based model of this app

1. **Email ≈ 0**, not per-user (no email features exist).
2. **AI ≈ 0 today** (disabled by default) and single-digit-dollars when enabled — but uncapped.
3. **Media is on the VPS disk, not object storage** — the cost isn't egress fees, it's the
   migration work plus the disk-leak fixes before disk usage compounds.

### Consolidated NEEDS PROD DATA list

Real photo/video byte distribution and format mix · which transcode branch runs (is ffmpeg
present in the prod image?) · prod `.env` values for AI driver/key, mailer, Reverb host/port and
max connections · whether prod websockets currently connect at all (443/8080 mismatch) · actual
Redis memory usage · actual MySQL table sizes and slow-query log · per-user tab count and session
device count · gzip status on API responses in prod · password-reset frequency.
