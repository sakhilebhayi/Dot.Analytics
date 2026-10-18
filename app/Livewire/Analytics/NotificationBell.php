<?php

namespace App\Livewire\Analytics;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Notification Bell
 *
 * Reads the signed-in user's own database notifications (Laravel's Notifiable trait, already
 * on User), narrowed to the team they are currently working in: a user on several companies'
 * teams sees one company's alerts at a time, and after being removed from a team its alerts
 * never show again.
 *
 * Reading and clearing one's own notifications changes nothing the team relies on and
 * spends no AI budget, so every role may do it, the read-only viewer included. Nothing
 * here can reach another user's notifications: every query starts from Auth::user().
 *
 * Freshness: the Echo client dispatches `notification-received` when Reverb is running; the
 * poll keeps it current when it is not (e.g. the shared cPanel host, which cannot run Reverb).
 */
class NotificationBell extends Component
{
    #[Computed]
    public function unreadCount(): int
    {
        return $this->inCurrentTeam()->whereNull('read_at')->count();
    }

    #[Computed]
    public function recentNotifications(): Collection
    {
        return $this->inCurrentTeam()->latest()->limit(10)->get();
    }

    public function markAsRead(string $notificationId): void
    {
        $this->inCurrentTeam()->where('id', $notificationId)->first()?->markAsRead();

        $this->forgetCached();
    }

    public function markAllAsRead(): void
    {
        $this->inCurrentTeam()->whereNull('read_at')->update(['read_at' => now()]);

        $this->forgetCached();
    }

    #[On('notification-received')]
    public function refresh(): void
    {
        $this->forgetCached();
    }

    public function render(): View
    {
        return view('livewire.analytics.notification-bell');
    }

    /** @return MorphMany<DatabaseNotification, User> */
    private function inCurrentTeam(): MorphMany
    {
        $user = Auth::user();
        $teamId = $user->currentTeam?->id;

        // No current team: nothing is "current", so nothing shows (id 0 matches no team).
        return $user->notifications()->where('data->team_id', $teamId ?? 0);
    }

    private function forgetCached(): void
    {
        unset($this->unreadCount, $this->recentNotifications);
    }
}
