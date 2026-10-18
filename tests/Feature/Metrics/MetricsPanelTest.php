<?php

namespace Tests\Feature\Metrics;

use App\Livewire\Analytics\MetricsPanel;
use App\Models\DataSource;
use App\Models\Team;
use App\Models\User;
use App\Services\Metrics\ComputeMetrics;
use App\Services\Metrics\MetricAnomalyDetector;
use App\Services\Metrics\SnapshotIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

class MetricsPanelTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private User $owner;

    private Team $team;

    private string $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->ownerWithTeam();
        $this->team = $this->owner->currentTeam;
        $this->company = $this->linkCompany($this->team);
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(9, 0));
    }

    private function connect(string $platform): DataSource
    {
        return DataSource::factory()->create(['team_id' => $this->team->id, 'platform' => $platform, 'status' => 'connected']);
    }

    private function push(DataSource $source, array $perDay, string $type = 'webhook'): void
    {
        foreach ($perDay as $date => $metrics) {
            app(SnapshotIngestService::class)->ingest($source, $this->snapshotBody($this->company, $source->platform, $metrics, "{$date}T06:00:00Z"), $type);
        }
        app(ComputeMetrics::class)->forTeam($this->team);
    }

    /** The text of one field of one tile, found by its data attributes, never by a number appearing somewhere on the page. */
    private function field(string $html, string $key, string $testId): ?string
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $node = (new \DOMXPath($dom))->query("//*[@data-key='{$key}']//*[@data-testid='{$testId}']")->item(0);

        return $node ? trim(preg_replace('/\s+/', ' ', $node->textContent)) : null;
    }

    private function panel(?User $as = null)
    {
        return Livewire::actingAs($as ?? $this->owner)->test(MetricsPanel::class);
    }

    public function test_with_nothing_connected_it_says_so_and_explains_how_to_connect_and_what_to_send(): void
    {
        $this->panel()
            ->assertStatus(200)
            ->assertSee('Nothing is reporting yet.')
            ->assertSee('Connect a platform')
            ->assertSeeHtml('href="#connected-platforms"')
            ->assertSee('POST /api/v1/ingest/{platform}')
            ->assertSee('X-Analytics-Signature')
            ->assertSee('company_uuid')
            ->assertSee('docs/metrics-contract.md')
            ->assertDontSeeHtml('data-testid="sparkline"')
            ->assertDontSeeHtml('data-testid="trend-chart"');
    }

    public function test_it_lists_the_platforms_that_could_be_connected_by_their_real_names(): void
    {
        $this->panel()->assertSee('Dot.Tasks')->assertSee('Dot.Billing')->assertDontSee('Dot.Fleet')->assertDontSee('Dot.CRM');
    }

    public function test_a_connected_platform_with_no_snapshot_shows_no_data_yet_and_no_numbers(): void
    {
        $this->connect('tasks');

        $this->panel()
            ->assertSee('Dot.Tasks')
            ->assertSee('has not sent a snapshot yet')
            ->assertSee('no data yet')
            ->assertSeeHtml('data-state="no_data"')
            ->assertDontSeeHtml('data-state="ok"')
            ->assertDontSeeHtml('data-testid="sparkline"');
    }

    public function test_tiles_show_value_delta_and_a_sparkline_from_stored_points(): void
    {
        $this->push($this->connect('tasks'), [
            '2026-10-15' => ['open' => 8],
            '2026-10-16' => ['open' => 10],
            '2026-10-17' => ['open' => 15],
        ]);

        $html = $this->panel()
            ->assertSee('Open tasks')
            ->assertSeeHtml('data-state="ok"')
            ->assertSeeHtml('data-testid="sparkline"')
            ->assertSeeHtml('data-tone="bad"')
            ->html();

        $this->assertSame('15', $this->field($html, 'tasks.open', 'kpi-value'));
        $this->assertSame('+5', $this->field($html, 'tasks.open', 'kpi-delta'));
        $this->assertSame('+50%', $this->field($html, 'tasks.open', 'kpi-delta-pct'));
        $this->assertSame('as of 2026-10-17', $this->field($html, 'tasks.open', 'kpi-asof'));
        $this->assertSame('no data yet', $this->field($html, 'tasks.completed_7d', 'kpi-value'));
        $this->assertMatchesRegularExpression('/<polyline[^>]+points="[0-9. ,]+"/', $html);
    }

    public function test_a_stale_tile_keeps_its_last_value_and_is_marked_stale(): void
    {
        $this->push($this->connect('tasks'), ['2026-10-12' => ['open' => 10]]);

        $this->panel()
            ->assertSeeHtml('data-state="stale"')
            ->assertSee('Stale')
            ->assertSee('as of 2026-10-12')
            ->assertSee('1 metric has gone quiet.');
    }

    public function test_demo_data_is_labelled(): void
    {
        $this->push($this->connect('tasks'), ['2026-10-17' => ['open' => 10]], 'demo');

        $this->panel()->assertSee('DEMO DATA');
    }

    public function test_real_data_is_not_labelled_demo(): void
    {
        $this->push($this->connect('tasks'), ['2026-10-17' => ['open' => 10]]);

        $this->panel()->assertDontSee('DEMO DATA');
    }

    public function test_it_opens_on_the_first_metric_that_has_data_and_draws_a_trend(): void
    {
        $this->push($this->connect('tasks'), [
            '2026-10-16' => ['overdue' => 2],
            '2026-10-17' => ['overdue' => 3],
        ]);

        $this->panel()
            ->assertSet('selectedKey', 'tasks.overdue')
            ->assertSeeHtml('data-testid="trend-chart"')
            ->assertSee('Overdue tasks');
    }

    public function test_it_opens_on_the_metric_with_an_open_anomaly_rather_than_the_first_one(): void
    {
        $source = $this->connect('tasks');
        $perDay = [];
        foreach ([10, 11, 9, 10, 12, 10, 8, 10, 40] as $i => $v) {
            $day = now()->utc()->startOfDay()->subDays(9 - $i)->format('Y-m-d');
            $perDay[$day] = ['open' => 100 + $i, 'overdue' => $v];
        }
        $this->push($source, $perDay);
        app(MetricAnomalyDetector::class)->detectForTeam($this->team);

        $this->panel()->assertSet('selectedKey', 'tasks.overdue');
    }

    public function test_selecting_another_metric_redraws_the_trend_and_a_bad_key_is_ignored(): void
    {
        $this->push($this->connect('tasks'), [
            '2026-10-16' => ['open' => 10, 'overdue' => 2],
            '2026-10-17' => ['open' => 15, 'overdue' => 3],
        ]);

        $this->panel()
            ->call('select', 'tasks.open')
            ->assertSet('selectedKey', 'tasks.open')
            ->call('select', 'dot.fleet.speed')
            ->assertSet('selectedKey', 'tasks.open')
            ->call('select', 'billing.mrr_cents')
            ->assertSet('selectedKey', 'tasks.open', 'a platform that is not connected cannot be selected');
    }

    public function test_the_trend_range_can_be_changed_and_only_known_ranges_are_accepted(): void
    {
        $this->push($this->connect('tasks'), ['2026-10-17' => ['open' => 10]]);

        $this->panel()
            ->assertSet('days', 30)
            ->call('setDays', 60)->assertSet('days', 60)
            ->call('setDays', 5000)->assertSet('days', 60);
    }

    public function test_selecting_a_metric_with_no_data_says_no_data_yet_instead_of_drawing_a_chart(): void
    {
        $this->push($this->connect('tasks'), ['2026-10-17' => ['open' => 10]]);

        $this->panel()
            ->call('select', 'tasks.created_7d')
            ->assertSee('No data yet for Tasks created, 7 days')
            ->assertDontSeeHtml('data-testid="trend-chart"');
    }

    public function test_anomaly_days_are_marked_on_the_trend(): void
    {
        $source = $this->connect('tasks');
        $perDay = [];
        foreach ([10, 11, 9, 10, 12, 10, 8, 10, 40] as $i => $v) {
            $perDay[now()->utc()->startOfDay()->subDays(9 - $i)->format('Y-m-d')] = ['overdue' => $v];
        }
        $this->push($source, $perDay);
        app(MetricAnomalyDetector::class)->detectForTeam($this->team);

        $this->panel()
            ->assertSee('1 anomaly alert is open.')
            ->assertSeeHtml('data-anomaly="2026-10-17"');
    }

    public function test_the_trend_offers_its_values_as_a_table(): void
    {
        $this->push($this->connect('tasks'), ['2026-10-16' => ['open' => 10], '2026-10-17' => ['open' => 15]]);

        $this->panel()->assertSeeHtml('data-testid="trend-values"')->assertSee('2026-10-16');
    }

    public function test_a_viewer_can_read_everything_and_sees_no_secret(): void
    {
        $source = $this->connect('tasks');
        $secret = $source->rotateWebhookSecret();
        $this->push($source, ['2026-10-17' => ['open' => 10]]);
        $viewer = $this->memberOf($this->owner, 'viewer');

        $html = $this->panel($viewer)
            ->assertStatus(200)
            ->assertSee('Open tasks')
            ->assertDontSee($secret)
            ->html();

        $this->assertSame('10', $this->field($html, 'tasks.open', 'kpi-value'));
    }

    public function test_the_connect_hint_differs_for_someone_who_cannot_connect(): void
    {
        $viewer = $this->memberOf($this->owner, 'viewer');

        $this->panel($viewer)->assertSee('Ask an owner or admin to connect a platform')->assertDontSee('Connect a platform in');
        $this->panel($this->owner)->assertSee('Connect a platform in');
    }

    public function test_it_only_shows_the_current_teams_numbers(): void
    {
        $other = $this->ownerWithTeam();
        $otherCompany = $this->linkCompany($other->currentTeam);
        $otherSource = DataSource::factory()->create(['team_id' => $other->currentTeam->id, 'platform' => 'tasks', 'status' => 'connected']);
        app(SnapshotIngestService::class)->ingest($otherSource, $this->snapshotBody($otherCompany, 'tasks', ['open' => 424242], '2026-10-17T06:00:00Z'));
        app(ComputeMetrics::class)->forTeam($other->currentTeam);
        $this->push($this->connect('tasks'), ['2026-10-17' => ['open' => 7]]);

        $this->panel()->assertSee('Open tasks')->assertDontSee('424,242');
    }

    public function test_the_dashboard_page_mounts_the_panel(): void
    {
        $this->actingAs($this->owner)->get('/dashboard')
            ->assertOk()
            ->assertSee('Nothing is reporting yet.')
            ->assertSeeHtml('id="connected-platforms"');
    }

    public function test_money_tiles_show_the_currency(): void
    {
        $this->push($this->connect('billing'), ['2026-10-17' => ['mrr_cents' => 125000, 'currency' => 'ZAR']]);

        $this->panel()->assertSee('ZAR 1,250.00');
    }
}
