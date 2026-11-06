@php
    use App\Services\Intelligence\Reading\Format;
    $asks = ['How is South African manufacturing doing?', 'What is the repo rate?', 'Oil price today', 'Inflation in Nigeria', 'Which sectors are growing in Kenya?'];
@endphp
<x-public-layout title="Dot.Analytics" section="intel.home" :jsonld="['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Dot.Analytics', 'url' => url('/'), 'potentialAction' => ['@type' => 'SearchAction', 'target' => url('/find').'?q={query}', 'query-input' => 'required name=query']]">
    <section class="wrap statement">
        <div>
            <h1>Business figures that show their working.</h1>
            <p class="says">Developments and statistics from central banks, regulators and statistics offices, each with its source, its period and its licence. Where we do not have something, we say so. <a href="{{ route('intel.method') }}">How figures are labelled</a>.</p>
            <form class="search-big" action="{{ route('intel.find') }}" method="get" role="search" aria-label="Ask a question or search">
                <label class="sr" for="q-home">Search the library or ask a question</label>
                <input id="q-home" type="search" name="q" placeholder="Search, or ask a question" autocomplete="off" maxlength="160">
                <button type="submit">Search</button>
            </form>
            <ul class="chips" aria-label="Questions to try">
                @foreach($asks as $ask)<li><a href="{{ route('intel.find', ['q' => $ask]) }}">{{ $ask }}</a></li>@endforeach
            </ul>
        </div>
        <aside aria-labelledby="today">
            <h2 id="today" class="sr">The numbers today</h2>
            <div class="plates">
                @forelse($figures as $f)
                    <x-intel.figure :figure="$f"/>
                @empty
                    <p class="empty-note"><b>No figures yet.</b> The first collection has not finished. Nothing is shown until a source has published it.</p>
                @endforelse
            </div>
        </aside>
    </section>

    <section class="wrap section" aria-labelledby="news-h">
        <div class="section-head">
            <h2 id="news-h">Newsroom</h2>
            <p>Published by central banks, regulators and governments. We link to the original and add nothing to it. <a class="more" href="{{ route('intel.newsroom') }}">All developments</a></p>
        </div>
        <x-intel.news :items="$items" empty="The sources have not published anything we hold yet."/>
    </section>

    <section class="wrap section" aria-labelledby="ind-h">
        <div class="section-head">
            <h2 id="ind-h">By industry</h2>
            <p>South Africa first. The squares show how many of an industry's series are current, out of date or not available.</p>
        </div>
        <ul class="index">
            @foreach($industries as $row)
                <li>
                    <a href="{{ route('intel.industry', $row['industry']) }}">
                        <span class="name">{{ $row['industry']->name }}</span>
                        <span class="says">{{ $row['industry']->summary }}</span>
                        <span class="grade"><b>{{ $row['grade'] }} coverage</b><x-intel.cover :cells="$row['cells']"/></span>
                    </a>
                </li>
            @endforeach
        </ul>
        <x-intel.cover :cells="[]" :key="true"/>
    </section>

    <section class="wrap section" aria-labelledby="co-h">
        <div class="section-head">
            <h2 id="co-h">By country</h2>
            <p>Africa first, then the economies people compare against. <a class="more" href="{{ route('intel.countries') }}">All countries</a></p>
        </div>
        <x-intel.matrix :matrix="$matrix" caption="Latest growth, inflation, unemployment and investment by country"/>
    </section>

    <section class="band" aria-labelledby="moved-h">
        <div class="wrap section">
            <div class="section-head">
                <h2 id="moved-h">What moved</h2>
                <p>Worked out from the numbers, never written by a model. <x-intel.label kind="calculated"/></p>
            </div>
            @if($moves->isEmpty())
                <p class="empty">Nothing has moved more than usual in the last 45 days. We report a move only when it is larger than that series normally shows.</p>
            @else
                <ul class="moves">
                    @foreach($moves as $m)
                        <li><p class="sentence"><a href="{{ $m['href'] }}">{{ $m['sentence'] }}</a></p><p class="why">{{ $m['why'] }}</p></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <section class="wrap section" aria-labelledby="key-h">
        <div class="section-head">
            <h2 id="key-h">What each thing on a page is</h2>
            <p>Facts, our own arithmetic, summaries, opinion and forecasts are never mixed.</p>
        </div>
        <div class="key">
            <div><h3><x-intel.label kind="source"/></h3><p>What a source published, shown as published.</p></div>
            <div><h3><x-intel.label kind="calculated"/></h3><p>Arithmetic on source facts, such as a change since the last reading.</p></div>
            <div><h3><x-intel.label kind="ai"/></h3><p>A machine summary of text we may summarise. None is shown yet.</p></div>
            <div><h3><x-intel.label kind="editorial"/></h3><p>An opinion, signed and dated. None is published yet.</p></div>
            <div><h3><x-intel.label kind="forecast"/></h3><p>A projection with its assumptions. None is published yet.</p></div>
        </div>
        <div class="not-yet">
            <h3>Not shown yet, and why</h3>
            <ul>
                @foreach($waiting as $w)<li><b>{{ $w->name }}</b><span>{{ $w->restrictions }}</span></li>@endforeach
            </ul>
            <p class="prov">{{ $holdings['series'] }} series · {{ number_format((int) $holdings['observations']) }} readings · {{ $holdings['items'] }} developments @if($holdings['last_collection']) · last collected {{ Format::age(\Illuminate\Support\Carbon::parse($holdings['last_collection'])) }}@endif</p>
        </div>
    </section>

    @if($infodot)
        <section class="wrap section" aria-labelledby="disc-h">
            <div class="discover">
                <div>
                    <h2 id="disc-h">Looking for a business rather than a figure?</h2>
                    <p>InfoDot lists South African businesses by sector and place, with the ones that have been verified marked. It is a separate site with its own rules.</p>
                </div>
                <a class="btn" href="{{ route('intel.go', ['infodot', 'directory']) }}" rel="noopener">Browse the directory</a>
            </div>
        </section>
    @endif
</x-public-layout>
