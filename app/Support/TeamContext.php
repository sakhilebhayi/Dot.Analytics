<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/** The company the signed-in person is working in right now, if they really belong to it and it is not suspended. */
class TeamContext
{
    private static bool $system = false;

    /** Authenticated machine work inside a request, such as a signed snapshot. It names its team itself. */
    public static function system(callable $work): mixed
    {
        $previous = self::$system;
        self::$system = true;

        try {
            return $work();
        } finally {
            self::$system = $previous;
        }
    }

    public static function inSystem(): bool
    {
        return self::$system;
    }

    public static function id(): ?int
    {
        return self::team()?->id;
    }

    public static function team(): ?Team
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $team = $user->currentTeam;

        if (! $team instanceof Team || $team->suspended_at !== null || ! $user->belongsToTeam($team)) {
            return null;
        }

        return $team;
    }
}
