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
}
