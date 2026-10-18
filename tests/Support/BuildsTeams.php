<?php

namespace Tests\Support;

use App\Models\AnalyticsSnapshot;
use App\Models\DataSource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

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

    /** Give the team the InfoDot company uuid a publisher would address it by. */
    protected function linkCompany(Team $team, ?string $uuid = null): string
    {
        $uuid ??= (string) Str::uuid();
        $team->forceFill(['infodot_team_uuid' => $uuid])->save();

        return $uuid;
    }

    /**
     * A contract snapshot body (docs/metrics-contract.md).
     *
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    protected function snapshotBody(string $companyUuid, string $platform, array $metrics, string $takenAt = '2026-10-17T06:00:00Z', string $period = 'day'): array
    {
        return [
            'company_uuid' => $companyUuid,
            'platform' => $platform,
            'taken_at' => $takenAt,
            'period' => $period,
            'metrics' => $metrics,
        ];
    }

    /** POST a signed snapshot as $user, signing the exact raw body the way a publisher must. */
    protected function postSnapshot(User $user, string $platform, array $body, string $secret, ?string $signWith = null): TestResponse
    {
        $signature = 'sha256='.hash_hmac('sha256', json_encode($body), $signWith ?? $secret);

        return $this->asApi($user)
            ->withHeaders(['X-Analytics-Signature' => $signature])
            ->postJson('/api/v1/ingest/'.$platform, $body);
    }
}
