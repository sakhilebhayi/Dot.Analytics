<?php

namespace Tests\Feature\Metrics;

use App\Models\AnalyticsSnapshot;
use App\Models\ComputedMetric;
use App\Models\MetricDefinition;
use App\Models\Team;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoMetricsSeeder;
use Database\Seeders\MetricDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemo(): void
    {
        $this->seed(DemoMetricsSeeder::class);
    }

    public function test_it_posts_demo_snapshots_through_the_contract_and_computes_them(): void
    {
        $this->seedDemo();

        $team = Team::where('name', DemoMetricsSeeder::TEAM_NAME)->sole();
        $this->assertNotNull($team->infodot_team_uuid);

        $snapshots = AnalyticsSnapshot::withoutGlobalScopes()->get();
        $this->assertGreaterThanOrEqual(4 * (DemoMetricsSeeder::DAYS - 1), $snapshots->count());
        $this->assertSame(['demo'], $snapshots->pluck('snapshot_type')->unique()->values()->all());
        $this->assertSame($team->id, $snapshots->pluck('team_id')->unique()->sole());

        $metrics = ComputedMetric::withoutGlobalScopes()->get();
        $this->assertNotEmpty($metrics);
        $this->assertTrue($metrics->every(fn ($m) => $m->is_demo), 'seeded numbers must be flagged as demo');
    }

    public function test_it_is_idempotent(): void
    {
        $this->seedDemo();
        $snapshots = AnalyticsSnapshot::withoutGlobalScopes()->count();
        $metrics = ComputedMetric::withoutGlobalScopes()->count();

        $this->seedDemo();

        $this->assertSame($snapshots, AnalyticsSnapshot::withoutGlobalScopes()->count());
        $this->assertSame($metrics, ComputedMetric::withoutGlobalScopes()->count());
        $this->assertSame(1, Team::where('name', DemoMetricsSeeder::TEAM_NAME)->count());
    }

    public function test_it_refuses_to_run_outside_local_and_testing(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        try {
            app(DemoMetricsSeeder::class)->run();
        } finally {
            $this->assertSame(0, AnalyticsSnapshot::withoutGlobalScopes()->count());
            $this->assertSame(0, ComputedMetric::withoutGlobalScopes()->count());
        }
    }

    public function test_the_default_seeder_never_calls_it(): void
    {
        $this->assertStringNotContainsString('DemoMetricsSeeder', file_get_contents((new \ReflectionClass(DatabaseSeeder::class))->getFileName()));

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, AnalyticsSnapshot::withoutGlobalScopes()->count());
        $this->assertSame(0, ComputedMetric::withoutGlobalScopes()->count());
    }

    public function test_the_metric_definition_seeder_syncs_the_catalogue_and_drops_retired_definitions(): void
    {
        MetricDefinition::create(['key' => 'fleet.health_score', 'label' => 'Fleet Health Score', 'source_platform' => 'dot.fleet', 'engine' => 'asset', 'aggregation' => 'avg']);

        $this->seed(MetricDefinitionSeeder::class);
        $this->seed(MetricDefinitionSeeder::class);

        $expected = collect(config('metrics.platforms'))->sum(fn ($p) => count($p['metrics']));
        $this->assertSame($expected, MetricDefinition::count());
        $this->assertSame(0, MetricDefinition::where('source_platform', 'like', 'dot.%')->count());
        $this->assertSame('financial', MetricDefinition::where('key', 'billing.mrr_cents')->value('engine'));
        $this->assertSame(0, ComputedMetric::withoutGlobalScopes()->count(), 'definitions are not values');
    }
}
