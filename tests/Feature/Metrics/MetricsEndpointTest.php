<?php

namespace Tests\Feature\Metrics;

use App\Models\DataSource;
use App\Models\Team;
use App\Models\User;
use App\Services\Metrics\ComputeMetrics;
use App\Services\Metrics\SnapshotIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private User $owner;

    private string $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->ownerWithTeam();
        $this->company = $this->linkCompany($this->owner->currentTeam);
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(9, 0));
    }

    private function pushDays(Team $team, string $company, string $platform, array $perDay): void
    {
        $source = DataSource::withoutGlobalScopes()->firstOrCreate(
            ['team_id' => $team->id, 'platform' => $platform],
            ['display_name' => $platform, 'status' => 'connected'],
        );

        foreach ($perDay as $date => $metrics) {
            app(SnapshotIngestService::class)->ingest($source, $this->snapshotBody($company, $platform, $metrics, "{$date}T06:00:00Z"));
        }
        app(ComputeMetrics::class)->forTeam($team);
    }

    public function test_the_metrics_endpoint_returns_value_delta_staleness_and_demo_flag(): void
    {
        $this->pushDays($this->owner->currentTeam, $this->company, 'tasks', [
            '2026-10-16' => ['open' => 10, 'overdue' => 5],
            '2026-10-17' => ['open' => 15],
        ]);

        $rows = collect($this->asApi($this->owner)->getJson('/api/v1/metrics')->assertOk()->json('data'));

        $open = $rows->where('key', 'tasks.open')->sortByDesc('date')->first();
        $this->assertSame('Open tasks', $open['label']);
        $this->assertEquals(15, $open['value']);
        $this->assertEquals(10, $open['previous_value']);
        $this->assertEquals(5, $open['delta']);
        $this->assertEquals(50, $open['delta_pct']);
        $this->assertFalse($open['stale']);
        $this->assertFalse($open['demo']);
        $this->assertSame('daily', $open['period']);

        // Reported on 16 Oct only: 51 hours before "now", so past two daily periods.
        $overdue = $rows->where('key', 'tasks.overdue')->sole();
        $this->assertTrue($overdue['stale']);

        $this->travelTo(now()->addDays(5));
        $later = collect($this->asApi($this->owner)->getJson('/api/v1/metrics')->json('data'));
        $this->assertTrue($later->every(fn ($r) => $r['stale'] === true), 'every value has aged out');
    }

    public function test_the_metrics_endpoint_only_returns_the_current_teams_values(): void
    {
        $other = $this->ownerWithTeam();
        $otherCompany = $this->linkCompany($other->currentTeam);
        $this->pushDays($other->currentTeam, $otherCompany, 'tasks', ['2026-10-17' => ['open' => 777]]);
        $this->pushDays($this->owner->currentTeam, $this->company, 'tasks', ['2026-10-17' => ['open' => 1]]);

        $rows = $this->asApi($this->owner)->getJson('/api/v1/metrics')->json('data');

        $this->assertCount(1, $rows);
        $this->assertEquals(1, $rows[0]['value']);
    }

    public function test_money_values_come_back_with_their_currency(): void
    {
        $this->pushDays($this->owner->currentTeam, $this->company, 'billing', ['2026-10-17' => ['mrr_cents' => 4500000, 'currency' => 'ZAR']]);

        $row = $this->asApi($this->owner)->getJson('/api/v1/metrics?metric_key=billing.mrr_cents')->json('data.0');

        $this->assertSame('ZAR', $row['currency']);
        $this->assertSame('cents', $row['unit']);
    }

    public function test_retired_platform_names_are_not_listed_as_connected_or_counted(): void
    {
        DataSource::factory()->create(['team_id' => $this->owner->currentTeam->id, 'platform' => 'dot.fleet', 'status' => 'connected']);
        DataSource::factory()->create(['team_id' => $this->owner->currentTeam->id, 'platform' => 'tasks', 'status' => 'connected']);

        $connected = collect($this->asApi($this->owner)->getJson('/api/v1/platforms/connected')->json('data'))->pluck('platform');

        $this->assertSame(['tasks'], $connected->all());
        $this->actingAs($this->owner)->get('/dashboard')->assertOk()->assertViewHas('connectedCount', 1);
    }
}
