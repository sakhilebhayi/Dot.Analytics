<x-me-layout title="Alerts">
    <div class="wrap page-head">
        <h1>Alerts.</h1>
        <p class="lede">What the library collected that touches what you watch. Each is written once. Movements are worked out from the numbers, and developments are linked to the publisher.</p>
        @if($alerts->contains(fn ($a) => $a->read_at === null))
            <form method="post" action="{{ route('me.alerts.read') }}">@csrf<button class="btn quiet" type="submit">Mark all as read</button></form>
        @endif
    </div>
    <div class="wrap section">
        @if($alerts->isEmpty())
            <p class="empty-note"><b>No alerts.</b> They appear when something new is collected about what you <a href="{{ route('me.watchlist') }}">watch</a>.</p>
        @else
            <ul class="rows unread-list">
                @foreach($alerts as $a)
                    <li><span class="what"><a href="{{ $a->href }}">{{ $a->read_at === null ? 'New: ' : '' }}{{ $a->title }}</a></span><span class="when num">{{ $a->created_at->format('j M Y') }}</span><span class="sub">{{ $a->kind === 'movement' ? 'Moved more than usual' : 'New development' }} · {{ $a->detail }}</span></li>
                @endforeach
            </ul>
            {{ $alerts->links('vendor.pagination.library') }}
        @endif
    </div>
</x-me-layout>
