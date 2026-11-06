@props(['figure'])
@php
    use App\Services\Intelligence\Reading\Chart;
    $series = $figure->series;
    $c = $series === [] ? null : Chart::layout($figure, $series);
    $name = $figure->indicator->name.', '.$figure->geography->name;
@endphp
@if($c && count($series) > 1)
    <figure class="chart">
        <svg viewBox="0 0 {{ Chart::W }} {{ Chart::H }}" role="img" tabindex="0" aria-describedby="read-{{ $figure->indicator->id }}-{{ $figure->geography->id }}" aria-label="{{ $name }}: {{ count($series) }} readings from {{ $c['points'][0]['label'] }} to {{ $c['points'][count($series) - 1]['label'] }}. The newest is {{ $c['points'][count($series) - 1]['readout'] }}. Use the left and right arrow keys to read each point; every value is also in the table under Show working.">
            @foreach($c['ticks'] as $t)
                <line class="grid" x1="8" x2="{{ Chart::W - 8 }}" y1="{{ $t['y'] }}" y2="{{ $t['y'] }}"/>
                <text class="axis" x="{{ Chart::W - 8 }}" y="{{ $t['y'] - 4 }}" text-anchor="end">{{ $t['label'] }}</text>
            @endforeach
            @if($c['zero'] !== null)<line class="zero" x1="8" x2="{{ Chart::W - 8 }}" y1="{{ $c['zero'] }}" y2="{{ $c['zero'] }}"/>@endif
            <path class="line" d="{{ $c['path'] }}"/>
            @foreach($c['points'] as $p)
                <g>
                    <rect class="hit" x="{{ $p['x'] - $p['w'] / 2 }}" y="0" width="{{ $p['w'] }}" height="{{ Chart::H - 24 }}" data-readout="{{ $p['readout'] }}"/>
                    <circle class="dot" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4.5"/>
                </g>
            @endforeach
            @foreach($c['xlabels'] as $l)<text class="axis" x="{{ $l['x'] }}" y="{{ Chart::H - 6 }}" text-anchor="{{ $l['anchor'] }}">{{ $l['label'] }}</text>@endforeach
        </svg>
        <p class="chart-read" id="read-{{ $figure->indicator->id }}-{{ $figure->geography->id }}" aria-live="polite">{{ $c['points'][count($series) - 1]['readout'] }}</p>
        <figcaption class="chart-cap">{{ $figure->indicator->name }}, {{ $figure->geography->name }}. Each point is one published reading. Gaps are left as gaps.</figcaption>
    </figure>
@endif
