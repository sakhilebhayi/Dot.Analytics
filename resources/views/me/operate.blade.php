<x-me-layout title="How the library is doing">
    <div class="wrap page-head">
        <h1>How the library is doing.</h1>
        <p class="lede">For platform operators. Visitors are counted without cookies, by a weekly one-way hash, so these are numbers of people, never of individuals.</p>
        <p class="prov">{{ $counts['series'] }} series · {{ number_format($counts['readings']) }} readings · {{ $counts['items'] }} developments @if($counts['size']) · store {{ $counts['size'] }} MB @endif</p>
    </div>

    <section class="wrap section" aria-labelledby="t-h">
        <div class="section-head"><h2 id="t-h">Readers, last 14 days</h2></div>
        @if($days->isEmpty())<p class="empty-note"><b>No visits counted yet.</b> Counting starts with the first public page view.</p>@else
        <div class="matrix-wrap" tabindex="0" role="region" aria-label="Readers by day, scrollable"><table class="table-plain"><caption class="sr">Readers by day</caption>
            <thead><tr><th scope="col">Day</th><th scope="col" class="n">People</th><th scope="col" class="n">Came back this week</th><th scope="col" class="n">Page views</th></tr></thead>
            <tbody>@foreach($days as $d)<tr><th scope="row">{{ $d->day }}</th><td class="n num">{{ $d->visitors }}</td><td class="n num">{{ $d->returning }}</td><td class="n num">{{ $d->views }}</td></tr>@endforeach</tbody>
        </table></div>@endif
    </section>

    <section class="wrap section split">
        <div>
            <div class="section-head"><h2>Most read</h2></div>
            <div class="matrix-wrap" tabindex="0" role="region" aria-label="Most read pages, scrollable"><table class="table-plain"><caption class="sr">Most read pages</caption><thead><tr><th scope="col">Page</th><th scope="col" class="n">Views</th></tr></thead>
                <tbody>@forelse($pages as $p)<tr><th scope="row">{{ $p->area }}{{ $p->target ? ' / '.$p->target : '' }}</th><td class="n num">{{ $p->views }}</td></tr>@empty<tr><td colspan="2">Nothing yet.</td></tr>@endforelse</tbody></table></div>
        </div>
        <aside class="panel"><h3>Where they went next</h3>
            @forelse($referrals as $r)<p>{{ $r->kind }} / {{ $r->target }}: <b class="num">{{ $r->clicks }}</b> clicks</p>@empty<p>No one has followed a link to another platform yet.</p>@endforelse
        </aside>
    </section>

    <section class="wrap section" aria-labelledby="s-h">
        <div class="section-head"><h2 id="s-h">Searches that found nothing</h2><p>{{ (int) ($searches->asked ?? 0) }} asked, {{ (int) ($searches->empty ?? 0) }} found nothing. Each is a gap to fill or a word to teach the search.</p></div>
        <div class="matrix-wrap" tabindex="0" role="region" aria-label="Searches that found nothing, scrollable"><table class="table-plain"><caption class="sr">Empty searches</caption><thead><tr><th scope="col">What was asked</th><th scope="col" class="n">Times</th></tr></thead>
            <tbody>@forelse($empty as $e)<tr><th scope="row">{{ $e->query }}</th><td class="n num">{{ $e->asked }}</td></tr>@empty<tr><td colspan="2">None.</td></tr>@endforelse</tbody></table></div>
    </section>

    <section class="wrap section" aria-labelledby="src-h">
        <div class="section-head"><h2 id="src-h">Sources</h2></div>
        <div class="matrix-wrap" tabindex="0" role="region" aria-label="Source health, scrollable"><table class="table-plain"><caption class="sr">Source health</caption>
            <thead><tr><th scope="col">Source</th><th scope="col">State</th><th scope="col">Last success</th><th scope="col" class="n">Failures</th><th scope="col">Last error</th></tr></thead>
            <tbody>@foreach($sources as $s)<tr><th scope="row">{{ $s->name }}</th><td>{{ $s->status }}@unless($s->is_enabled), off @endunless</td><td>{{ $s->last_success_at ? $s->last_success_at->diffForHumans() : 'never' }}</td><td class="n num">{{ $s->consecutive_failures }}</td><td>{{ $s->last_error }}</td></tr>@endforeach</tbody></table></div>
    </section>

    <section class="wrap section" aria-labelledby="r-h">
        <div class="section-head"><h2 id="r-h">Latest collection runs</h2></div>
        <div class="matrix-wrap" tabindex="0" role="region" aria-label="Latest collection runs, scrollable"><table class="table-plain"><caption class="sr">Latest runs</caption>
            <thead><tr><th scope="col">Source</th><th scope="col">Started</th><th scope="col">Result</th><th scope="col" class="n">New</th><th scope="col" class="n">Revised</th><th scope="col" class="n">Refused</th></tr></thead>
            <tbody>@foreach($runs as $r)<tr><th scope="row">{{ $r->source->name }}</th><td>{{ $r->started_at->diffForHumans() }}</td><td>{{ $r->status }}{{ $r->error ? ': '.$r->error : '' }}</td><td class="n num">{{ $r->created }}</td><td class="n num">{{ $r->updated }}</td><td class="n num">{{ $r->rejected }}</td></tr>@endforeach</tbody></table></div>
    </section>

    <section class="wrap section" aria-labelledby="i-h">
        <div class="section-head"><h2 id="i-h">Open issues and platform checks</h2></div>
        <ul class="lines">@forelse($issues as $i)<li><span>{{ $i->summary }}</span><span class="when">{{ $i->severity }}</span></li>@empty<li><span>No open issues.</span></li>@endforelse</ul>
        <p class="prov">@foreach($checks as $c){{ $c->key }} {{ $c->status ?? 'no answer' }} · @endforeach</p>
    </section>
</x-me-layout>
