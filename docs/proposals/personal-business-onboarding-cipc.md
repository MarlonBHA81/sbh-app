# Personal/Business Onboarding + CIPC-Verified Business Identity (v1.2.0)

**Status:** Shipped · **Date:** 2026-08-11 · **App:** SBH Community

## Summary

Tighten the line between a member's **personal** profile and a **business** profile, and make every business's identity **CIPC-verified**. Signup now clearly creates the personal profile; a first-login prompt invites a business profile; the onboarding checklist is profile-aware; business creation is **hard-gated on CIPC**; and the always-on Home tiles are now feature-flaggable.

## What shipped

### Onboarding clarity
- **Signup = personal.** The build-first wizard is kept but reframed so the copy honestly describes building *your personal profile* ("What's your industry?", "What should we call you?", "This is your personal profile — you can add a business profile once you're signed in").
- **Kind-aware checklist.** `computeProfileStrength` branches by profile kind: personal shows Add a profile photo · Follow 3 members · Make your first post (the business-only "Set your industry" step is dropped); business shows Add your logo · Set your industry · Follow · Post.
- **First-login business prompt.** A dismissible Home card ("Grow with a business profile") appears when the active profile is personal and the member has no business profile yet.

### CIPC-verified business identity (hard gate)
- `POST /api/v1/me/profiles` with `kind=business` now **requires** a CIPC `registration_number` (SA format `YYYY/NNNNNN/NN`) and verifies it via the CIPC verifier **before** creating anything.
  - Verified → profile created; `registration_number` + `cipc_registered_name` (from CIPC) stored; `cipc_verified_at` set; XP awarded.
  - Not found → 422 "That registration number wasn't found on CIPC."
  - CIPC unavailable/disabled → 422 "Business verification via CIPC is currently unavailable." (**hard gate** — no business profile is created)
- The gate lives at the controller layer, so factories/seeders/`ProfileService` callers are unaffected.

> **Operational note:** because the gate is hard, **business creation is blocked until a CIPC provider is configured** (`CIPC_ENABLED=true` + a driver: `CIPC_DRIVER=http` with `CIPC_BASE_URL`/`CIPC_TOKEN`, or `CIPC_DRIVER=stub` as a stopgap).

### Feature-flag the Home tiles
- New super-admin flags (default on): `community` (mentors + Q&A + forums), `ads` (promoted posts), `directory` (business directory), `business_tools` (insights). The **Learn** tile is gated on the existing `courses` flag.
- Tiles hide client-side and the matching member API routes 404 when a flag is off. `/discover` (a general discovery hub) is intentionally **not** page-gated — only its tile is.

## Data model

| Table | Change |
|---|---|
| `profiles` | `registration_number` (string, nullable), `cipc_registered_name` (string, nullable) |
| `config/features.php` | `community`, `ads`, `directory`, `business_tools` |

All additive/nullable.

## Verification

Full API Pest suite green (1048); web `lint` + `tsc` + `build` green. Built by 6 parallel agents (2 API, 4 web) with disjoint file ownership and a shared `POST /me/profiles` contract, then integrated and re-verified.
