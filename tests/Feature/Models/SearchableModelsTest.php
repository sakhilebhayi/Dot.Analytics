<?php

namespace Tests\Feature\Models;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsReport;
use App\Models\CrossPlatformInsight;
use App\Models\IntelligenceNode;
use App\Models\Recommendation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchableModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_intelligence_node_search_finds_a_match_by_label(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        IntelligenceNode::factory()->create(['team_id' => $user->currentTeam->id, 'label' => 'Acme Logistics Ltd']);
        IntelligenceNode::factory()->create(['team_id' => $user->currentTeam->id, 'label' => 'Unrelated Entity']);

        $results = IntelligenceNode::search('Acme')->where('team_id', $user->currentTeam->id)->get();

        $this->assertCount(1, $results);
        $this->assertSame('Acme Logistics Ltd', $results->first()->label);
    }

    public function test_intelligence_node_search_never_returns_another_teams_row(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeam = Team::factory()->create();
        IntelligenceNode::factory()->create(['team_id' => $otherTeam->id, 'label' => 'Acme Foreign Entity']);

        $this->actingAs($user);

        $results = IntelligenceNode::search('Acme')->where('team_id', $user->currentTeam->id)->get();

        $this->assertCount(0, $results);
    }

    public function test_analytics_report_search_finds_a_match_by_title(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        AnalyticsReport::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'title' => 'Weekly Risk Summary',
        ]);
        AnalyticsReport::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'title' => 'Unrelated Report',
        ]);

        $results = AnalyticsReport::search('Risk')->where('team_id', $user->currentTeam->id)->get();

        $this->assertCount(1, $results);
        $this->assertSame('Weekly Risk Summary', $results->first()->title);
    }

    public function test_cross_platform_insight_search_finds_a_match_by_title(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        CrossPlatformInsight::factory()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Productivity dropped 8% this month',
        ]);

        $results = CrossPlatformInsight::search('Productivity')->where('team_id', $user->currentTeam->id)->get();

        $this->assertCount(1, $results);
    }

    public function test_analytics_alert_search_finds_a_match_by_description(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        AnalyticsAlert::factory()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Generic Alert',
            'description' => 'Overtime costs spiked in the fleet division',
        ]);

        $results = AnalyticsAlert::search('Overtime')->where('team_id', $user->currentTeam->id)->get();

        $this->assertCount(1, $results);
    }

    public function test_recommendation_search_finds_a_match_by_title(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        Recommendation::factory()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Reassign three certified operators',
        ]);

        $results = Recommendation::search('operators')->where('team_id', $user->currentTeam->id)->get();

        $this->assertCount(1, $results);
    }

    public function test_analytics_dashboard_search_finds_a_match_and_stays_team_scoped(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeam = Team::factory()->create();
        AnalyticsDashboard::factory()->create(['team_id' => $otherTeam->id, 'title' => 'Ops Overview Foreign']);

        $this->actingAs($user);
        AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'title' => 'Ops Overview']);

        $results = AnalyticsDashboard::search('Ops Overview')->where('team_id', $user->currentTeam->id)->get();

        $this->assertCount(1, $results);
        $this->assertSame($user->currentTeam->id, $results->first()->team_id);
    }

    public function test_every_searchable_model_returns_nothing_unless_the_search_names_a_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        IntelligenceNode::factory()->create(['team_id' => $team->id, 'label' => 'Needle one']);
        CrossPlatformInsight::factory()->create(['team_id' => $team->id, 'title' => 'Needle two']);
        AnalyticsAlert::factory()->create(['team_id' => $team->id, 'title' => 'Needle three']);
        Recommendation::factory()->create(['team_id' => $team->id, 'title' => 'Needle four']);
        AnalyticsReport::factory()->create(['team_id' => $team->id, 'user_id' => $user->id, 'title' => 'Needle five']);
        AnalyticsDashboard::factory()->create(['team_id' => $team->id, 'user_id' => $user->id, 'title' => 'Needle six']);

        // Even signed in as a member of that very team (so the global scope would allow them),
        // a search that does not state its team is refused rather than trusted to ambient state.
        $this->actingAs($user);
        foreach ([IntelligenceNode::class, CrossPlatformInsight::class, AnalyticsAlert::class, Recommendation::class, AnalyticsReport::class, AnalyticsDashboard::class] as $model) {
            $this->assertCount(0, $model::search('Needle')->get(), $model);
            $this->assertCount(1, $model::search('Needle')->where('team_id', $team->id)->get(), $model);
        }
    }

    public function test_a_search_constrained_to_my_team_ignores_a_foreign_teams_matching_row(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $other = Team::factory()->create();
        Recommendation::factory()->create(['team_id' => $other->id, 'title' => 'Needle foreign']);

        $this->assertCount(0, Recommendation::search('Needle')->where('team_id', $user->currentTeam->id)->get());
    }

    public function test_a_search_callback_cannot_switch_the_team_constraint_off(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $other = Team::factory()->create();
        Recommendation::factory()->create(['team_id' => $user->currentTeam->id, 'title' => 'Needle mine']);
        Recommendation::factory()->create(['team_id' => $other->id, 'title' => 'Needle foreign']);

        // With a callback Scout's database engine ignores ->where() entirely, so the team has to
        // be held by the model's own query, which no callback replaces.
        $results = Recommendation::search('Needle', fn ($query) => $query)->where('team_id', $user->currentTeam->id)->get();

        $this->assertSame(['Needle mine'], $results->pluck('title')->all());
    }
}
