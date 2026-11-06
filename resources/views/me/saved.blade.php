<x-me-layout title="Saved">
    <div class="wrap page-head"><h1>Saved.</h1><p class="lede">Developments and searches you wanted to come back to.</p></div>
    <div class="wrap section">
        @if($rows->isEmpty())
            <p class="empty-note"><b>Nothing saved.</b> Choose Save on a development, or on a search you want to repeat.</p>
        @else
            <ul class="rows">
                @foreach($rows as $row)
                    <li>
                        <span class="what">@if($row['target'])<a href="{{ $row['target']['href'] }}">{{ $row['saved']->title }}</a>@else<span class="gone">{{ $row['saved']->title }} (no longer in the library)</span>@endif</span>
                        <form method="post" action="{{ route('me.saved.destroy', $row['saved']->id) }}">@csrf @method('DELETE')<button class="link-button" type="submit">Remove</button></form>
                        <span class="sub">{{ $row['saved']->kind === 'item' ? 'Development' : 'Search' }} · saved {{ $row['saved']->created_at->format('j M Y') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-me-layout>
