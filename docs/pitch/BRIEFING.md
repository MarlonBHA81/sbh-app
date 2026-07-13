# SBH Community App — Pitch Briefing

> **For Cowork:** use this as the source brief to build a pitch/presentation deck.
> Screenshots referenced below live in `docs/pitch/screenshots/`. Suggested deck
> length: 12–16 slides. The tone is confident but honest — this is a real,
> working product, not a mockup.

---

## 1. One-liner

**SBH Community App** is a fast, installable mobile app (PWA) where small
business owners connect, share wins, get advice, and grow together — a
moderated, brand-owned alternative to scattered WhatsApp groups and noisy
generic social feeds.

## 2. The problem

Small business owners are isolated. Their support network is fragmented across
WhatsApp groups, Facebook groups full of ads, and generic platforms that don't
understand their journey. There's no single, safe, purpose-built space to ask
questions, celebrate wins, find local peers, discover opportunities, and learn.

## 3. The solution

One community app, built for small business owners, that combines:

- A social feed designed around business moments (wins, questions, events, jobs)
- Real peer connection — direct messages, groups, local discovery
- Motivation through friendly competition (XP, ranks, challenges, leaderboards)
- A safe, actively-moderated environment
- A built-in path to monetisation (promoted posts / directory)

## 4. Who it's for

Small business owners on the go — mobile-first, often on slow or low-data
connections. Launch market: South Africa (built with POPIA compliance and a
local feel), architected to expand internationally (5 languages incl. RTL).

---

## 5. Product tour (screenshots)

Each screenshot is a real capture from the running app at phone width (390px).

| # | Screen | File | What it shows |
|---|--------|------|---------------|
| 1 | **Sign-in + consent** | `screenshots/00-login-cookies.png` | Branded entry, GDPR/POPIA cookie consent banner |
| 2 | **Home** | `screenshots/02-home.png` | Personalised greeting, one-tap composer, Quick Access tiles, activity feed |
| 3 | **Feed cards** | `screenshots/03-home-feed.png` | Rich post cards — jobs, events, media, polls |
| 4 | **Feeds** | `screenshots/04-feeds.png` | For You / Following / Nearby tabs |
| 5 | **Post + replies** | `screenshots/05-post-detail.png` | Threaded comments, reactions, votes |
| 6 | **Leaderboard + Challenges** | `screenshots/06-leaderboard.png` | XP ranking with admin-run challenges up top |
| 7 | **Events** | `screenshots/07-events.png` | Event posts with RSVP (Going / Interested) |
| 8 | **Discover** | `screenshots/08-discover.png` | Leaderboard, business hub, nearby, topic follows |
| 9 | **Business directory** | `screenshots/09-business.png` | Searchable directory + matchmaking |
| 10 | **Messages** | `screenshots/10-messages.png` | DMs and groups with unread badges |
| 11 | **Profile** | `screenshots/11-profile.png` | Avatar, level badge, follow/message, social links |
| 12 | **Challenge board** | `screenshots/12-challenge.png` | A challenge's own live XP leaderboard |
| 13 | **Privacy Policy** | `screenshots/13-privacy.png` | GDPR + POPIA compliance, public policy page |

**Suggested hero screenshots for the deck:** Home (2), Leaderboard+Challenges
(6), Profile (11). They're the most visually distinctive and on-brand.

---

## 6. Full feature list

### Community & social
- Personal profile + up to 3 business profiles per account, with an instant
  profile switcher
- Follows, including private accounts with request/approve
- 20+ post types: text, photos (multi-image), video, audio, blogs (rich text),
  links, quotes, reposts, polls, quizzes, events, job listings, check-ins,
  typewriter, magnifier, secrets, portfolios
- Threaded comments, likes, up/down votes, @mentions, reactions
- Three feeds: **For You** (personalised ranking), **Following**, **Nearby**
  (location-based) — plus topic feeds

### Discussion & discovery
- Topic tree you can browse and follow (acts as discussion forums)
- Typeahead search across people, businesses and posts
- **Nearby** map — find owners and businesses around you
- Business **directory** with category filters
- Business **matchmaking** — pairs businesses by what they offer / seek

