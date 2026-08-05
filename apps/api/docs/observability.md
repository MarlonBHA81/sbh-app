# Observability & error-triage

Turns the live app from "runs blind" (audit finding **B21**) into: errors are
captured, structured logs can be shipped, and error alerts can trigger a
fix-agent to open a PR for review. Everything is **off by default** — nothing is
sent or filed until it is configured.

## The three layers

1. **Sentry error tracking** — Laravel API (`sentry/sentry-laravel`) and Next.js
   web (`@sentry/nextjs`). Error-only: **no session replay** (POPIA — we capture
   errors, not user sessions). PII is scrubbed on every event
   (`App\Observability\SentryScrubber` on the API; `sendDefaultPii: false` on
   both).
2. **Structured JSON logging** — a `json` log channel writing one JSON object
   per line to stderr, ready for a container log collector. Opt-in via
   `LOG_STACK=single,json`.
3. **Alert triage → issue** — a signed inbound webhook
   (`POST /api/v1/observability/alert`) that de-duplicates alerts and files a
   GitHub issue a fix-agent can pick up.

## How the loop closes

```
error in prod
   → Sentry captures it
   → Sentry alert rule POSTs a signed payload to /api/v1/observability/alert
   → receiver verifies the HMAC signature (fail-closed)
   → alert is fingerprinted and queued (FileIssueForAlert)
   → GithubIssueTracker opens ONE issue per fingerprint (recurrences comment)
   → issue is labelled (sentry, auto-triage)
   → an external Claude Code fix-agent watching that label investigates and
     opens a PR  ← HUMAN REVIEWS AND MERGES. The starter never merges or deploys.
```

## Configuration

Set env vars (`apps/api/.env`, `apps/web/.env`) **or** use the super-admin
**Integrations** page (`/admin` → Settings → Integrations → Observability),
which layers over env at boot via `IntegrationSettingsProvider`.

| Setting | Env (API) | Integrations field |
|---|---|---|
| Sentry DSN (API) | `SENTRY_LARAVEL_DSN` | Sentry DSN |
| Sentry DSN (web) | `NEXT_PUBLIC_SENTRY_DSN` | — (build-time) |
| Triage driver | `OBSERVABILITY_ISSUE_DRIVER` (`null`\|`github`) | Alert triage destination |
| Alert webhook secret | `OBSERVABILITY_ALERT_SECRET` | Alert webhook secret |
| GitHub repo | `GITHUB_REPO` (`owner/repo`) | GitHub repo |
| GitHub token | `GITHUB_TOKEN` (PAT, Issues: R/W) | GitHub token |

### Signing an alert

The receiver verifies `X-SBH-Signature` (or Sentry's `Sentry-Hook-Signature`):

```
signature = hash_hmac('sha256', <raw request body>, OBSERVABILITY_ALERT_SECRET)
```

A blank secret **rejects every request** (fail-closed). Point a Sentry Internal
Integration (which signs with `Sentry-Hook-Signature`) or a small relay that
signs with `X-SBH-Signature` at the endpoint.

### Backend choice

Default is **Sentry.io SaaS** (free tier) with PII scrubbing on. Because the DSN
is config-driven, self-hosting (e.g. GlitchTip) later is just a DSN swap — the
data-residency upgrade path, no code change.

## Design notes

- **Driver pattern.** The issue tracker follows SBH's integration/driver shape:
  `config/observability.php` → `App\Contracts\IssueTracker` →
  `Null`/`Github` drivers → bound in `AppServiceProvider::register()` →
  DB overrides in `IntegrationSettingsProvider` → Filament Integrations page.
- **`before_send` is a `[class, method]` callable**, not a closure, so
  `php artisan config:cache` still serialises `config/sentry.php`.
- **Human-in-the-loop.** Filing an issue is the only automated write; opening and
  merging a PR are separate, reviewed steps.
