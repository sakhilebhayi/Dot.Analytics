<div style="padding:2rem 2.25rem;">
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--gold-soft);margin:0 0 0.35rem;">Business DNA</p>
            <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">Operational Fingerprint</h3>
            <p style="font-size:0.75rem;color:var(--mist);margin:0.3rem 0 0;">
                Your organisation's evolving operational fingerprint
                @if($this->profile?->last_computed_at)
                    &middot; Updated {{ $this->profile->last_computed_at->diffForHumans() }}
                @endif
            </p>
        </div>
        <button
            wire:click="compute"
            wire:loading.attr="disabled"
            wire:target="compute"
            class="press"
            style="font-size:0.72rem;padding:0.4rem 0.85rem;background:transparent;border:1px solid var(--line);color:var(--paper);border-radius:0.4rem;"
        >
            <span wire:loading.remove wire:target="compute">Recompute DNA</span>
            <span wire:loading wire:target="compute">Computing&hellip;</span>
        </button>
    </div>

    @if(! $this->profile)
        <div class="text-center py-8">
            <p style="font-size:0.85rem;color:var(--mist);">No DNA profile computed yet.</p>
            <p style="font-size:0.75rem;color:var(--mist);margin-top:0.25rem;">Connect at least one platform and click <strong style="color:var(--paper);">Recompute DNA</strong>.</p>
        </div>
    @else
        @php $profile = $this->profile; @endphp

        {{-- Confidence meter --}}
        <div class="mb-5">
            <div class="flex items-center justify-between mb-1">
                <span style="font-size:0.72rem;color:var(--mist);">Profile confidence</span>
                <span style="font-size:0.72rem;font-weight:600;color:{{ $profile->confidence_score >= 0.7 ? 'var(--teal-soft)' : ($profile->confidence_score >= 0.4 ? 'var(--gold-soft)' : 'var(--mist)') }};">
                    {{ round($profile->confidence_score * 100) }}%
                </span>
            </div>
            <div class="w-full rounded-full" style="height:0.35rem;background:rgba(255,255,255,0.06);">
                <div
                    style="height:0.35rem;border-radius:9999px;width:{{ round($profile->confidence_score * 100) }}%;background:{{ $profile->confidence_score >= 0.7 ? 'var(--teal-soft)' : ($profile->confidence_score >= 0.4 ? 'var(--gold)' : 'var(--mist)') }};"
                ></div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            {{-- Operational patterns --}}
            @if($profile->operational_patterns)
                <div class="rounded-lg p-3" style="border:1px solid var(--line);">
                    <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.4rem;">Operational Patterns</p>
                    <p style="font-size:0.75rem;color:var(--paper);">{{ $profile->operational_patterns['summary'] ?? '—' }}</p>
                    @if(!empty($profile->operational_patterns['key_patterns']))
                        <ul class="mt-2 space-y-0.5">
                            @foreach(array_slice($profile->operational_patterns['key_patterns'], 0, 3) as $p)
                                <li style="font-size:0.72rem;color:var(--mist);display:flex;gap:0.4rem;"><span style="color:var(--line);">&bull;</span>{{ $p }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            {{-- Risk profile --}}
            @if($profile->risk_tolerance)
                @php
                    $level      = $profile->risk_tolerance['level'] ?? 'medium';
                    $riskTone   = match($level) { 'high' => 'var(--danger-soft)', 'low' => 'var(--teal-soft)', default => 'var(--gold-soft)' };
                @endphp
                <div class="rounded-lg p-3" style="border:1px solid var(--line);">
                    <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.4rem;">Risk Profile</p>
                    <div class="flex items-center gap-2 mb-1">
                        <span style="font-size:0.7rem;padding:0.15rem 0.5rem;border-radius:9999px;font-weight:600;color:{{ $riskTone }};border:1px solid {{ $riskTone }};">{{ ucfirst($level) }} risk tolerance</span>
                    </div>
                    <p style="font-size:0.72rem;color:var(--mist);">{{ $profile->risk_tolerance['notes'] ?? '' }}</p>
                </div>
            @endif

            {{-- Growth signals --}}
            @if($profile->growth_signals)
                <div class="rounded-lg p-3" style="border:1px solid var(--line);">
                    <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.4rem;">Growth Signals</p>
                    <p style="font-size:0.75rem;color:var(--paper);">{{ $profile->growth_signals['summary'] ?? '—' }}</p>
                    @if(!empty($profile->growth_signals['signals']))
                        <ul class="mt-2 space-y-0.5">
                            @foreach(array_slice($profile->growth_signals['signals'], 0, 3) as $s)
                                <li style="font-size:0.72rem;color:var(--teal-soft);display:flex;gap:0.4rem;"><span style="opacity:0.6;">&uarr;</span>{{ $s }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            {{-- Bottlenecks --}}
            @if($profile->bottlenecks)
                <div class="rounded-lg p-3" style="border:1px solid var(--line);">
                    <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.4rem;">Operational Bottlenecks</p>
                    <p style="font-size:0.75rem;color:var(--paper);">{{ $profile->bottlenecks['summary'] ?? '—' }}</p>
                    @if(!empty($profile->bottlenecks['areas']))
                        <ul class="mt-2 space-y-0.5">
                            @foreach(array_slice($profile->bottlenecks['areas'], 0, 3) as $b)
                                <li style="font-size:0.72rem;color:var(--gold-soft);display:flex;gap:0.4rem;"><span style="opacity:0.6;">!</span>{{ $b }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            {{-- Customer behaviour --}}
            @if($profile->customer_behavior)
                <div class="rounded-lg p-3" style="border:1px solid var(--line);">
                    <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.4rem;">Customer Behaviour</p>
                    <p style="font-size:0.75rem;color:var(--paper);">{{ $profile->customer_behavior['summary'] ?? '—' }}</p>
                </div>
            @endif

            {{-- Decision patterns --}}
            @if($profile->decision_patterns)
                <div class="rounded-lg p-3" style="border:1px solid var(--line);">
                    <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.4rem;">Decision Patterns</p>
                    <p style="font-size:0.75rem;color:var(--paper);">{{ $profile->decision_patterns['summary'] ?? '—' }}</p>
                    @if(!empty($profile->decision_patterns['tendencies']))
                        <ul class="mt-2 space-y-0.5">
                            @foreach(array_slice($profile->decision_patterns['tendencies'], 0, 3) as $t)
                                <li style="font-size:0.72rem;color:var(--mist);display:flex;gap:0.4rem;"><span style="color:var(--line);">&bull;</span>{{ $t }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

        </div>
    @endif
</div>
