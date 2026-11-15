<div class="dh-livewire" wire:poll.60s>
    <x-dot.bell :count="$this->unreadCount" id="notifications-panel" panel-label="Notifications">
        <div class="dh-panel__head">
            <span class="dh-panel__title">Notifications</span>
            @if($this->unreadCount > 0)
                <button type="button" class="dh-mark" wire:click="markAllAsRead">Mark all as read</button>
            @endif
        </div>

        @if($this->recentNotifications->isEmpty())
            <p class="dh-panel__empty">No notifications yet.</p>
        @else
            <div class="dh-panel__list">
                @foreach($this->recentNotifications as $notification)
                    <a href="{{ $notification->data['url'] ?? route('dashboard') }}"
                       wire:click="markAsRead('{{ $notification->id }}')"
                       wire:key="bell-{{ $notification->id }}"
                       class="dh-panel__item"
                       @if(! $notification->read_at) data-unread @endif>
                        <span class="ws-bell-title">{{ $notification->data['title'] ?? 'Notification' }}</span>
                        @if(! empty($notification->data['description']))
                            <span class="ws-bell-text">{{ \Illuminate\Support\Str::limit($notification->data['description'], 100) }}</span>
                        @endif
                        <span class="ws-bell-when">{{ $notification->created_at->diffForHumans() }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-dot.bell>
</div>
