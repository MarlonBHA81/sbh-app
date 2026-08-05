<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alert triage → issue tracker
    |--------------------------------------------------------------------------
    |
    | When an error monitor (Sentry alert rule, uptime check, …) POSTs a signed
    | alert to /api/v1/observability/alert, the payload is de-duplicated and
    | filed as an issue so an external fix-agent (Claude Code) can pick it up and
    | open a pull request for human review. `driver` selects where issues go:
    |
    |   null   → nothing is filed (the alert is logged only) — the default.
    |   github → open/update a GitHub issue in `github.repo`.
    |
    | Credentials are also settable from the super-admin Integrations page, which
    | layers over these values at boot (IntegrationSettingsProvider).
    |
    */

    'driver' => env('OBSERVABILITY_ISSUE_DRIVER', 'null'),

    // HMAC-SHA256 shared secret used to verify inbound alert webhooks. Blank
    // rejects every request (fail closed) — set it before pointing a monitor here.
    'alert_webhook_secret' => env('OBSERVABILITY_ALERT_SECRET'),

    'github' => [
        'token' => env('GITHUB_TOKEN'),
        // owner/repo the triage issues are opened against.
        'repo' => env('GITHUB_REPO'),
        'labels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('OBSERVABILITY_GITHUB_LABELS', 'sentry,auto-triage')),
        ))),
        'api_url' => rtrim((string) env('GITHUB_API_URL', 'https://api.github.com'), '/'),
        'timeout' => (int) env('OBSERVABILITY_GITHUB_TIMEOUT', 15),
    ],

];
