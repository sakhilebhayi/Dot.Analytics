<?php

namespace Tests\Unit\Observers;

use App\Events\Analytics\PlatformConnected;
use App\Events\Analytics\PlatformDisconnected;
use App\Models\AuditLog;
use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DataSourceObserverTest extends TestCase
{
    use RefreshDatabase;

    private function team()
    {
        return User::factory()->withPersonalTeam()->create()->currentTeam;
    }

    public function test_creating_connected_source_fires_platform_connected_event(): void
    {
        Event::fake([PlatformConnected::class]);

        $team = $this->team();
        DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
        ]);

        Event::assertDispatched(PlatformConnected::class);
    }

    public function test_creating_pending_source_does_not_fire_platform_connected(): void
    {
        Event::fake([PlatformConnected::class]);

        $team = $this->team();
        DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => 'dot.fleet',
            'status' => 'pending',
        ]);

        Event::assertNotDispatched(PlatformConnected::class);
    }

    public function test_updating_status_to_connected_fires_platform_connected(): void
    {
        Event::fake([PlatformConnected::class]);

        $team = $this->team();
        $source = DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => 'dot.fleet',
            'status' => 'pending',
        ]);

        $source->update(['status' => 'connected']);

        Event::assertDispatched(PlatformConnected::class);
    }

    public function test_deleting_connected_source_fires_platform_disconnected(): void
    {
        Event::fake([PlatformDisconnected::class]);

        $team = $this->team();
        $source = DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
        ]);

        $source->delete();

        Event::assertDispatched(PlatformDisconnected::class, function ($event) {
            return $event->platform === 'dot.fleet';
        });
    }

    public function test_creating_source_writes_audit_log(): void
    {
        $team = $this->team();
        DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'team_id' => $team->id,
            'event' => 'platform.created',
        ]);
    }

    public function test_updating_source_writes_audit_log(): void
    {
        $team = $this->team();
        $source = DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => 'dot.fleet',
            'status' => 'pending',
        ]);

        $source->update(['status' => 'connected']);

        $this->assertDatabaseHas('audit_logs', [
            'team_id' => $team->id,
            'event' => 'platform.updated',
        ]);
    }

    public function test_deleting_source_writes_audit_log(): void
    {
        $team = $this->team();
        $source = DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
        ]);

        $source->delete();

        $this->assertDatabaseHas('audit_logs', [
            'team_id' => $team->id,
            'event' => 'platform.deleted',
        ]);
    }

    public function test_audit_log_is_immutable(): void
    {
        $this->expectException(\RuntimeException::class);

        $team = $this->team();
        DataSource::factory()->create(['team_id' => $team->id, 'platform' => 'dot.fleet', 'status' => 'connected']);

        $log = AuditLog::where('team_id', $team->id)->first();
        $log->update(['event' => 'tampered']);
    }
}
