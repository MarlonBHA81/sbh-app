<?php

use App\Observability\SentryScrubber;

/*
|--------------------------------------------------------------------------
| Sentry (error tracking / APM)
|--------------------------------------------------------------------------
|
| Only the keys SBH pins are defined here; every other Sentry option falls
| back to the package default (sentry/sentry-laravel merges its own config
| underneath this file, app values win at the top level).
|
| Off by default: with no DSN the SDK is inert, so nothing is sent until a
| super admin adds one in Integrations (layered over this via
| IntegrationSettingsProvider) or `SENTRY_LARAVEL_DSN` is set.
|
| PII is scrubbed on every event — `send_default_pii` is false (the SDK omits
| cookies/body/IP) and `before_send` redacts anything else (SentryScrubber).
| The callable is a [class, method] array, not a closure, so the config stays
| serialisable for `php artisan config:cache`.
|
*/

return [

    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    'release' => env('SENTRY_RELEASE'),

    // Falls back to APP_ENV when null.
    'environment' => env('SENTRY_ENVIRONMENT'),

    // Never attach the requester's IP / email / cookies automatically.
    'send_default_pii' => false,

    // Performance tracing off by default to preserve the free-tier error quota;
    // raise SENTRY_TRACES_SAMPLE_RATE (e.g. 0.1) to sample transactions.
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),

    // Redact PII on every outgoing event (defence-in-depth over send_default_pii).
    'before_send' => [SentryScrubber::class, 'beforeSend'],

];
