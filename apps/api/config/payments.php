<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marketplace payments
    |--------------------------------------------------------------------------
    |
    | Provider-agnostic: `driver` selects the gateway (payfast | null). Null
    | disables checkout. PayFast is the SA default. Credentials are also
    | settable from the super-admin Integrations page.
    |
    */

    'driver' => env('PAYMENTS_DRIVER', 'null'),

    // Platform commission taken from each paid order (percent of total).
    'platform_fee_percent' => (float) env('PLATFORM_FEE_PERCENT', 10),

    // Where PayFast sends the buyer back / where the API receives the ITN.
    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),
    'api_url' => rtrim((string) env('APP_URL', 'http://localhost'), '/'),

    'payfast' => [
        'merchant_id' => env('PAYFAST_MERCHANT_ID'),
        'merchant_key' => env('PAYFAST_MERCHANT_KEY'),
        'passphrase' => env('PAYFAST_PASSPHRASE'),
        // Default OFF: a production instance must opt IN to sandbox, so a
        // misconfigured deploy never silently runs test transactions as real.
        'sandbox' => (bool) env('PAYFAST_SANDBOX', false),
        'timeout' => (int) env('PAYFAST_TIMEOUT', 15),
    ],

];
