<?php

namespace App\Livewire\Analytics;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Notification Bell
 *
 * Reads Auth::user()->notifications/unreadNotifications directly --
 * both built into Laravel's Notifiable trait (already on User), no new
 * query code. Real-time refresh is wired via the 'notification-received'
 * Livewire event, dispatched from a browser-side Echo listener.
 */
class NotificationBell extends Component
{
    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    #[Computed]
    public function recentNotifications(): Collection
    {
        return Auth::user()->notifications()->latest()->limit(10)->get();
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = Auth::user()->notifications()->where('id', $notificationId)->first();
        $notification?->markAsRead();

        unset($this->unreadCount, $this->recentNotifications);
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();

        unset($this->unreadCount, $this->recentNotifications);
    }

    #[On('notification-received')]
    public function refresh(): void
    {
        unset($this->unreadCount, $this->recentNotifications);
    }

    public function render(): View
    {
        return view('livewire.analytics.notification-bell');
    }
}
