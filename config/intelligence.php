<?php

/*
|--------------------------------------------------------------------------
| Public business intelligence
|--------------------------------------------------------------------------
| The store lives apart from every company's private data. In production it is its own SQLite file in WAL mode, or a
| MySQL database when INTEL_DB_DRIVER=mysql. Nothing here carries a team_id.
*/

return [

    'connection' => env('INTEL_CONNECTION', 'intel'),

    // Who is asking, for sources that want to know. A role mailbox, never a person's address.
    'user_agent' => env('INTEL_USER_AGENT', 'Dot.Analytics (+https://analytics.infodot.co.za/sources)'),

    'http_timeout' => (int) env('INTEL_HTTP_TIMEOUT', 20),

    'max_response_bytes' => (int) env('INTEL_MAX_RESPONSE_BYTES', 8_000_000),

    // One scheduled pass starts this many sources at most, so a cron worker that lasts under a minute is never overrun.
    'sources_per_pass' => 3,

    'seconds_per_source' => (int) env('INTEL_SECONDS_PER_SOURCE', 35),

    'measure' => (bool) env('INTEL_MEASURE', true),
    'page_cache' => (bool) env('INTEL_PAGE_CACHE', true),
    'page_cache_store' => env('INTEL_PAGE_CACHE_STORE', 'pages'),

    'keys' => [
        'eia' => env('EIA_API_KEY'),
    ],

    // How many days of an item stay on the news pages. Older ones stay reachable by their own address.
    'news_window_days' => 30,

    // Items kept for good only with their link, title, date and publisher. Longer text is never stored for sources that
    // do not allow it.
    'retain_items_days' => 1095,
];
