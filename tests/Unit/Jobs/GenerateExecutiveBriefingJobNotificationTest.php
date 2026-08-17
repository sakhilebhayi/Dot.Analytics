<?php

namespace Tests\Unit\Jobs;

use App\Jobs\Analytics\GenerateExecutiveBriefingJob;
use App\Models\User;
use App\Notifications\ExecutiveBriefingReady;
use App\Services\AiModelRouter;
use App\Services\IntelligenceEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GenerateExecutiveBriefingJobNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_a_briefing_notifies_every_team_member_including_the_owner(): void
    {
        Notification::fake();

        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);

        (new GenerateExecutiveBriefingJob($owner->currentTeam->id, 'weekly', now()->toDateString()))->handle(
            app(AiModelRouter::class),
            app(IntelligenceEngineService::class),
        );

        Notification::assertSentTo($owner, ExecutiveBriefingReady::class);
        Notification::assertSentTo($member, ExecutiveBriefingReady::class);
    }
}
