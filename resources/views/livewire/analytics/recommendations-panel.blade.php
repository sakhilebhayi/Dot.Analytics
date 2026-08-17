<div style="padding:2rem 2.25rem;">
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--gold-soft);margin:0 0 0.35rem;">Recommendations</p>
            <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">AI Recommendations</h3>
        </div>
        <button
            wire:click="generate"
            class="press"
            style="font-size:0.72rem;padding:0.4rem 0.85rem;background:var(--gold);color:var(--ink);border-radius:0.4rem;font-weight:600;"
            wire:loading.attr="disabled"
            wire:target="generate"
        >
            <span wire:loading.remove wire:target="generate">Generate</span>
            <span wire:loading wire:target="generate">Analysing&hellip;</span>
        </button>
    </div>

    @if($this->recommendations->isEmpty())
        <p style="font-size:0.85rem;color:var(--mist);padding:1rem 0;text-align:center;">No pending recommendations. Click Generate to run the intelligence engines.</p>
    @else
        <ul class="space-y-3">
            @foreach($this->recommendations as $rec)
                @php
                    $priTone = match($rec->priority) {
                        'critical' => 'var(--danger-soft)',
                        'high'     => 'var(--gold-soft)',
                        default    => 'var(--mist)',
                    };
                @endphp
                <li class="rounded-lg p-3" style="border:1px solid var(--line);">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-mono" style="font-size:0.62rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;padding:0.15rem 0.5rem;border-radius:9999px;color:{{ $priTone }};border:1px solid {{ $priTone }};">
                                    {{ ucfirst($rec->priority) }}
                                </span>
                                <span style="font-size:0.68rem;color:var(--mist);">{{ ucfirst(str_replace('_', ' ', $rec->engine)) }} Engine</span>
                            </div>
                            <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $rec->title }}</p>
                            <p style="font-size:0.75rem;color:var(--mist);margin:0.2rem 0 0;">{{ $rec->rationale }}</p>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <button wire:click="action({{ $rec->id }})" class="press" style="font-size:0.68rem;padding:0.25rem 0.6rem;border:1px solid var(--teal-soft);border-radius:0.3rem;color:var(--teal-soft);background:transparent;">Act</button>
                            <button wire:click="dismiss({{ $rec->id }})" class="press" style="font-size:0.68rem;padding:0.25rem 0.6rem;border:1px solid var(--line);border-radius:0.3rem;color:var(--mist);background:transparent;">Dismiss</button>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