### Messaging
- Direct messages and group chats
- Typing indicators, read receipts, message reactions, reply threads, image share
- **Member-created groups require admin approval** before going live
- DM privacy controls (everyone / followers / no one)

### Gamification
- XP for posting and engagement, ranks/levels, badges
- Weekly and all-time **leaderboards**
- **Challenges** — time-boxed XP competitions created by admins, each with its
  own leaderboard; members opt in

### Events & opportunities
- Event posts with date, venue, RSVP (Going / Interested)
- Dedicated Events screen; geo-filtered upcoming events
- Job listings as a first-class post type

### Safety & moderation
- Mute, block (two-way hide + severs follows), private accounts
- Sensitive-content blur with per-user preference
- Multi-step reporting on posts, comments and profiles
- Admin moderation queue with audit-logged actions, bans, and **optional
  AI-assisted report triage**
- Rate limiting against spam and abuse

### Monetisation (admin-controlled)
- **Ad Center** — promote posts; metrics-first campaigns (impressions, post
  opens, link clicks, CTR, unique reach)
- Ad spots shown inline in feeds and the desktop right rail
- Admins can **launch, pause, resume and end** campaigns from the admin panel
- Ad access restricted to admins

### Platform & admin
- Super-admin panel: platform analytics, user management, moderation, topics,
  ranks, XP config, challenges, group approvals, ads
- **Integrations** page: configure AI provider (Anthropic **or** OpenAI) and
  email provider (Brevo / Resend / SMTP) with live key management — no redeploy
- Master reset + demo-content seeding for staging

### Reach & polish
- **Installable PWA** — add to home screen, works offline (app shell), web push
  notifications
- **5 languages**: English, Bangla, Arabic (full RTL), Spanish, French
- Light / dark / **system** theme
- SEO: public read-only profiles and posts (crawlable), dynamic sitemap, rich
  social share cards
- Brand-consistent design throughout (SBH brand palette + typography)

### Trust & compliance
- **GDPR + POPIA** compliant: privacy policy, cookie policy, terms of service
- Cookie consent (accept / reject non-essential)
- Data-subject rights: **download my data** (export) and **delete my account**
  (full erasure) — self-service in Settings

---

## 7. Technology (for a credibility slide)

Laravel 12 API · Next.js 16 (React 19) · MySQL 8 + Redis · Laravel Reverb
(real-time websockets) · Tailwind 4 · Docker on a VPS. 600+ automated tests.
Live in production at `sbhapp.getstoryadvantage.com`.

---

## 8. What's live vs. what's next (be honest in the pitch)

**Live and working today:** everything in the feature list above, deployed and
running with automated test coverage.

**Hardening before heavy public launch** (small, known list — not blockers for a
beta):
- Video transcoding worker timeout (long videos need a config change to avoid
  getting stuck "processing")
- Move media to object storage + CDN (Cloudflare/R2) for scale beyond the single
  server — the app is built for this switch; it isn't wired yet
- A few security hardening items from our internal audit (admin-privilege gating,
  token revocation on logout, upload size re-validation) — all identified, none
  exploited, fixes scoped

**Roadmap ideas:** curated resources/guides library, automated event reminders,
native app-store wrappers (Phase 2).

---

## 9. Suggested deck flow (for Cowork)

1. Title — SBH Community App + one-liner + hero screenshot (Home)
2. The problem — isolation & fragmentation for small business owners
3. The solution — one purpose-built community (3 bullets)
4. Product tour — 4–5 screenshots (Home, Feed, Leaderboard/Challenges, Profile,
   Messages)
5. Community & engagement — follows, feeds, gamification
6. Safe space — moderation, approvals, privacy
7. Monetisation — Ad Center + directory (revenue slide)
8. Reach — PWA, 5 languages, SEO, offline
9. Trust & compliance — GDPR/POPIA, data rights (screenshot 13)
10. Technology & scale — credibility slide
11. Status & roadmap — live today + what's next
12. Call to action — beta / investment / partnership ask

**Design cues for the deck:** use the SBH brand colours — Muted Teal `#4e8a88`
(primary), Dusty Plum `#683f59`, Sage `#5d7868`, Charcoal Slate `#484851` on a
warm near-white `#f6f4f3`. Headings in Poppins SemiBold. Keep it clean and warm,
not corporate-cold.
