<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cost model
    |--------------------------------------------------------------------------
    |
    | Promoted-post campaigns are billed per impression. The CPI is snapshotted
    | onto each campaign at create time, so changing this value never alters the
    | economics of campaigns that are already running. Budgets are bounded to
    | keep a single campaign within sane spend limits (values in cents; ZAR).
    |
    */

    'cpi_cents' => (int) env('ADS_CPI_CENTS', 2),

    'min_budget_cents' => (int) env('ADS_MIN_BUDGET_CENTS', 5000),

    'max_budget_cents' => (int) env('ADS_MAX_BUDGET_CENTS', 500000),

    'max_duration_days' => (int) env('ADS_MAX_DURATION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | For-you injection
    |--------------------------------------------------------------------------
    |
    | One promoted post is injected per N organic posts on the for-you feed.
    |
    */

    'injection_rate' => (int) env('ADS_INJECTION_RATE', 10),

];
