<?php

namespace Tests\Feature\Metrics;

use App\Models\AnalyticsAlert;
use App\Models\DataSource;
use App\Models\Team;
use App\Services\Metrics\ComputeMetrics;
use App\Services\Metrics\MetricAnomalyDetector;
use App\Services\Metrics\MetricReader;
use App\Services\Metrics\SnapshotIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

class MetricReaderTest extends TestCase
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

    private function connect(string $platform, ?Team $team = null): DataSource
    {
        return DataSource::factory()->create(['team_id' => ($team ?? $this->team)->id, 'platform' => $platform, 'status' => 'connected']);
    }

    /** @param  array<string, array<string, mixed>>  $perDay  date => metrics */
    private function push(DataSource $source, array $perDay, string $type = 'webhook', ?string $company = null): void
    {
        foreach ($perDay as $date => $metrics) {
            app(SnapshotIngestService::class)->ingest(
                $source,
                $this->snapshotBody($company ?? $this->company, $source->platform, $metrics, "{$date}T06:00:00Z"),
                $type,
            );
        }
        app(ComputeMetrics::class)->forTeam(Team::find($source->team_id));
    }

    private function platform(array $dashboard, string $key): array
    {
        return collect($dashboard['platforms'])->firstWhere('key', $key);
    }

    private function tile(array $dashboard, string $platform, string $metric): array
    {
        return collect($this->platform($dashboard, $platform)['tiles'])->firstWhere('key', "{$platform}.{$metric}");
    }

    private function read(): array
    {
        return app(MetricReader::class)->dashboard($this->team);
    }

    public function test_with_nothing_connected_every_platform_is_listed_as_not_connected(): void
    {
        $dashboard = $this->read();

        $this->assertCount(11, $dashboard['platforms']);
        $this->assertTrue(collect($dashboard['platforms'])->every(fn ($p) => $p['connected'] === false && $p['tiles'] === []));
        $this->assertSame(0, $dashboard['summary']['connected']);
        $this->assertSame(0, $dashboard['summary']['metrics_reporting']);
        $this->assertSame('Nothing is reporting yet.', $dashboard['summary']['headline']);
    }

    public function test_a_connected_platform_with_no_snapshot_shows_every_metric_as_no_data(): void
    {
        $this->connect('tasks');

        $platform = $this->platform($this->read(), 'tasks');

        $this->assertTrue($platform['connected']);
        $this->assertFalse($platform['has_data']);
        $this->assertCount(4, $platform['tiles']);
        foreach ($platform['tiles'] as $tile) {
            $this->assertSame('no_data', $tile['state']);
            $this->assertNull($tile['value'], 'no data must never be shown as 0');
            $this->assertNull($tile['raw']);
            $this->assertSame([], $tile['points']);
        }
    }

    public function test_a_reported_metric_has_value_delta_tone_and_points_and_unreported_ones_stay_no_data(): void
    {
        $this->push($this->connect('tasks'), [
            '2026-10-16' => ['open' => 10, 'overdue' => 2],
            '2026-10-17' => ['open' => 15],
        ]);

        $dashboard = $this->read();
        $open = $this->tile($dashboard, 'tasks', 'open');

        $this->assertSame('ok', $open['state']);
        $this->assertSame('15', $open['value']);
        $this->assertSame('+5', $open['delta']);
        $this->assertSame('+50%', $open['delta_pct']);
        $this->assertSame('10', $open['previous']);
        $this->assertSame('up', $open['direction']);
        $this->assertSame('bad', $open['tone'], 'more open tasks is worse (good=down)');
        $this->assertSame('2026-10-17', $open['as_of']);
        $this->assertSame('Open tasks', $open['label']);
        $this->assertSame([['date' => '2026-10-16', 'value' => 10.0], ['date' => '2026-10-17', 'value' => 15.0]], $open['points']);
        $this->assertFalse($open['is_demo']);

        $this->assertSame('no_data', $this->tile($dashboard, 'tasks', 'completed_7d')['state']);
        $this->assertTrue($this->platform($dashboard, 'tasks')['has_data']);
    }

    public function test_a_first_point_has_no_delta_and_a_flat_one_is_neutral(): void
    {
        $this->push($this->connect('tasks'), ['2026-10-17' => ['open' => 10]]);
        $first = $this->tile($this->read(), 'tasks', 'open');
        $this->assertNull($first['delta']);
        $this->assertNull($first['direction']);
        $this->assertSame('neutral', $first['tone']);

        $this->push(DataSource::where('platform', 'tasks')->first(), ['2026-10-18' => ['open' => 10]]);
        $flat = $this->tile($this->read(), 'tasks', 'open');
        $this->assertSame('0', $flat['delta']);
        $this->assertSame('flat', $flat['direction']);
        $this->assertSame('neutral', $flat['tone']);
    }

    public function test_a_favourable_move_is_good_and_a_neutral_metric_is_never_toned(): void
    {
        $this->push($this->connect('tasks'), [
            '2026-10-16' => ['completed_7d' => 10, 'created_7d' => 10],
            '2026-10-17' => ['completed_7d' => 20, 'created_7d' => 20],
        ]);

        $dashboard = $this->read();

        $this->assertSame('good', $this->tile($dashboard, 'tasks', 'completed_7d')['tone']);
        $this->assertSame('neutral', $this->tile($dashboard, 'tasks', 'created_7d')['tone']);
    }

    public function test_a_value_older_than_two_periods_is_stale_and_keeps_its_last_known_value(): void
    {
        $this->push($this->connect('tasks'), ['2026-10-12' => ['open' => 10]]);

        $tile = $this->tile($this->read(), 'tasks', 'open');

        $this->assertSame('stale', $tile['state']);
        $this->assertSame('10', $tile['value']);
        $this->assertSame('2026-10-12', $tile['as_of']);
    }

    public function test_money_is_formatted_from_cents_with_its_currency(): void
    {
        $this->push($this->connect('billing'), ['2026-10-17' => ['mrr_cents' => 125000, 'currency' => 'ZAR']]);

        $tile = $this->tile($this->read(), 'billing', 'mrr_cents');

        $this->assertSame('ZAR 1,250.00', $tile['value']);
        $this->assertSame('ZAR', $tile['currency']);
    }

    public function test_seeded_demo_values_are_flagged(): void
    {
        $this->push($this->connect('tasks'), ['2026-10-17' => ['open' => 10]], 'demo');

        $this->assertTrue($this->tile($this->read(), 'tasks', 'open')['is_demo']);
        $this->assertTrue($this->read()['summary']['has_demo']);
    }

    public function test_the_summary_answers_in_words_what_needs_attention(): void
    {
        $tasks = $this->connect('tasks');
        $this->connect('billing');
        $this->push($tasks, ['2026-10-17' => ['open' => 10, 'overdue' => 1], '2026-10-10' => ['created_7d' => 5]]);

        $summary = $this->read()['summary'];

        $this->assertSame(2, $summary['connected']);
        $this->assertSame(1, $summary['reporting_platforms']);
        $this->assertSame(3, $summary['metrics_reporting']);
        $this->assertSame(1, $summary['stale_metrics']);
        $this->assertSame(0, $summary['open_anomalies']);
        $this->assertSame('1 metric has gone quiet.', $summary['headline']);
    }

    public function test_open_anomaly_alerts_come_first_in_the_headline(): void
    {
        $tasks = $this->connect('tasks');
        $perDay = [];
        foreach ([10, 11, 9, 10, 12, 10, 8, 10, 40] as $i => $v) {
            $perDay[now()->utc()->startOfDay()->subDays(9 - $i)->format('Y-m-d')] = ['overdue' => $v];
        }
        $this->push($tasks, $perDay);
        app(MetricAnomalyDetector::class)->detectForTeam($this->team);

        $summary = $this->read()['summary'];

        $this->assertSame(1, $summary['open_anomalies']);
        $this->assertSame('1 anomaly alert is open.', $summary['headline']);

        AnalyticsAlert::withoutGlobalScopes()->update(['status' => 'resolved']);
        $this->assertSame(0, $this->read()['summary']['open_anomalies']);
        $this->assertSame('Everything reporting is current.', $this->read()['summary']['headline']);
    }

    public function test_series_returns_ascending_points_and_the_days_that_raised_an_alert(): void
    {
        $tasks = $this->connect('tasks');
        $perDay = [];
        foreach ([10, 11, 9, 10, 12, 10, 8, 10, 40] as $i => $v) {
            $perDay[now()->utc()->startOfDay()->subDays(9 - $i)->format('Y-m-d')] = ['overdue' => $v];
        }
        $this->push($tasks, $perDay);
        app(MetricAnomalyDetector::class)->detectForTeam($this->team);

        $series = app(MetricReader::class)->series($this->team, 'tasks.overdue', 30);

        $this->assertCount(9, $series['points']);
        $this->assertSame('2026-10-09', $series['points'][0]['date']);
        $this->assertSame(40.0, $series['points'][8]['value']);
        $this->assertSame(['2026-10-17'], array_keys($series['anomalies']));
        $this->assertSame('overdue', $series['metric']);
        $this->assertSame('Overdue tasks', $series['label']);
    }

    public function test_series_respects_the_requested_window_and_unknown_keys_give_nothing(): void
    {
        $tasks = $this->connect('tasks');
        $this->push($tasks, ['2026-09-01' => ['open' => 1], '2026-10-16' => ['open' => 2], '2026-10-17' => ['open' => 3]]);

        $series = app(MetricReader::class)->series($this->team, 'tasks.open', 14);

        $this->assertCount(2, $series['points']);
        $this->assertNull(app(MetricReader::class)->series($this->team, 'tasks.not_a_metric', 14));
        $this->assertNull(app(MetricReader::class)->series($this->team, 'dot.fleet.speed', 14));
    }

    public function test_other_teams_values_never_appear(): void
    {
        $other = $this->ownerWithTeam()->currentTeam;
        $otherCompany = $this->linkCompany($other);
        $this->push($this->connect('tasks', $other), ['2026-10-17' => ['open' => 999]], 'webhook', $otherCompany);
        $this->push($this->connect('tasks'), ['2026-10-17' => ['open' => 1]]);

        $tile = $this->tile($this->read(), 'tasks', 'open');
        $series = app(MetricReader::class)->series($this->team, 'tasks.open', 30);

        $this->assertSame('1', $tile['value']);
        $this->assertSame([1.0], array_column($series['points'], 'value'));
    }

    public function test_a_disconnected_platform_shows_nothing_even_if_history_remains(): void
    {
        $source = $this->connect('tasks');
        $this->push($source, ['2026-10-17' => ['open' => 10]]);
        $source->update(['status' => 'pending']);

        $platform = $this->platform($this->read(), 'tasks');

        $this->assertFalse($platform['connected']);
        $this->assertSame([], $platform['tiles']);
    }
}
