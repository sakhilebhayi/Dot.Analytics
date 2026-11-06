@php $asks = ['How is South African manufacturing doing?', 'What is the repo rate?', 'Oil price today', 'Inflation in Nigeria', 'Unemployment in Kenya']; @endphp
<x-public-layout :title="$q === '' ? 'Search and ask' : 'Results for '.$q" section="intel.find" :noindex="$q !== ''" description="Search the library, or ask a question that stored figures can answer.">
    <div class="wrap page-head">
        <h1>Search, or ask a question.</h1>
        <p class="lede">Answers come from figures and releases we have stored, with the source beside each. If we hold nothing that answers, we say that, and we do not guess.</p>
        <form class="search-big" action="{{ route('intel.find') }}" method="get" role="search" aria-label="Search again">
            <label class="sr" for="q-find">Search the library or ask a question</label>
            <input id="q-find" type="search" name="q" value="{{ $q }}" placeholder="Search, or ask a question" autocomplete="off" maxlength="160">
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="wrap section">
        @if($q === '')
            <p class="prov">Questions to try</p>
            <ul class="chips">@foreach($asks as $ask)<li><a href="{{ route('intel.find', ['q' => $ask]) }}">{{ $ask }}</a></li>@endforeach</ul>
        @else
            <p><a class="btn quiet" href="{{ route('me.save.confirm', ['kind' => 'search', 'ref' => $q]) }}">Save this search</a></p>
            @if($answer)
                <section class="answer" aria-labelledby="ans-h">
                    <h2 id="ans-h">{{ $answer['title'] }}</h2>
                    @if($answer['lines'] !== [])
                        <p class="prov"><x-intel.label kind="source"/> <span>Taken from stored figures. No model wrote this answer.</span></p>
                        <ul class="lines">
                            @foreach($answer['lines'] as $line)
                                <li><a href="{{ $line['href'] }}">{{ $line['text'] }}</a><span class="when">{{ $line['period'] }}</span><span class="src">{{ $line['source'] }}@if($line['stale']) · out of date @endif</span></li>
                            @endforeach
                        </ul>
                    @endif
                    @if($answer['missing'] !== [])
                        <div class="not-yet"><h3>What we do not have</h3><ul>@foreach($answer['missing'] as $m)<li><span>{{ $m }}</span></li>@endforeach</ul></div>
                    @endif
                </section>
            @endif

            <section class="results" aria-labelledby="res-h">
                <h2 id="res-h">Matches</h2>
                @if($hits === 0)
                    <p class="empty-note"><b>We found nothing for "{{ $q }}".</b> Try the name of an industry, a country or a measure such as inflation. The failed search is counted, without who made it, so we can see what is missing.</p>
                @endif
                @if($found['industries']->isNotEmpty())
                    <h3 class="group">Industries</h3><ul>@foreach($found['industries'] as $i)<li><a href="{{ route('intel.industry', $i) }}">{{ $i->name }}</a><p>{{ $i->summary }}</p></li>@endforeach</ul>
                @endif
                @if($found['places']->isNotEmpty())
                    <h3 class="group">Countries</h3><ul>@foreach($found['places'] as $p)<li><a href="{{ route('intel.country', $p) }}">{{ $p->name }}</a><p>{{ $p->region }}</p></li>@endforeach</ul>
                @endif
                @if($found['indicators']->isNotEmpty())
                    <h3 class="group">Figures</h3><ul>@foreach($found['indicators'] as $r)<li><a href="{{ route('intel.indicator', $r['indicator']) }}">{{ $r['indicator']->name }}</a><p>{{ $r['indicator']->source->name }} · {{ $r['why'] }}</p></li>@endforeach</ul>
                @endif
                @if($found['items']->isNotEmpty())
                    <h3 class="group">Developments</h3><ul>@foreach($found['items'] as $r)<li><a href="{{ route('intel.item', $r['item']) }}">{{ $r['item']->title }}</a><p>{{ $r['item']->source->name }} · {{ $r['item']->published_at->format('j M Y') }} · {{ $r['why'] }}</p></li>@endforeach</ul>
                @endif
            </section>
        @endif
    </div>
</x-public-layout>
