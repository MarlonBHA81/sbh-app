# SBH — Behavioral UX Patterns

Six persuasion/UX patterns applied across the SBH frontend as **behavior
changes** (design tokens, `PostCard`, pill buttons and the slate bottom nav are
untouched). This doc is the rule-book: follow it on future screens so the app
stays consistent and **honest**.

Guiding principle from the brief: *"Not all these rules apply — show what can be
adapted."* Each pattern below records a verdict (**Applies / Adapt /
Doesn't-apply**) and, crucially, what we **refuse to build** because it would
mislead users.

Each principle shipped as its own revert-safe phase branch off the smart-defaults
base:

| # | Pattern | Branch |
|---|---------|--------|
| 1 | Smart defaults | `claude/social-engagement-pwa-5yayus` (base) |
| 2 | Goal gradient | `claude/ux-phase2-goal-gradient` |
| 3 | Reciprocity | `claude/ux-phase3-reciprocity` |
| 4 | IKEA effect | `claude/ux-phase4-ikea-effect` |
| 5 | Loss aversion | `claude/ux-phase5-loss-aversion` |
| 6 | Contrast effect | `claude/ux-phase6-contrast-effect` |

---

## 1. Smart defaults — *Applies (adapt)*

**Rule:** no required field ships blank if a value can be inferred; every primary
button states its outcome, never a bare "Post"/"Submit"/"Search".

- **Event** opens seeded: next Saturday 10:00, +1h end, venue = profile location.
- **Job** opens with location = profile location; type already defaults Full-time.
- **Post type** defaults to the user's most-used type; honest fallback **Text**
  (SBH has no "Discussion" type).
- **Primary button** states the outcome: "Post to your N followers" (real
  count), "Publish event", "Publish job", else "Share with the community".
- Files: `lib/composer-defaults.ts`, `components/composer/composer.tsx`,
  `messages/*.json`.

**Doesn't apply:** promoted-post *budget* default — SBH ads are metrics-first,
there are no budgets. Duration defaults are handled in pattern 6.

**Stubs:** `mostUsedPostType()` (needs `me/stats`); public "Post to N members"
count (no member-count endpoint).

---

## 2. Goal gradient — *Applies fully*

**Rule:** never show a user at 0%.

- **Onboarding checklist** (`/home`) starts at **20%** with "Create your
  account" pre-checked; remaining steps link out with a "+20%" weight;
  auto-hides at 100%, dismissible per profile.
- **Profile strength meter** under the level badge (own profile): slim teal bar
  floored at 20% with a next-action chip ("Add your logo · +20%").
- **XP bar** is floored to a visible sliver so it never reads empty after a
  level-up; the "X / Y XP to {rank}" caption stays truthful.
- One source of truth: `lib/profile-strength.ts` feeds both the checklist and
  the meter. Files: `components/home/onboarding-checklist.tsx`,
  `components/profile-strength-meter.tsx`,
  `components/gamification/xp-progress-card.tsx`.

**Adapt note:** step weights are 5 × 20% (real), so the chip reads "+20%", not
the brief's illustrative "+15%".

**Stubs:** none — computed from existing `Profile` fields.

---

## 3. Reciprocity — *Adapt*

**Rule:** give value before asking; the signup prompt appears only on an
**action** and names the benefit.

- **Contextual CTA**: "Join SBH to reply to Thabo" / "…to follow {name}", not a
  generic wall. Profiles and posts were already fully readable by guests (no
  blur except sensitive) — that part was already correct.
- **Guest `/explore`**: public feed + business directory, browsable logged-out;
  the CTA sits under the feed, never as a gate. Business rows link to
  fully-viewable profiles (contacting is gated, viewing is not).
- **Value-first entry**: guests hitting `/` or `/home` land on `/explore`, not
  the sign-in wall; genuinely private areas still route to sign-in.
- Files: `components/posts/public-post-card.tsx` (`GuestCta`),
  `components/explore/explore-view.tsx`, `lib/public-routes.ts`,
  `app/(app)/layout.tsx`.

**Stubs:** `GET public/feed`, `GET public/business/directory` — both fail soft to
empty so Explore degrades gracefully until the endpoints exist.

