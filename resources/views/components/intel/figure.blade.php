@props(['figure', 'chart' => false, 'working' => true, 'place' => true, 'level' => 3])
@php
    $f = $figure; $i = $f->indicator; $change = $f->change();
    $arrow = ['up' => 'M6 1 L11 8 H1 Z', 'down' => 'M6 11 L1 4 H11 Z', 'flat' => 'M1 6 H11'];
@endphp
<article class="plate">
    <div class="figure-head">
        <h{{ $level }} class="figure-name"><a href="{{ route('intel.indicator', [$i, 'place' => $f->geography->code]) }}">{{ $i->name }}</a></h{{ $level }}>
        @if($place)<p class="figure-place">{{ $f->geography->name }}</p>@endif
    </div>
    <p class="figure-value"><span class="big num">{{ $f->value() }}</span> <span class="note">{{ $f->note() }}</span></p>
    @if($change)
        <p class="figure-change tone-{{ $change['tone'] }}">
            <svg viewBox="0 0 12 12" aria-hidden="true">@if($change['direction'] === 'flat')<path d="{{ $arrow['flat'] }}" stroke="currentColor" stroke-width="2" fill="none"/>@else<path d="{{ $arrow[$change['direction']] }}" fill="currentColor"/>@endif</svg>
            {{ $change['words'] }}
        </p>
    @endif
    @if($chart)
        <x-intel.chart :figure="$f"/>
    @else
        @php $pts = array_slice($f->series, -24); @endphp
        @if(count($pts) > 2 && max(array_column($pts, 'value')) !== min(array_column($pts, 'value')))
            @php
                $vals = array_column($pts, 'value'); $lo = min($vals); $hi = max($vals); $span = max($hi - $lo, 1e-9);
                $d = collect($pts)->map(fn ($p, $k) => ($k ? 'L' : 'M').round(2 + 296 * $k / (count($pts) - 1), 1).' '.round(40 - 36 * ($p['value'] - $lo) / $span, 1))->implode(' ');
            @endphp
            <svg class="spark" viewBox="0 0 300 44" preserveAspectRatio="none" role="img" aria-label="{{ $i->name }}, {{ $f->geography->name }}: the last {{ count($pts) }} readings"><path d="{{ $d }}"/></svg>
        @endif
    @endif
    <p class="prov"><x-intel.status :freshness="$f->freshness()"/> <span>{{ $f->source->name }} · {{ $f->period() }}@if($f->collectedAt) · checked {{ \App\Services\Intelligence\Reading\Format::age($f->collectedAt) }}@endif</span></p>
    @if($working)<x-intel.working :figure="$f"/>@endif
</article>
