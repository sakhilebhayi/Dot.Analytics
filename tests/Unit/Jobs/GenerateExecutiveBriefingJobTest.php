<?php

namespace Tests\Unit\Jobs;

use App\Jobs\Analytics\GenerateExecutiveBriefingJob;
use App\Models\ExecutiveBriefing;
use App\Models\User;
use App\Services\AiModelRouter;
use App\Services\IntelligenceEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateExecutiveBriefingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_briefing_record(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        (new GenerateExecutiveBriefingJob($user->currentTeam->id, 'weekly', now()->toDateString()))
            ->handle(app(AiModelRouter::class), app(IntelligenceEngineService::class));

        $this->assertDatabaseHas('executive_briefings', [
            'team_id' => $user->currentTeam->id,
            'period'  => 'weekly',
            'status'  => 'ready',
        ]);
    }

    public function test_job_does_not_run_twice_for_same_period(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $date = now()->toDateString();

        $job = new GenerateExecutiveBriefingJob($user->currentTeam->id, 'weekly', $date);
        $job->handle(app(AiModelRouter::class), app(IntelligenceEngineService::class));

        // Run again — should be a no-op
        $job->handle(app(AiModelRouter::class), app(IntelligenceEngineService::class));

        $this->assertDatabaseCount('executive_briefings', 1);
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

        (new GenerateExecutiveBriefingJob($user->currentTeam->id, 'daily', now()->toDateString()))
            ->handle(app(AiModelRouter::class), app(IntelligenceEngineService::class));

        $briefing = ExecutiveBriefing::first();
        $this->assertNotNull($briefing->summary);
        $this->assertNotEmpty($briefing->summary);
    }
}
