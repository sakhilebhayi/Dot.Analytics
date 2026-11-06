@props(['items', 'empty' => 'Nothing has been collected here yet.', 'level' => 3])
@php
    $kinds = ['announcement' => 'Announcement', 'regulatory' => 'Government', 'press-release' => 'Press release', 'statistics-release' => 'Statistics release'];
@endphp
@if($items->isEmpty())
    <p class="empty-note"><b>Nothing to show.</b> {{ $empty }}</p>
@else
    <ul class="news">
        @foreach($items as $item)
            <li>
                <time class="when num" datetime="{{ $item->published_at->toIso8601String() }}">{{ $item->published_at->format('j M Y') }}</time>
                <div>
                    <h{{ $level }}><a href="{{ route('intel.item', $item) }}">{{ $item->title }}</a></h{{ $level }}>
                    <p class="by"><span>{{ $item->source->name }}</span><span class="label"><i class="glyph source" aria-hidden="true"></i>{{ $kinds[$item->kind] ?? 'Development' }}</span></p>
                    @if($item->source_summary)<p class="summary">{{ $item->source_summary }}</p>@endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
