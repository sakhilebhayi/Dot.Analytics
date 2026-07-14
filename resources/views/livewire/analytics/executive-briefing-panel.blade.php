<div class="bg-white rounded-xl shadow p-6" wire:poll.30s>
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Executive Intelligence Briefing</h3>
            <p class="text-xs text-gray-500 mt-0.5">AI-synthesised signals across all connected platforms</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex rounded-lg overflow-hidden border border-gray-200">
                @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $key => $label)
                    <button
                        wire:click="$set('period', '{{ $key }}')"
                        class="px-3 py-1.5 text-xs font-medium transition-colors {{ $period === $key ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-50' }}"
                    >{{ $label }}</button>
                @endforeach
            </div>
            <button
                wire:click="generate"
                wire:loading.attr="disabled"
                wire:target="generate"
                class="text-xs px-3 py-1.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 font-medium"
            >
                <span wire:loading.remove wire:target="generate">Generate</span>
                <span wire:loading wire:target="generate">Queuing...</span>
            </button>
        </div>
    </div>

    @if(! $this->briefing)
        <div class="text-center py-10 border-2 border-dashed border-gray-100 rounded-xl">
            <p class="text-sm text-gray-400">No {{ $period }} briefing available.</p>
            <p class="text-xs text-gray-400 mt-1">Click <strong>Generate</strong> to create one, or it will run automatically on schedule.</p>
        </div>
    @elseif($this->briefing->status === 'generating')
        <div class="flex items-center gap-3 py-8 justify-center">
            <div class="w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
            <p class="text-sm text-gray-500">Synthesising intelligence across all engines...</p>
        </div>
    @else
        @php $b = $this->briefing; @endphp

        {{-- Meta --}}
        <div class="flex items-center gap-3 mb-5 pb-4 border-b border-gray-100">
            <span class="text-xs text-gray-400">{{ ucfirst($b->period) }} briefing &middot; {{ \Carbon\Carbon::parse($b->period_date)->format('D, d M Y') }}</span>
            @if($b->insight_count > 0)
                <span class="text-xs bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full">{{ $b->insight_count }} insights analysed</span>
            @endif
            @if(!empty($b->engines_consulted))
                <span class="text-xs text-gray-400">{{ count($b->engines_consulted) }} engines</span>
            @endif
        </div>

        {{-- Executive summary --}}
        @if($b->summary)
            <p class="text-sm text-gray-700 leading-relaxed mb-5">{{ $b->summary }}</p>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Highlights --}}
            @if(!empty($b->highlights))
                <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                    <p class="text-xs font-semibold text-green-700 uppercase tracking-wide mb-2">Highlights</p>
                    <ul class="space-y-1.5">
                        @foreach($b->highlights as $h)
                            <li class="text-xs text-green-800 flex gap-2">
                                <span class="text-green-400 mt-0.5">↑</span>
                                <span>{{ $h }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Risks --}}
            @if(!empty($b->risks))
                <div class="bg-red-50 border border-red-100 rounded-xl p-4">
                    <p class="text-xs font-semibold text-red-700 uppercase tracking-wide mb-2">Risks</p>
                    <ul class="space-y-1.5">
                        @foreach($b->risks as $r)
                            <li class="text-xs text-red-800 flex gap-2">
                                <span class="text-red-400 mt-0.5">!</span>
                                <span>{{ $r }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Recommendations --}}
            @if(!empty($b->recommendations))
                <div class="bg-amber-50 border border-amber-100 rounded-xl p-4">
                    <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-2">Top Actions</p>
                    <ul class="space-y-2">
                        @foreach($b->recommendations as $rec)
                            <li class="text-xs text-amber-900">
                                <div class="flex items-center gap-1.5 mb-0.5">
                                    @php $pri = $rec['priority'] ?? 'medium'; @endphp
                                    <span class="w-1.5 h-1.5 rounded-full {{ $pri === 'high' || $pri === 'critical' ? 'bg-red-500' : 'bg-amber-500' }}"></span>
                                    <span class="font-medium">{{ $rec['title'] ?? '' }}</span>
                                </div>
                                @if(!empty($rec['rationale']))
                                    <p class="text-amber-700 pl-3">{{ $rec['rationale'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>