---

## 4. IKEA effect — *Applies (adapt)*

**Rule:** let users build before the account exists; never lose what they built.

- **Build-first signup**: `/register` is industry → business name → brand colour
  → **then** email/password, button labeled **"Continue"**. A live profile card
  assembles as they answer. Two+ personalizing choices precede any credential
  field.
- **Persistence + hydration**: choices saved to `localStorage`
  (`lib/signup-draft.ts`) and hydrated on completion — industry is PATCHed onto
  the real profile.
- **Draft-through-signup**: the composer autosaves body text
  (`lib/compose-draft.ts`) and restores it on reopen; finishing signup routes to
  `/home?compose=draft` and reopens the composer with the text intact.
- Files: `app/(auth)/register/page.tsx`, `lib/brand-colors.ts`,
  `lib/signup-draft.ts`, `lib/compose-draft.ts`,
  `components/composer/composer.tsx`.

**Stubs:** `brand_color` profile persistence (no field yet — stored client-side).

---

## 5. Loss aversion — *Mostly doesn't apply; honest pieces only*

**Rule (hard):** no countdown on anything that doesn't truly expire, no guilt
buttons, no invented scarcity, neutral decline copy. **If a pattern requires
lying about stakes, don't build it.**

- **Streak chip**: frames the streak as owned ("Your 6-day streak") and warns of
  loss only when the backend confirms it truly ends today. No streak feature
  exists, so it renders nothing until real data arrives.
- **Draft reassurance**: "Draft saved — it'll be here when you're back"; empty
  state says drafts never expire (true — they persist indefinitely).
- Files: `components/gamification/streak-chip.tsx`, `lib/api/streak.ts`,
  `components/composer/use-save-post.ts`, `app/(app)/drafts/drafts-view.tsx`.

**Refused (do not build):**
- ❌ "Your draft expires tomorrow" — drafts never expire; this would invent
  scarcity.
- ❌ Promoted budget "running down" — no budgets exist. Campaign cards already
  frame duration honestly as "N days remaining".
- No guilt buttons or fear prompts exist in the app; decline copy stays neutral
  ("Cancel" only on genuine delete-confirmations).

**Stubs:** `GET me/streak`.

---

## 6. Contrast effect — *Doesn't apply as written; honest adaptation*

**Rule:** never show a price naked; anchor options against each other.

Pricing tiers and Rand framing have **no surface** — promotion is free /
metrics-first, there is no money anywhere in the app. Honest adaptation:

- **Promote duration as three tiers** (7 / 14 / 30 days) side by side, with **14
  pre-selected and tagged "Most popular"** so the shorter and longer runs anchor
  it. The slider remains for fine-tuning.
- Each tier's sub-label is a plain-language framing of the **same duration** ("1
  week" / "2 weeks" / "1 month") — never an invented per-day cost.
- File: `components/ads/promote-sheet.tsx`.

**Refused:** any Rand amount, per-day price, or "R0.12 per member" framing —
there are no real numbers to compute them from, so inventing them is off-limits.

**Stubs:** none (a real per-tier reach estimate could be added later as a
`// BACKEND-TODO` caption, but no number is shown until it's real).

---

## BACKEND-TODO summary

| # | Stub | Falls back to |
|---|------|---------------|
| 1 | `me/stats` → most-used post type | "text" |
| 1 | member/audience count → "Post to N members" | "Share with the community" |
| 3 | `GET public/feed` | empty Explore feed |
| 3 | `GET public/business/directory` | empty directory |
| 4 | `brand_color` on profile | localStorage (cosmetic) |
| 5 | `GET me/streak` | chip renders nothing |
| 6 | per-tier reach estimate | duration framing only |

## Acceptance criteria — status

- No blank defaultable field — ✅ (pattern 1)
- No 0% progress indicator — ✅ (pattern 2)
- No content gate before value — ✅ (pattern 3)
- No signup screen before ≥2 personalizing choices — ✅ (pattern 4)
- No fear-based copy — ✅ (pattern 5; scarcity claims refused)
- No isolated price — ✅ (pattern 6; no prices exist, none invented)
