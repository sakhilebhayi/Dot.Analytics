<?php

namespace Tests\Feature\Api;

use App\Models\CrossPlatformInsight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): array
    {
        $user  = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;
        return [$user, $token];
    }

    public function test_json_report_returns_structured_data(): void
    {
        [$user, $token] = $this->actingAsUser();

        CrossPlatformInsight::factory()->count(3)->create(['team_id' => $user->currentTeam->id]);

        $response = $this->withToken($token)->getJson('/api/v1/reports/insights');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['report_type', 'team', 'generated_at', 'columns', 'rows', 'row_count'],
            ]);

        $this->assertEquals('insights', $response->json('data.report_type'));
        $this->assertEquals(3, $response->json('data.row_count'));
    }

    public function test_csv_report_returns_csv_content_type(): void
    {
        [$user, $token] = $this->actingAsUser();

        $response = $this->withToken($token)->get('/api/v1/reports/insights/csv', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        // Should be a streamed response
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_html_report_returns_html_content(): void
    {
        [$user, $token] = $this->actingAsUser();

        $response = $this->withToken($token)->get('/api/v1/reports/alerts/html', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<!DOCTYPE html>', $response->getContent());
    }

    public function test_unknown_report_type_returns_422(): void
    {
        [$user, $token] = $this->actingAsUser();

        $response = $this->withToken($token)->getJson('/api/v1/reports/invalid_type');

        $response->assertUnprocessable();
    }

    public function test_report_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/reports/insights')->assertUnauthorized();
    }

    public function test_reports_are_team_scoped(): void
    {
        [$userA, $tokenA] = $this->actingAsUser();
        [$userB, $tokenB] = $this->actingAsUser();

        CrossPlatformInsight::factory()->count(3)->create(['team_id' => $userB->currentTeam->id]);

        $response = $this->withToken($tokenA)->getJson('/api/v1/reports/insights');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.row_count'));
    }

    /**
     * No route in this API group runs a team-context middleware that
     * guarantees current_team_id is set. A user who belongs to no team
     * (e.g. removed from their last team) must get a 403, not a crash on
     * a null currentTeam dereference.
     */
    public function test_user_with_no_team_gets_403_not_a_crash(): void
    {
        $user  = User::factory()->create(['current_team_id' => null]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/reports/insights');

        $response->assertForbidden();
    }
}
