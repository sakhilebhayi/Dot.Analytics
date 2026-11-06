@php $kind = ['statistics' => 'Statistics', 'newsroom' => 'Newsroom']; @endphp
<x-public-layout title="Sources" section="intel.sources" description="Where every figure and development comes from, the licence it is used under, when it was last collected and what is not shown yet.">
    <div class="wrap page-head">
        <h1>Sources, their licences, and how they are doing.</h1>
        <p class="lede">We publish only what a licence lets us publish. A source that fails stays visible here, with the time of its last success.</p>
        <p class="prov">{{ $holdings['series'] }} series · {{ number_format((int) $holdings['observations']) }} readings · {{ $holdings['items'] }} developments · {{ $holdings['open_issues'] }} open {{ \Illuminate\Support\Str::plural('issue', (int) $holdings['open_issues']) }}@if($unpublished) · {{ $unpublished }} {{ \Illuminate\Support\Str::plural('series', $unpublished) }} held back until the licence is confirmed @endif</p>
    </div>

    <section class="wrap section" aria-labelledby="live-h">
        <div class="section-head"><h2 id="live-h">Collecting</h2></div>
        <div class="src-grid">
            @foreach($sources as $s)
                <article class="src-card">
                    <h3><a href="{{ $s->homepage_url }}" rel="noopener">{{ $s->name }}</a> <x-intel.status :freshness="$s->freshness()"/></h3>
                    <dl class="facts">
                        <dt>Kind</dt><dd>{{ $kind[$s->kind] ?? ucfirst($s->kind) }}, {{ $s->kind === 'statistics' ? $s->series_count.' '.\Illuminate\Support\Str::plural('series', $s->series_count) : $s->item_count.' '.\Illuminate\Support\Str::plural('item', $s->item_count) }}</dd>
                        <dt>Licence</dt><dd>@if($s->terms_url)<a href="{{ $s->terms_url }}" rel="noopener">{{ $s->licence }}</a>@else{{ $s->licence }}@endif</dd>
                        <dt>Credit line</dt><dd>{{ $s->attribution }}</dd>
                        <dt>Last collected</dt><dd>{{ $s->last_success_at ? \App\Services\Intelligence\Reading\Format::age($s->last_success_at) : 'Not collected yet' }}@if($s->consecutive_failures > 0); {{ $s->consecutive_failures }} failed {{ \Illuminate\Support\Str::plural('attempt', $s->consecutive_failures) }} since @endif</dd>
                        @if($s->restrictions)<dt>Limits</dt><dd>{{ $s->restrictions }}</dd>@endif
                    </dl>
                </article>
            @endforeach
        </div>
    </section>

    @if($issues->isNotEmpty())
        <section class="wrap section" aria-labelledby="iss-h">
            <div class="section-head"><h2 id="iss-h">Open issues</h2><p>Raised by our own checks. They close themselves when the cause clears.</p></div>
            <ul class="lines">
                @foreach($issues as $i)<li><span>{{ $i->summary }}</span><span class="when">{{ $i->severity }}</span><span class="src">First seen {{ \App\Services\Intelligence\Reading\Format::age($i->first_seen_at) }}</span></li>@endforeach
            </ul>
        </section>
    @endif

    <section class="wrap section" aria-labelledby="wait-h">
        <div class="section-head"><h2 id="wait-h">Waiting for permission</h2><p>Not collected, and not shown, until the owner of the data agrees in writing.</p></div>
        <ul class="lines">
            @foreach($waiting as $w)<li><b>{{ $w->name }}</b><span class="when">waiting</span><span class="src">{{ $w->restrictions }}</span></li>@endforeach
        </ul>
    </section>
</x-public-layout>
