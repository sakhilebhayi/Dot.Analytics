@php
    use App\Services\Intelligence\Reading\Freshness;
@endphp
<x-public-layout :title="$industry->name" section="intel.industries" :description="\Illuminate\Support\Str::limit($industry->summary.' Figures, developments and what is missing, each with its source.', 155)">
    <div class="wrap page-head">
        <ol class="crumbs"><li><a href="{{ route('intel.industries') }}">Industries</a></li><li aria-current="page">{{ $industry->name }}</li></ol>
        <h1>{{ $industry->name }}</h1>
        <p class="lede">{{ $industry->summary }}</p>
        <p class="btn-row"><a class="btn quiet" href="{{ route('me.watch.confirm', ['kind' => 'industry', 'key' => $industry->slug]) }}">Watch this industry</a></p>
    </div>

    <section class="wrap section" aria-labelledby="cov-h">
        <div class="section-head">
            <h2 id="cov-h">What we hold for {{ $place?->name ?? 'South Africa' }}</h2>
            <p><b>{{ $grade }} coverage.</b> <x-intel.cover :cells="$cells"/></p>
        </div>
        <nav class="chips" aria-label="Choose a country">
            @foreach($places as $p)<a href="{{ route('intel.industry', [$industry, 'place' => $p->code]) }}" @if($place && $place->id === $p->id) aria-current="true" @endif>{{ $p->name }}</a>@endforeach
        </nav>
        @if($cells === [])
            <p class="empty-note"><b>No series yet.</b> We do not list any measures for {{ $industry->name }}. Developments in the newsroom below are filed here by words in their headlines.</p>
        @else
            <ul class="cover-list">
                @foreach($cells as $c)
                    <li>
                        <i class="sw {{ $c['freshness']->value }}" aria-hidden="true"></i>
                        <a href="{{ route('intel.indicator', [$c['indicator'], 'place' => $place?->code]) }}">{{ $c['indicator']->name }}</a>
                        <span class="when">{{ $c['freshness']->label() }}@if($c['period']), {{ $c['period'] }}@endif</span>
                    </li>
                @endforeach
            </ul>
            <x-intel.cover :cells="[]" :key="true"/>
        @endif
    </section>

    @if($figures !== [])
        <section class="wrap section" aria-labelledby="fig-h">
            <div class="section-head"><h2 id="fig-h">The figures</h2><p>Open "Show working" under any figure for its definition, source and every value.</p></div>
            <div class="cols two">@foreach($figures as $f)<x-intel.figure :figure="$f" :place="false"/>@endforeach</div>
        </section>
    @endif

    <section class="wrap section" aria-labelledby="news-h">
        <div class="section-head"><h2 id="news-h">Developments</h2><p><a class="more" href="{{ route('intel.newsroom', ['industry' => $industry->slug]) }}">All for {{ $industry->name }}</a></p></div>
        <x-intel.news :items="$items" :empty="'No development has been filed under '.$industry->name.' yet.'"/>
    </section>

    @if($directory)
        <section class="wrap section" aria-labelledby="disc-h">
            <div class="discover">
                <div>
                    <h2 id="disc-h">Businesses in {{ $industry->name }}</h2>
                    <p>InfoDot's directory lists South African businesses in this sector. Verified ones are marked there. It is a separate site with its own rules.</p>
                </div>
                <div class="btn-row">
                    <a class="btn" href="{{ route('intel.go', ['directory', $industry->slug]) }}" rel="noopener">Browse the directory</a>
                    @if($directoryVerified)<a class="btn quiet" href="{{ route('intel.go', ['directory-verified', $industry->slug]) }}" rel="noopener">Verified only</a>@endif
                </div>
            </div>
        </section>
    @endif
</x-public-layout>
