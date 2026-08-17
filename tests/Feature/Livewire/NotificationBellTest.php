<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Analytics\NotificationBell;
use App\Models\AnalyticsAlert;
use App\Models\User;
use App\Notifications\CriticalAlertTriggered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_count_reflects_real_unread_notifications(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $alert = AnalyticsAlert::factory()->create(['team_id' => $user->currentTeam->id]);
        $user->notify(new CriticalAlertTriggered($alert));
        $user->notify(new CriticalAlertTriggered($alert));

        $component = Livewire::actingAs($user)->test(NotificationBell::class);

        $this->assertSame(2, $component->get('unreadCount'));
    }

    public function test_clicking_a_notification_marks_it_as_read(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $alert = AnalyticsAlert::factory()->create(['team_id' => $user->currentTeam->id]);
        $user->notify(new CriticalAlertTriggered($alert));
        $notificationId = $user->fresh()->notifications->first()->id;

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('markAsRead', $notificationId);

        $this->assertNotNull($user->fresh()->notifications->first()->read_at);
    }

    public function test_mark_all_as_read_clears_the_unread_count(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $alert = AnalyticsAlert::factory()->create(['team_id' => $user->currentTeam->id]);
        $user->notify(new CriticalAlertTriggered($alert));
        $user->notify(new CriticalAlertTriggered($alert));

        $component = Livewire::actingAs($user)->test(NotificationBell::class)->call('markAllAsRead');

        $this->assertSame(0, $component->get('unreadCount'));
    }

    public function test_a_notification_for_a_different_user_never_appears(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();
        $alert = AnalyticsAlert::factory()->create(['team_id' => $otherUser->currentTeam->id]);
        $otherUser->notify(new CriticalAlertTriggered($alert));

        $component = Livewire::actingAs($user)->test(NotificationBell::class);

        $this->assertSame(0, $component->get('unreadCount'));
    }
}
