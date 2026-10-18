<?php

namespace Tests\Unit\Notifications;

use App\Models\ExecutiveBriefing;
use App\Models\User;
use App\Notifications\Channels\BestEffortBroadcastChannel;
use App\Notifications\ExecutiveBriefingReady;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveBriefingReadyTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_declares_database_broadcast_and_mail_with_mail_last(): void
    {
        $user = User::factory()->create();
        $notification = new ExecutiveBriefingReady(ExecutiveBriefing::factory()->create());

        $this->assertSame(['database', BestEffortBroadcastChannel::class, 'mail'], $notification->via($user));
    }

    public function test_mail_is_dropped_when_no_mailer_is_configured(): void
    {
        $user = User::factory()->create();
        $notification = new ExecutiveBriefingReady(ExecutiveBriefing::factory()->create());

        config(['mail.default' => null]);

        $this->assertSame(['database', BestEffortBroadcastChannel::class], $notification->via($user));
    }

    public function test_it_is_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new ExecutiveBriefingReady(ExecutiveBriefing::factory()->create()));
    }

    public function test_to_mail_names_the_period_and_includes_the_summary(): void
    {
        $user = User::factory()->create();
        $briefing = ExecutiveBriefing::factory()->create([
            'period' => 'weekly',
            'summary' => 'Productivity is up 5% this week.',
        ]);

        $mail = (new ExecutiveBriefingReady($briefing))->toMail($user);

        $this->assertStringContainsString('weekly', $mail->subject);
        $this->assertContains('Productivity is up 5% this week.', $mail->introLines);
    }

    public function test_it_carries_only_the_summary_never_provenance_or_other_briefing_fields(): void
    {
        $user = User::factory()->create();
        $briefing = ExecutiveBriefing::factory()->create([
            'summary' => 'Overdue tasks quadrupled.',
            'risks' => ['risk-text-not-for-email'],
            'provenance' => ['metric_keys' => ['tasks.overdue'], 'secret' => 'provenance-leak'],
        ]);
        $notification = new ExecutiveBriefingReady($briefing);

        $rendered = json_encode([
            $notification->toMail($user)->toArray(),
            $notification->toArray($user),
            $notification->toBroadcast($user)->data,
        ]);

        $this->assertStringContainsString('Overdue tasks quadrupled.', $rendered);
        $this->assertStringNotContainsString('risk-text-not-for-email', $rendered);
        $this->assertStringNotContainsString('provenance-leak', $rendered);
    }

    public function test_to_array_names_the_team_and_links_to_the_dashboard(): void
    {
        $user = User::factory()->create();
        $briefing = ExecutiveBriefing::factory()->create(['period' => 'monthly']);

        $data = (new ExecutiveBriefingReady($briefing))->toArray($user);

        $this->assertSame('briefing_ready', $data['type']);
        $this->assertSame('Monthly briefing ready', $data['title']);
        $this->assertSame(route('dashboard'), $data['url']);
        $this->assertSame($briefing->team_id, $data['team_id']);
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

        /** @var ExecutiveBriefingReady $restored */
        $restored = unserialize(serialize(new ExecutiveBriefingReady($briefing)));

        $this->assertSame('weekly', $restored->briefing->period);
        $this->assertSame($briefing->getKey(), $restored->briefing->getKey());

        $data = $restored->toArray($user);
        $this->assertSame('Serialized summary.', $data['description']);
    }
}
