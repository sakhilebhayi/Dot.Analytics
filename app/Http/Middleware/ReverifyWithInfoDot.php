<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * InfoDot decides who belongs to a business, and it only tells us at sign-in. So in SSO mode a session lasts a working
 * day: after that the person signs in through InfoDot again, and anyone it has removed loses access.
 */
class ReverifyWithInfoDot
{
    public const VERIFIED_AT = 'infodot_verified_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ecosystem.sso_only') || $request->bearerToken() || ! $request->user()) {
            return $next($request);
        }

        $verifiedAt = (int) $request->session()->get(self::VERIFIED_AT, 0);

        if (now()->getTimestamp() - $verifiedAt > (int) config('ecosystem.reverify_hours') * 3600) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
