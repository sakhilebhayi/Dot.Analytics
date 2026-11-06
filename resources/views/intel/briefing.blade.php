<x-public-layout :title="$briefing->title.', '.$briefing->edition_date->format('j F Y')" section="intel.briefings" :description="$def['purpose']">
    <div class="wrap page-head">
        <ol class="crumbs"><li><a href="{{ route('intel.briefings') }}">Briefings</a></li><li aria-current="page">{{ $briefing->edition_date->format('j F Y') }}</li></ol>
        <h1>{{ $briefing->title }}</h1>
        <p class="lede">{{ $def['purpose'] }}</p>
        <p class="prov"><x-intel.label kind="calculated"/> <span>Assembled by code on {{ $briefing->published_at->format('j F Y, H:i') }} from stored figures and releases. No person or model wrote these lines.</span></p>
    </div>
    <div class="wrap section prose">
        @foreach($briefing->sections as $section)
            <h2>{{ $section['heading'] }}</h2>
            <ul class="lines">
                @foreach($section['entries'] as $entry)
                    <li>
                        <a href="{{ $entry['href'] }}">{{ $entry['text'] }}</a>
                        <span class="when">{{ $entry['when'] }}</span>
                        <span class="src">{{ $entry['source'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </div>
</x-public-layout>
