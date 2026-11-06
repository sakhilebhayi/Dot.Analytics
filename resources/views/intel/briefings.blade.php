<x-public-layout title="Briefings" section="intel.briefings" description="Short daily editions built from stored figures and published developments, each line linked to where it came from.">
    <div class="wrap page-head">
        <h1>Briefings, built from what was published.</h1>
        <p class="lede">Each edition has one purpose and is assembled by code from stored figures and published releases. Every line links to the figure or release behind it. An edition with nothing to say is not published.</p>
    </div>
    <div class="wrap section">
        <ul class="index">
            @foreach($editions as $e)
                <li>
                    @if($e['latest'])
                        <a href="{{ route('intel.briefing', [$e['key'], $e['latest']->edition_date->toDateString()]) }}">
                            <span class="name">{{ $e['name'] }}</span><span class="says">{{ $e['purpose'] }}</span>
                            <span class="grade"><b>Latest edition</b><span class="num">{{ $e['latest']->edition_date->format('j F Y') }}</span></span>
                        </a>
                    @else
                        <div class="row-static">
                            <span class="name">{{ $e['name'] }}</span><span class="says">{{ $e['purpose'] }}</span>
                            <span class="grade"><b>Not published yet</b><span>It needs figures and releases to draw on.</span></span>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</x-public-layout>
