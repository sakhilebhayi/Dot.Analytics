<?php

namespace Tests\Feature\Livewire;

use App\Models\ComputedMetric;
use App\Models\DashboardWidget;
use App\Models\MetricDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricChartWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_a_polyline_through_the_computed_history(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $definition = MetricDefinition::factory()->create(['label' => 'Overtime Hours']);
        foreach ([10, 25, 15, 40] as $i => $value) {
            ComputedMetric::factory()->create([
                'team_id' => $user->currentTeam->id,
                'metric_definition_id' => $definition->id,
                'value' => $value,
                'period_date' => now()->subDays(10 - $i * 2)->format('Y-m-d'),
            ]);
        }

        $widget = new DashboardWidget(['config' => ['metric_definition_id' => $definition->id]]);

        $html = view('livewire.analytics.widgets.metric-chart', ['widget' => $widget])->render();

        $this->assertStringContainsString('<polyline', $html);
        $this->assertStringContainsString('Overtime Hours', $html);
    }

    public function test_shows_an_empty_state_with_fewer_than_two_points(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $definition = MetricDefinition::factory()->create(['label' => 'Idle Time']);
        ComputedMetric::factory()->create([
            'team_id' => $user->currentTeam->id,
            'metric_definition_id' => $definition->id,
        ]);

        $widget = new DashboardWidget(['config' => ['metric_definition_id' => $definition->id]]);

        $html = view('livewire.analytics.widgets.metric-chart', ['widget' => $widget])->render();

        $this->assertStringContainsString('No computed history yet for Idle Time', $html);
        $this->assertStringNotContainsString('<polyline', $html);
    }

    public function test_shows_an_empty_state_with_zero_points(): void
    {
        $definition = MetricDefinition::factory()->create(['label' => 'Downtime']);
        $widget = new DashboardWidget(['config' => ['metric_definition_id' => $definition->id]]);

        $html = view('livewire.analytics.widgets.metric-chart', ['widget' => $widget])->render();

        $this->assertStringContainsString('No computed history yet for Downtime', $html);
    }

    public function test_flat_history_does_not_error_on_division_by_zero(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $definition = MetricDefinition::factory()->create(['label' => 'Constant Metric']);
        foreach (range(1, 3) as $i) {
            ComputedMetric::factory()->create([
                'team_id' => $user->currentTeam->id,
                'metric_definition_id' => $definition->id,
                'value' => 50,
                'period_date' => now()->subDays(3 - $i)->format('Y-m-d'),
            ]);
        }

        $widget = new DashboardWidget(['config' => ['metric_definition_id' => $definition->id]]);

        $html = view('livewire.analytics.widgets.metric-chart', ['widget' => $widget])->render();

        $this->assertStringContainsString('<polyline', $html);
    }
}
