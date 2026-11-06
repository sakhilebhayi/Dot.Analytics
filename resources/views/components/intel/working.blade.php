@props(['figure'])
@php
    use App\Services\Intelligence\Reading\Format;
    $i = $figure->indicator; $s = $figure->source;
    $freq = ['A' => 'Yearly', 'Q' => 'Quarterly', 'M' => 'Monthly', 'D' => 'Daily'][$i->frequency] ?? $i->frequency;
    $rows = array_reverse($figure->series);
@endphp
<details class="working">
    <summary>Show working</summary>
    <div class="tape">
        <dl>
            <dt>Measures</dt><dd>{{ $i->definition }}</dd>
            <dt>Unit</dt><dd>{{ $i->unit }}</dd>
            <dt>Source</dt><dd><a href="{{ $s->homepage_url }}" rel="noopener">{{ $s->name }}</a>@if($i->source_code) <span class="num">({{ $i->source_code }})</span>@endif</dd>
            <dt>Reading for</dt><dd>{{ $figure->period() ?? 'none yet' }}</dd>
            <dt>Frequency</dt><dd>{{ $freq }}</dd>
            <dt>Collected</dt><dd>{{ $figure->collectedAt ? Format::age($figure->collectedAt) : 'not yet' }}</dd>
            <dt>Next check</dt><dd>{{ $s->next_due_at ? $s->next_due_at->isPast() ? 'due now' : 'in '.$s->next_due_at->diffForHumans(['parts' => 1, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) : 'when the source next publishes' }}</dd>
            <dt>Licence</dt><dd>{{ $i->licence ?: $s->licence }}</dd>
            @if($i->caveat)<dt>Limits</dt><dd>{{ $i->caveat }}</dd>@endif
            <dt>Label</dt><dd><x-intel.label kind="source"/></dd>
            @if($figure->revisions !== [])
                <dt>Revised</dt><dd>@foreach($figure->revisions as $r){{ \Carbon\Carbon::parse($r['period'])->format('Y') }}: {{ Format::number($r['previous'], $i->decimals) }} became {{ Format::number($r['now'], $i->decimals) }} on {{ $r['revised_at'] }}@if(!$loop->last)<br>@endif @endforeach</dd>
            @endif
        </dl>
        <p class="tape-h">Every value we hold</p>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Values for {{ $i->name }}, {{ $figure->geography->name }}">
            <table>
                <caption class="sr">{{ $i->name }}, {{ $figure->geography->name }}, {{ $i->unit }}</caption>
                <thead><tr><th scope="col">Period</th><th scope="col">{{ Format::note($i->unit) }}</th></tr></thead>
                <tbody>@foreach($rows as $r)<tr><td>{{ Format::period($r['period'], $i->frequency) }}</td><td class="num">{{ Format::number($r['value'], $i->decimals) }}</td></tr>@endforeach</tbody>
            </table>
        </div>
        <div class="tape-links">
            <a href="{{ route('intel.indicator', [$i, 'place' => $figure->geography->code]) }}">Open this figure</a>
            <a href="{{ route('intel.indicator.csv', [$i, 'place' => $figure->geography->code]) }}">Download the values (CSV)</a>
            <a href="{{ $s->terms_url ?: $s->homepage_url }}" rel="noopener">The source's terms</a>
        </div>
        @if($s->attribution)<p class="attr">{{ $s->attribution }}</p>@endif
    </div>
</details>
