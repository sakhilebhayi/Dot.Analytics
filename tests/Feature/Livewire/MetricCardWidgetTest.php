<?php

namespace Tests\Feature\Livewire;

use App\Models\ComputedMetric;
use App\Models\DashboardWidget;
use App\Models\MetricDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricCardWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_the_most_recent_computed_value_and_unit(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $definition = MetricDefinition::factory()->create(['label' => 'Fleet Utilisation', 'unit' => '%']);
        ComputedMetric::factory()->create([
            'team_id' => $user->currentTeam->id,
            'metric_definition_id' => $definition->id,
            'value' => 82.5,
            'period_date' => '2026-08-01',
        ]);
        ComputedMetric::factory()->create([
            'team_id' => $user->currentTeam->id,
            'metric_definition_id' => $definition->id,
            'value' => 91.25,
            'period_date' => '2026-08-15',
        ]);

        $widget = new DashboardWidget(['config' => ['metric_definition_id' => $definition->id]]);

        $html = view('livewire.analytics.widgets.metric-card', ['widget' => $widget])->render();

        $this->assertStringContainsString('Fleet Utilisation', $html);
        $this->assertStringContainsString('91.25', $html);
        $this->assertStringContainsString('%', $html);
        $this->assertStringNotContainsString('82.50', $html);
    }

    public function test_shows_an_empty_state_when_no_computed_value_exists_yet(): void
    {
        $definition = MetricDefinition::factory()->create(['label' => 'Support Ticket Backlog']);
        $widget = new DashboardWidget(['config' => ['metric_definition_id' => $definition->id]]);

        $html = view('livewire.analytics.widgets.metric-card', ['widget' => $widget])->render();

        $this->assertStringContainsString('No computed value yet for Support Ticket Backlog', $html);
    }

    public function test_shows_a_graceful_message_if_the_metric_definition_no_longer_exists(): void
    {
        $widget = new DashboardWidget(['config' => ['metric_definition_id' => 999999]]);

        $html = view('livewire.analytics.widgets.metric-card', ['widget' => $widget])->render();

        $this->assertStringContainsString('Metric no longer exists', $html);
    }
}
