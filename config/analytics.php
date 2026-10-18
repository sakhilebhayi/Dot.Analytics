<?php

/*
|--------------------------------------------------------------------------
| Dot.Analytics service settings
|--------------------------------------------------------------------------
*/

// A platform can only be given a secret if it is in the metric catalogue: the
// keys here ARE the catalogue's platform keys (config/metrics.php), so a new
// platform needs a catalogue entry and then ANALYTICS_INGEST_SECRET_<KEY>.
$platformKeys = array_keys((require __DIR__.'/metrics.php')['platforms']);

return [

    'ingest' => [
        /*
        | POST /api/ingest/snapshots is server to server: no user, no token. The
        | CALLING platform signs "{timestamp}.{raw body}" with HMAC-SHA256 using
        | its OWN secret and Dot.Analytics verifies it (same scheme as
        | Dot.Notify's ingest). The platform named in the body picks the secret,
        | so a platform can only ever publish as itself. A blank secret means that
        | platform cannot call at all.
        | Generate with: php -r 'echo bin2hex(random_bytes(32));'
        */
        'secrets' => collect($platformKeys)
            ->mapWithKeys(fn (string $key) => [$key => (string) env('ANALYTICS_INGEST_SECRET_'.strtoupper($key), '')])
            ->all(),

        // Seconds a signed timestamp stays valid either side of "now".
        'tolerance' => 300,

        // Authenticated calls per platform per minute.
        'rate_limit_per_minute' => (int) env('ANALYTICS_INGEST_RATE_LIMIT', 120),

        // Larger bodies are refused before anything else is done with them.
        'max_body_bytes' => 65536,
    ],

];
