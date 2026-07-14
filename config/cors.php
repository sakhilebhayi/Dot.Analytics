<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Configure which origins, methods, and headers are allowed for cross-origin
    | requests to the Dot.Analytics API. This is critical for embedded dashboards
    | and other Dot platforms calling the API from their own domains.
    |
    | In production, replace '*' with explicit Dot ecosystem origins:
    |   'allowed_origins' => ['https://infodot.app', 'https://*.infodot.app']
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-Analytics-Request-Id',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => 0,

    'supports_credentials' => true,

];
