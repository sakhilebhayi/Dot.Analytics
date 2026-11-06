<x-public-layout :title="$geography->name" section="intel.countries" :description="'Figures and developments for '.$geography->name.', each with its source and year.'">
    <div class="wrap page-head">
        <ol class="crumbs"><li><a href="{{ route('intel.countries') }}">Countries</a></li><li aria-current="page">{{ $geography->name }}</li></ol>
        <h1>{{ $geography->name }}</h1>
        <p class="lede">{{ $geography->region ?: 'World' }}. @if($count > 0){{ $count }} {{ \Illuminate\Support\Str::plural('figure', $count) }} held, grouped by what they measure.@else We hold no figures for this country yet. Nothing is estimated in their place.@endif</p>
        <p class="btn-row"><a class="btn quiet" href="{{ route('me.watch.confirm', ['kind' => 'country', 'key' => $geography->code]) }}">Watch {{ $geography->name }}</a></p>
    </div>

    @forelse($byTopic as $topic => $figures)
        <section class="wrap section" aria-labelledby="t-{{ \Illuminate\Support\Str::slug($topic) }}">
            <div class="section-head"><h2 id="t-{{ \Illuminate\Support\Str::slug($topic) }}">{{ \Illuminate\Support\Str::headline($topic) }}</h2></div>
            <div class="cols two">@foreach($figures as $f)<x-intel.figure :figure="$f" :place="false"/>@endforeach</div>
        </section>
    @empty
        <section class="wrap section"><p class="empty-note"><b>Nothing to show.</b> No source we collect from has published a figure for {{ $geography->name }}.</p></section>
    @endforelse

    <section class="wrap section" aria-labelledby="ind-h">
        <div class="section-head"><h2 id="ind-h">By industry</h2><p>How much we hold for each industry in {{ $geography->name }}.</p></div>
        <ul class="index">
            @foreach($industries as $row)
                <li><a href="{{ route('intel.industry', [$row['industry'], 'place' => $geography->code]) }}"><span class="name">{{ $row['industry']->name }}</span><span class="grade"><b>{{ $row['grade'] }} coverage</b><x-intel.cover :cells="$row['cells']"/></span></a></li>
            @endforeach
        </ul>
        <x-intel.cover :cells="[]" :key="true"/>
    </section>

    <section class="wrap section" aria-labelledby="news-h">
        <div class="section-head"><h2 id="news-h">Developments</h2><p><a class="more" href="{{ route('intel.newsroom', ['place' => $geography->code]) }}">All for {{ $geography->name }}</a></p></div>
        <x-intel.news :items="$items" :empty="'No development has been filed under '.$geography->name.' yet.'"/>
    </section>
</x-public-layout>
