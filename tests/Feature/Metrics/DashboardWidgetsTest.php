<?php

namespace Tests\Feature\Metrics;

use App\Livewire\Analytics\DashboardBuilderPanel;
use App\Models\AnalyticsDashboard;
use App\Models\DashboardWidget;
use App\Models\DataSource;
use App\Models\Team;
use App\Models\User;
use App\Services\Metrics\ComputeMetrics;
use App\Services\Metrics\SnapshotIngestService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private User $owner;

    private Team $team;

    private string $company;

    private AnalyticsDashboard $dashboard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->ownerWithTeam();
        $this->team = $this->owner->currentTeam;
        $this->company = $this->linkCompany($this->team);
        $this->dashboard = AnalyticsDashboard::create(['team_id' => $this->team->id, 'user_id' => $this->owner->id, 'title' => 'Board One', 'is_default' => true]);
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(9, 0));
    }

    private function connect(string $platform): DataSource
    {
        return DataSource::factory()->create(['team_id' => $this->team->id, 'platform' => $platform, 'status' => 'connected']);
    }

    private function push(DataSource $source, array $perDay): void
    {
        foreach ($perDay as $date => $metrics) {
            app(SnapshotIngestService::class)->ingest($source, $this->snapshotBody($this->company, $source->platform, $metrics, "{$date}T06:00:00Z"));
        }
        app(ComputeMetrics::class)->forTeam($this->team);
    }

    private function builder(?User $as = null)
    {
        return Livewire::actingAs($as ?? $this->owner)->test(DashboardBuilderPanel::class)->set('activeDashboardId', $this->dashboard->id);
    }

    private function widget(string $type, string $metricKey, int $row = 0, int $col = 0): DashboardWidget
    {
        return DashboardWidget::create([
            'analytics_dashboard_id' => $this->dashboard->id, 'widget_type' => $type, 'title' => 'W '.$metricKey,
            'config' => ['metric_key' => $metricKey], 'col' => $col, 'row' => $row, 'width' => 4, 'height' => 2,
        ]);
    }

    private function field(string $html, string $widgetId, string $testId): ?string
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $node = (new \DOMXPath($dom))->query("//*[@data-widget-id='{$widgetId}']//*[@data-testid='{$testId}']")->item(0);

        return $node ? trim(preg_replace('/\s+/', ' ', $node->textContent)) : null;
    }

    public function test_only_real_metric_widget_types_exist(): void
    {
        $this->assertSame(['kpi', 'trend'], array_keys(DashboardBuilderPanel::WIDGET_TYPES));
    }

    public function test_a_kpi_widget_is_stored_with_the_metric_it_shows(): void
    {
        $this->connect('tasks');

        $this->builder()
            ->set('widgetType', 'kpi')
            ->set('widgetMetric', 'tasks.overdue')
            ->call('addWidget')
            ->assertHasNoErrors();

        $widget = DashboardWidget::sole();
        $this->assertSame('kpi', $widget->widget_type);
        $this->assertSame(['metric_key' => 'tasks.overdue'], $widget->config);
        $this->assertSame('Overdue tasks', $widget->title);
    }

    public function test_a_trend_widget_can_have_its_own_title(): void
    {
        $this->connect('tasks');

        $this->builder()->set('widgetType', 'trend')->set('widgetMetric', 'tasks.open')->set('widgetTitle', 'Backlog')->call('addWidget');

        $widget = DashboardWidget::sole();
        $this->assertSame('trend', $widget->widget_type);
        $this->assertSame('Backlog', $widget->title);
    }

    public function test_the_retired_placeholder_types_cannot_be_added(): void
    {
        $this->connect('tasks');

        foreach (['metric_card', 'chart', 'alert_feed', 'recommendation_feed', 'insight_feed', 'dna_snapshot', 'briefing_summary', 'nonsense'] as $type) {
            $this->builder()->set('widgetType', $type)->set('widgetMetric', 'tasks.open')->call('addWidget')->assertHasErrors(['widgetType']);
        }

        $this->assertSame(0, DashboardWidget::count());
    }

    public function test_a_widget_needs_a_metric_from_a_platform_that_is_connected(): void
    {
        $this->connect('tasks');

        $this->builder()->set('widgetType', 'kpi')->set('widgetMetric', '')->call('addWidget')->assertHasErrors(['widgetMetric']);
        $this->builder()->set('widgetType', 'kpi')->set('widgetMetric', 'tasks.not_a_metric')->call('addWidget')->assertHasErrors(['widgetMetric']);
        $this->builder()->set('widgetType', 'kpi')->set('widgetMetric', 'billing.mrr_cents')->call('addWidget')->assertHasErrors(['widgetMetric']);
        $this->builder()->set('widgetType', 'kpi')->set('widgetMetric', 'dot.fleet.speed')->call('addWidget')->assertHasErrors(['widgetMetric']);

        $this->assertSame(0, DashboardWidget::count());
    }

    public function test_another_teams_connected_platform_does_not_make_a_metric_pickable(): void
    {
        $other = $this->ownerWithTeam();
        DataSource::factory()->create(['team_id' => $other->currentTeam->id, 'platform' => 'billing', 'status' => 'connected']);

        $this->builder()->set('widgetType', 'kpi')->set('widgetMetric', 'billing.mrr_cents')->call('addWidget')->assertHasErrors(['widgetMetric']);
    }

    public function test_a_kpi_widget_shows_the_real_value_and_a_trend_widget_draws_the_real_points(): void
    {
        $this->push($this->connect('tasks'), ['2026-10-16' => ['open' => 10], '2026-10-17' => ['open' => 15]]);
        $kpi = $this->widget('kpi', 'tasks.open');
        $trend = $this->widget('trend', 'tasks.open', 0, 4);

        $component = $this->builder()->assertSeeHtml('data-widget-id="'.$kpi->id.'"')->assertSeeHtml('data-testid="trend-chart"');
        $html = $component->html();

        $this->assertSame('15', $this->field($html, (string) $kpi->id, 'kpi-value'));
        $this->assertSame('+5', $this->field($html, (string) $kpi->id, 'kpi-delta'));
        $this->assertNotNull($this->field($html, (string) $trend->id, 'trend'));
        $this->assertStringContainsString('2 stored points', $this->field($html, (string) $trend->id, 'trend'));
    }

    public function test_a_kpi_widget_for_a_metric_nobody_reported_says_no_data_yet(): void
    {
        $this->connect('tasks');
        $widget = $this->widget('kpi', 'tasks.overdue');

        $html = $this->builder()->html();

        $this->assertSame('no data yet', $this->field($html, (string) $widget->id, 'kpi-value'));
    }

    public function test_a_widget_whose_platform_is_no_longer_connected_says_so_and_shows_no_number(): void
    {
        $source = $this->connect('tasks');
        $this->push($source, ['2026-10-17' => ['open' => 10]]);
        $widget = $this->widget('kpi', 'tasks.open');
        $source->update(['status' => 'pending']);

        $component = $this->builder();
        $html = $component->html();

        $component->assertSee('is not available: its platform is not connected');
        $this->assertNull($this->field($html, (string) $widget->id, 'kpi-value'));
    }

    public function test_a_legacy_placeholder_widget_says_it_was_retired_and_can_still_be_removed(): void
    {
        $legacy = DashboardWidget::create([
            'analytics_dashboard_id' => $this->dashboard->id, 'widget_type' => 'chart', 'title' => 'Old chart',
            'config' => ['type' => 'chart'], 'col' => 0, 'row' => 0, 'width' => 4, 'height' => 2,
        ]);

        $this->builder()
            ->assertSee('Retired widget type')
            ->assertSeeHtml('wire:click="removeWidget('.$legacy->id.')"')
            ->call('removeWidget', $legacy->id);

        $this->assertDatabaseMissing('dashboard_widgets', ['id' => $legacy->id]);
    }

    public function test_widgets_can_be_moved_earlier_and_later(): void
    {
        $a = $this->widget('kpi', 'tasks.open', 0, 0);
        $b = $this->widget('kpi', 'tasks.overdue', 0, 4);
        $c = $this->widget('kpi', 'tasks.created_7d', 0, 8);

        $order = fn () => DashboardWidget::orderBy('row')->orderBy('col')->pluck('id')->all();
        $this->assertSame([$a->id, $b->id, $c->id], $order());

        $this->builder()->call('moveWidget', $c->id, 'earlier');
        $this->assertSame([$a->id, $c->id, $b->id], $order());

        $this->builder()->call('moveWidget', $a->id, 'later');
        $this->assertSame([$c->id, $a->id, $b->id], $order());

        $this->builder()->call('moveWidget', $c->id, 'earlier'); // already first
        $this->assertSame([$c->id, $a->id, $b->id], $order());
    }

    public function test_the_move_controls_only_appear_where_they_can_work(): void
    {
        $a = $this->widget('kpi', 'tasks.open', 0, 0);
        $b = $this->widget('kpi', 'tasks.overdue', 0, 4);

        $this->builder()
            ->assertSeeHtml('wire:click="moveWidget('.$b->id.', \'earlier\')"')
            ->assertDontSeeHtml('wire:click="moveWidget('.$a->id.', \'earlier\')"')
            ->assertSeeHtml('wire:click="moveWidget('.$a->id.', \'later\')"')
            ->assertDontSeeHtml('wire:click="moveWidget('.$b->id.', \'later\')"');
    }

    public function test_a_viewer_reads_widgets_but_cannot_arrange_or_add_them(): void
    {
        $this->push($this->connect('tasks'), ['2026-10-17' => ['open' => 10]]);
        $widget = $this->widget('kpi', 'tasks.open');
        $second = $this->widget('kpi', 'tasks.overdue', 0, 4);
        $viewer = $this->memberOf($this->owner, 'viewer');

        $html = $this->builder($viewer)->assertSee('Board One')->assertDontSeeHtml('wire:click="moveWidget(')->html();
        $this->assertSame('10', $this->field($html, (string) $widget->id, 'kpi-value'));

        $this->builder($viewer)->call('moveWidget', $second->id, 'earlier')->assertForbidden();
        $this->builder($viewer)->set('widgetType', 'kpi')->set('widgetMetric', 'tasks.open')->call('addWidget')->assertForbidden();
        $this->assertSame(2, DashboardWidget::count());
    }

    public function test_another_teams_widget_cannot_be_moved_or_removed(): void
    {
        $other = $this->ownerWithTeam();
        $otherBoard = AnalyticsDashboard::create(['team_id' => $other->currentTeam->id, 'user_id' => $other->id, 'title' => 'Theirs', 'is_default' => true]);
        $theirs = DashboardWidget::create(['analytics_dashboard_id' => $otherBoard->id, 'widget_type' => 'kpi', 'title' => 'T', 'config' => ['metric_key' => 'tasks.open'], 'col' => 0, 'row' => 0, 'width' => 4, 'height' => 2]);

        $this->expectException(ModelNotFoundException::class);
        $this->builder()->call('moveWidget', $theirs->id, 'later');
    }

    public function test_adding_a_widget_when_nothing_is_connected_explains_what_to_do_instead_of_offering_empty_choices(): void
    {
        $this->builder()
            ->set('showAddWidget', true)
            ->assertSee('Widgets show real metrics from connected platforms')
            ->assertSeeHtml('href="#connected-platforms"')
            ->assertDontSeeHtml('wire:submit="addWidget"');
    }

    public function test_the_picker_offers_only_connected_platforms_metrics(): void
    {
        $this->connect('tasks');

        $component = $this->builder()->set('showAddWidget', true)->assertSeeHtml('wire:submit="addWidget"');
        $html = $component->html();

        $this->assertStringContainsString('value="tasks.overdue"', $html);
        $this->assertStringNotContainsString('value="billing.mrr_cents"', $html);
    }

    public function test_the_dashboard_page_mounts_the_builder(): void
    {
        $this->actingAs($this->owner)->get('/dashboard')->assertOk()->assertSee('Dashboard Builder')->assertSee('Board One');
    }
}
