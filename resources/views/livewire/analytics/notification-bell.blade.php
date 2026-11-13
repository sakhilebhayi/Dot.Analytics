<div class="ms-3 relative" wire:poll.60s>
    <x-dropdown align="right" width="80">
        <x-slot name="trigger">
            <button type="button" class="press ws-bell" aria-label="Notifications">
                <span class="material-symbols-outlined" aria-hidden="true">notifications</span>
                @if($this->unreadCount > 0)
                    <span class="ws-bell-count">{{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}</span>
                @endif
            </button>
        </x-slot>

        <x-slot name="content">
            <div class="ws-bell-list">
                <div class="ws-bell-head">
                    <span>Notifications</span>
                    @if($this->unreadCount > 0)
                        <button type="button" wire:click="markAllAsRead">Mark all as read</button>
                    @endif
                </div>

                @if($this->recentNotifications->isEmpty())
                    <p class="ws-bell-none">No notifications yet.</p>
                @else
                    @foreach($this->recentNotifications as $notification)
                        <a
                            href="{{ $notification->data['url'] ?? route('dashboard') }}"
                            wire:click="markAsRead('{{ $notification->id }}')"
                            class="ws-bell-item {{ $notification->read_at ? '' : 'is-unread' }}"
                        >
                            <p class="ws-bell-title">{{ $notification->data['title'] ?? 'Notification' }}</p>
                            @if(!empty($notification->data['description']))
                                <p class="ws-bell-text">{{ \Illuminate\Support\Str::limit($notification->data['description'], 100) }}</p>
                            @endif
                            <p class="ws-bell-when">{{ $notification->created_at->diffForHumans() }}</p>
                        </a>
                    @endforeach
                @endif
            </div>
        </x-slot>
    </x-dropdown>
</div>
