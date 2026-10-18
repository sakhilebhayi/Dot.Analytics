<?php

namespace Tests\Unit\Notifications;

use App\Models\AnalyticsAlert;
use App\Models\User;
use App\Notifications\Channels\BestEffortBroadcastChannel;
use App\Notifications\CriticalAlertTriggered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalAlertTriggeredTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_declares_database_broadcast_and_mail_with_mail_last(): void
    {
        $user = User::factory()->create();
        $alert = AnalyticsAlert::factory()->create();
        $notification = new CriticalAlertTriggered($alert);

        // Mail last: a failing transport must never stop the in-app copy.
        $this->assertSame(['database', BestEffortBroadcastChannel::class, 'mail'], $notification->via($user));
    }

    public function test_mail_is_dropped_when_no_mailer_is_configured(): void
    {
        $user = User::factory()->create();
        $notification = new CriticalAlertTriggered(AnalyticsAlert::factory()->create());

        config(['mail.default' => null]);
        $this->assertSame(['database', BestEffortBroadcastChannel::class], $notification->via($user));

        config(['mail.default' => 'does-not-exist']);
        $this->assertSame(['database', BestEffortBroadcastChannel::class], $notification->via($user));
    }

    public function test_mail_is_dropped_for_a_user_without_an_email_address(): void
    {
        $user = User::factory()->create(['email' => '']);
        $notification = new CriticalAlertTriggered(AnalyticsAlert::factory()->create());

        $this->assertSame(['database', BestEffortBroadcastChannel::class], $notification->via($user));
    }

    public function test_it_is_queued_so_a_request_never_waits_on_the_mail_transport(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new CriticalAlertTriggered(AnalyticsAlert::factory()->create()));
    }

    public function test_to_mail_includes_the_alert_title_and_description(): void
    {
        $user = User::factory()->create();
        $alert = AnalyticsAlert::factory()->create([
            'title' => 'Overtime spike detected',
            'description' => 'Costs are up 30% this week.',
        ]);

        $mail = (new CriticalAlertTriggered($alert))->toMail($user);

        $this->assertStringContainsString('Overtime spike detected', $mail->subject);
        $this->assertContains('Costs are up 30% this week.', $mail->introLines);
    }

    public function test_the_mail_and_the_stored_payload_carry_no_alert_context_or_secrets(): void
    {
        $user = User::factory()->create();
        $alert = AnalyticsAlert::factory()->create([
            'title' => 'Anomaly detected: Overdue tasks',
            'description' => 'Overdue tasks was 40.',
            'context' => [
                'source' => 'metric_anomaly',
                'api_key' => 'sk-live-context-leak',
                'webhook_secret' => 'whsec-context-leak',
                'window' => ['values' => [['date' => '2026-10-16', 'value' => 10.0]]],
                'raw_payload' => ['customer_email' => 'raw-snapshot-row@example.com'],
            ],
        ]);
        $notification = new CriticalAlertTriggered($alert);

        $rendered = json_encode([
            $notification->toMail($user)->toArray(),
            $notification->toArray($user),
            $notification->toBroadcast($user)->data,
        ]);

        foreach (['sk-live-context-leak', 'whsec-context-leak', 'raw-snapshot-row@example.com', 'raw_payload', 'webhook_secret'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $rendered);
        }
    }

    public function test_a_very_long_description_is_cut_down_in_mail_and_in_app(): void
    {
        $user = User::factory()->create();
        $alert = AnalyticsAlert::factory()->create(['description' => str_repeat('long ', 400)]);
        $notification = new CriticalAlertTriggered($alert);

        $this->assertLessThanOrEqual(310, mb_strlen($notification->toArray($user)['description']));
        $this->assertLessThanOrEqual(310, mb_strlen($notification->toMail($user)->introLines[0]));
    }

    public function test_to_array_names_the_team_and_links_to_the_dashboard(): void
    {
        $user = User::factory()->create();
        $alert = AnalyticsAlert::factory()->create(['title' => 'Test Alert', 'description' => 'Detail']);

        $data = (new CriticalAlertTriggered($alert))->toArray($user);

        $this->assertSame('critical_alert', $data['type']);
        $this->assertSame('Test Alert', $data['title']);
        $this->assertSame(route('dashboard'), $data['url']);
        $this->assertSame($alert->team_id, $data['team_id']);
    }

    public function test_the_realtime_copy_is_attempted_inline_so_a_dead_websocket_server_cannot_fill_failed_jobs(): void
    {
        $user = User::factory()->create();
        $notification = new CriticalAlertTriggered(AnalyticsAlert::factory()->create());

        $this->assertSame('sync', $notification->toBroadcast($user)->connection);
    }

    /**
     * Regression test for a real production bug: this notification is
     * ShouldQueue, but originally only used the Queueable trait, not
     * SerializesModels. phpunit.xml forces QUEUE_CONNECTION=sync, so
     * every other test above executes this class inline and never
     * actually serializes it -- the bug was invisible to the whole
     * suite. It only surfaced against the real `database` queue driver.
     * Round-tripping through PHP's own serialize()/unserialize() is
     * exactly what a real queue driver does to store and later restore
     * the job payload.
     */
    public function test_survives_a_real_serialize_unserialize_round_trip(): void
    {
        $user = User::factory()->create();
        $alert = AnalyticsAlert::factory()->create(['title' => 'Serialized Alert', 'description' => 'Detail']);
        $notification = new CriticalAlertTriggered($alert);

        /** @var CriticalAlertTriggered $restored */
        $restored = unserialize(serialize($notification));

        $this->assertSame('Serialized Alert', $restored->alert->title);
        $this->assertSame($alert->getKey(), $restored->alert->getKey());

        $data = $restored->toArray($user);
        $this->assertSame('Serialized Alert', $data['title']);
    }
}
