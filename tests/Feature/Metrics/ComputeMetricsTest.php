<?php

namespace Tests\Feature\Metrics;

use App\Models\AnalyticsSnapshot;
use App\Models\ComputedMetric;
use App\Models\DataSource;
use App\Models\MetricDefinition;
use App\Models\Team;
use App\Services\Metrics\ComputeMetrics;
use App\Services\Metrics\SnapshotIngestService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

class ComputeMetricsTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private Team $team;

    private string $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->ownerWithTeam()->currentTeam;
        $this->company = $this->linkCompany($this->team);
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(9, 0));
    }

    private function source(string $platform, ?Team $team = null, string $status = 'connected'): DataSource
    {
        return DataSource::factory()->create([
            'team_id' => ($team ?? $this->team)->id,
            'platform' => $platform,
            'status' => $status,
        ]);
    }

    /** Ingest through the real contract so the tests exercise the same path as production. */
    private function push(DataSource $source, array $metrics, string $takenAt, string $type = 'webhook'): AnalyticsSnapshot
    {
        $body = $this->snapshotBody($this->company, $source->platform, $metrics, $takenAt);

        return app(SnapshotIngestService::class)->ingest($source, $body, $type)['snapshot'];
    }

    private function compute(?Team $team = null): array
    {
        return app(ComputeMetrics::class)->forTeam($team ?? $this->team);
    }

    private function rows(string $key, ?Team $team = null)
    {
        return ComputedMetric::withoutGlobalScopes()
            ->where('team_id', ($team ?? $this->team)->id)
            ->whereHas('metricDefinition', fn ($q) => $q->where('key', $key))
            ->orderBy('period_date')
            ->get();
    }

    public function test_it_computes_value_previous_delta_and_window_per_day(): void
    {
        $source = $this->source('tasks');
        $this->push($source, ['open' => 10], '2026-10-16T06:00:00Z');
        $this->push($source, ['open' => 15], '2026-10-17T06:00:00Z');

        $this->compute();

        $rows = $this->rows('tasks.open');
        $this->assertCount(2, $rows);

        $first = $rows[0];
        $this->assertSame('daily', $first->period);
        $this->assertSame('2026-10-16', $first->period_date->toDateString());
        $this->assertSame(10.0, (float) $first->value);
        $this->assertNull($first->previous_value);
        $this->assertNull($first->delta);
        $this->assertNull($first->delta_pct);
        $this->assertNull($first->previous_period_date);

        $latest = $rows[1];
        $this->assertSame('2026-10-17', $latest->period_date->toDateString());
        $this->assertSame(15.0, (float) $latest->value);
        $this->assertSame(10.0, (float) $latest->previous_value);
        $this->assertSame(5.0, (float) $latest->delta);
        $this->assertSame(50.0, (float) $latest->delta_pct);
        $this->assertSame('2026-10-16', $latest->previous_period_date->toDateString());
        $this->assertSame('2026-10-17 06:00:00', $latest->observed_at->utc()->format('Y-m-d H:i:s'));
        $this->assertNotNull($latest->snapshot_id);
    }

    public function test_a_drop_has_a_negative_delta(): void
    {
        $source = $this->source('tasks');
        $this->push($source, ['overdue' => 8], '2026-10-16T06:00:00Z');
        $this->push($source, ['overdue' => 6], '2026-10-17T06:00:00Z');

        $this->compute();

        $latest = $this->rows('tasks.overdue')->last();
        $this->assertSame(-2.0, (float) $latest->delta);
        $this->assertSame(-25.0, (float) $latest->delta_pct);
    }

    public function test_delta_percent_is_absent_not_infinite_when_the_previous_value_is_zero(): void
    {
        $source = $this->source('tasks');
        $this->push($source, ['overdue' => 0], '2026-10-16T06:00:00Z');
        $this->push($source, ['overdue' => 4], '2026-10-17T06:00:00Z');

        $this->compute();

        $latest = $this->rows('tasks.overdue')->last();
        $this->assertSame(4.0, (float) $latest->delta);
        $this->assertNull($latest->delta_pct);
    }

    public function test_a_connected_platform_with_no_snapshot_has_no_computed_rows(): void
    {
        $this->source('tasks');

        $summary = $this->compute();

        $this->assertSame(0, ComputedMetric::withoutGlobalScopes()->count());
        $this->assertSame(0, $summary['metrics_written']);
    }

    public function test_a_key_the_platform_never_reported_is_absent_not_zero(): void
    {
        $source = $this->source('tasks');
        $this->push($source, ['open' => 10], '2026-10-17T06:00:00Z');

        $this->compute();

        $this->assertCount(1, $this->rows('tasks.open'));
        $this->assertCount(0, $this->rows('tasks.overdue'));
        $this->assertCount(0, $this->rows('tasks.completed_7d'));
    }

    public function test_computing_twice_changes_nothing(): void
    {
        $source = $this->source('tasks');
        $this->push($source, ['open' => 10, 'overdue' => 2], '2026-10-16T06:00:00Z');
        $this->push($source, ['open' => 15, 'overdue' => 1], '2026-10-17T06:00:00Z');

        $first = $this->compute();
        $before = ComputedMetric::withoutGlobalScopes()->orderBy('id')->get()->map->only(['id', 'metric_definition_id', 'period_date', 'value', 'delta'])->all();
        $second = $this->compute();
        $after = ComputedMetric::withoutGlobalScopes()->orderBy('id')->get()->map->only(['id', 'metric_definition_id', 'period_date', 'value', 'delta'])->all();

        $this->assertSame(4, $first['metrics_written']);
        $this->assertSame(0, $second['metrics_written'], 'nothing changed, so nothing is rewritten');
        $this->assertEquals($before, $after);
        $this->assertSame(4, ComputedMetric::withoutGlobalScopes()->count());
    }

    public function test_the_latest_snapshot_of_a_day_wins(): void
    {
        $source = $this->source('tasks');
        $this->push($source, ['open' => 10], '2026-10-17T06:00:00Z');
        $this->push($source, ['open' => 11], '2026-10-17T18:00:00Z');

        $this->compute();

        $rows = $this->rows('tasks.open');
        $this->assertCount(1, $rows);
        $this->assertSame(11.0, (float) $rows[0]->value);
    }

    public function test_late_arriving_older_snapshots_are_ordered_by_taken_at(): void
    {
        $source = $this->source('tasks');
        $this->push($source, ['open' => 15], '2026-10-17T06:00:00Z');
        $this->push($source, ['open' => 10], '2026-10-16T06:00:00Z');

        $this->compute();

        $latest = $this->rows('tasks.open')->last();
        $this->assertSame('2026-10-17', $latest->period_date->toDateString());
        $this->assertSame(10.0, (float) $latest->previous_value);
    }

    public function test_a_late_snapshot_corrects_the_following_days_delta_on_the_next_run(): void
    {
        $source = $this->source('tasks');
        $this->push($source, ['open' => 20], '2026-10-17T06:00:00Z');
        $this->compute();
        $this->assertNull($this->rows('tasks.open')->last()->delta);

        $this->push($source, ['open' => 10], '2026-10-16T06:00:00Z');
        $this->compute();

        $this->assertSame(10.0, (float) $this->rows('tasks.open')->last()->delta);
    }

    public function test_definitions_are_created_from_the_catalogue_once(): void
    {
        $source = $this->source('billing');
        $this->push($source, ['mrr_cents' => 100000, 'open_invoices' => 3, 'currency' => 'ZAR'], '2026-10-17T06:00:00Z');

        $this->compute();
        $this->compute();

        $definition = MetricDefinition::where('key', 'billing.mrr_cents')->sole();
        $this->assertSame('billing', $definition->source_platform);
        $this->assertSame('financial', $definition->engine);
        $this->assertSame('Monthly recurring revenue', $definition->label);
        $this->assertSame('cents', $definition->unit);
        $this->assertSame('latest', $definition->aggregation);
        $this->assertSame(2, MetricDefinition::where('source_platform', 'billing')->count());
    }

    public function test_money_keeps_its_currency_and_never_compares_across_currencies(): void
    {
        $source = $this->source('billing');
        $this->push($source, ['mrr_cents' => 100000, 'currency' => 'ZAR'], '2026-10-16T06:00:00Z');
        $this->push($source, ['mrr_cents' => 5000, 'currency' => 'USD'], '2026-10-17T06:00:00Z');

        $this->compute();

        $rows = $this->rows('billing.mrr_cents');
        $this->assertSame('ZAR', $rows[0]->currency);
        $this->assertSame('USD', $rows[1]->currency);
        $this->assertNull($rows[1]->delta, 'ZAR to USD is not a change in value');
        $this->assertNull($rows[1]->delta_pct);
        $this->assertNull($rows[1]->previous_value);
    }

    public function test_a_latest_value_older_than_two_periods_is_stale(): void
    {
        $source = $this->source('tasks');
        $this->push($source, ['open' => 10], '2026-10-14T06:00:00Z'); // 4 days before "now"
        $this->push($source, ['overdue' => 1], '2026-10-17T06:00:00Z'); // 27 hours before "now"

        $this->compute();
        $now = now();

        $this->assertTrue($this->rows('tasks.open')->last()->isStale($now));
        $this->assertFalse($this->rows('tasks.overdue')->last()->isStale($now));

        $this->travelTo(now()->addDays(3));
        $this->assertTrue($this->rows('tasks.overdue')->last()->isStale(now()), 'it ages into stale without any recompute');
    }

    public function test_exactly_two_periods_old_is_still_fresh_and_just_over_is_stale(): void
    {
        $source = $this->source('tasks');
        $this->push($source, ['open' => 1], '2026-10-16T09:00:00Z'); // exactly 2 days before now
        $this->compute();
        $row = $this->rows('tasks.open')->last();

        $this->assertFalse($row->isStale(now()));
        $this->assertTrue($row->isStale(now()->addMinute()));
    }

    public function test_the_summary_counts_stale_metrics(): void
    {
        $source = $this->source('tasks');
        $this->push($source, ['open' => 10], '2026-10-10T06:00:00Z');
        $this->push($source, ['overdue' => 1], '2026-10-17T06:00:00Z');

        $summary = $this->compute();

        $this->assertSame(1, $summary['stale_metrics']);
    }

    public function test_legacy_snapshots_without_a_taken_at_are_ignored(): void
    {
        $source = $this->source('tasks');
        $this->snapshot($source, ['vehicles' => [['id' => 'V-1']], 'metrics' => ['open' => 999]]);

        $this->compute();

        $this->assertSame(0, ComputedMetric::withoutGlobalScopes()->count());
    }

    public function test_only_connected_sources_are_computed(): void
    {
        $source = $this->source('tasks');
        $this->push($source, ['open' => 10], '2026-10-17T06:00:00Z');
        $source->update(['status' => 'pending']);

        $this->compute();

        $this->assertSame(0, ComputedMetric::withoutGlobalScopes()->count());
    }

    public function test_one_teams_snapshots_never_reach_another_team(): void
    {
        $other = $this->ownerWithTeam()->currentTeam;
        $otherCompany = $this->linkCompany($other);
        $mine = $this->source('tasks');
        $theirs = $this->source('tasks', $other);
        $this->push($mine, ['open' => 1], '2026-10-17T06:00:00Z');
        app(SnapshotIngestService::class)->ingest($theirs, $this->snapshotBody($otherCompany, 'tasks', ['open' => 500], '2026-10-17T06:00:00Z'));

        $this->compute();
        $this->compute($other);

        $this->assertSame(1.0, (float) $this->rows('tasks.open', $this->team)->sole()->value);
        $this->assertSame(500.0, (float) $this->rows('tasks.open', $other)->sole()->value);
    }

    public function test_demo_snapshots_are_flagged_so_they_are_never_shown_as_real(): void
    {
        $source = $this->source('tasks');
        $this->push($source, ['open' => 10], '2026-10-16T06:00:00Z', 'demo');
        $this->push($source, ['overdue' => 1], '2026-10-17T06:00:00Z', 'webhook');

        $this->compute();

        $this->assertTrue($this->rows('tasks.open')->last()->is_demo);
        $this->assertFalse($this->rows('tasks.overdue')->last()->is_demo);
    }

    public function test_the_command_computes_every_team_and_can_be_limited_to_one(): void
    {
        $other = $this->ownerWithTeam()->currentTeam;
        $otherCompany = $this->linkCompany($other);
        $this->push($this->source('tasks'), ['open' => 1], '2026-10-17T06:00:00Z');
        app(SnapshotIngestService::class)->ingest($this->source('tasks', $other), $this->snapshotBody($otherCompany, 'tasks', ['open' => 2], '2026-10-17T06:00:00Z'));

        $this->artisan('analytics:compute-metrics', ['--team' => $this->team->id])->assertSuccessful();
        $this->assertSame(1, ComputedMetric::withoutGlobalScopes()->count());

        $this->artisan('analytics:compute-metrics')->assertSuccessful();
        $this->assertSame(2, ComputedMetric::withoutGlobalScopes()->count());

        $this->artisan('analytics:compute-metrics')->assertSuccessful();
        $this->assertSame(2, ComputedMetric::withoutGlobalScopes()->count());
    }

    public function test_the_computation_is_scheduled_hourly_without_overlap(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'analytics:compute-metrics'));

        $this->assertNotNull($event, 'analytics:compute-metrics is not scheduled');
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    public function test_every_catalogue_metric_round_trips_through_ingest_and_compute(): void
    {
        foreach (config('metrics.platforms') as $platform => $definition) {
            $source = $this->source($platform);
            $metrics = [];
            $needsCurrency = false;
            foreach ($definition['metrics'] as $key => $metric) {
                $metrics[$key] = match ($metric['type']) {
                    'rating' => 4.5,
                    'quantity' => 12.5,
                    default => 3,
                };
                $needsCurrency = $needsCurrency || $metric['type'] === 'cents';
            }
            if ($needsCurrency) {
                $metrics['currency'] = 'ZAR';
            }
            $this->push($source, $metrics, '2026-10-17T06:00:00Z');
        }

        $this->compute();

        $expected = collect(config('metrics.platforms'))->sum(fn ($p) => count($p['metrics']));
        $this->assertSame($expected, ComputedMetric::withoutGlobalScopes()->count());
    }
}
