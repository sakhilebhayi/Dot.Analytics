<x-me-layout title="Watchlist">
    <div class="wrap page-head">
        <h1>Your watchlist.</h1>
        <p class="lede">{{ $watching->count() }} of {{ $limits->watches() }} places used. Stopping a watch also stops its alerts and emails. Past alerts stay until you clear them.</p>
    </div>
    <div class="wrap section">
        @if($watching->isEmpty())
            <p class="empty-note"><b>Nothing here yet.</b> Open an <a href="{{ route('intel.industries') }}">industry</a>, a <a href="{{ route('intel.countries') }}">country</a> or any figure and choose Watch.</p>
        @else
            <ul class="rows">
                @foreach($watching as $w)
                    <li>
                        <span class="what">@if($w['target'])<a href="{{ $w['target']['href'] }}">{{ $w['target']['label'] }}</a>@else<span class="gone">{{ $w['watch']->key }} (no longer in the library)</span>@endif</span>
                        <form method="post" action="{{ route('me.watch.destroy', $w['watch']->id) }}">@csrf @method('DELETE')<button class="link-button" type="submit">Stop watching</button></form>
                        <span class="sub">{{ $w['target']['sub'] ?? 'Gone' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-me-layout>
