<?php

namespace Tests\Unit\Notifications;

use App\Models\ExecutiveBriefing;
use App\Models\User;
use App\Notifications\ExecutiveBriefingReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveBriefingReadyTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_declares_mail_database_and_broadcast(): void
    {
        $user = User::factory()->create();
        $briefing = ExecutiveBriefing::factory()->create();
        $notification = new ExecutiveBriefingReady($briefing);

        $this->assertSame(['mail', 'database', 'broadcast'], $notification->via($user));
    }

    public function test_to_mail_names_the_period_and_includes_the_summary(): void
    {
        $user = User::factory()->create();
        $briefing = ExecutiveBriefing::factory()->create([
            'period' => 'weekly',
            'summary' => 'Productivity is up 5% this week.',
        ]);
        $notification = new ExecutiveBriefingReady($briefing);

        $mail = $notification->toMail($user);

        $this->assertStringContainsString('weekly', $mail->subject);
        $this->assertContains('Productivity is up 5% this week.', $mail->introLines);
    }

    public function test_to_array_includes_a_url_to_the_dashboard(): void
    {
        $user = User::factory()->create();
        $briefing = ExecutiveBriefing::factory()->create(['period' => 'monthly']);
        $notification = new ExecutiveBriefingReady($briefing);

        $data = $notification->toArray($user);

        $this->assertSame('briefing_ready', $data['type']);
        $this->assertSame('Monthly briefing ready', $data['title']);
        $this->assertSame(route('dashboard'), $data['url']);
    }

    /**
     * Regression test -- see the matching test in CriticalAlertTriggeredTest
     * for the full story: this ShouldQueue notification originally used
     * only Queueable, not SerializesModels, which is invisible under
     * phpunit.xml's forced QUEUE_CONNECTION=sync (never actually
     * serializes) but broke silently against a real queue driver.
     */
    public function test_survives_a_real_serialize_unserialize_round_trip(): void
    {
        $user = User::factory()->create();
        $briefing = ExecutiveBriefing::factory()->create(['period' => 'weekly', 'summary' => 'Serialized summary.']);
        $notification = new ExecutiveBriefingReady($briefing);

        /** @var ExecutiveBriefingReady $restored */
        $restored = unserialize(serialize($notification));

        $this->assertSame('weekly', $restored->briefing->period);
        $this->assertSame($briefing->getKey(), $restored->briefing->getKey());

        $data = $restored->toArray($user);
        $this->assertSame('Serialized summary.', $data['description']);
    }
}
