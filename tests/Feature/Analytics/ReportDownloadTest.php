<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsReport;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDownloadTest extends TestCase
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

    public function test_report_download_requires_authentication(): void
    {
        $report = AnalyticsReport::create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'title'   => 'Weekly Insights',
            'type'    => 'insights',
            'config'  => ['report_type' => 'insights'],
        ]);

        $this->get("/reports/{$report->id}/download")->assertRedirect('/login');
    }

    public function test_authenticated_user_can_download_own_team_report_as_csv(): void
    {
        $report = AnalyticsReport::create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'title'   => 'Weekly Insights',
            'type'    => 'insights',
            'config'  => ['report_type' => 'insights'],
        ]);

        $response = $this->actingAs($this->user)
            ->get("/reports/{$report->id}/download");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_user_cannot_download_report_belonging_to_another_team(): void
    {
        $otherUser = User::factory()->withPersonalTeam()->create();
        $otherTeam = $otherUser->currentTeam;

        $report = AnalyticsReport::create([
            'team_id' => $otherTeam->id,
            'user_id' => $otherUser->id,
            'title'   => 'Other Team Report',
            'type'    => 'insights',
            'config'  => ['report_type' => 'insights'],
        ]);

        $this->actingAs($this->user)
            ->get("/reports/{$report->id}/download")
            ->assertNotFound();
    }

    public function test_downloading_unknown_report_returns_404(): void
    {
        $this->actingAs($this->user)
            ->get('/reports/999999/download')
            ->assertNotFound();
    }
}
