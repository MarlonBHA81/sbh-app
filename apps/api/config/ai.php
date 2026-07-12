<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI driver
    |--------------------------------------------------------------------------
    |
    | Selects the concrete AiGateway implementation bound in the container.
    | 'null' disables every AI feature (no network calls, safe defaults); the
    | 'anthropic' driver talks to the Claude Messages API when an API key is
    | configured. Any other value falls back to the null driver.
    |
    */

    'driver' => env('AI_DRIVER', 'null'),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),

        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),

        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),

        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),

        // Request timeout (seconds) for a single Messages API call.
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 15),
    ],

];
