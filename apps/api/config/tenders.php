<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenProcurement tender ingestion
    |--------------------------------------------------------------------------
    |
    | Pulls open tenders from an OpenProcurement-compatible API (the OCDS /
    | Prozorro-family change-feed) into the Opportunity model. The base URL is
    | endpoint-agnostic: point it at a South-African OpenProcurement deployment
    | for SA tenders; the public default serves the standard (Ukrainian) data,
    | useful for proving the pipeline end to end.
    |
    */

    // Master switch. When false, `tenders:sync` is a no-op.
    'enabled' => (bool) env('OPENPROCUREMENT_ENABLED', false),

    'base_url' => rtrim((string) env('OPENPROCUREMENT_BASE_URL', 'https://public.api.openprocurement.org'), '/'),

    'version' => (string) env('OPENPROCUREMENT_VERSION', '2.5'),

    // Optional HTTP Basic auth for private deployments ("user:pass"). Public
    // read endpoints need none.
    'auth' => env('OPENPROCUREMENT_AUTH'),

    // Source label stamped on every imported opportunity (dedupe namespace).
    'source' => (string) env('OPENPROCUREMENT_SOURCE', 'OpenProcurement'),

    // Max tender detail fetches per run (bounds one run; the saved offset lets
    // the next run continue where this one stopped).
    'page_limit' => (int) env('OPENPROCUREMENT_PAGE_LIMIT', 200),

    // Publish imported tenders immediately, or leave them as drafts for review.
    'publish' => (bool) env('OPENPROCUREMENT_PUBLISH', true),

    // Request timeout (seconds).
    'timeout' => (int) env('OPENPROCUREMENT_TIMEOUT', 20),

];
