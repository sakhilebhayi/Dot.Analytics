<?php

namespace Tests\Support;

use App\Models\AnalyticsSnapshot;
use App\Models\DataSource;
use App\Models\Team;
use App\Models\User;

trait BuildsTeams
{
    protected function ownerWithTeam(): User
    {
        return User::factory()->withPersonalTeam()->create();
    }

    /** A user attached to $owner's team with the given Jetstream role, switched into it. */
    protected function memberOf(User $owner, string $role = 'editor'): User
    {
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => $role]);
        $member->switchTeam($owner->currentTeam);

        return $member->fresh();
    }

    /**
     * Authenticate the next API call as $user. Forgetting the cached guards matters:
     * Sanctum keeps the first resolved user for the life of the test application.
     */
    protected function asApi(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('t')->plainTextToken);
    }

    protected function connect(Team $team, string $platform): DataSource
    {
        return DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => $platform,
            'status' => 'connected',
        ]);
    }

    protected function snapshot(DataSource $source, array $payload, ?\DateTimeInterface $at = null): AnalyticsSnapshot
    {
        return AnalyticsSnapshot::withoutGlobalScopes()->create([
            'team_id' => $source->team_id,
            'data_source_id' => $source->id,
            'snapshot_type' => 'webhook',
            'payload' => $payload,
            'captured_at' => $at ?? now(),
        ]);
    }
}
