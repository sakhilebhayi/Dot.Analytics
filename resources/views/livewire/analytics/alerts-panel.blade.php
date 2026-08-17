<div style="padding:2rem 2.25rem;">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <div>
            <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--danger-soft);margin:0 0 0.35rem;">Alerts</p>
            <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">Intelligence Alerts</h3>
        </div>
        <div class="flex gap-2">
            <select wire:model.live="filterSeverity" class="text-xs px-2 py-1 rounded" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--mist);">
                <option value="">All severities</option>
                <option value="critical">Critical</option>
                <option value="warning">Warning</option>
                <option value="info">Info</option>
            </select>
            <select wire:model.live="filterStatus" class="text-xs px-2 py-1 rounded" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--mist);">
                <option value="open">Open</option>
                <option value="acknowledged">Acknowledged</option>
                <option value="resolved">Resolved</option>
                <option value="">All</option>
            </select>
        </div>
    </div>

    @if($this->alerts->isEmpty())
        <p style="font-size:0.85rem;color:var(--mist);padding:1rem 0;text-align:center;">No alerts for the selected filters.</p>
    @else
        <ul class="space-y-3">
            @foreach($this->alerts as $alert)
                @php
                    $tone = match($alert->severity) {
                        'critical' => ['border' => 'var(--danger)', 'bg' => 'rgba(240,138,108,0.08)'],
                        'warning'  => ['border' => 'var(--gold)', 'bg' => 'rgba(241,198,46,0.06)'],
                        default    => ['border' => 'var(--line)', 'bg' => 'transparent'],
                    };
                @endphp
                <li class="rounded-lg p-3" style="border:1px solid {{ $tone['border'] }};background:{{ $tone['bg'] }};">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $alert->title }}</p>
                            <p style="font-size:0.75rem;color:var(--mist);margin:0.2rem 0 0;">{{ $alert->description }}</p>
                            <p style="font-size:0.68rem;color:var(--mist);opacity:0.7;margin:0.25rem 0 0;">{{ $alert->triggered_at->diffForHumans() }}</p>
                        </div>
                        @if($alert->isOpen())
                            <div class="flex gap-1 shrink-0">
                                <button wire:click="acknowledge({{ $alert->id }})" class="press" style="font-size:0.68rem;padding:0.25rem 0.6rem;border:1px solid var(--line);border-radius:0.3rem;color:var(--mist);background:transparent;">Ack</button>
                                <button wire:click="resolve({{ $alert->id }})" class="press" style="font-size:0.68rem;padding:0.25rem 0.6rem;border:1px solid var(--teal-soft);border-radius:0.3rem;color:var(--teal-soft);background:transparent;">Resolve</button>
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
