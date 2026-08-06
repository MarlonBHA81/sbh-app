<?php

use App\Models\Opportunity;
use App\Models\Post;
use App\Models\Profile;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    |
    | This option controls the default search connection that gets used while
    | using Laravel Scout. We default to the "database" engine, which runs
    | LIKE queries against the models' searchable columns and requires no
    | external infrastructure. Point SCOUT_DRIVER at "meilisearch" or
    | "typesense" (and add the relevant credentials) to move to a real search
    | service without touching the searchable models.
    |
    | Supported: "algolia", "meilisearch", "typesense", "database", "collection", "null"
    |
    */

    'driver' => env('SCOUT_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    */

    'prefix' => env('SCOUT_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Queue Data Syncing
    |--------------------------------------------------------------------------
    |
    | Index syncing is a no-op for the database engine, but this is honoured by
    | the external drivers should we switch to one later.
    |
    */

    'queue' => env('SCOUT_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Database Transactions
    |--------------------------------------------------------------------------
    */

    'after_commit' => false,

    /*
    |--------------------------------------------------------------------------
    | Chunk Sizes
    |--------------------------------------------------------------------------
    */

    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | Keep soft-deleted records out of search results (the models' soft-delete
    | global scope already excludes them; this mirrors that for Scout).
    |
    */

    'soft_delete' => false,

    /*
    |--------------------------------------------------------------------------
    | Identify User
    |--------------------------------------------------------------------------
    */

    'identify' => env('SCOUT_IDENTIFY', false),

    /*
    |--------------------------------------------------------------------------
    | Database Engine
    |--------------------------------------------------------------------------
    |
    | Default the database engine's matching strategy to a case-insensitive
    | LIKE across a model's searchable columns, preserving the substring
    | semantics the hand-written queries used.
    |
    */

    'database' => [
        'searchable' => [
            Profile::class => [],
            Post::class => [],
            Opportunity::class => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Algolia / Meilisearch / Typesense Configuration
    |--------------------------------------------------------------------------
    |
    | Left as env-driven placeholders so switching SCOUT_DRIVER to a hosted
    | engine is purely configuration.
    |
    */

    'algolia' => [
        'id' => env('ALGOLIA_APP_ID', ''),
        'secret' => env('ALGOLIA_SECRET', ''),
    ],

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
    ],

    'typesense' => [
        'client-settings' => [
            'api_key' => env('TYPESENSE_API_KEY', 'xyz'),
            'nodes' => [
                [
                    'host' => env('TYPESENSE_HOST', 'localhost'),
                    'port' => env('TYPESENSE_PORT', '8108'),
                    'path' => env('TYPESENSE_PATH', ''),
                    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
                ],
            ],
            'connection_timeout_seconds' => env('TYPESENSE_CONNECTION_TIMEOUT_SECONDS', 2),
        ],
    ],

];
