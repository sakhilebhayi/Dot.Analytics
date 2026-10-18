@props(['series'])
@php
    $points = $series['points'];
    $n = count($points);
    $type = $series['type'];
    $currency = $series['currency'];
    $W = 720; $H = 240; $L = 58; $R = 18; $T = 16; $B = 30;
    $plotW = $W - $L - $R; $plotH = $H - $T - $B;

    $chart = null;
    if ($n > 0) {
        $dates = array_map(fn ($p) => \Illuminate\Support\Carbon::parse($p['date']), $points);
        $first = $dates[0];
        $span = max(1, $first->diffInDays($dates[$n - 1]));
        $values = array_column($points, 'value');
        $max = max($values);
        $min = min($values);
        // Counts, money and quantities start at zero so the height of the line is honest.
        // A rating is bounded 0 to 5 and is drawn over its own range.
        if ($type === 'rating') {
            $lo = max(0, floor(($min - 0.2) * 5) / 5); $hi = min(5, ceil(($max + 0.2) * 5) / 5);
            if ($hi <= $lo) { $hi = $lo + 1; }
        } else {
            $lo = 0; $hi = $max > 0 ? $max * 1.1 : 1;
        }
        $x = fn ($i) => $L + $plotW * ($n === 1 ? 0.5 : $first->diffInDays($dates[$i]) / $span);
        $y = fn ($v) => $T + $plotH * (1 - ($v - $lo) / ($hi - $lo));
        $f = fn ($v) => number_format($v, 1, '.', '');

        $segments = []; $segment = [];
        foreach ($points as $i => $p) {
            if ($i > 0 && $dates[$i - 1]->diffInDays($dates[$i]) > 2) { $segments[] = $segment; $segment = []; }
            $segment[] = $f($x($i)).','.$f($y($p['value']));
        }
        $segments[] = $segment;
        $chart = compact('segments', 'lo', 'hi', 'first');
    }
@endphp
<div data-testid="trend">
    @if($n === 0)
        <p style="font-size:0.85rem;color:var(--mist);margin:0.5rem 0;">No stored points for {{ $series['label'] }} in this range.</p>
    @else
        <svg data-testid="trend-chart" viewBox="0 0 {{ $W }} {{ $H }}" style="width:100%;height:auto;max-height:260px;display:block;" role="img"
             aria-label="{{ $series['label'] }}: {{ $n }} stored daily {{ \Illuminate\Support\Str::plural('point', $n) }} from {{ $points[0]['date'] }} to {{ $points[$n - 1]['date'] }}, latest {{ \App\Support\MetricFormat::value($points[$n - 1]['value'], $type, $currency) }}">
            @foreach([0, 0.5, 1] as $g)
                @php $gv = $chart['lo'] + ($chart['hi'] - $chart['lo']) * $g; @endphp
                <line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $f($y($gv)) }}" y2="{{ $f($y($gv)) }}" stroke="var(--line)" stroke-width="1" />
                <text x="{{ $L - 8 }}" y="{{ $f($y($gv) + 3.5) }}" text-anchor="end" fill="var(--mist)" font-size="10" font-family="var(--font-mono)">{{ \App\Support\MetricFormat::axis($gv, $type) }}</text>
            @endforeach
            <text x="{{ $L }}" y="{{ $H - 8 }}" fill="var(--mist)" font-size="10" font-family="var(--font-mono)">{{ $points[0]['date'] }}</text>
            <text x="{{ $W - $R }}" y="{{ $H - 8 }}" text-anchor="end" fill="var(--mist)" font-size="10" font-family="var(--font-mono)">{{ $points[$n - 1]['date'] }}</text>

            @foreach($chart['segments'] as $segment)
                @if(count($segment) > 1)
                    <polyline points="{{ implode(' ', $segment) }}" fill="none" stroke="var(--teal-soft)" stroke-width="1.75" stroke-linejoin="round" stroke-linecap="round" />
                @endif
            @endforeach

            @foreach($points as $i => $p)
                @php $isAnomaly = isset($series['anomalies'][$p['date']]); @endphp
                <circle cx="{{ $f($x($i)) }}" cy="{{ $f($y($p['value'])) }}" r="2.75" fill="{{ $p['is_demo'] ? 'var(--gold-soft)' : 'var(--teal-soft)' }}">
                    <title>{{ $p['date'] }}: {{ \App\Support\MetricFormat::value($p['value'], $type, $currency) }}{{ $p['is_demo'] ? ' (demo data)' : '' }}</title>
                </circle>
                @if($isAnomaly)
                    <circle data-anomaly="{{ $p['date'] }}" cx="{{ $f($x($i)) }}" cy="{{ $f($y($p['value'])) }}" r="7" fill="none" stroke="var(--danger)" stroke-width="1.5">
                        <title>Anomaly alert raised for {{ $p['date'] }}</title>
                    </circle>
                @endif
            @endforeach
        </svg>

        <p style="font-size:0.7rem;color:var(--mist);margin:0.5rem 0 0;">
            {{ $n }} stored {{ \Illuminate\Support\Str::plural('point', $n) }}@if($currency) &middot; {{ $currency }}@endif.
            A day with no snapshot is a gap in the line, not a zero.
            @if(count($series['anomalies']) > 0) A red ring marks a day an anomaly alert was raised for. @endif
        </p>

        <details data-testid="trend-values" style="margin-top:0.6rem;">
            <summary class="font-mono" style="font-size:0.62rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--mist);cursor:pointer;">Values as a table</summary>
            <table style="margin-top:0.5rem;border-collapse:collapse;font-size:0.75rem;">
                <thead><tr><th class="font-mono" style="text-align:left;font-weight:500;color:var(--mist);padding:0.2rem 1.5rem 0.2rem 0;">Date</th><th class="font-mono" style="text-align:right;font-weight:500;color:var(--mist);padding:0.2rem 0;">{{ $series['label'] }}</th></tr></thead>
                <tbody>
                    @foreach(array_reverse($points) as $p)
                        <tr><td class="font-mono" style="color:var(--paper);padding:0.15rem 1.5rem 0.15rem 0;">{{ $p['date'] }}</td><td class="font-mono" style="color:var(--paper);text-align:right;padding:0.15rem 0;font-variant-numeric:tabular-nums;">{{ \App\Support\MetricFormat::value($p['value'], $type, $currency) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>
