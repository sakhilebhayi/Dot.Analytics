<?php

namespace Tests\Feature\Integrity;

use App\Jobs\Analytics\GenerateExecutiveBriefingJob;
use App\Livewire\Analytics\AlertsPanel;
use App\Livewire\Analytics\BusinessDnaPanel;
use App\Livewire\Analytics\CrossPlatformInsightPanel;
use App\Livewire\Analytics\EcosystemMapPanel;
use App\Livewire\Analytics\ExecutiveBriefingPanel;
use App\Livewire\Analytics\RecommendationsPanel;
use App\Livewire\Analytics\SavedReportsPanel;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsReport;
use App\Models\CrossPlatformInsight;
use App\Models\DataConnector;
use App\Models\DataSource;
use App\Models\User;
use App\Policies\CrossPlatformInsightPolicy;
use App\Policies\DataSourcePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

/**
 * The gates and policies were declared but never invoked. Plain members are read-only.
 */
class MemberReadOnlyTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private User $owner;

    private User $member;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->ownerWithTeam();
        $this->member = $this->memberOf($this->owner, 'editor');
        $this->admin = $this->memberOf($this->owner, 'admin');
    }

    // ─── API ─────────────────────────────────────────────────────────────────

    public function test_member_cannot_run_engines_over_the_api_but_admin_can(): void
    {
        Bus::fake();
        $this->connect($this->owner->currentTeam, 'dot.hear');

        $this->asApi($this->member)->postJson('/api/v1/intelligence/run')->assertForbidden();
        $this->asApi($this->admin)->postJson('/api/v1/intelligence/run')->assertOk();
    }

    public function test_member_cannot_connect_or_disconnect_platforms_over_the_api(): void
    {
        $this->connect($this->owner->currentTeam, 'dot.fleet');

        $this->asApi($this->member)->postJson('/api/v1/platforms/dot.crm/connect')->assertForbidden();
        $this->asApi($this->member)->deleteJson('/api/v1/platforms/dot.fleet')->assertForbidden();
        $this->assertDatabaseHas('data_sources', ['platform' => 'dot.fleet']);
        $this->assertDatabaseMissing('data_sources', ['platform' => 'dot.crm']);

        $this->asApi($this->admin)->postJson('/api/v1/platforms/dot.crm/connect')->assertCreated();
        $this->asApi($this->admin)->deleteJson('/api/v1/platforms/dot.fleet')->assertOk();
    }

    public function test_member_cannot_use_sql_endpoints(): void
    {
        $connector = DataConnector::create([
            'team_id' => $this->owner->currentTeam->id, 'name' => 'DB', 'type' => 'database',
            'driver' => 'sqlite', 'config' => ['driver' => 'sqlite', 'database' => ':memory:'], 'status' => 'active',
        ]);
        $body = ['question' => 'Show me every user', 'connector_id' => $connector->id, 'schema' => ['tables' => [['name' => 'users']]]];

        $this->asApi($this->member)->postJson('/api/v1/sql/generate', $body)->assertForbidden();
        $this->asApi($this->member)->postJson('/api/v1/sql/query', $body)->assertForbidden();
        $this->asApi($this->admin)->postJson('/api/v1/sql/generate', $body)->assertOk();
    }

    public function test_member_cannot_create_or_delete_saved_reports_but_can_list(): void
    {
        $report = AnalyticsReport::create([
            'team_id' => $this->owner->currentTeam->id, 'user_id' => $this->owner->id,
            'title' => 'R', 'type' => 'insights', 'config' => ['report_type' => 'insights'],
        ]);

        $this->asApi($this->member)->getJson('/api/v1/saved-reports')->assertOk();
        $this->asApi($this->member)->deleteJson("/api/v1/saved-reports/{$report->id}")->assertForbidden();
        $this->asApi($this->member)->postJson('/api/v1/saved-reports', ['title' => 'x', 'type' => 'insights'])->assertForbidden();
        $this->assertDatabaseHas('analytics_reports', ['id' => $report->id]);

        $this->asApi($this->admin)->deleteJson("/api/v1/saved-reports/{$report->id}")->assertOk();
    }

    public function test_member_can_still_read_intelligence(): void
    {
        $this->asApi($this->member)->getJson('/api/v1/intelligence/engines')->assertOk();
        $this->asApi($this->member)->getJson('/api/v1/intelligence/insights')->assertOk();
        $this->asApi($this->member)->getJson('/api/v1/platforms/connected')->assertOk();
    }

    // ─── Livewire ────────────────────────────────────────────────────────────

    public function test_member_cannot_run_engines_from_the_insight_panel(): void
    {
        Livewire::actingAs($this->member)->test(CrossPlatformInsightPanel::class)
            ->call('runEngines')->assertForbidden();
    }

    public function test_member_cannot_review_or_dismiss_insights(): void
    {
        $insight = CrossPlatformInsight::factory()->create(['team_id' => $this->owner->currentTeam->id, 'status' => 'new']);

        Livewire::actingAs($this->member)->test(CrossPlatformInsightPanel::class)->call('review', $insight->id)->assertForbidden();
        Livewire::actingAs($this->member)->test(CrossPlatformInsightPanel::class)->call('dismiss', $insight->id)->assertForbidden();
        $this->assertSame('new', $insight->fresh()->status);

        Livewire::actingAs($this->admin)->test(CrossPlatformInsightPanel::class)->call('dismiss', $insight->id);
        $this->assertSame('dismissed', $insight->fresh()->status);
    }

    public function test_member_cannot_generate_briefings(): void
    {
        Bus::fake();

        Livewire::actingAs($this->member)->test(ExecutiveBriefingPanel::class)->call('generate')->assertForbidden();
        Bus::assertNotDispatched(GenerateExecutiveBriefingJob::class);

        Livewire::actingAs($this->admin)->test(ExecutiveBriefingPanel::class)->call('generate');
        Bus::assertDispatched(GenerateExecutiveBriefingJob::class);
    }

    public function test_member_cannot_connect_or_disconnect_from_the_ecosystem_map(): void
    {
        $this->connect($this->owner->currentTeam, 'dot.fleet');

        Livewire::actingAs($this->member)->test(EcosystemMapPanel::class)
            ->call('startConnect', 'dot.crm')->assertForbidden();
        Livewire::actingAs($this->member)->test(EcosystemMapPanel::class)
            ->set('connectingPlatform', 'dot.crm')->call('confirmConnect')->assertForbidden();
        Livewire::actingAs($this->member)->test(EcosystemMapPanel::class)
            ->call('disconnect', 'dot.fleet')->assertForbidden();

        $this->assertDatabaseHas('data_sources', ['platform' => 'dot.fleet']);
        $this->assertDatabaseMissing('data_sources', ['platform' => 'dot.crm']);

        Livewire::actingAs($this->admin)->test(EcosystemMapPanel::class)->call('disconnect', 'dot.fleet');
        $this->assertDatabaseMissing('data_sources', ['platform' => 'dot.fleet']);
    }

    public function test_member_cannot_acknowledge_or_resolve_alerts(): void
    {
        $alert = AnalyticsAlert::factory()->create(['team_id' => $this->owner->currentTeam->id, 'status' => 'open']);

        Livewire::actingAs($this->member)->test(AlertsPanel::class)->call('resolve', $alert->id)->assertForbidden();
        Livewire::actingAs($this->member)->test(AlertsPanel::class)->call('acknowledge', $alert->id)->assertForbidden();
        $this->assertSame('open', $alert->fresh()->status);

        Livewire::actingAs($this->admin)->test(AlertsPanel::class)->call('resolve', $alert->id);
        $this->assertSame('resolved', $alert->fresh()->status);
    }

    public function test_member_cannot_delete_reports_from_the_panel(): void
    {
        $report = AnalyticsReport::create([
            'team_id' => $this->owner->currentTeam->id, 'user_id' => $this->owner->id,
            'title' => 'R', 'type' => 'insights', 'config' => ['report_type' => 'insights'],
        ]);

        Livewire::actingAs($this->member)->test(SavedReportsPanel::class)->call('delete', $report->id)->assertForbidden();
        $this->assertDatabaseHas('analytics_reports', ['id' => $report->id]);

        Livewire::actingAs($this->admin)->test(SavedReportsPanel::class)->call('delete', $report->id);
        $this->assertDatabaseMissing('analytics_reports', ['id' => $report->id]);
    }

    public function test_member_cannot_trigger_ai_generation_panels(): void
    {
        Livewire::actingAs($this->member)->test(RecommendationsPanel::class)->call('generate')->assertForbidden();
        Livewire::actingAs($this->member)->test(BusinessDnaPanel::class)->call('compute')->assertForbidden();
    }

    // ─── Policies ────────────────────────────────────────────────────────────

    public function test_data_source_policy_create_and_update_need_owner_or_admin(): void
    {
        $source = DataSource::factory()->create(['team_id' => $this->owner->currentTeam->id]);
        $policy = new DataSourcePolicy;

        $this->assertFalse($policy->create($this->member));
        $this->assertFalse($policy->update($this->member, $source));
        $this->assertFalse($policy->delete($this->member, $source));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->create($this->owner));
    }

    public function test_insight_policy_update_needs_owner_or_admin(): void
    {
        $insight = CrossPlatformInsight::factory()->create(['team_id' => $this->owner->currentTeam->id]);
        $policy = new CrossPlatformInsightPolicy;

        $this->assertFalse($policy->update($this->member, $insight));
        $this->assertTrue($policy->update($this->admin, $insight));
        $this->assertTrue($policy->update($this->owner, $insight));
        $this->assertTrue($policy->view($this->member, $insight));
    }
}
