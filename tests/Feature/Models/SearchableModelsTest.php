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

        $results = IntelligenceNode::search('Acme')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Acme Logistics Ltd', $results->first()->label);
    }

    public function test_intelligence_node_search_never_returns_another_teams_row(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeam = Team::factory()->create();
        IntelligenceNode::factory()->create(['team_id' => $otherTeam->id, 'label' => 'Acme Foreign Entity']);

        $this->actingAs($user);

        $results = IntelligenceNode::search('Acme')->get();

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

        $results = AnalyticsReport::search('Risk')->get();

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

        $results = CrossPlatformInsight::search('Productivity')->get();

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

        $results = AnalyticsAlert::search('Overtime')->get();

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

        $results = Recommendation::search('operators')->get();

        $this->assertCount(1, $results);
    }

    public function test_analytics_dashboard_search_finds_a_match_and_stays_team_scoped(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeam = Team::factory()->create();
        AnalyticsDashboard::factory()->create(['team_id' => $otherTeam->id, 'title' => 'Ops Overview Foreign']);

        $this->actingAs($user);
        AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'title' => 'Ops Overview']);

        $results = AnalyticsDashboard::search('Ops Overview')->get();

        $this->assertCount(1, $results);
        $this->assertSame($user->currentTeam->id, $results->first()->team_id);
    }
}
