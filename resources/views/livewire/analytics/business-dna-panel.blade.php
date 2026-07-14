<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Business DNA Profile</h3>
            <p class="text-xs text-gray-500 mt-0.5">
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
            class="text-xs px-3 py-1.5 bg-gray-800 text-white rounded hover:bg-gray-700 disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="compute">Recompute DNA</span>
            <span wire:loading wire:target="compute">Computing...</span>
        </button>
    </div>

    @if(! $this->profile)
        <div class="text-center py-8">
            <p class="text-sm text-gray-400">No DNA profile computed yet.</p>
            <p class="text-xs text-gray-400 mt-1">Connect at least one platform and click <strong>Recompute DNA</strong>.</p>
        </div>
    @else
        @php $profile = $this->profile; @endphp

        {{-- Confidence meter --}}
        <div class="mb-5">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs text-gray-500">Profile confidence</span>
                <span class="text-xs font-semibold {{ $profile->confidence_score >= 0.7 ? 'text-green-600' : ($profile->confidence_score >= 0.4 ? 'text-amber-600' : 'text-gray-500') }}">
                    {{ round($profile->confidence_score * 100) }}%
                </span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-1.5">
                <div
                    class="h-1.5 rounded-full {{ $profile->confidence_score >= 0.7 ? 'bg-green-500' : ($profile->confidence_score >= 0.4 ? 'bg-amber-500' : 'bg-gray-400') }}"
                    style="width: {{ round($profile->confidence_score * 100) }}%"
                ></div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            {{-- Operational patterns --}}
            @if($profile->operational_patterns)
                <div class="border border-gray-100 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Operational Patterns</p>
                    <p class="text-xs text-gray-700">{{ $profile->operational_patterns['summary'] ?? '—' }}</p>
                    @if(!empty($profile->operational_patterns['key_patterns']))
                        <ul class="mt-2 space-y-0.5">
                            @foreach(array_slice($profile->operational_patterns['key_patterns'], 0, 3) as $p)
                                <li class="text-xs text-gray-500 flex gap-1.5"><span class="text-gray-300">•</span>{{ $p }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            {{-- Risk profile --}}
            @if($profile->risk_tolerance)
                <div class="border border-gray-100 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Risk Profile</p>
                    <div class="flex items-center gap-2 mb-1">
                        @php
                            $level      = $profile->risk_tolerance['level'] ?? 'medium';
                            $riskColor  = match($level) { 'high' => 'bg-red-100 text-red-700', 'low' => 'bg-green-100 text-green-700', default => 'bg-amber-100 text-amber-700' };
                        @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $riskColor }}">{{ ucfirst($level) }} risk tolerance</span>
                    </div>
                    <p class="text-xs text-gray-600">{{ $profile->risk_tolerance['notes'] ?? '' }}</p>
                </div>
            @endif

            {{-- Growth signals --}}
            @if($profile->growth_signals)
                <div class="border border-gray-100 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Growth Signals</p>
                    <p class="text-xs text-gray-700">{{ $profile->growth_signals['summary'] ?? '—' }}</p>
                    @if(!empty($profile->growth_signals['signals']))
                        <ul class="mt-2 space-y-0.5">
                            @foreach(array_slice($profile->growth_signals['signals'], 0, 3) as $s)
                                <li class="text-xs text-green-600 flex gap-1.5"><span class="text-green-300">↑</span>{{ $s }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            {{-- Bottlenecks --}}
            @if($profile->bottlenecks)
                <div class="border border-gray-100 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Operational Bottlenecks</p>
                    <p class="text-xs text-gray-700">{{ $profile->bottlenecks['summary'] ?? '—' }}</p>
                    @if(!empty($profile->bottlenecks['areas']))
                        <ul class="mt-2 space-y-0.5">
                            @foreach(array_slice($profile->bottlenecks['areas'], 0, 3) as $b)
                                <li class="text-xs text-amber-600 flex gap-1.5"><span class="text-amber-300">!</span>{{ $b }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            {{-- Customer behaviour --}}
            @if($profile->customer_behavior)
                <div class="border border-gray-100 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Customer Behaviour</p>
                    <p class="text-xs text-gray-700">{{ $profile->customer_behavior['summary'] ?? '—' }}</p>
                </div>
            @endif

            {{-- Decision patterns --}}
            @if($profile->decision_patterns)
                <div class="border border-gray-100 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Decision Patterns</p>
                    <p class="text-xs text-gray-700">{{ $profile->decision_patterns['summary'] ?? '—' }}</p>
                    @if(!empty($profile->decision_patterns['tendencies']))
                        <ul class="mt-2 space-y-0.5">
                            @foreach(array_slice($profile->decision_patterns['tendencies'], 0, 3) as $t)
                                <li class="text-xs text-gray-500 flex gap-1.5"><span class="text-gray-300">•</span>{{ $t }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

        </div>
    @endif
</div>
