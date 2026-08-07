<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsAlert;
use App\Models\DataSource;
use App\Models\Recommendation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewIs('dashboard');
    }

    public function test_dashboard_passes_kpi_counts(): void
    {
        DataSource::create([
            'team_id' => $this->team->id,
            'platform' => 'dot.finance',
            'display_name' => 'Dot.Finance',
            'status' => 'connected',
        ]);

        AnalyticsAlert::create([
            'team_id' => $this->team->id,
            'title' => 'Fleet idle time high',
            'description' => 'Vehicle idle time exceeded threshold',
            'severity' => 'warning',
            'status' => 'open',
            'triggered_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertViewHas('connectedCount', 1)
            ->assertViewHas('openAlertCount', 1)
            ->assertViewHas('pendingRecommendationCount', 0);
    }

    public function test_ecosystem_auth_rejects_missing_token(): void
    {
        $this->get('/auth/ecosystem')->assertStatus(403);
    }

    public function test_data_source_belongs_to_team(): void
    {
        $source = DataSource::create([
            'team_id' => $this->team->id,
            'platform' => 'dot.agents',
            'display_name' => 'Dot.Agents',
            'status' => 'pending',
        ]);

        $this->assertTrue($source->team->is($this->team));
        $this->assertFalse($source->isConnected());
    }

    public function test_data_source_connected_state(): void
    {
        $source = DataSource::create([
            'team_id' => $this->team->id,
            'platform' => 'dot.files',
            'display_name' => 'Dot.Files',
            'status' => 'connected',
        ]);

        $this->assertTrue($source->isConnected());
    }

    public function test_data_source_platform_unique_per_team(): void
    {
        DataSource::create([
            'team_id' => $this->team->id,
            'platform' => 'dot.finance',
            'display_name' => 'Dot.Finance',
            'status' => 'pending',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        DataSource::create([
            'team_id' => $this->team->id,
            'platform' => 'dot.finance',
            'display_name' => 'Dot.Finance Duplicate',
            'status' => 'pending',
        ]);
    }

    public function test_analytics_alert_is_open(): void
    {
        $alert = AnalyticsAlert::create([
            'team_id' => $this->team->id,
            'title' => 'Test alert',
            'description' => 'Description',
            'severity' => 'critical',
            'status' => 'open',
            'triggered_at' => now(),
        ]);

        $this->assertTrue($alert->isOpen());
    }

    public function test_recommendation_is_pending(): void
    {
        $rec = Recommendation::create([
            'team_id' => $this->team->id,
            'engine' => 'financial',
            'title' => 'Cash flow risk detected',
            'rationale' => 'Accounts receivable aging above threshold',
            'priority' => 'high',
            'status' => 'pending',
        ]);

        $this->assertTrue($rec->isPending());
    }

    public function test_team_has_data_sources_relationship(): void
    {
        DataSource::create([
            'team_id' => $this->team->id,
            'platform' => 'dot.press',
            'display_name' => 'Dot.Press',
            'status' => 'pending',
        ]);

        $this->assertCount(1, $this->team->dataSources);
    }

    public function test_team_has_alerts_relationship(): void
    {
        AnalyticsAlert::create([
            'team_id' => $this->team->id,
            'title' => 'Alert',
            'description' => 'Desc',
            'severity' => 'info',
            'status' => 'open',
            'triggered_at' => now(),
        ]);

        $this->assertCount(1, $this->team->alerts);
    }

    public function test_team_has_recommendations_relationship(): void
    {
        Recommendation::create([
            'team_id' => $this->team->id,
            'engine' => 'risk',
            'title' => 'Risk recommendation',
            'rationale' => 'Cross-platform risk signals detected',
            'priority' => 'medium',
            'status' => 'pending',
        ]);

        $this->assertCount(1, $this->team->recommendations);
    }
}
