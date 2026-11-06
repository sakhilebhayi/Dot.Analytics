<x-me-layout title="My library">
    <div class="wrap page-head">
        <h1>What you watch, and what changed.</h1>
        <p class="lede">Things you watch are kept for you here. The library tells you when something new touches them, and says why.</p>
    </div>

    <section class="wrap section" aria-labelledby="al-h">
        <div class="section-head"><h2 id="al-h">Latest alerts</h2><p><a class="more" href="{{ route('me.alerts') }}">All alerts</a></p></div>
        @if($alerts->isEmpty())
            <p class="empty-note"><b>Nothing yet.</b> @if($watching->isEmpty())Watch an industry, a country or a figure and the library will tell you what it collects about it.@else Nothing new has touched what you watch since you started. We look every half hour.@endif</p>
        @else
            <ul class="rows">
                @foreach($alerts as $a)
                    <li><span class="what"><a href="{{ $a->href }}">{{ $a->title }}</a></span><span class="when num">{{ $a->created_at->format('j M') }}</span><span class="sub">{{ $a->kind === 'movement' ? 'Moved more than usual' : 'New development' }} · {{ $a->detail }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>

    @if($figures->isNotEmpty())
        <section class="wrap section" aria-labelledby="fg-h">
            <div class="section-head"><h2 id="fg-h">Figures you watch</h2></div>
            <div class="cols two">@foreach($figures as $f)<x-intel.figure :figure="$f"/>@endforeach</div>
        </section>
    @endif

    <section class="wrap section" aria-labelledby="w-h">
        <div class="section-head"><h2 id="w-h">Watching {{ $watching->count() }} of {{ $limits->watches() }}</h2><p><a class="more" href="{{ route('me.watchlist') }}">Manage</a></p></div>
        @if($watching->isEmpty())
            <p class="empty-note"><b>You are not watching anything.</b> Open an <a href="{{ route('intel.industries') }}">industry</a>, a <a href="{{ route('intel.countries') }}">country</a> or any figure and choose Watch.</p>
        @else
            <ul class="chips">@foreach($watching as $w)@if($w['target'])<li><a href="{{ $w['target']['href'] }}">{{ $w['target']['label'] }}</a></li>@endif @endforeach</ul>
        @endif
    </section>

    @if($saved->isNotEmpty())
        <section class="wrap section" aria-labelledby="s-h">
            <div class="section-head"><h2 id="s-h">Saved</h2><p><a class="more" href="{{ route('me.saved') }}">All saved</a></p></div>
            <ul class="rows">@foreach($saved as $row)<li><span class="what">@if($row['target'])<a href="{{ $row['target']['href'] }}">{{ $row['saved']->title }}</a>@else<span class="gone">{{ $row['saved']->title }} (no longer in the library)</span>@endif</span></li>@endforeach</ul>
        </section>
    @endif
</x-me-layout>
