<?php

namespace App\Policies;

use App\Models\CrossPlatformInsight;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrossPlatformInsightPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, CrossPlatformInsight $insight): bool
    {
        return $user->currentTeam?->id === $insight->team_id;
    }

    public function update(User $user, CrossPlatformInsight $insight): bool
    {
        return $user->currentTeam?->id === $insight->team_id;
    }

    public function delete(User $user, CrossPlatformInsight $insight): bool
    {
        $team = $user->currentTeam;
        return $team?->id === $insight->team_id
            && $team->user_id === $user->id;
    }
}
