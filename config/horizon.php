<?php

/*
|--------------------------------------------------------------------------
| Laravel Horizon Configuration
|--------------------------------------------------------------------------
|
| Horizon requires ext-pcntl (Linux/macOS only).
| Install: composer require laravel/horizon
| Publish: php artisan horizon:install
|
| In production, run: php artisan horizon
| Monitor at: /horizon (requires HorizonServiceProvider to be registered)
|
*/

return [

    'domain'  => env('HORIZON_DOMAIN'),
    'path'    => env('HORIZON_PATH', 'horizon'),
    'driver'  => env('QUEUE_CONNECTION', 'redis'),
    'prefix'  => env('HORIZON_PREFIX', 'horizon:'),

    'middleware' => ['web'],

    'waits' => [
        'redis:default' => 60,
    ],

    'trim' => [
        'recent'           => 60,
        'pending'          => 60,
        'completed'        => 60,
        'recent_failed'    => 10080,
        'failed'           => 10080,
        'monitored'        => 10080,
    ],

    'silenced' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 64,

    // Queue worker environments
    'environments' => [

        'production' => [
            'supervisor-default' => [
                'connection' => 'redis',
                'queue'      => ['default'],
                'balance'    => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses'  => 10,
                'minProcesses'  => 1,
                'maxTime'       => 0,
                'maxJobs'       => 0,
                'memory'        => 128,
                'tries'         => 3,
                'timeout'       => 60,
                'nice'          => 0,
            ],

            // Dedicated supervisor for AI-heavy intelligence jobs
            'supervisor-intelligence' => [
                'connection' => 'redis',
                'queue'      => ['intelligence'],
                'balance'    => 'simple',
                'processes'  => 3,
                'maxTime'    => 0,
                'memory'     => 256,
                'tries'      => 2,
                'timeout'    => 120,
                'nice'       => 5,
            ],
        ],

        'local' => [
            'supervisor-default' => [
                'connection' => 'redis',
                'queue'      => ['default', 'intelligence'],
                'balance'    => 'simple',
                'processes'  => 3,
                'maxTime'    => 0,
                'memory'     => 128,
                'tries'      => 1,
                'timeout'    => 60,
                'nice'       => 0,
            ],
        ],
    ],
];
