<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * app/Http/Middleware/SecurityHeaders.php's CSP had three real mismatches
 * against what the app's own layouts actually load, only caught once a
 * real authenticated dashboard page (with real Livewire panels) was
 * loaded in a browser -- every prior verification pass this platform went
 * through only ever checked guest-facing, non-interactive pages:
 *  1. script-src omitted 'unsafe-eval', which Alpine.js (bundled by
 *     Livewire 3) needs to evaluate wire:click/wire:poll/x-data
 *     expressions at runtime.
 *  2. script-src didn't allow cdn.tailwindcss.com/unpkg.com, which
 *     layouts/app.blade.php loads Tailwind and Alpine from directly.
 *  3. style-src/font-src only allowed fonts.bunny.net, but every layout
 *     actually loads Google Fonts from fonts.googleapis.com/gstatic.com.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_csp_allows_unsafe_eval_for_alpine_livewire_expressions(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $csp);
    }

    public function test_csp_allows_the_cdns_the_app_layout_actually_loads(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://cdn.tailwindcss.com', $csp);
        $this->assertStringContainsString('https://unpkg.com', $csp);
    }

    public function test_csp_allows_the_google_fonts_domains_every_layout_actually_uses(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://fonts.googleapis.com', $csp);
        $this->assertStringContainsString('https://fonts.gstatic.com', $csp);
    }

    public function test_csp_still_restricts_default_src_to_self(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_csp_connect_src_matches_the_echo_client_host_exactly(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        $expectedOrigin = 'ws://'.config('echo.host').':'.config('echo.port');
        $this->assertStringContainsString($expectedOrigin, $csp);
    }
}
