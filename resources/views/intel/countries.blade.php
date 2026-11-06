<x-public-layout title="Countries" section="intel.countries" description="Growth, inflation, unemployment and investment for African economies and the large economies they trade with, each with its source and year.">
    <div class="wrap page-head">
        <h1>Countries, Africa first.</h1>
        <p class="lede">Annual national figures from the World Bank. The latest year differs by country because countries report at different speeds. A hatched cell is out of date.</p>
    </div>
    <section class="wrap section" aria-labelledby="m-h">
        <div class="section-head"><h2 id="m-h">The comparison</h2><p>Select a country for everything we hold on it.</p></div>
        <x-intel.matrix :matrix="$matrix" caption="Latest growth, inflation, unemployment and investment by country"/>
    </section>
    <section class="wrap section" aria-labelledby="all-h">
        <div class="section-head"><h2 id="all-h">Every country we list</h2><p>Some have few series yet. Their pages say so.</p></div>
        @foreach($regions as $region => $places)
            <h3 class="group">{{ $region ?: 'Other' }}</h3>
            <ul class="chips">@foreach($places as $g)<li><a href="{{ route('intel.country', $g) }}">{{ $g->name }}</a></li>@endforeach</ul>
        @endforeach
    </section>
</x-public-layout>
