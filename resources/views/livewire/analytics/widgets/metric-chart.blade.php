@php
    $definition = \App\Models\MetricDefinition::find($widget->config['metric_definition_id'] ?? null);
    $points = $definition
        ? \App\Models\ComputedMetric::where('metric_definition_id', $definition->id)
            ->orderBy('period_date', 'desc')
            ->limit(12)
            ->get()
            ->sortBy('period_date')
            ->values()
        : collect();
@endphp

@if(! $definition)
    <p style="font-size:0.8rem;color:var(--mist);">Metric no longer exists.</p>
@elseif($points->count() < 2)
    <p style="font-size:0.8rem;color:var(--mist);">No computed history yet for {{ $definition->label }}.</p>
@else
    @php
        $chartW = 320;
        $chartH = 90;
        $values = $points->map(fn ($p) => (float) $p->value);
        $min = $values->min();
        $max = $values->max();
        $range = ($max - $min) > 0 ? ($max - $min) : 1;
        $step = $chartW / ($points->count() - 1);
        $coords = $points->values()->map(function ($point, $i) use ($step, $chartH, $min, $range) {
            $x = round($i * $step, 2);
            $y = round($chartH - ((((float) $point->value) - $min) / $range) * $chartH, 2);
            return "{$x},{$y}";
        })->implode(' ');
    @endphp
    <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin:0 0 0.5rem;">{{ $definition->label }}</p>
    <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" style="width:100%;height:auto;overflow:visible;" xmlns="http://www.w3.org/2000/svg">
        <polyline points="{{ $coords }}" fill="none" stroke="var(--gold)" stroke-width="2" />
        @foreach($points as $i => $point)
            @php
                $x = round($i * $step, 2);
                $y = round($chartH - ((((float) $point->value) - $min) / $range) * $chartH, 2);
            @endphp
            <circle cx="{{ $x }}" cy="{{ $y }}" r="2.5" fill="var(--teal-soft)" />
        @endforeach
    </svg>
    <p style="font-size:0.68rem;color:var(--mist);opacity:0.7;margin:0.4rem 0 0;">{{ $points->first()->period_date->format('d M') }} &ndash; {{ $points->last()->period_date->format('d M Y') }}</p>
@endif
