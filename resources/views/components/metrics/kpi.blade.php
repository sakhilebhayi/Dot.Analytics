@props(['tile', 'selectable' => false, 'selected' => false, 'bare' => false])
@php
    $state = $tile['state'];
    $tones = ['good' => 'var(--teal-soft)', 'bad' => 'var(--danger-soft)', 'neutral' => 'var(--mist)'];
    $toneColor = $tones[$tile['tone']] ?? $tones['neutral'];
    $arrow = ['up' => '▲', 'down' => '▼', 'flat' => '■'][$tile['direction']] ?? '';
    $muted = $state !== 'ok';
@endphp
<article data-key="{{ $tile['key'] }}" data-state="{{ $state }}" data-tone="{{ $tile['tone'] }}"
         style="position:relative;background:var(--ink-soft);padding:1rem 1.1rem 0.9rem;display:flex;flex-direction:column;gap:0.35rem;min-height:132px;{{ $selected ? 'box-shadow:inset 0 0 0 1px var(--gold);' : ($bare ? '' : 'box-shadow:inset -1px -1px 0 var(--line);') }}">
    @if($selectable)
        <button type="button" wire:click="select('{{ $tile['key'] }}')" aria-pressed="{{ $selected ? 'true' : 'false' }}" aria-label="Show the trend for {{ $tile['label'] }}"
                style="position:absolute;inset:0;background:transparent;border:0;cursor:pointer;z-index:1;"></button>
    @endif

    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem;">
        <span data-testid="kpi-label" class="font-mono" style="font-size:0.62rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--mist);line-height:1.35;">{{ $tile['label'] }}</span>
        <span style="display:flex;gap:0.35rem;flex-shrink:0;">
            @if($state === 'stale')
                <span data-testid="kpi-stale" class="font-mono" style="font-size:0.56rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--gold);border:1px solid var(--gold);padding:0.05rem 0.35rem;border-radius:2px;">Stale</span>
            @endif
            @if($tile['is_demo'])
                <span data-testid="kpi-demo" class="font-mono" style="font-size:0.56rem;letter-spacing:0.1em;color:var(--gold-soft);border:1px dashed var(--gold-soft);padding:0.05rem 0.35rem;border-radius:2px;">DEMO DATA</span>
            @endif
        </span>
    </div>

    @if($state === 'no_data')
        <span data-testid="kpi-value" style="font-size:0.85rem;color:var(--mist);margin-top:0.3rem;font-style:italic;">no data yet</span>
    @else
        <span data-testid="kpi-value" class="font-mono" style="font-size:1.55rem;font-weight:500;line-height:1.1;letter-spacing:-0.01em;font-variant-numeric:tabular-nums;color:{{ $muted ? 'var(--mist)' : 'var(--paper)' }};">{{ $tile['value'] }}</span>

        <div style="display:flex;align-items:baseline;gap:0.5rem;min-height:1.1rem;">
            @if($tile['delta'] !== null)
                <span class="font-mono" style="font-size:0.72rem;color:{{ $toneColor }};font-variant-numeric:tabular-nums;">
                    <span aria-hidden="true" style="font-size:0.55rem;">{{ $arrow }}</span>
                    <span data-testid="kpi-delta">{{ $tile['delta'] }}</span>
                    @if($tile['delta_pct'] !== null)
                        <span data-testid="kpi-delta-pct" style="opacity:0.85;">{{ $tile['delta_pct'] }}</span>
                    @endif
                </span>
                <span style="font-size:0.66rem;color:var(--mist);">vs previous report</span>
            @else
                <span style="font-size:0.68rem;color:var(--mist);">first reported point</span>
            @endif
        </div>

        <div style="margin-top:auto;display:flex;align-items:flex-end;justify-content:space-between;gap:0.5rem;padding-top:0.4rem;">
            <span data-testid="kpi-asof" class="font-mono" style="font-size:0.6rem;color:var(--mist);">as of {{ $tile['as_of'] }}</span>
            @if(count($tile['points']) > 0)
                <span style="opacity:{{ $muted ? '0.5' : '1' }};"><x-metrics.sparkline :points="$tile['points']" :label="$tile['label']" stroke="{{ $muted ? 'var(--mist)' : 'var(--teal-soft)' }}" /></span>
            @endif
        </div>
    @endif
</article>
