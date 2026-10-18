@props(['points', 'label' => 'metric', 'width' => 112, 'height' => 28, 'stroke' => 'var(--teal-soft)'])
@php
    // Drawn from the stored daily points only. X is the calendar day, so a day
    // with no snapshot is a gap in the line, never a flat run or a zero.
    $n = count($points);
    $svg = ['segments' => [], 'last' => null];
    if ($n > 0) {
        $first = \Illuminate\Support\Carbon::parse($points[0]['date']);
        $span = max(1, $first->diffInDays(\Illuminate\Support\Carbon::parse($points[$n - 1]['date'])));
        $values = array_column($points, 'value');
        $min = min($values);
        $max = max($values);
        $pad = 3;
        $x = fn ($p) => $pad + ($width - 2 * $pad) * ($n === 1 ? 0.5 : $first->diffInDays(\Illuminate\Support\Carbon::parse($p['date'])) / $span);
        $y = fn ($p) => $max === $min ? $height / 2 : $pad + ($height - 2 * $pad) * (1 - ($p['value'] - $min) / ($max - $min));
        $segment = [];
        $prev = null;
        foreach ($points as $p) {
            $date = \Illuminate\Support\Carbon::parse($p['date']);
            if ($prev !== null && $prev->diffInDays($date) > 2) {
                $svg['segments'][] = $segment;
                $segment = [];
            }
            $segment[] = number_format($x($p), 1, '.', '').','.number_format($y($p), 1, '.', '');
            $prev = $date;
        }
        $svg['segments'][] = $segment;
        $lastPoint = $points[$n - 1];
        $svg['last'] = [number_format($x($lastPoint), 1, '.', ''), number_format($y($lastPoint), 1, '.', '')];
    }
@endphp
<svg data-testid="sparkline" viewBox="0 0 {{ $width }} {{ $height }}" width="{{ $width }}" height="{{ $height }}" role="img"
     aria-label="{{ $label }}: {{ $n }} stored {{ \Illuminate\Support\Str::plural('point', $n) }}, from {{ $points[0]['date'] ?? '' }} to {{ $points[$n - 1]['date'] ?? '' }}"
     style="display:block;overflow:visible;">
    @foreach($svg['segments'] as $segment)
        @if(count($segment) > 1)
            <polyline points="{{ implode(' ', $segment) }}" fill="none" stroke="{{ $stroke }}" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
        @endif
    @endforeach
    @if($svg['last'])
        <circle cx="{{ $svg['last'][0] }}" cy="{{ $svg['last'][1] }}" r="2.25" fill="{{ $stroke }}" />
    @endif
</svg>
