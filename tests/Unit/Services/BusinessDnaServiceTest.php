<?php

namespace Tests\Unit\Services;

use App\Models\BusinessDnaProfile;
use App\Models\DataSource;
use App\Models\Team;
use App\Models\User;
use App\Services\BusinessDnaService;
use App\Services\IntelligenceEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTeams;
use Tests\Support\FakeAiRouter;
use Tests\TestCase;

/**
 * The profile is written from real snapshots by a model, never from fixed text.
 * The no-data, no-provider and unusable-answer behaviour is in
 * Tests\Feature\Metrics\BriefingAndDnaTest.
 */
class BusinessDnaServiceTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private function service(?FakeAiRouter $router = null): BusinessDnaService
    {
        return new BusinessDnaService(new IntelligenceEngineService, $router ?? new FakeAiRouter(response: $this->answer()));
    }

    private function answer(string $risk = 'low'): string
    {
        return json_encode([
            'operational_patterns' => ['summary' => 'Steady weekly rhythm.', 'key_patterns' => ['Monday peak']],
            'risk_tolerance' => ['level' => $risk, 'notes' => 'From overdue trend.'],
            'growth_signals' => ['summary' => 'Orders rising.', 'signals' => []],
        ]);
    }

    /** A team whose connected platforms each have one real snapshot. */
    private function teamWithData(array $platforms = ['tasks']): Team
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        foreach ($platforms as $platform) {
            $source = DataSource::factory()->create(['team_id' => $team->id, 'platform' => $platform, 'status' => 'connected']);
            $this->snapshot($source, ['metrics' => ['open' => 3]]);
        }

        return $team;
    }

    public function test_compute_creates_a_profile_for_a_team_with_data(): void
    {
        $team = $this->teamWithData();
        $profile = $this->service()->computeForTeam($team);

        $this->assertInstanceOf(BusinessDnaProfile::class, $profile);
        $this->assertEquals($team->id, $profile->team_id);
        $this->assertNotNull($profile->last_computed_at);
    }

    public function test_compute_updates_the_existing_profile(): void
    {
        $team = $this->teamWithData();
        $this->service()->computeForTeam($team);
        $this->service()->computeForTeam($team);

        $this->assertDatabaseCount('business_dna_profiles', 1);
    }

    public function test_a_team_with_no_platforms_has_no_profile_rather_than_a_zero_confidence_one(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->assertNull($this->service()->computeForTeam($user->currentTeam));
        $this->assertDatabaseCount('business_dna_profiles', 0);
    }

    public function test_confidence_increases_with_more_platforms_reporting(): void
    {
        $a = $this->teamWithData(['tasks']);
        $b = $this->teamWithData(['tasks', 'emall', 'hr', 'billing', 'notify']);

        $this->assertGreaterThan(
            $this->service()->computeForTeam($a)->confidence_score,
            $this->service()->computeForTeam($b)->confidence_score,
        );
    }

    public function test_the_profile_holds_what_the_model_returned_and_no_more(): void
    {
        $profile = $this->service()->computeForTeam($this->teamWithData());

        $this->assertSame('Steady weekly rhythm.', $profile->operational_patterns['summary']);
        $this->assertSame('Orders rising.', $profile->growth_signals['summary']);
        $this->assertNull($profile->bottlenecks);
        $this->assertNull($profile->industry_benchmarks);
    }

    public function test_the_risk_level_is_the_models_not_a_default(): void
    {
        $this->assertSame('low', $this->service(new FakeAiRouter(response: $this->answer('low')))->computeForTeam($this->teamWithData())->risk_tolerance['level']);
        $this->assertSame('high', $this->service(new FakeAiRouter(response: $this->answer('high')))->computeForTeam($this->teamWithData())->risk_tolerance['level']);
    }

    public function test_only_snapshots_of_connected_roster_platforms_count_as_data(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $retired = DataSource::factory()->create(['team_id' => $team->id, 'platform' => 'dot.fleet', 'status' => 'connected']);
        $this->snapshot($retired, ['vehicles' => [1]]);

        $this->assertNull($this->service()->computeForTeam($team));
    }
}
