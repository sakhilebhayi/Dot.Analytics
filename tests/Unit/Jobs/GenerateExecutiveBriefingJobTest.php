<?php

namespace Tests\Unit\Jobs;

use App\Jobs\Analytics\GenerateExecutiveBriefingJob;
use App\Models\CrossPlatformInsight;
use App\Models\ExecutiveBriefing;
use App\Models\User;
use App\Services\AiModelRouter;
use App\Services\IntelligenceEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiRouter;
use Tests\TestCase;

/**
 * Integrity cases (no provider, unparseable output, provenance) are in
 * Feature\Integrity\BriefingIntegrityTest; these cover the job's basic contract.
 */
class GenerateExecutiveBriefingJobTest extends TestCase
{
    use RefreshDatabase;

    private function readyRouter(): FakeAiRouter
    {
        return new FakeAiRouter(response: json_encode(['summary' => 'Support load is the main risk this week.']));
    }

    public function test_job_creates_ready_briefing_from_real_insights(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CrossPlatformInsight::factory()->create(['team_id' => $user->currentTeam->id]);

        (new GenerateExecutiveBriefingJob($user->currentTeam->id, 'weekly', now()->toDateString()))
            ->handle($this->readyRouter(), app(IntelligenceEngineService::class));

        $this->assertDatabaseHas('executive_briefings', [
            'team_id' => $user->currentTeam->id,
            'period' => 'weekly',
            'status' => 'ready',
        ]);
    }

    public function test_job_records_unavailable_not_a_briefing_when_there_is_no_data(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        (new GenerateExecutiveBriefingJob($user->currentTeam->id, 'weekly', now()->toDateString()))
            ->handle(app(AiModelRouter::class), app(IntelligenceEngineService::class));

        $this->assertDatabaseHas('executive_briefings', [
            'team_id' => $user->currentTeam->id,
            'period' => 'weekly',
            'status' => 'unavailable',
        ]);
    }

    public function test_job_does_not_run_twice_for_same_period(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CrossPlatformInsight::factory()->create(['team_id' => $user->currentTeam->id]);
        $date = now()->toDateString();
        $router = $this->readyRouter();

        $job = new GenerateExecutiveBriefingJob($user->currentTeam->id, 'weekly', $date);
        $job->handle($router, app(IntelligenceEngineService::class));

        // Run again — should be a no-op
        $job->handle($router, app(IntelligenceEngineService::class));

        $this->assertDatabaseCount('executive_briefings', 1);
        $this->assertCount(1, $router->prompts);
    }

    public function test_job_does_nothing_for_nonexistent_team(): void
    {
        (new GenerateExecutiveBriefingJob(99999, 'weekly', now()->toDateString()))
            ->handle(app(AiModelRouter::class), app(IntelligenceEngineService::class));

        $this->assertDatabaseCount('executive_briefings', 0);
    }

    public function test_briefing_has_summary(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CrossPlatformInsight::factory()->create(['team_id' => $user->currentTeam->id]);

        (new GenerateExecutiveBriefingJob($user->currentTeam->id, 'daily', now()->toDateString()))
            ->handle($this->readyRouter(), app(IntelligenceEngineService::class));

        $briefing = ExecutiveBriefing::first();
        $this->assertNotEmpty($briefing->summary);
    }
}
