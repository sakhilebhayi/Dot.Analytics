<?php

namespace Tests\Unit\Services;

use App\Models\BusinessDnaProfile;
use App\Models\DataSource;
use App\Models\Team;
use App\Models\User;
use App\Services\AiModelRouter;
use App\Services\BusinessDnaService;
use App\Services\IntelligenceEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessDnaServiceTest extends TestCase
{
    use RefreshDatabase;

    private BusinessDnaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BusinessDnaService(
            new IntelligenceEngineService,
            new AiModelRouter,
        );
    }

    private function teamWithPlatforms(array $platforms = ['dot.fleet']): Team
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        foreach ($platforms as $platform) {
            DataSource::factory()->create([
                'team_id' => $team->id,
                'platform' => $platform,
                'status' => 'connected',
            ]);
        }

        return $team;
    }

    public function test_compute_creates_profile_for_new_team(): void
    {
        $team = $this->teamWithPlatforms(['dot.fleet']);
        $profile = $this->service->computeForTeam($team);

        $this->assertInstanceOf(BusinessDnaProfile::class, $profile);
        $this->assertEquals($team->id, $profile->team_id);
        $this->assertNotNull($profile->last_computed_at);
    }

    public function test_compute_updates_existing_profile(): void
    {
        $team = $this->teamWithPlatforms(['dot.fleet']);
        $this->service->computeForTeam($team);
        $this->service->computeForTeam($team);

        $this->assertDatabaseCount('business_dna_profiles', 1);
    }

    public function test_confidence_score_is_zero_with_no_platforms(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $profile = $this->service->computeForTeam($user->currentTeam);

        $this->assertEquals(0.0, $profile->confidence_score);
    }

    public function test_confidence_score_increases_with_more_platforms(): void
    {
        $userA = User::factory()->withPersonalTeam()->create();
        $userB = User::factory()->withPersonalTeam()->create();

        DataSource::factory()->create(['team_id' => $userA->currentTeam->id, 'platform' => 'dot.fleet', 'status' => 'connected']);

        foreach (['dot.fleet', 'dot.crm', 'dot.hr', 'dot.payments', 'dot.support'] as $p) {
            DataSource::factory()->create(['team_id' => $userB->currentTeam->id, 'platform' => $p, 'status' => 'connected']);
        }

        $profileA = $this->service->computeForTeam($userA->currentTeam);
        $profileB = $this->service->computeForTeam($userB->currentTeam);

        $this->assertGreaterThan($profileA->confidence_score, $profileB->confidence_score);
    }

    public function test_profile_contains_required_pattern_keys(): void
    {
        $team = $this->teamWithPlatforms(['dot.fleet']);
        $profile = $this->service->computeForTeam($team);

        $this->assertNotNull($profile->operational_patterns);
        $this->assertNotNull($profile->risk_tolerance);
        $this->assertNotNull($profile->growth_signals);
    }

    public function test_risk_tolerance_level_is_valid(): void
    {
        $team = $this->teamWithPlatforms(['dot.fleet']);
        $profile = $this->service->computeForTeam($team);

        $level = $profile->risk_tolerance['level'] ?? null;
        $this->assertContains($level, ['low', 'medium', 'high']);
    }
}
