<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Universal Intelligence Graph</h3>
            <p class="text-xs text-gray-500 mt-0.5">Trace relationships between any entity across the Dot ecosystem</p>
        </div>
        {{-- Stats --}}
        <div class="flex items-center gap-4 text-xs text-gray-500">
            <span><strong class="text-indigo-600">{{ $this->graphStats['node_count'] }}</strong> entities</span>
            <span><strong class="text-indigo-600">{{ $this->graphStats['edge_count'] }}</strong> relationships</span>
        </div>
    </div>

    {{-- Search form --}}
    <form wire:submit="traverse" class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-5">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Entity type</label>
            <select wire:model="entityType" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @foreach(['customer','invoice','equipment','operator','contract','order','ticket','project','supplier','asset','employee'] as $type)
                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs text-gray-500 mb-1">Entity ID</label>
            <input
                wire:model="entityId"
                type="text"
                placeholder="e.g. CUST-001"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            />
            @error('entityId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Max depth</label>
            <select wire:model="maxDepth" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @foreach([1,2,3,4,5] as $d)
                    <option value="{{ $d }}">{{ $d }} {{ $d === 1 ? 'hop' : 'hops' }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-4 flex gap-3">
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
            >
                <span wire:loading.remove>Traverse Graph</span>
                <span wire:loading>Traversing...</span>
            </button>
            @if(!empty($results) || $error)
                <button type="button" wire:click="clear" class="text-sm text-gray-400 hover:text-gray-600">Clear</button>
            @endif
        </div>
    </form>

    {{-- Error state --}}
    @if($error)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-700">
            {{ $error }}
        </div>
    @endif

    {{-- Results --}}
    @if(!empty($results))
        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                {{ count($results) }} related {{ count($results) === 1 ? 'entity' : 'entities' }} reachable within {{ $maxDepth }} {{ $maxDepth === 1 ? 'hop' : 'hops' }}
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($results as $node)
                    <div class="border border-gray-100 rounded-xl p-3 bg-gray-50">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ $node['label'] }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <span class="bg-gray-200 px-1.5 py-0.5 rounded text-xs">{{ $node['entity_type'] }}</span>
                                    &middot; {{ $node['entity_id'] }}
                                </p>
                            </div>
                            <span class="text-xs text-gray-400 shrink-0">{{ $node['source_platform'] ?? '' }}</span>
                        </div>
                        @if(!empty($node['attributes']))
                            <div class="mt-2 space-y-0.5">
                                @foreach(array_slice($node['attributes'], 0, 3) as $k => $v)
                                    <p class="text-xs text-gray-500"><span class="font-medium">{{ $k }}:</span> {{ is_array($v) ? json_encode($v) : $v }}</p>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @elseif(!$searching && empty($error))
        {{-- Entity type distribution --}}
        @if(!empty($this->graphStats['entity_types']))
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Entity distribution in graph</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->graphStats['entity_types'] as $type => $count)
                        <span class="bg-indigo-50 text-indigo-700 text-xs px-2.5 py-1 rounded-full">
                            {{ ucfirst($type) }}: {{ $count }}
                        </span>
                    @endforeach
                </div>
            </div>
        @else
            <p class="text-sm text-gray-400 text-center py-6">
                The graph is empty. Connect platforms and run intelligence engines to populate entity relationships.
            </p>
        @endif
    @endif
</div>
