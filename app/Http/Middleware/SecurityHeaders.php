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
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net",
            "img-src 'self' data: blob:",
            "connect-src 'self' " . $this->reverbWsOrigin(),
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
        $scheme = config('reverb.servers.reverb.options.tls', false) ? 'wss' : 'ws';
        $host   = config('reverb.servers.reverb.host', 'localhost');
        $port   = config('reverb.servers.reverb.port', 8080);
        return "{$scheme}://{$host}:{$port}";
    }
}
