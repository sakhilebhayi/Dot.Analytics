@php $kinds = ['announcement' => 'Announcement', 'regulatory' => 'Government', 'press-release' => 'Press release', 'statistics-release' => 'Statistics release']; @endphp
<x-public-layout :title="$item->title" section="intel.newsroom" :description="\Illuminate\Support\Str::limit(strip_tags((string) $item->source_summary) ?: $item->title.' Published by '.$item->source->name.'.', 200)">
    <div class="wrap page-head">
        <ol class="crumbs"><li><a href="{{ route('intel.newsroom') }}">Newsroom</a></li><li aria-current="page">{{ $kinds[$item->kind] ?? 'Development' }}</li></ol>
        <h1>{{ $item->title }}</h1>
        <p class="lede"><time datetime="{{ $item->published_at->toIso8601String() }}">{{ $item->published_at->format('j F Y') }}</time> · {{ $item->source->name }}</p>
    </div>
    <div class="wrap section split">
        <article class="prose">
            <p><x-intel.label kind="source"/></p>
            @if($item->source_summary)<p>{{ $item->source_summary }}</p>@else<p>The publisher gave no summary for this item, and we do not write one in its place.</p>@endif
            @if($item->ai_summary && $item->source->allows_summary)
                <h2>Summary</h2>
                <p><x-intel.label kind="ai"/></p>
                <p>{{ $item->ai_summary }}</p>
                <p class="prov">Written by {{ $item->ai_model }} on {{ $item->ai_generated_at?->format('j F Y') }} from the publisher's text. Check it against the original.</p>
            @endif
            <p class="btn-row"><a class="btn" href="{{ $item->url }}" rel="noopener nofollow">Read the original at {{ $item->source->name }}</a><a class="btn quiet" href="{{ route('me.save.confirm', ['kind' => 'item', 'ref' => $item->uuid]) }}">Save</a></p>
            @if($item->industries->isNotEmpty() || $item->geographies->isNotEmpty())
                <ul class="tags" aria-label="Filed under">
                    @foreach($item->industries as $i)<li><a href="{{ route('intel.industry', $i) }}">{{ $i->name }}</a></li>@endforeach
                    @foreach($item->geographies as $g)<li><a href="{{ route('intel.country', $g) }}">{{ $g->name }}</a></li>@endforeach
                </ul>
                <p class="prov">Filed by words in the headline and summary, not by a person or a model. A wrong filing can be reported from the sources page.</p>
            @endif
        </article>
        <aside class="panel" aria-labelledby="p-h">
            <h3 id="p-h">About this item</h3>
            <dl class="facts">
                <dt>Publisher</dt><dd><a href="{{ $item->source->homepage_url }}" rel="noopener">{{ $item->source->name }}</a></dd>
                <dt>Published</dt><dd>{{ $item->published_at->format('j F Y, H:i') }}</dd>
                <dt>Collected</dt><dd>{{ \App\Services\Intelligence\Reading\Format::age($item->collected_at) }}</dd>
                <dt>Licence</dt><dd>{{ $item->source->licence }}</dd>
            </dl>
            @if($item->source->attribution)<p>{{ $item->source->attribution }}</p>@endif
        </aside>
    </div>
    @if($related->isNotEmpty())
        <section class="wrap section" aria-labelledby="rel-h">
            <div class="section-head"><h2 id="rel-h">Related developments</h2></div>
            <x-intel.news :items="$related"/>
        </section>
    @endif
</x-public-layout>
