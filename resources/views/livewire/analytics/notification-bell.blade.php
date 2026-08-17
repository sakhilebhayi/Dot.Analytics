<div class="ms-3 relative">
    <x-dropdown align="right" width="80">
        <x-slot name="trigger">
            <button type="button" class="press relative" style="width:36px;height:36px;border-radius:9999px;background:transparent;border:1px solid var(--line);display:flex;align-items:center;justify-content:center;">
                <span class="material-symbols-outlined" style="font-size:18px;color:var(--mist);">notifications</span>
                @if($this->unreadCount > 0)
                    <span class="font-mono" style="position:absolute;top:-4px;right:-4px;background:var(--gold);color:var(--ink);font-size:0.6rem;font-weight:700;border-radius:9999px;min-width:16px;height:16px;display:flex;align-items:center;justify-content:center;padding:0 3px;">{{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}</span>
                @endif
            </button>
        </x-slot>

        <x-slot name="content">
            <div style="width:20rem;max-height:24rem;overflow-y:auto;">
                <div class="flex items-center justify-between" style="padding:0.6rem 1rem;border-bottom:1px solid var(--line);">
                    <span class="font-mono" style="font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);">Notifications</span>
                    @if($this->unreadCount > 0)
                        <button wire:click="markAllAsRead" style="font-size:0.68rem;color:var(--teal-soft);background:none;border:none;cursor:pointer;">Mark all as read</button>
                    @endif
                </div>

                @if($this->recentNotifications->isEmpty())
                    <p style="font-size:0.8rem;color:var(--mist);text-align:center;padding:1.5rem 1rem;">No notifications yet.</p>
                @else
                    @foreach($this->recentNotifications as $notification)
                        <a
                            href="{{ $notification->data['url'] ?? route('dashboard') }}"
                            wire:click="markAsRead('{{ $notification->id }}')"
                            style="display:block;padding:0.6rem 1rem;border-bottom:1px solid var(--line);text-decoration:none;background:{{ $notification->read_at ? 'transparent' : 'rgba(241,198,46,0.05)' }};"
                        >
                            <p style="font-size:0.8rem;font-weight:600;color:var(--paper);margin:0;">{{ $notification->data['title'] ?? 'Notification' }}</p>
                            @if(!empty($notification->data['description']))
                                <p style="font-size:0.7rem;color:var(--mist);margin:0.2rem 0 0;">{{ \Illuminate\Support\Str::limit($notification->data['description'], 100) }}</p>
                            @endif
                            <p style="font-size:0.62rem;color:var(--mist);opacity:0.6;margin:0.25rem 0 0;">{{ $notification->created_at->diffForHumans() }}</p>
                        </a>
                    @endforeach
                @endif
            </div>
        </x-slot>
    </x-dropdown>
</div>
