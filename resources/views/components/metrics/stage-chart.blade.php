@props(['series'])
{{--
    The selected figure's chart on the landing. One series, so no legend; counts and money start at zero; a day with no
    snapshot is a gap, never a zero. Geometry is drawn here so the page needs no script to read it. The built dashboard
    script only adds a crosshair, a tooltip and arrow-key movement, and every value is also in the table under it.
--}}
@php
    use App\Support\MetricFormat;
    use Illuminate\Support\Carbon;

    $points = $series['points'];
    $n = count($points);
    $type = $series['type'];
    $currency = $series['currency'];
    $W = 560; $H = 210; $L = 46; $R = 24; $T = 22; $B = 28;
    $plotW = $W - $L - $R; $plotH = $H - $T - $B;
    $f = fn ($v) => number_format($v, 1, '.', '');
@endphp
@if($n === 0)
    <p class="cap" data-testid="trend">No stored points for {{ $series['label'] }} in this range.</p>
@else
    @php
        $dates = array_map(fn ($p) => Carbon::parse($p['date']), $points);
        $first = $dates[0];
        $span = max(1, $first->diffInDays($dates[$n - 1]));
        $values = array_column($points, 'value');
        $max = max($values); $min = min($values);
        if ($type === 'rating') {
            $lo = max(0, floor(($min - 0.2) * 5) / 5); $hi = min(5, ceil(($max + 0.2) * 5) / 5);
            if ($hi <= $lo) { $hi = $lo + 1; }
        } else {
            $lo = 0; $hi = $max > 0 ? $max * 1.1 : 1;
        }
        $x = fn ($i) => $L + $plotW * ($n === 1 ? 0.5 : $first->diffInDays($dates[$i]) / $span);
        $y = fn ($v) => $T + $plotH * (1 - ($v - $lo) / ($hi - $lo));
        $segments = []; $segment = [];
        foreach ($points as $i => $p) {
            if ($i > 0 && $dates[$i - 1]->diffInDays($dates[$i]) > 2) { $segments[] = $segment; $segment = []; }
            $segment[] = $f($x($i)).','.$f($y($p['value']));
        }
        $segments[] = $segment;
        $last = $points[$n - 1];
        $lx = $x($n - 1); $ly = $y($last['value']);
        $hits = array_map(fn ($p, $i) => ['x' => round($x($i), 1), 'y' => round($y($p['value']), 1), 't' => $dates[$i]->format('j M Y'), 'v' => MetricFormat::value($p['value'], $type, $currency)], $points, array_keys($points));
    @endphp
    <div class="plot" data-testid="trend" data-chart>
        <svg viewBox="0 0 {{ $W }} {{ $H }}" width="{{ $W }}" height="{{ $H }}" role="img"
             aria-label="{{ $series['label'] }}: {{ $n }} stored daily {{ \Illuminate\Support\Str::plural('point', $n) }} from {{ $points[0]['date'] }} to {{ $last['date'] }}, latest {{ MetricFormat::value($last['value'], $type, $currency) }}{{ $type !== 'rating' ? ', drawn from zero' : '' }}">
            @foreach([0, 0.5, 1] as $g)
                @php $gv = $lo + ($hi - $lo) * $g; @endphp
                <line class="grid" x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $f($y($gv)) }}" y2="{{ $f($y($gv)) }}" />
                <text class="tick" x="{{ $L - 6 }}" y="{{ $f($y($gv) + 4) }}" text-anchor="end">{{ MetricFormat::axis($gv, $type) }}</text>
            @endforeach
            <text class="tick" x="{{ $L }}" y="{{ $H - 8 }}">{{ $first->format('j M') }}</text>
            <text class="tick" x="{{ $W - $R }}" y="{{ $H - 8 }}" text-anchor="end">{{ $dates[$n - 1]->format('j M') }}</text>
            @foreach($segments as $segment)
                @if(count($segment) > 1)
                    <polyline class="line" pathLength="1" points="{{ implode(' ', $segment) }}" />
                @endif
            @endforeach
            @foreach($points as $i => $p)
                @if(isset($series['anomalies'][$p['date']]))
                    <circle class="ring" data-anomaly="{{ $p['date'] }}" cx="{{ $f($x($i)) }}" cy="{{ $f($y($p['value'])) }}" r="8"><title>Anomaly alert raised for {{ $dates[$i]->format('j M Y') }}</title></circle>
                @endif
            @endforeach
            <line class="cross" x1="0" x2="0" y1="{{ $T }}" y2="{{ $T + $plotH }}" visibility="hidden" />
            <circle class="pt" cx="{{ $f($lx) }}" cy="{{ $f($ly) }}" r="4.5" />
            <text class="lab" x="{{ $f($lx) }}" y="{{ $f(max(12, $ly - 14)) }}" text-anchor="end">{{ MetricFormat::value($last['value'], $type, $currency) }}</text>
            <rect class="hit" x="{{ $L }}" y="{{ $T }}" width="{{ $plotW }}" height="{{ $plotH }}" fill="transparent" tabindex="0" aria-label="Chart values: arrow keys move between days" data-points="{{ json_encode($hits) }}" />
        </svg>
        <div class="tip" hidden></div>
    </div>
    <p class="cap">
        {{ $n }} stored daily {{ \Illuminate\Support\Str::plural('point', $n) }}{{ $currency ? ', in '.$currency : '' }}{{ $type !== 'rating' ? ', counted from zero' : '' }}.
        A day with no report is a gap in the line, not a zero.@if(count($series['anomalies']) > 0) A red ring marks a day an anomaly alert was raised for.@endif
    </p>
@endif
