<?php

namespace Tests\Unit\Jobs;

use App\Jobs\Analytics\IngestPlatformSnapshotJob;
use App\Models\AnalyticsSnapshot;
use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngestPlatformSnapshotJobTest extends TestCase
{
    use RefreshDatabase;

    private function connectedSource(): DataSource
    {
        $user = User::factory()->withPersonalTeam()->create();
        return DataSource::factory()->create([
            'team_id'  => $user->currentTeam->id,
            'platform' => 'dot.fleet',
            'status'   => 'connected',
        ]);
    }

    public function test_job_creates_analytics_snapshot(): void
    {
        $source  = $this->connectedSource();
        $payload = ['vehicles' => [['id' => 'V-001', 'fuel' => 45.2]]];

        (new IngestPlatformSnapshotJob($source->id, $payload))->handle();

        $this->assertDatabaseHas('analytics_snapshots', [
            'team_id'        => $source->team_id,
            'data_source_id' => $source->id,
            'snapshot_type'  => 'hourly',
        ]);
    }

    public function test_job_updates_last_synced_at(): void
    {
        $source = $this->connectedSource();

        (new IngestPlatformSnapshotJob($source->id, ['data' => []]))->handle();

        $source->refresh();
        $this->assertNotNull($source->last_synced_at);
    }

    public function test_job_includes_quality_assessment_in_payload(): void
    {
        $source = $this->connectedSource();

        (new IngestPlatformSnapshotJob($source->id, ['key' => 'value']))->handle();

        $snapshot = AnalyticsSnapshot::where('data_source_id', $source->id)->first();
        $this->assertArrayHasKey('_quality', $snapshot->payload);
        $this->assertArrayHasKey('completeness_pct', $snapshot->payload['_quality']);
    }

    public function test_job_does_nothing_for_nonexistent_source(): void
    {
        (new IngestPlatformSnapshotJob(99999, ['data' => []]))->handle();

        $this->assertDatabaseCount('analytics_snapshots', 0);
    }

    public function test_job_does_nothing_for_disconnected_source(): void
    {
        $user   = User::factory()->withPersonalTeam()->create();
        $source = DataSource::factory()->create([
            'team_id'  => $user->currentTeam->id,
            'platform' => 'dot.fleet',
            'status'   => 'pending', // not connected
        ]);

        (new IngestPlatformSnapshotJob($source->id, ['data' => []]))->handle();

        $this->assertDatabaseCount('analytics_snapshots', 0);
    }

    public function test_job_uses_custom_snapshot_type(): void
    {
        $source = $this->connectedSource();

        (new IngestPlatformSnapshotJob($source->id, ['data' => []], 'webhook'))->handle();

        $this->assertDatabaseHas('analytics_snapshots', ['snapshot_type' => 'webhook']);
    }
}
