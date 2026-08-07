<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Analytics\AlertsPanel;
use App\Livewire\Analytics\BusinessDnaPanel;
use App\Livewire\Analytics\CrossPlatformInsightPanel;
use App\Livewire\Analytics\EcosystemMapPanel;
use App\Livewire\Analytics\ExecutiveBriefingPanel;
use App\Livewire\Analytics\IntelligenceDashboard;
use App\Livewire\Analytics\RecommendationsPanel;
use App\Models\AnalyticsAlert;
use App\Models\CrossPlatformInsight;
use App\Models\DataSource;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsLivewireTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($this->user);
    }

    private function team()
    {
        return $this->user->currentTeam;
    }

    // ─── EcosystemMapPanel ───────────────────────────────────────────────────

    public function test_ecosystem_map_panel_renders(): void
    {
        Livewire::test(EcosystemMapPanel::class)
            ->assertStatus(200)
            ->assertSee('Dot.Fleet');
    }

    public function test_ecosystem_map_start_connect_shows_form(): void
    {
        Livewire::test(EcosystemMapPanel::class)
            ->call('startConnect', 'dot.fleet')
            ->assertSet('connectingPlatform', 'dot.fleet');
    }

    public function test_ecosystem_map_cancel_connect_clears_state(): void
    {
        Livewire::test(EcosystemMapPanel::class)
            ->call('startConnect', 'dot.fleet')
            ->call('cancelConnect')
            ->assertSet('connectingPlatform', null);
    }

    public function test_ecosystem_map_confirm_connect_creates_source(): void
    {
        Livewire::test(EcosystemMapPanel::class)
            ->call('startConnect', 'dot.fleet')
            ->set('connectUrl', 'https://fleet.infodot.app')
            ->call('confirmConnect');

        $this->assertDatabaseHas('data_sources', [
            'team_id' => $this->team()->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
        ]);
    }

    public function test_ecosystem_map_disconnect_removes_source(): void
    {
        DataSource::factory()->create([
            'team_id' => $this->team()->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
        ]);

        Livewire::test(EcosystemMapPanel::class)
            ->call('disconnect', 'dot.fleet');

        $this->assertDatabaseMissing('data_sources', [
            'team_id' => $this->team()->id,
            'platform' => 'dot.fleet',
        ]);
    }

    // ─── AlertsPanel ────────────────────────────────────────────────────────

    public function test_alerts_panel_renders(): void
    {
        Livewire::test(AlertsPanel::class)->assertStatus(200);
    }

    public function test_alerts_panel_shows_open_alerts(): void
    {
        AnalyticsAlert::factory()->create([
            'team_id' => $this->team()->id,
            'title' => 'Test alert',
            'status' => 'open',
            'severity' => 'warning',
            'triggered_at' => now(),
        ]);

        Livewire::test(AlertsPanel::class)
            ->assertSee('Test alert');
    }

    public function test_alerts_panel_acknowledge_changes_status(): void
    {
        $alert = AnalyticsAlert::factory()->create([
            'team_id' => $this->team()->id,
            'status' => 'open',
            'triggered_at' => now(),
        ]);

        Livewire::test(AlertsPanel::class)
            ->call('acknowledge', $alert->id);

        $this->assertDatabaseHas('analytics_alerts', [
            'id' => $alert->id,
            'status' => 'acknowledged',
        ]);
    }

    public function test_alerts_panel_resolve_changes_status(): void
    {
        $alert = AnalyticsAlert::factory()->create([
            'team_id' => $this->team()->id,
            'status' => 'open',
            'triggered_at' => now(),
        ]);

        Livewire::test(AlertsPanel::class)
            ->call('resolve', $alert->id);

        $this->assertDatabaseHas('analytics_alerts', [
            'id' => $alert->id,
            'status' => 'resolved',
        ]);
    }

    // ─── CrossPlatformInsightPanel ──────────────────────────────────────────

    public function test_cross_platform_insight_panel_renders(): void
    {
        Livewire::test(CrossPlatformInsightPanel::class)->assertStatus(200);
    }

    public function test_cross_platform_insight_panel_shows_insights(): void
    {
        CrossPlatformInsight::factory()->create([
            'team_id' => $this->team()->id,
            'title' => 'Unique fleet insight',
            'status' => 'new',
        ]);

        Livewire::test(CrossPlatformInsightPanel::class)
            ->assertSee('Unique fleet insight');
    }

    public function test_cross_platform_insight_dismiss_changes_status(): void
    {
        $insight = CrossPlatformInsight::factory()->create([
            'team_id' => $this->team()->id,
            'status' => 'new',
        ]);

        Livewire::test(CrossPlatformInsightPanel::class)
            ->call('dismiss', $insight->id);

        $this->assertDatabaseHas('cross_platform_insights', [
            'id' => $insight->id,
            'status' => 'dismissed',
        ]);
    }

    public function test_cross_platform_insight_review_changes_status(): void
    {
        $insight = CrossPlatformInsight::factory()->create([
            'team_id' => $this->team()->id,
            'status' => 'new',
        ]);

        Livewire::test(CrossPlatformInsightPanel::class)
            ->call('review', $insight->id);

        $this->assertDatabaseHas('cross_platform_insights', [
            'id' => $insight->id,
            'status' => 'reviewed',
        ]);
    }

    // ─── RecommendationsPanel ───────────────────────────────────────────────

    public function test_recommendations_panel_renders(): void
    {
        Livewire::test(RecommendationsPanel::class)->assertStatus(200);
    }

    public function test_recommendations_panel_shows_pending_recommendations(): void
    {
        Recommendation::factory()->create([
            'team_id' => $this->team()->id,
            'title' => 'Unique recommendation title',
            'status' => 'pending',
        ]);

        Livewire::test(RecommendationsPanel::class)
            ->assertSee('Unique recommendation title');
    }

    public function test_recommendations_panel_dismiss_changes_status(): void
    {
        $rec = Recommendation::factory()->create([
            'team_id' => $this->team()->id,
            'status' => 'pending',
        ]);

        Livewire::test(RecommendationsPanel::class)
            ->call('dismiss', $rec->id);

        $this->assertDatabaseHas('recommendations', ['id' => $rec->id, 'status' => 'dismissed']);
    }

    public function test_recommendations_panel_action_changes_status(): void
    {
        $rec = Recommendation::factory()->create([
            'team_id' => $this->team()->id,
            'status' => 'pending',
        ]);

        Livewire::test(RecommendationsPanel::class)
            ->call('action', $rec->id);

        $this->assertDatabaseHas('recommendations', ['id' => $rec->id, 'status' => 'actioned']);
    }

    // ─── BusinessDnaPanel ───────────────────────────────────────────────────

    public function test_business_dna_panel_renders(): void
    {
        Livewire::test(BusinessDnaPanel::class)->assertStatus(200);
    }

    public function test_business_dna_panel_compute_creates_profile(): void
    {
        Livewire::test(BusinessDnaPanel::class)
            ->call('compute');

        $this->assertDatabaseHas('business_dna_profiles', [
            'team_id' => $this->team()->id,
        ]);
    }

    // ─── ExecutiveBriefingPanel ─────────────────────────────────────────────

    public function test_executive_briefing_panel_renders(): void
    {
        Livewire::test(ExecutiveBriefingPanel::class)->assertStatus(200);
    }

    public function test_executive_briefing_panel_period_defaults_to_weekly(): void
    {
        Livewire::test(ExecutiveBriefingPanel::class)
            ->assertSet('period', 'weekly');
    }

    public function test_executive_briefing_panel_can_switch_period(): void
    {
        Livewire::test(ExecutiveBriefingPanel::class)
            ->set('period', 'daily')
            ->assertSet('period', 'daily');
    }

    // ─── IntelligenceDashboard ──────────────────────────────────────────────

    public function test_intelligence_dashboard_renders(): void
    {
        Livewire::test(IntelligenceDashboard::class)->assertStatus(200);
    }

    public function test_intelligence_dashboard_validates_empty_query(): void
    {
        Livewire::test(IntelligenceDashboard::class)
            ->set('intelligenceQuery', '')
            ->call('askIntelligence')
            ->assertHasErrors(['intelligenceQuery']);
    }

    public function test_intelligence_dashboard_validates_short_query(): void
    {
        Livewire::test(IntelligenceDashboard::class)
            ->set('intelligenceQuery', 'hi')
            ->call('askIntelligence')
            ->assertHasErrors(['intelligenceQuery']);
    }

    public function test_intelligence_dashboard_returns_answer_for_valid_query(): void
    {
        Livewire::test(IntelligenceDashboard::class)
            ->set('intelligenceQuery', 'Why is productivity down this month?')
            ->call('askIntelligence')
            ->assertSet('queryLoading', false)
            ->assertNotSet('queryAnswer', '');
    }
}
