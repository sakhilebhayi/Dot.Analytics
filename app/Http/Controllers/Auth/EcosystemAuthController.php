<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class EcosystemAuthController extends Controller
{
    public function handle(Request $request): RedirectResponse
    {
        $plain = $request->query('token');

        $accessToken = is_string($plain) && $plain !== ''
            ? PersonalAccessToken::findToken($plain)
            : null;

        abort_if(
            ! $accessToken
            || ! $accessToken->can('ecosystem:read')
            || ($accessToken->expires_at && $accessToken->expires_at->isPast()),
            403
        );

        /** @var User $user */
        $user = $accessToken->tokenable;
        $accessToken->delete();

        Auth::login($user);

        // New session id on privilege change (prevents session fixation).
        $request->session()->regenerate();

        // A user arriving without a valid current team would otherwise land on a
        // dashboard that has to guess. Keep a valid current team; otherwise fall
        // back to their personal team, then any team they belong to.
        if (! $user->currentTeam || ! $user->belongsToTeam($user->currentTeam)) {
            $team = $user->personalTeam() ?? $user->allTeams()->first();
            if ($team) {
                $user->switchTeam($team);
            }
        }

        return redirect()->route('dashboard');
    }
}
