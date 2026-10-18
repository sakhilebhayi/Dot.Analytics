<?php

namespace Tests\Feature\Metrics;

use App\Jobs\Analytics\ComputeMetricsJob;
use App\Livewire\Analytics\AlertsPanel;
use App\Models\AnalyticsAlert;
use App\Models\ComputedMetric;
use App\Models\DataSource;
use App\Models\Team;
use App\Services\Metrics\ComputeMetrics;
use App\Services\Metrics\MetricAnomalyDetector;
use App\Services\Metrics\SnapshotIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

class AnomalyDetectionTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private Team $team;

    private string $company;

    private DataSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->ownerWithTeam()->currentTeam;
        $this->company = $this->linkCompany($this->team);
        $this->source = DataSource::factory()->create(['team_id' => $this->team->id, 'platform' => 'tasks', 'status' => 'connected']);
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(9, 0));
    }

    /**
     * One 'tasks.overdue' snapshot per value, the last value on 2026-10-17, going back a day each.
     *
     * @param  list<int>  $values  oldest first
     */
    private function series(array $values, string $key = 'overdue', string $type = 'webhook', ?DataSource $source = null, ?string $company = null): void
    {
        $source ??= $this->source;
        $count = count($values);

        foreach ($values as $i => $value) {
            $day = now()->utc()->startOfDay()->subDays($count - $i)->format('Y-m-d');
            app(SnapshotIngestService::class)->ingest(
                $source,
                $this->snapshotBody($company ?? $this->company, $source->platform, [$key => $value], "{$day}T06:00:00Z"),
                $type,
            );
        }
    }

    private function detect(?Team $team = null): array
    {
        $team ??= $this->team;
        app(ComputeMetrics::class)->forTeam($team);

        return app(MetricAnomalyDetector::class)->detectForTeam($team);
    }

    private function alerts()
    {
        return AnalyticsAlert::withoutGlobalScopes()->orderBy('id')->get();
    }

    public function test_a_spike_after_seven_prior_points_raises_one_alert_with_provenance(): void
    {
        // 8 prior points around 10 (median 10, MAD 0.5, so the floor of 1 applies), then 40.
        $this->series([10, 11, 9, 10, 12, 10, 8, 10, 40]);

        $result = $this->detect();

        $this->assertSame(1, $result['created']);
        $alert = $this->alerts()->sole();
        $this->assertSame($this->team->id, $alert->team_id);
        $this->assertSame('open', $alert->status);
        $this->assertNotNull($alert->metric_definition_id);
        $this->assertSame('tasks.overdue', $alert->metricDefinition->key);
        $this->assertStringContainsString('Overdue tasks', $alert->title);
        $this->assertStringContainsString('40', $alert->description);
        $this->assertStringContainsString('median', $alert->description);

        $context = $alert->context;
        $this->assertSame('metric_anomaly', $context['source']);
        $this->assertSame('tasks.overdue', $context['metric_key']);
        $this->assertSame('tasks', $context['platform']);
        $this->assertSame('spike', $context['direction']);
        $this->assertEquals(40, $context['value']);
        $this->assertSame('2026-10-17', $context['period_date']);
        $this->assertEquals(10, $context['baseline']['median']);
        $this->assertEquals(0.5, $context['baseline']['mad']);
        $this->assertEquals(1.0, $context['baseline']['scale']);
        $this->assertEquals(30, $context['score']);
        $this->assertEquals(3.5, $context['threshold']);
        $this->assertSame(8, $context['window']['prior_points']);
        $this->assertCount(8, $context['window']['values']);
        $this->assertSame('2026-10-09', $context['window']['start']);
        $this->assertSame('2026-10-17', $context['window']['end']);
        $this->assertFalse($context['is_demo']);
        $this->assertSame('critical', $alert->severity, '30 MADs is at least twice the threshold');
    }

    public function test_six_prior_points_are_not_enough_but_seven_are(): void
    {
        $this->series([10, 11, 9, 10, 12, 10, 90]);
        $this->assertSame(0, $this->detect()['created'], 'six prior points: not enough history');
        $this->assertCount(0, $this->alerts());

        $this->team = $this->ownerWithTeam()->currentTeam;
        $company = $this->linkCompany($this->team);
        $source = DataSource::factory()->create(['team_id' => $this->team->id, 'platform' => 'tasks', 'status' => 'connected']);
        $this->series([10, 11, 9, 10, 12, 10, 8, 90], 'overdue', 'webhook', $source, $company);
        $this->assertSame(1, $this->detect($this->team)['created'], 'seven prior points: enough');
    }

    public function test_a_deviation_at_or_below_the_threshold_is_not_flagged(): void
    {
        // Prior points have median 10 and MAD 2: 17 is exactly 3.5 MADs away (not more than), 18 is 4.
        $this->series([10, 12, 8, 14, 6, 10, 12, 8, 17]);
        $this->assertSame(0, $this->detect()['created']);
    }

    public function test_a_deviation_just_above_the_threshold_is_flagged(): void
    {
        $this->series([10, 12, 8, 14, 6, 10, 12, 8, 18]);

        $this->assertSame(1, $this->detect()['created']);
    }

    public function test_a_drop_is_flagged_as_a_drop(): void
    {
        $this->series([100, 102, 98, 100, 104, 100, 96, 100, 40], 'open');

        $this->detect();

        $alert = $this->alerts()->sole();
        $this->assertSame('drop', $alert->context['direction']);
        $this->assertStringContainsString('Open tasks', $alert->title);
    }

    public function test_running_again_does_not_duplicate_the_alert(): void
    {
        $this->series([10, 11, 9, 10, 12, 10, 8, 10, 40]);

        $first = $this->detect();
        $second = $this->detect();
        $third = app(MetricAnomalyDetector::class)->detectForTeam($this->team);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['flagged'], 'it is still anomalous, just already reported');
        $this->assertSame(0, $third['created']);
        $this->assertCount(1, $this->alerts());
    }

    public function test_a_resolved_alert_is_not_reopened_the_same_day(): void
    {
        $this->series([10, 11, 9, 10, 12, 10, 8, 10, 40]);
        $this->detect();
        $this->alerts()->sole()->update(['status' => 'resolved', 'resolved_at' => now()]);

        $this->detect();

        $alert = $this->alerts()->sole();
        $this->assertSame('resolved', $alert->status);
    }

    public function test_the_same_metric_flagged_on_a_later_day_is_a_new_alert(): void
    {
        $this->series([10, 11, 9, 10, 12, 10, 8, 10, 40]);
        $this->detect();

        $this->travelTo(now()->addDay());
        app(SnapshotIngestService::class)->ingest(
            $this->source,
            $this->snapshotBody($this->company, 'tasks', ['overdue' => 60], '2026-10-18T06:00:00Z'),
        );
        $this->detect();

        $this->assertCount(2, $this->alerts());
        $this->assertSame(['2026-10-17', '2026-10-18'], $this->alerts()->map(fn ($a) => $a->context['period_date'])->all());
    }

    public function test_a_stale_latest_value_is_never_flagged(): void
    {
        $this->series([10, 11, 9, 10, 12, 10, 8, 10, 40]);
        $this->travelTo(now()->addDays(4)); // latest point is now 4 days old

        $result = $this->detect();

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['skipped_stale']);
        $this->assertCount(0, $this->alerts());
    }

    public function test_a_flat_history_uses_a_floor_so_tiny_wobbles_are_quiet_and_real_jumps_are_not(): void
    {
        // Flat at 100 (MAD 0): scale is 1% of the median = 1.
        $this->series([100, 100, 100, 100, 100, 100, 100, 100, 102], 'open');
        $this->assertSame(0, $this->detect()['created'], '2 over a flat 100 is 2 MADs');

        $this->team = $this->ownerWithTeam()->currentTeam;
        $company = $this->linkCompany($this->team);
        $source = DataSource::factory()->create(['team_id' => $this->team->id, 'platform' => 'tasks', 'status' => 'connected']);
        $this->series([100, 100, 100, 100, 100, 100, 100, 100, 150], 'open', 'webhook', $source, $company);
        $this->assertSame(1, $this->detect($this->team)['created']);
    }

    public function test_a_history_of_zeros_flags_a_jump_using_the_metric_kinds_minimum_scale(): void
    {
        $this->series([0, 0, 0, 0, 0, 0, 0, 0, 3], 'overdue');
        $this->assertSame(0, $this->detect()['created'], '3 over zero with a scale of 1 is 3 MADs');

        $this->team = $this->ownerWithTeam()->currentTeam;
        $company = $this->linkCompany($this->team);
        $source = DataSource::factory()->create(['team_id' => $this->team->id, 'platform' => 'tasks', 'status' => 'connected']);
        $this->series([0, 0, 0, 0, 0, 0, 0, 0, 5], 'overdue', 'webhook', $source, $company);
        $this->assertSame(1, $this->detect($this->team)['created']);
    }

    public function test_the_threshold_comes_from_config(): void
    {
        $this->series([10, 11, 9, 10, 12, 10, 8, 10, 14]);
        config(['metrics.anomaly.mad_threshold' => 10]);

        $this->assertSame(0, $this->detect()['created']);
    }

    public function test_only_points_inside_the_window_count_as_history(): void
    {
        // 12 old points then a 40-day gap-free tail would be unusual; build 9 points
        // ending 17 Oct but push the first 8 far back so only 3 fall inside 30 days.
        $source = $this->source;
        foreach ([10, 11, 9, 10, 12] as $i => $v) {
            $day = now()->utc()->startOfDay()->subDays(60 + $i)->format('Y-m-d');
            app(SnapshotIngestService::class)->ingest($source, $this->snapshotBody($this->company, 'tasks', ['overdue' => $v], "{$day}T06:00:00Z"));
        }
        foreach ([10, 8, 10, 40] as $i => $v) {
            $day = now()->utc()->startOfDay()->subDays(4 - $i)->format('Y-m-d');
            app(SnapshotIngestService::class)->ingest($source, $this->snapshotBody($this->company, 'tasks', ['overdue' => $v], "{$day}T06:00:00Z"));
        }

        $this->assertSame(0, $this->detect()['created'], 'only three prior points are inside the 30-day window');
    }

    public function test_missing_days_are_not_filled_so_history_can_have_gaps(): void
    {
        // Eight prior points over 20 days (every ~2.5 days), then a spike.
        $days = [24, 22, 20, 17, 14, 11, 8, 5, 1];
        foreach ($days as $i => $ago) {
            $value = $i === count($days) - 1 ? 40 : [10, 11, 9, 10, 12, 10, 8, 10][$i];
            $day = now()->utc()->startOfDay()->subDays($ago)->format('Y-m-d');
            app(SnapshotIngestService::class)->ingest($this->source, $this->snapshotBody($this->company, 'tasks', ['overdue' => $value], "{$day}T06:00:00Z"));
        }

        $this->assertSame(1, $this->detect()['created']);
    }

    public function test_money_alerts_carry_the_currency_and_format_it(): void
    {
        $source = DataSource::factory()->create(['team_id' => $this->team->id, 'platform' => 'billing', 'status' => 'connected']);
        $values = [100000, 101000, 99000, 100000, 102000, 100000, 98000, 100000, 900000];
        foreach ($values as $i => $v) {
            $day = now()->utc()->startOfDay()->subDays(count($values) - $i)->format('Y-m-d');
            app(SnapshotIngestService::class)->ingest($source, $this->snapshotBody($this->company, 'billing', ['overdue_cents' => $v, 'currency' => 'ZAR'], "{$day}T06:00:00Z"));
        }

        $this->detect();

        $alert = $this->alerts()->sole();
        $this->assertSame('ZAR', $alert->context['currency']);
        $this->assertStringContainsString('ZAR 9,000.00', $alert->description);
    }

    public function test_demo_data_is_marked_on_the_alert(): void
    {
        $this->series([10, 11, 9, 10, 12, 10, 8, 10, 40], 'overdue', 'demo');

        $this->detect();

        $this->assertTrue($this->alerts()->sole()->context['is_demo']);
    }

    public function test_alerts_belong_to_the_team_whose_data_triggered_them(): void
    {
        $other = $this->ownerWithTeam()->currentTeam;
        $otherCompany = $this->linkCompany($other);
        $otherSource = DataSource::factory()->create(['team_id' => $other->id, 'platform' => 'tasks', 'status' => 'connected']);
        $this->series([10, 11, 9, 10, 12, 10, 8, 10, 40], 'overdue', 'webhook', $otherSource, $otherCompany);
        $this->series([10, 11, 9, 10, 12, 10, 8, 10, 10]);

        $this->assertSame(0, $this->detect($this->team)['created']);
        $this->assertSame(1, $this->detect($other)['created']);
        $this->assertSame($other->id, $this->alerts()->sole()->team_id);
    }

    public function test_it_reads_only_computed_metrics_not_raw_snapshots(): void
    {
        $this->series([10, 11, 9, 10, 12, 10, 8, 10, 40]);
        // Snapshots exist but nothing has been computed yet.
        $this->assertSame(0, ComputedMetric::withoutGlobalScopes()->count());

        $result = app(MetricAnomalyDetector::class)->detectForTeam($this->team);

        $this->assertSame(0, $result['created']);
    }

    public function test_a_team_with_no_data_is_checked_without_inventing_anything(): void
    {
        $result = $this->detect();

        $this->assertSame(['checked' => 0, 'flagged' => 0, 'created' => 0, 'skipped_insufficient' => 0, 'skipped_stale' => 0], $result);
        $this->assertCount(0, $this->alerts());
    }

    public function test_the_compute_command_runs_detection_after_computing(): void
    {
        $this->series([10, 11, 9, 10, 12, 10, 8, 10, 40]);

        $this->artisan('analytics:compute-metrics')
            ->expectsOutputToContain('1 alert(s) raised')
            ->assertSuccessful();

        $this->assertCount(1, $this->alerts());
    }

    public function test_the_post_ingest_job_also_detects(): void
    {
        $this->series([10, 11, 9, 10, 12, 10, 8, 10]);
        app(ComputeMetrics::class)->forTeam($this->team);
        app(SnapshotIngestService::class)->ingest($this->source, $this->snapshotBody($this->company, 'tasks', ['overdue' => 77], '2026-10-17T18:00:00Z'));

        dispatch_sync(new ComputeMetricsJob($this->team->id));

        $this->assertCount(1, $this->alerts());
    }

    public function test_the_alerts_panel_shows_where_an_anomaly_alert_came_from(): void
    {
        $this->series([10, 11, 9, 10, 12, 10, 8, 10, 40], 'overdue', 'demo');
        $this->detect();
        $owner = $this->team->owner;

        $this->actingAs($owner);
        Livewire::test(AlertsPanel::class)
            ->assertSee('Anomaly detected: Overdue tasks')
            ->assertSeeHtml('data-testid="alert-provenance"')
            ->assertSee('tasks.overdue')
            ->assertSee('2026-10-09 to 2026-10-17')
            ->assertSee('8 prior points')
            ->assertSee('threshold 3.5')
            ->assertSee('DEMO DATA');
    }
}
