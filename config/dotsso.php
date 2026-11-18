<?php

use App\Sso\AnalyticsProvisioner;

/*
 * Dot.Analytics' own choices for the shared sign-in package (packages/dot-sso-client). Everything else, including
 * DOTSSO_ENABLED (default false), comes from the package's defaults and the host .env.
 */
return [
    'provisioner' => AnalyticsProvisioner::class,

    // Analytics has always created an account for a person InfoDot vouches for (INFODOT_PROVISION_USERS), so that stays.
    'auto_provision' => (bool) env('DOTSSO_AUTO_PROVISION', env('INFODOT_PROVISION_USERS', true)),

    // The signed-in routes use `auth:sanctum`, which makes `sanctum` the default guard for the rest of that request;
    // the shared package must always act on the session guard (login, logout, revocation), so it is named outright.
    'guard' => 'web',

    'landing' => '/dashboard',

    // A deep link may open any signed-in page of the app (the company workspace and "My library"), and nothing else.
    // The public library pages need no session, so they are never a sign-in target.
    'deep_link' => [
        'allow' => ['#^/(dashboard|workspace|search|reports|me|user/profile|teams|team-invitations)(/|\?|$)#'],
        'deny' => ['#^/(sso|login|register|logout|forgot-password|reset-password|auth|oauth|two-factor-challenge|user/confirm)(/|\?|$)#i'],
    ],

    // Never intercepted: nothing extra. The machine doors (the HMAC ingest at /api/ingest/snapshots, the Sanctum API at
    // /api/v1/*, /api/user, /api/ping, /api/health), the signed one-click unsubscribe at /me/digest/stop/{user}, the
    // public library (no session at all) and /auth/ecosystem are all outside the pages the package takes over.
    'allow_paths' => [],
];
