<?php

namespace App\Policies;

use App\Models\AnalyticsDashboard;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AnalyticsDashboardPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, AnalyticsDashboard $dashboard): bool
    {
        if ($user->currentTeam?->id !== $dashboard->team_id) {
            return false;
        }

        return $dashboard->user_id === $user->id || $dashboard->visibility === 'team';
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function update(User $user, AnalyticsDashboard $dashboard): bool
    {
        if ($user->currentTeam?->id !== $dashboard->team_id) {
            return false;
        }

        if ($dashboard->user_id === $user->id) {
            return true;
        }

        return $dashboard->visibility === 'team' && $this->isTeamOwnerOrAdmin($user, $dashboard->team_id);
    }

    public function delete(User $user, AnalyticsDashboard $dashboard): bool
    {
        return $this->update($user, $dashboard);
    }

    private function isTeamOwnerOrAdmin(User $user, int $teamId): bool
    {
        $team = $user->currentTeam;
        if (! $team || $team->id !== $teamId) {
            return false;
        }

        return $team->user_id === $user->id
            || $team->users()->where('user_id', $user->id)->wherePivot('role', 'admin')->exists();
    }
}
