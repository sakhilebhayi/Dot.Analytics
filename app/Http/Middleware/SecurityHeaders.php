<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security Headers Middleware
 *
 * Applies enterprise-grade HTTP security headers to every response.
 * Implements OWASP recommended headers to mitigate:
 *  - Clickjacking (X-Frame-Options, CSP frame-ancestors)
 *  - MIME sniffing (X-Content-Type-Options)
 *  - XSS (Content-Security-Policy)
 *  - Information leakage (X-Powered-By removal, Referrer-Policy)
 *  - Protocol downgrade attacks (HSTS in production)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // XSS protection (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions policy — restrict powerful browser features
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()',
        );

        // Content Security Policy
        //
        // Five real mismatches found while first loading the real
        // authenticated dashboard in a browser (every prior
        // browser-verification pass this platform went through only ever
        // checked guest-facing pages, which don't exercise any of these):
        //
        // 1. script-src needs 'unsafe-eval': Livewire 3 bundles Alpine.js's
        //    default build, which compiles every wire:click/wire:poll/x-data
        //    expression via `new Function(...)` at runtime -- without
        //    'unsafe-eval' the browser silently refuses to evaluate any of
        //    them. ExecutiveBriefingPanel's wire:poll.30s crashed the whole
        //    page with a real 500 (Livewire\Exceptions\MethodNotFoundException:
        //    "toJSON" not found) -- Alpine's blocked expression evaluator
        //    falls back to a broken path that serializes the $wire proxy
        //    itself and sends it as a bogus server-side method call.
        //    'unsafe-eval' is the standard, universally-applied trade-off for
        //    apps using Alpine's default build; switching to its CSP-safe
        //    build (@alpinejs/csp) is a real alternative but requires
        //    rewriting every directive to that build's restricted expression
        //    subset -- out of scope for this fix.
        // 2. script-src needs https://cdn.tailwindcss.com and
        //    https://unpkg.com: resources/views/layouts/app.blade.php (every
        //    authenticated page) loads Tailwind and Alpine from these CDNs
        //    directly, not via the compiled @vite bundle guest pages use --
        //    blocked, so NONE of the dashboard's Tailwind utility classes
        //    ever applied. Only the sidebar (built with inline style="..."
        //    attributes, immune to this) rendered correctly; every panel
        //    (built with Tailwind classes like "bg-white rounded-xl shadow
        //    p-6") rendered as unstyled plain text.
        // 3. style-src/font-src need fonts.googleapis.com/fonts.gstatic.com:
        //    both layouts (guest and app) load Google Fonts directly from
        //    those domains, not fonts.bunny.net -- silently degraded to
        //    system fonts everywhere rather than breaking layout, so this
        //    one was never visually obvious. fonts.bunny.net kept in case
        //    something else legitimately depends on it.
        // 4. connect-src must match the host the browser's own Echo client
        //    actually connects to, not the Reverb server's bind address.
        //    reverbWsOrigin() previously read config('reverb.servers.reverb.host'),
        //    which is 0.0.0.0 in this dev environment (confirmed via
        //    `php artisan config:show reverb`) -- the server's listen
        //    address, not something a browser can connect to. Never
        //    exercised before Push Notifications, since nothing opened a
        //    browser-side WebSocket connection until now. Both this CSP
        //    header and the Echo client (layouts/app.blade.php) now read
        //    the same config/echo.php, so there's one source of truth
        //    instead of two values that can silently drift apart.
        // 5. script-src also needs https://cdn.jsdelivr.net: Push
        //    Notifications loads laravel-echo/pusher-js from this CDN
        //    (layouts/app.blade.php). Caught by actually loading the page
        //    in a browser after the fix above -- fixing connect-src alone
        //    wasn't enough, since the CDN <script> tags themselves were
        //    still blocked at the script-src level, so `Echo` was never
        //    even defined and window.Echo stayed undefined.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.bunny.net https://cdn.tailwindcss.com https://unpkg.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com",
            "font-src 'self' https://fonts.bunny.net https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "connect-src 'self' ".$this->reverbWsOrigin(),
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // HSTS — only in production to avoid breaking local dev
        if (config('app.env') === 'production') {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload',
            );
        }

        // Remove information-leaking headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }

    private function reverbWsOrigin(): string
    {
        $scheme = config('echo.scheme') === 'https' ? 'wss' : 'ws';
        $host = config('echo.host');
        $port = config('echo.port');

        return "{$scheme}://{$host}:{$port}";
    }
}
