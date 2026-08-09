<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'http://localhost').'/api/v1/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', env('APP_URL', 'http://localhost').'/api/v1/auth/facebook/callback'),
    ],

    'twitter' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_REDIRECT_URI', env('APP_URL', 'http://localhost').'/api/v1/auth/twitter/callback'),
    ],

    'nominatim' => [
        'enabled' => env('NOMINATIM_ENABLED', true),
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'contact' => env('NOMINATIM_CONTACT'),
    ],

    // GitHub API — used by the observability alert triage to file issues that a
    // fix-agent picks up (see config/observability.php).
    'github' => [
        'token' => env('GITHUB_TOKEN'),
        'repo' => env('GITHUB_REPO'),
    ],

    // ClamAV antivirus (clamd) — scans uploads over the INSTREAM TCP protocol.
    // Disabled by default so local/tests and any deploy without a clamd sidecar
    // keep working; enable it (and run the `av` compose profile) in production.
    'clamav' => [
        'enabled' => env('CLAMAV_ENABLED', false),
        'host' => env('CLAMAV_HOST', 'clamav'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 30),
        // Files larger than this are skipped (clamd's StreamMaxLength must be at
        // least this big). Default 26 MB ~ clamd's 25 MB default plus headroom.
        'max_bytes' => (int) env('CLAMAV_MAX_BYTES', 26214400),
        // When the scanner is enabled but can't produce a verdict: false lets
        // the upload through (logged); true rejects it.
        'fail_closed' => env('CLAMAV_FAIL_CLOSED', false),
    ],

    // CIPC (Companies and Intellectual Property Commission) registration lookup.
    // Confirms a business's registration number and, on a hit, awards XP and a
    // "CIPC verified" sticker. Disabled by default → the Null verifier, so no
    // sticker is ever granted without a real, configured provider.
    //   driver=http: calls base_url with a Bearer token (adapt to your provider
    //   or aggregator); driver=stub: verifies any well-formed SA reg number
    //   (dev/demo only).
    'cipc' => [
        'enabled' => env('CIPC_ENABLED', false),
        'driver' => env('CIPC_DRIVER', 'stub'),
        'base_url' => env('CIPC_BASE_URL'),
        'token' => env('CIPC_TOKEN'),
        'timeout' => (int) env('CIPC_TIMEOUT', 15),
        // Award XP on a successful CIPC verification.
        'award_xp' => env('CIPC_AWARD_XP', true),
    ],

];
