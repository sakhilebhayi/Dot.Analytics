<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Analytics\IntelligenceDashboard;
use App\Models\AnalyticsAlert;
use App\Models\DataSource;
use App\Models\MetricDefinition;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers IntelligenceDashboard's #[Computed] properties directly (they are
 * lazily evaluated by Livewire, so simply rendering the component in other
 * tests doesn't necessarily exercise them) and the "no current team" guard
 * branch shared by every public method, neither of which the existing
 * IntelligenceDashboard tests in AnalyticsLivewireTest / ComponentCompletionTest
 * reach.
 */
class IntelligenceDashboardComputedTest extends TestCase
{
    use RefreshDatabase;

    public function test_connected_sources_returns_only_connected_sources_for_the_current_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $team = $user->currentTeam;

        DataSource::factory()->create(['team_id' => $team->id, 'status' => 'connected']);
        DataSource::factory()->create(['team_id' => $team->id, 'status' => 'pending']);

        $component = Livewire::test(IntelligenceDashboard::class);

        $this->assertCount(1, $component->instance()->connectedSources());
    }

    public function test_open_alerts_returns_only_open_alerts_for_the_current_team_limited_to_five(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $team = $user->currentTeam;

        $def = MetricDefinition::create([
            'key' => 'dash.metric', 'label' => 'Dash', 'source_platform' => 'dot.fleet',
            'engine' => 'operational', 'aggregation' => 'avg',
        ]);

        for ($i = 0; $i < 7; $i++) {
            AnalyticsAlert::create([
                'team_id' => $team->id,
                'metric_definition_id' => $def->id,
                'title' => "Alert {$i}",
                'description' => 'x',
                'severity' => 'low',
                'status' => 'open',
                'context' => [],
                'triggered_at' => now(),
            ]);
        }

        AnalyticsAlert::create([
            'team_id' => $team->id,
            'metric_definition_id' => $def->id,
            'title' => 'Resolved alert',
            'description' => 'x',
            'severity' => 'low',
            'status' => 'resolved',
            'context' => [],
            'triggered_at' => now(),
            'resolved_at' => now(),
        ]);

        $component = Livewire::test(IntelligenceDashboard::class);
        $alerts = $component->instance()->openAlerts();

        $this->assertCount(5, $alerts);
        $this->assertTrue($alerts->every(fn ($a) => $a->status === 'open'));
    }

    public function test_pending_recommendations_returns_only_pending_for_the_current_team_limited_to_five(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $team = $user->currentTeam;

        for ($i = 0; $i < 6; $i++) {
            Recommendation::factory()->create(['team_id' => $team->id, 'status' => 'pending']);
        }
        Recommendation::factory()->create(['team_id' => $team->id, 'status' => 'dismissed']);

        $component = Livewire::test(IntelligenceDashboard::class);
        $recs = $component->instance()->pendingRecommendations();

        $this->assertCount(5, $recs);
        $this->assertTrue($recs->every(fn ($r) => $r->status === 'pending'));
    }

    public function test_connected_sources_is_empty_when_user_has_no_current_team(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(IntelligenceDashboard::class);

        $this->assertCount(0, $component->instance()->connectedSources());
    }

    public function test_open_alerts_is_empty_when_user_has_no_current_team(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(IntelligenceDashboard::class);

        $this->assertCount(0, $component->instance()->openAlerts());
    }

    public function test_pending_recommendations_is_empty_when_user_has_no_current_team(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(IntelligenceDashboard::class);

        $this->assertCount(0, $component->instance()->pendingRecommendations());
    }

    public function test_ask_intelligence_errors_when_user_has_no_current_team(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(IntelligenceDashboard::class)
            ->set('intelligenceQuery', 'Why is productivity down this month?')
            ->call('askIntelligence')
            ->assertHasErrors(['intelligenceQuery']);
    }
}
