<x-public-layout title="Newsroom" section="intel.newsroom" description="Developments published by central banks, regulators and governments, linked to the original and filed by industry and country.">
    <div class="wrap page-head">
        <h1>Developments, from the people who publish them.</h1>
        <p class="lede">We collect from central banks, regulators and governments. Each item links to the original and carries the publisher's own summary, where it allows one. We are not a news aggregator.</p>
        <form class="filters" method="get" action="{{ route('intel.newsroom') }}">
            <label>Industry
                <select name="industry"><option value="">All industries</option>@foreach($industries as $i)<option value="{{ $i->slug }}" @selected($industry?->id === $i->id)>{{ $i->name }}</option>@endforeach</select>
            </label>
            <label>Country
                <select name="place"><option value="">All countries</option>@foreach($places as $p)<option value="{{ $p->code }}" @selected($place?->id === $p->id)>{{ $p->name }}</option>@endforeach</select>
            </label>
            <label>Kind
                <select name="kind"><option value="">All kinds</option>@foreach(['announcement' => 'Announcement', 'regulatory' => 'Government', 'press-release' => 'Press release', 'statistics-release' => 'Statistics release'] as $k => $label)<option value="{{ $k }}" @selected($kind === $k)>{{ $label }}</option>@endforeach</select>
            </label>
            <button class="btn" type="submit">Filter</button>
        </form>
    </div>
    <div class="wrap section">
        <x-intel.news :items="$items" empty="Nothing matches these filters. Items are filed by words in their headlines, so a thin result can mean a thin filing, not a quiet week."/>
        @if($items->hasPages())
            <nav class="pager" aria-label="Pages">
                @if($items->onFirstPage())<span></span>@else<a href="{{ $items->previousPageUrl() }}" rel="prev">Newer</a>@endif
                <span>Page {{ $items->currentPage() }} of {{ $items->lastPage() }}</span>
                @if($items->hasMorePages())<a href="{{ $items->nextPageUrl() }}" rel="next">Older</a>@else<span></span>@endif
            </nav>
        @endif
    </div>
</x-public-layout>
