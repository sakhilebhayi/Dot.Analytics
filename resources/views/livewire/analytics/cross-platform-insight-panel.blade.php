<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Cross-Platform Intelligence</h3>
            <p class="text-xs text-gray-500 mt-0.5">Insights that require multiple platforms — impossible in traditional BI</p>
        </div>
        <div class="flex items-center gap-2">
            <select wire:model.live="filterType" class="border border-gray-300 rounded text-xs px-2 py-1">
                <option value="">All types</option>
                <option value="causation">Causation</option>
                <option value="correlation">Correlation</option>
                <option value="prediction">Prediction</option>
                <option value="risk">Risk</option>
                <option value="opportunity">Opportunity</option>
            </select>
            <select wire:model.live="filterSeverity" class="border border-gray-300 rounded text-xs px-2 py-1">
                <option value="">All severities</option>
                <option value="critical">Critical</option>
                <option value="warning">Warning</option>
                <option value="info">Info</option>
            </select>
            <button
                wire:click="runEngines"
                wire:loading.attr="disabled"
                wire:target="runEngines"
                class="text-xs px-3 py-1.5 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="runEngines">Run Engines</span>
                <span wire:loading wire:target="runEngines">Running...</span>
            </button>
        </div>
    </div>

    @if($this->insights->isEmpty())
        <div class="text-center py-8">
            <p class="text-sm text-gray-400">No cross-platform insights yet.</p>
            <p class="text-xs text-gray-400 mt-1">Connect at least 2 platforms and click <strong>Run Engines</strong>.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($this->insights as $insight)
                @php
                    $borderClass = match($insight->severity) {
                        'critical' => 'border-red-300 bg-red-50',
                        'warning'  => 'border-amber-300 bg-amber-50',
                        default    => 'border-gray-200',
                    };
                    $typeColor = match($insight->insight_type) {
                        'causation'   => 'bg-red-100 text-red-700',
                        'correlation' => 'bg-blue-100 text-blue-700',
                        'prediction'  => 'bg-purple-100 text-purple-700',
                        'risk'        => 'bg-orange-100 text-orange-700',
                        'opportunity' => 'bg-green-100 text-green-700',
                        default       => 'bg-gray-100 text-gray-600',
                    };
                @endphp
                <div class="border rounded-xl p-4 {{ $borderClass }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $typeColor }}">
                                    {{ ucfirst($insight->insight_type) }}
                                </span>
                                @foreach($insight->platforms_involved as $p)
                                    <span class="text-xs px-1.5 py-0.5 bg-white border border-gray-200 rounded text-gray-600">
                                        {{ \App\Services\IntelligenceEngineService::PLATFORMS[$p]['label'] ?? $p }}
                                    </span>
                                @endforeach
                                <span class="text-xs text-gray-400 ml-auto">{{ round($insight->confidence * 100) }}% confidence</span>
                            </div>
                            <p class="text-sm font-semibold text-gray-800">{{ $insight->title }}</p>
                            <p class="text-xs text-gray-600 mt-1 leading-relaxed">{{ $insight->narrative }}</p>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            @if($insight->isNew())
                                <button wire:click="review({{ $insight->id }})" class="text-xs px-2 py-1 bg-white border border-gray-200 rounded hover:bg-gray-50">Review</button>
                            @endif
                            <button wire:click="dismiss({{ $insight->id }})" class="text-xs px-2 py-1 bg-white border border-gray-200 rounded hover:bg-gray-50 text-gray-400">Dismiss</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
