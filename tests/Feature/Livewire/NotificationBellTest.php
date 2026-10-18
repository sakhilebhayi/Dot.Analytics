<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Analytics\NotificationBell;
use App\Models\AnalyticsAlert;
use App\Models\Team;
use App\Models\User;
use App\Notifications\CriticalAlertTriggered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private function alertFor(Team $team, string $title = 'Overdue tasks spiked'): AnalyticsAlert
    {
        return AnalyticsAlert::factory()->create([
            'team_id' => $team->id,
            'title' => $title,
            'severity' => 'critical',
        ]);
    }

    public function test_unread_count_reflects_real_unread_notifications(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $alert = $this->alertFor($user->currentTeam);
        $user->notify(new CriticalAlertTriggered($alert));
        $user->notify(new CriticalAlertTriggered($alert));

        $component = Livewire::actingAs($user)->test(NotificationBell::class);

        $this->assertSame(2, $component->get('unreadCount'));
    }

    public function test_clicking_a_notification_marks_it_as_read(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->notify(new CriticalAlertTriggered($this->alertFor($user->currentTeam)));
        $notificationId = $user->fresh()->notifications->first()->id;

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('markAsRead', $notificationId);

        $this->assertNotNull($user->fresh()->notifications->first()->read_at);
    }

    public function test_mark_all_as_read_clears_the_unread_count(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $alert = $this->alertFor($user->currentTeam);
        $user->notify(new CriticalAlertTriggered($alert));
        $user->notify(new CriticalAlertTriggered($alert));

        $component = Livewire::actingAs($user)->test(NotificationBell::class)->call('markAllAsRead');

        $this->assertSame(0, $component->get('unreadCount'));
    }

    public function test_a_notification_for_a_different_user_never_appears(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();
        $otherUser->notify(new CriticalAlertTriggered($this->alertFor($otherUser->currentTeam)));

        $component = Livewire::actingAs($user)->test(NotificationBell::class);

        $this->assertSame(0, $component->get('unreadCount'));
    }

    public function test_another_users_notification_cannot_be_marked_read_by_id(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();
        $otherUser->notify(new CriticalAlertTriggered($this->alertFor($otherUser->currentTeam)));
        $foreignId = $otherUser->fresh()->notifications->first()->id;

        Livewire::actingAs($user)->test(NotificationBell::class)->call('markAsRead', $foreignId);

        $this->assertNull($otherUser->fresh()->notifications->first()->read_at);
    }

    public function test_it_lists_the_notification_title_and_link(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->notify(new CriticalAlertTriggered($this->alertFor($user->currentTeam, 'Overdue tasks spiked')));

        Livewire::actingAs($user)->test(NotificationBell::class)
            ->assertSee('Overdue tasks spiked')
            ->assertSeeHtml('href="'.route('dashboard').'"');
    }

    public function test_it_falls_back_to_polling_so_it_still_updates_where_websockets_cannot_run(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($user)->test(NotificationBell::class)->assertSeeHtml('wire:poll');
    }

    public function test_only_the_current_teams_notifications_show_and_switching_teams_swaps_them(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $home = $user->currentTeam;
        $other = User::factory()->withPersonalTeam()->create()->currentTeam;
        $other->users()->attach($user, ['role' => 'viewer']);
        $user->notify(new CriticalAlertTriggered($this->alertFor($home, 'Home team alert')));
        $user->notify(new CriticalAlertTriggered($this->alertFor($other, 'Other company alert')));

        $component = Livewire::actingAs($user)->test(NotificationBell::class);
        $component->assertSee('Home team alert')->assertDontSee('Other company alert');
        $this->assertSame(1, $component->get('unreadCount'));

        $user->switchTeam($other);

        $component = Livewire::actingAs($user->fresh())->test(NotificationBell::class);
        $component->assertSee('Other company alert')->assertDontSee('Home team alert');
    }

    public function test_marking_all_read_only_touches_the_current_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $other = User::factory()->withPersonalTeam()->create()->currentTeam;
        $other->users()->attach($user, ['role' => 'viewer']);
        $user->notify(new CriticalAlertTriggered($this->alertFor($user->currentTeam)));
        $user->notify(new CriticalAlertTriggered($this->alertFor($other)));

        Livewire::actingAs($user)->test(NotificationBell::class)->call('markAllAsRead');

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }

    public function test_a_user_without_a_current_team_sees_an_empty_bell(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->notify(new CriticalAlertTriggered($this->alertFor($user->currentTeam)));
        $user->forceFill(['current_team_id' => null])->save();

        $component = Livewire::actingAs($user->fresh())->test(NotificationBell::class);

        $this->assertSame(0, $component->get('unreadCount'));
    }

    public function test_a_viewer_reads_and_clears_the_notifications_addressed_to_them(): void
    {
        $owner = $this->ownerWithTeam();
        $viewer = $this->memberOf($owner, 'viewer');
        $viewer->notify(new CriticalAlertTriggered($this->alertFor($owner->currentTeam, 'Visible to viewers')));

        $component = Livewire::actingAs($viewer)->test(NotificationBell::class)
            ->assertSee('Visible to viewers')
            ->call('markAllAsRead');

        $this->assertSame(0, $component->get('unreadCount'));
    }

    public function test_the_bell_is_on_every_authenticated_page_including_for_a_viewer(): void
    {
        $owner = $this->ownerWithTeam();
        $viewer = $this->memberOf($owner, 'viewer');
        $viewer->notify(new CriticalAlertTriggered($this->alertFor($owner->currentTeam, 'Seen in the nav bar')));

        $this->actingAs($viewer)->get('/dashboard')
            ->assertOk()
            ->assertSee('Seen in the nav bar');
    }
}
