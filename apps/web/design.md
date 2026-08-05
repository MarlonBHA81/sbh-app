# Design — SBH Community (web)

A locked design system for the SBH PWA. Every screen refresh reads this file first.
The **source of truth for values** is the token layer — `src/app/design-tokens.css`
(brand `--sbh-*` + `.dark` remaps) and `src/app/globals.css` (shadcn mapping,
`@theme inline` utilities, fonts, motion). This file documents *how* to use them; it
does **not** redefine colours. Consistency across screens is the goal, not variety.

## Genre
playful — soft surfaces, generously-rounded cards, soft layered shadows, hover-lift,
warm-but-restrained type. Never childish; low-chroma; no glassmorphism, no gradient
text, no italic headings.

## Corporate identity — preserved verbatim
- **Colours** (unchanged): teal `#4e8a88` (accent/ring + teal "hero" actions),
  plum `#683f59`, sage `#5d7868`, gold `#a8801e`, slate `#484851` (neutral/structural
  primary), warmgray borders, warm paper `#f6f4f3`, white surface. No coral introduced.
- **Typography** (unchanged): **Poppins** (headings, buttons, labels, nav) via
  `--font-heading`; **Mulish** (body) via `--font-sans`; Geist Mono for code. Loaded
  through `next/font` in `src/app/layout.tsx`. No font or weight-system changes.

## App-shell family (every screen)
Sticky rounded header (`HeaderIconButton` 40px circular controls) → vertical rhythm of
soft cards (`gap-4/5`) → floating tab bar with a centered **teal compose FAB** (mobile)
/ sidebar with a teal "Post" CTA (desktop). Screens vary in content, not in this shape.

## Shape & elevation (the refresh levers)
- Radii: `rounded-(--radius-card)` 20px · `rounded-(--radius-tile)` 24px ·
  `rounded-(--radius-media)` 16px · `rounded-(--radius-hero)` 24px · pills `rounded-full`.
- Shadows: `shadow-card` (soft, layered) · `shadow-lift` (hover) · `shadow-fab`
  (teal glow, FAB + teal-hero CTAs). All have `.dark` counterparts in design-tokens.css.
- Hover-lift: add `className="sbh-lift"` to interactive cards (pointer-only, flattened
  under `prefers-reduced-motion`).

## Motion
Named easings `--ease-out` / `--ease-in` / `--ease-in-out`, durations `--dur-fast/base/slow`.
Reveal = soft; hover = `sbh-lift`; press = `active:scale-[0.98]`. Smooth easings only
(no spring/overshoot). Honour `prefers-reduced-motion`.

## CTA voice
- **Primary (neutral):** slate fill, pill, Poppins — structural actions.
- **Hero (teal):** teal fill + `shadow-fab`, pill — the compose FAB and key
  create / enrol / buy actions (`Button` `hero` intent).
- **Secondary:** warmgray-bordered surface pill (`ghost`/`outline`).

## Locked component patterns (restructures)
- **Bottom nav:** floating rounded bar, 2 + 2 items around a centered circular teal
  compose FAB (opens the composer).
- **Media post card:** large rounded image, overlaid author/text, **right-side vertical
  engagement rail** (like / comment / share + counts). Non-media posts keep a refreshed
  horizontal action row.
- **Profile:** cover full-bleed with the avatar overlapping its lower edge (**banner
  behind the avatar**); 3-column stat block (Posts / Followers / Following); pill
  Follow / Message; **segmented tab control**; soft media grid.

## What every screen MUST share
The wordmark, the `--sbh-*` colours and their placement, Poppins + Mulish, the CTA voice
(pill shape + teal-hero/slate-primary split), card radii + `shadow-card`, and the
app-shell family. Dark mode is a first-class target — verify every change in both themes.

## What screens MAY differ on
Content composition and which cards appear; nothing in the token/CTA/shell system.

## Stamp
Refreshed CSS/components carry
`/* Hallmark · genre: playful · design-system: design.md · designed-as-app */`.
