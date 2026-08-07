<?php

namespace Tests\Feature\Api;

use App\Models\AnalyticsReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedReportApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $token];
    }

    public function test_index_returns_empty_list_for_new_team(): void
    {
        [$user, $token] = $this->actingAsUser();

        $this->withToken($token)->getJson('/api/v1/saved-reports')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_store_creates_report_definition(): void
    {
        [$user, $token] = $this->actingAsUser();

        $this->withToken($token)->postJson('/api/v1/saved-reports', [
            'title' => 'Weekly Insights',
            'type' => 'insights',
        ])->assertCreated();

        $this->assertDatabaseHas('analytics_reports', [
            'team_id' => $user->currentTeam->id,
            'title' => 'Weekly Insights',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        [$user, $token] = $this->actingAsUser();

        $this->withToken($token)->postJson('/api/v1/saved-reports', [])
            ->assertUnprocessable();
    }

    public function test_store_rejects_invalid_type(): void
    {
        [$user, $token] = $this->actingAsUser();

        $this->withToken($token)->postJson('/api/v1/saved-reports', [
            'title' => 'Test',
            'type' => 'invalid_type',
        ])->assertUnprocessable();
    }

    public function test_run_executes_and_persists_output(): void
    {
        [$user, $token] = $this->actingAsUser();

        $report = AnalyticsReport::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'title' => 'Test Report',
            'type' => 'ad_hoc',
            'config' => ['report_type' => 'insights'],
        ]);

        $response = $this->withToken($token)->postJson("/api/v1/saved-reports/{$report->id}/run");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['run', 'output']]);

        $this->assertDatabaseHas('report_runs', [
            'analytics_report_id' => $report->id,
            'status' => 'completed',
        ]);
    }

    public function test_runs_returns_history(): void
    {
        [$user, $token] = $this->actingAsUser();

        $report = AnalyticsReport::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'title' => 'History Report',
            'type' => 'ad_hoc',
            'config' => ['report_type' => 'insights'],
        ]);

        $this->withToken($token)->postJson("/api/v1/saved-reports/{$report->id}/run");

        $this->withToken($token)->getJson("/api/v1/saved-reports/{$report->id}/runs")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_destroy_removes_report(): void
    {
        [$user, $token] = $this->actingAsUser();

        $report = AnalyticsReport::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'title' => 'To Delete',
            'type' => 'ad_hoc',
            'config' => ['report_type' => 'insights'],
        ]);

        $this->withToken($token)->deleteJson("/api/v1/saved-reports/{$report->id}")
            ->assertOk();

        $this->assertDatabaseMissing('analytics_reports', ['id' => $report->id]);
    }

    public function test_saved_reports_are_team_scoped(): void
    {
        [$userA, $tokenA] = $this->actingAsUser();
        [$userB, $tokenB] = $this->actingAsUser();

        AnalyticsReport::create([
            'team_id' => $userB->currentTeam->id,
            'user_id' => $userB->id,
            'title' => 'Team B Report',
            'type' => 'ad_hoc',
            'config' => [],
        ]);

        $this->withToken($tokenA)->getJson('/api/v1/saved-reports')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_saved_reports_require_authentication(): void
    {
        $this->getJson('/api/v1/saved-reports')->assertUnauthorized();
    }
}
