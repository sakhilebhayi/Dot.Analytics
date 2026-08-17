@php
    $definition = \App\Models\MetricDefinition::find($widget->config['metric_definition_id'] ?? null);
    $latest = $definition
        ? \App\Models\ComputedMetric::where('metric_definition_id', $definition->id)
            ->latest('period_date')
            ->first()
        : null;
@endphp

@if(! $definition)
    <p style="font-size:0.8rem;color:var(--mist);">Metric no longer exists.</p>
@elseif(! $latest)
    <p style="font-size:0.8rem;color:var(--mist);">No computed value yet for {{ $definition->label }}.</p>
@else
    <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin:0 0 0.4rem;">{{ $definition->label }}</p>
    <p class="font-display" style="font-size:1.8rem;font-weight:700;color:var(--paper);margin:0;">
        {{ number_format((float) $latest->value, 2) }}
        <span style="font-size:0.9rem;font-weight:500;color:var(--mist);">{{ $definition->unit }}</span>
    </p>
    <p style="font-size:0.68rem;color:var(--mist);opacity:0.7;margin:0.3rem 0 0;">as of {{ $latest->period_date->format('d M Y') }}</p>
@endif
