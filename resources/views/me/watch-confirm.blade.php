<x-me-layout title="Watch {{ $target['label'] }}">
    <div class="wrap page-head">
        <h1>Watch {{ $target['label'] }}?</h1>
        <p class="lede">{{ $target['sub'] }}. When something new is collected that touches it, it appears in your alerts with the reason. Email is separate and off until you turn it on.</p>
    </div>
    <div class="wrap section">
        @if($already)
            <p class="empty-note"><b>You already watch this.</b> <a href="{{ route('me.watchlist') }}">See your watchlist</a>.</p>
        @elseif($full)
            <p class="empty-note"><b>Your watchlist is full.</b> <a href="{{ route('me.watchlist') }}">Stop watching something</a> to make room.</p>
        @else
            <form method="post" action="{{ route('me.watch.store') }}" class="btn-row">
                @csrf
                <input type="hidden" name="kind" value="{{ $target['kind'] }}"><input type="hidden" name="key" value="{{ $target['key'] }}"><input type="hidden" name="place" value="{{ $target['place'] }}">
                <button class="btn" type="submit">Watch</button>
                <a class="btn quiet" href="{{ $target['href'] }}">Back</a>
            </form>
        @endif
    </div>
</x-me-layout>
