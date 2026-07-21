<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live streaming (RTMP → HLS)
    |--------------------------------------------------------------------------
    |
    | Provider-agnostic live video for masterclass rooms. The host streams via
    | RTMP to the provider, which transcodes to HLS for in-app playback. `driver`
    | selects the provider (mux | null); null disables going live. Credentials
    | are also settable from the super-admin Integrations page.
    |
    */

    'driver' => env('STREAM_DRIVER', 'null'),

    'mux' => [
        'token_id' => env('MUX_TOKEN_ID'),
        'token_secret' => env('MUX_TOKEN_SECRET'),
        'webhook_secret' => env('MUX_WEBHOOK_SECRET'),
        'api_url' => rtrim((string) env('MUX_API_URL', 'https://api.mux.com'), '/'),
        // RTMPS ingest endpoint the host points their encoder (OBS etc.) at.
        'ingest_url' => env('MUX_INGEST_URL', 'rtmps://global-live.mux.com:443/app'),
        'playback_base' => rtrim((string) env('MUX_PLAYBACK_BASE', 'https://stream.mux.com'), '/'),
        'timeout' => (int) env('MUX_TIMEOUT', 15),
    ],

];
