<div style="padding:2rem 2.25rem;">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <div>
            <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.35rem;">Cross-Platform Intelligence</p>
            <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">Insights No Single Tool Can See</h3>
        </div>
        <div class="flex items-center gap-2">
            <select wire:model.live="filterType" class="text-xs px-2 py-1 rounded" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--mist);">
                <option value="">All types</option>
                <option value="causation">Causation</option>
                <option value="correlation">Correlation</option>
                <option value="prediction">Prediction</option>
                <option value="risk">Risk</option>
                <option value="opportunity">Opportunity</option>
            </select>
            <select wire:model.live="filterSeverity" class="text-xs px-2 py-1 rounded" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--mist);">
                <option value="">All severities</option>
                <option value="critical">Critical</option>
                <option value="warning">Warning</option>
                <option value="info">Info</option>
            </select>
            <button
                wire:click="runEngines"
                wire:loading.attr="disabled"
                wire:target="runEngines"
                class="press"
                style="font-size:0.72rem;padding:0.4rem 0.85rem;background:var(--gold);color:var(--ink);border-radius:0.4rem;font-weight:600;"
            >
                <span wire:loading.remove wire:target="runEngines">Run Engines</span>
                <span wire:loading wire:target="runEngines">Running&hellip;</span>
            </button>
        </div>
    </div>

    @if($this->insights->isEmpty())
        <div class="text-center py-8">
            <p style="font-size:0.85rem;color:var(--mist);">No cross-platform insights yet.</p>
            <p style="font-size:0.75rem;color:var(--mist);margin-top:0.25rem;">Connect at least 2 platforms and click <strong style="color:var(--paper);">Run Engines</strong>.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($this->insights as $insight)
                @php
                    $tone = match($insight->severity) {
                        'critical' => ['border' => 'var(--danger)', 'bg' => 'rgba(240,138,108,0.08)'],
                        'warning'  => ['border' => 'var(--gold)', 'bg' => 'rgba(241,198,46,0.06)'],
                        default    => ['border' => 'var(--line)', 'bg' => 'transparent'],
                    };
                    $typeTone = match($insight->insight_type) {
                        'causation'   => 'var(--danger-soft)',
                        'risk'        => 'var(--danger-soft)',
                        'opportunity' => 'var(--teal-soft)',
                        'correlation' => 'var(--gold-soft)',
                        'prediction'  => 'var(--gold-soft)',
                        default       => 'var(--mist)',
                    };
                @endphp
                <div class="rounded-xl p-4" style="border:1px solid {{ $tone['border'] }};background:{{ $tone['bg'] }};">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                <span class="font-mono" style="font-size:0.65rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;padding:0.15rem 0.5rem;border-radius:9999px;color:{{ $typeTone }};border:1px solid {{ $typeTone }};">
                                    {{ ucfirst($insight->insight_type) }}
                                </span>
                                @foreach($insight->platforms_involved as $p)
                                    <span style="font-size:0.68rem;padding:0.1rem 0.45rem;border:1px solid var(--line);border-radius:0.3rem;color:var(--mist);">
                                        {{ \App\Services\IntelligenceEngineService::PLATFORMS[$p]['label'] ?? $p }}
                                    </span>
                                @endforeach
                                <span style="font-size:0.68rem;color:var(--mist);margin-left:auto;">{{ round($insight->confidence * 100) }}% confidence</span>
                            </div>
                            <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $insight->title }}</p>
                            <p style="font-size:0.75rem;color:var(--mist);margin:0.25rem 0 0;line-height:1.5;">{{ $insight->narrative }}</p>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            @if($insight->isNew())
                                <button wire:click="review({{ $insight->id }})" class="press" style="font-size:0.68rem;padding:0.25rem 0.6rem;border:1px solid var(--line);border-radius:0.3rem;color:var(--paper);background:transparent;">Review</button>
                            @endif
                            <button wire:click="dismiss({{ $insight->id }})" class="press" style="font-size:0.68rem;padding:0.25rem 0.6rem;border:1px solid var(--line);border-radius:0.3rem;color:var(--mist);background:transparent;">Dismiss</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
