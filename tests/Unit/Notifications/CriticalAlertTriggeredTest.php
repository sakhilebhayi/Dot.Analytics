<?php

namespace Tests\Unit\Notifications;

use App\Models\AnalyticsAlert;
use App\Models\User;
use App\Notifications\CriticalAlertTriggered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalAlertTriggeredTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_declares_mail_database_and_broadcast(): void
    {
        $user = User::factory()->create();
        $alert = AnalyticsAlert::factory()->create();
        $notification = new CriticalAlertTriggered($alert);

        $this->assertSame(['mail', 'database', 'broadcast'], $notification->via($user));
    }

    public function test_to_mail_includes_the_alert_title_and_description(): void
    {
        $user = User::factory()->create();
        $alert = AnalyticsAlert::factory()->create([
            'title' => 'Overtime spike detected',
            'description' => 'Costs are up 30% this week.',
        ]);
        $notification = new CriticalAlertTriggered($alert);

        $mail = $notification->toMail($user);

        $this->assertStringContainsString('Overtime spike detected', $mail->subject);
        $this->assertContains('Costs are up 30% this week.', $mail->introLines);
    }

    public function test_to_array_includes_a_url_to_the_dashboard(): void
    {
        $user = User::factory()->create();
        $alert = AnalyticsAlert::factory()->create(['title' => 'Test Alert', 'description' => 'Detail']);
        $notification = new CriticalAlertTriggered($alert);

        $data = $notification->toArray($user);

        $this->assertSame('critical_alert', $data['type']);
        $this->assertSame('Test Alert', $data['title']);
        $this->assertSame(route('dashboard'), $data['url']);
    }

    /**
     * Regression test for a real production bug: this notification is
     * ShouldQueue, but originally only used the Queueable trait, not
     * SerializesModels. phpunit.xml forces QUEUE_CONNECTION=sync, so
     * every other test above executes this class inline and never
     * actually serializes it -- the bug was invisible to the whole
     * suite. It only surfaced against this dev environment's real
     * `database` queue driver: `php artisan queue:work` reported the
     * job as DONE, but the notifications table stayed empty and
     * failed_jobs stayed empty too (no exception was ever thrown/
     * caught). Round-tripping through PHP's own serialize()/
     * unserialize() -- exactly what a real queue driver does to store
     * and later restore the job payload -- reproduces the bug without
     * needing a live queue backend in the test suite.
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
