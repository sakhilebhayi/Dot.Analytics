<?php

namespace App\Policies;

use App\Models\DataSource;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DataSourcePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, DataSource $dataSource): bool
    {
        return $user->currentTeam?->id === $dataSource->team_id;
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function update(User $user, DataSource $dataSource): bool
    {
        return $this->isTeamOwnerOrAdmin($user, $dataSource->team_id);
    }

    public function delete(User $user, DataSource $dataSource): bool
    {
        return $this->isTeamOwnerOrAdmin($user, $dataSource->team_id);
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
