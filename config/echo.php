<?php

/*
|--------------------------------------------------------------------------
| Browser-side real-time (Laravel Echo -> Reverb)
|--------------------------------------------------------------------------
|
| What the *browser* connects to, which is not the address the Reverb server binds
| to (that is 0.0.0.0 in containers). The layout's Echo client and the
| Content-Security-Policy connect-src both read this file, so they cannot drift.
|
| Real-time is optional. It is on only when Reverb is the broadcast connection and has
| an app key, or when REALTIME_ENABLED says so. Off, nothing is loaded in the browser,
| no CDN or WebSocket origin is allowed by the CSP, and the notification bell keeps itself
| current by polling instead. Only the PUBLIC key is exposed here, never REVERB_APP_SECRET.
|
*/

return [
    'enabled' => filter_var(
        env('REALTIME_ENABLED', filled(env('REVERB_APP_KEY')) && env('BROADCAST_CONNECTION') === 'reverb'),
        FILTER_VALIDATE_BOOL,
    ),
    'key' => env('REVERB_APP_KEY'),
    'host' => env('REVERB_HOST', 'localhost'),
    'port' => env('REVERB_PORT', 8080),
    'scheme' => env('REVERB_SCHEME', 'http'),
];
