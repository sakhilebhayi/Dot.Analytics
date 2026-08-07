<?php

namespace Tests\Unit\Actions;

use App\Actions\Analytics\GenerateExecutiveBriefingAction;
use App\Jobs\Analytics\GenerateExecutiveBriefingJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateExecutiveBriefingActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_dispatches_briefing_job(): void
    {
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        app(GenerateExecutiveBriefingAction::class)->handle($team, 'weekly');

        Queue::assertPushed(GenerateExecutiveBriefingJob::class, function ($job) use ($team) {
            return $job->teamId === $team->id && $job->period === 'weekly';
        });
    }

    public function test_handle_uses_today_as_default_date(): void
    {
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        app(GenerateExecutiveBriefingAction::class)->handle($user->currentTeam, 'daily');

        Queue::assertPushed(GenerateExecutiveBriefingJob::class, function ($job) {
            return $job->periodDate === now()->toDateString();
        });
    }

    public function test_handle_accepts_custom_period_date(): void
    {
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        app(GenerateExecutiveBriefingAction::class)->handle($user->currentTeam, 'monthly', '2026-07-01');

        Queue::assertPushed(GenerateExecutiveBriefingJob::class, function ($job) {
            return $job->periodDate === '2026-07-01';
        });
    }
}
