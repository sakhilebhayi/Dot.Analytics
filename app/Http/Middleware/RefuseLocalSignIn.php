<?php

namespace App\Http\Middleware;

use App\Support\CentralSignIn;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * With InfoDot as the only front door, a password, reset or two-factor post here must not become a second one.
 *
 * "Only front door" means the older ecosystem lock (ECOSYSTEM_SSO_ONLY) or the R-54 central sign-in (DOTSSO_ENABLED,
 * unless the break-glass DOTSSO_ALLOW_LOCAL_LOGIN is set). R-54 closes the gap this platform had: under sso_only the
 * login POST never authenticated (Fortify::authenticateUsing returns null), but the forgot-password and reset-password
 * POSTs still ran, sending mail and changing a local hash. They are refused now.
 */
class RefuseLocalSignIn
{
    public function handle(Request $request, Closure $next): Response
    {
        $locked = config('ecosystem.sso_only') || CentralSignIn::active();

        abort_if($locked && ! $request->isMethodSafe() && $request->routeIs('login.store', 'password.email', 'password.update', 'two-factor.login.store'), 404);

        return $next($request);
    }
}
