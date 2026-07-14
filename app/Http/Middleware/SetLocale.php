<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale, timezone, and Carbon locale from the
 * authenticated user's current team settings.
 *
 * Supports runtime locale switching without a deployment.
 * Falls back to APP_LOCALE env var when no team preference is set.
 */
class SetLocale
{
    /** Allowlist of supported locale codes. Prevents locale injection. */
    private const ALLOWED_LOCALES = [
        'en', 'en_US', 'en_GB', 'en_ZA', 'en_AU',
        'af', 'fr', 'de', 'es', 'pt', 'pt_BR',
        'ar', 'zh', 'zh_CN', 'zh_TW', 'hi', 'sw',
        'zu', 'xh', 'st', 'tn',
    ];

    /** Allowlist of supported IANA timezones (abbreviated). */
    private const ALLOWED_TIMEZONES = [
        'UTC', 'Africa/Johannesburg', 'Africa/Nairobi', 'Africa/Lagos',
        'Africa/Cairo', 'Africa/Accra', 'Europe/London', 'Europe/Paris',
        'Europe/Berlin', 'America/New_York', 'America/Chicago',
        'America/Los_Angeles', 'America/Sao_Paulo', 'Asia/Dubai',
        'Asia/Kolkata', 'Asia/Shanghai', 'Asia/Tokyo', 'Australia/Sydney',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $team = $user?->currentTeam;

        if ($team) {
            $locale   = $this->sanitiseLocale($team->locale ?? config('app.locale', 'en'));
            $timezone = $this->sanitiseTimezone($team->timezone ?? config('app.timezone', 'UTC'));

            App::setLocale($locale);
            Carbon::setLocale($locale);
            config(['app.timezone' => $timezone]);
        }

        return $next($request);
    }

    private function sanitiseLocale(string $locale): string
    {
        return in_array($locale, self::ALLOWED_LOCALES, true) ? $locale : config('app.locale', 'en');
    }

    private function sanitiseTimezone(string $timezone): string
    {
        return in_array($timezone, self::ALLOWED_TIMEZONES, true) ? $timezone : 'UTC';
    }
}
