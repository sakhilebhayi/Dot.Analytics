<div style="padding:2rem 2.25rem;">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.35rem;">Knowledge Graph</p>
            <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">Universal Intelligence Graph</h3>
            <p style="font-size:0.75rem;color:var(--mist);margin:0.3rem 0 0;">Trace relationships between any entity across the Dot ecosystem</p>
        </div>
        {{-- Stats --}}
        <div class="flex items-center gap-4" style="font-size:0.75rem;color:var(--mist);">
            <span><strong class="font-display" style="color:var(--gold);">{{ $this->graphStats['node_count'] }}</strong> entities</span>
            <span><strong class="font-display" style="color:var(--gold);">{{ $this->graphStats['edge_count'] }}</strong> relationships</span>
        </div>
    </div>

    {{-- Search form --}}
    <form wire:submit="traverse" class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-5">
        <div>
            <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Entity type</label>
            <select wire:model="entityType" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);">
                @foreach(['customer','invoice','equipment','operator','contract','order','ticket','project','supplier','asset','employee'] as $type)
                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Entity ID</label>
            <input
                wire:model="entityId"
                type="text"
                placeholder="e.g. CUST-001"
                class="w-full rounded-lg px-3 py-2 text-sm focus:outline-none"
                style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);"
            />
            @error('entityId') <p style="color:var(--danger);font-size:0.7rem;margin-top:0.25rem;">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Max depth</label>
            <select wire:model="maxDepth" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);">
                @foreach([1,2,3,4,5] as $d)
                    <option value="{{ $d }}">{{ $d }} {{ $d === 1 ? 'hop' : 'hops' }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-4 flex gap-3 items-center">
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="press"
                style="background:var(--gold);color:var(--ink);padding:0.55rem 1.25rem;border-radius:0.5rem;font-size:0.8rem;font-weight:600;"
            >
                <span wire:loading.remove>Traverse Graph</span>
                <span wire:loading>Traversing&hellip;</span>
            </button>
            @if(!empty($results) || $error)
                <button type="button" wire:click="clear" style="font-size:0.8rem;color:var(--mist);background:none;border:none;cursor:pointer;">Clear</button>
            @endif
        </div>
    </form>

    {{-- Error state --}}
    @if($error)
        <div class="rounded-xl p-4" style="background:rgba(241,198,46,0.06);border:1px solid var(--gold);font-size:0.8rem;color:var(--gold-soft);">
            {{ $error }}
        </div>
    @endif

    {{-- Results --}}
    @if(!empty($results))
        <div>
            <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--mist);margin-bottom:0.75rem;">
                {{ count($results) }} related {{ count($results) === 1 ? 'entity' : 'entities' }} reachable within {{ $maxDepth }} {{ $maxDepth === 1 ? 'hop' : 'hops' }}
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($results as $node)
                    <div class="rounded-xl p-3" style="border:1px solid var(--line);">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $node['label'] }}</p>
                                <p style="font-size:0.72rem;color:var(--mist);margin:0.2rem 0 0;">
                                    <span style="background:rgba(255,255,255,0.06);padding:0.1rem 0.4rem;border-radius:0.25rem;">{{ $node['entity_type'] }}</span>
                                    &middot; {{ $node['entity_id'] }}
                                </p>
                            </div>
                            <span style="font-size:0.68rem;color:var(--mist);opacity:0.7;flex-shrink:0;">{{ $node['source_platform'] ?? '' }}</span>
                        </div>
                        @if(!empty($node['attributes']))
                            <div class="mt-2 space-y-0.5">
                                @foreach(array_slice($node['attributes'], 0, 3) as $k => $v)
                                    <p style="font-size:0.7rem;color:var(--mist);"><span style="font-weight:600;color:var(--paper);">{{ $k }}:</span> {{ is_array($v) ? json_encode($v) : $v }}</p>
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
                <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--mist);margin-bottom:0.75rem;">Entity distribution in graph</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->graphStats['entity_types'] as $type => $count)
                        <span style="background:rgba(43,182,183,0.08);color:var(--teal-soft);font-size:0.72rem;padding:0.3rem 0.7rem;border-radius:9999px;">
                            {{ ucfirst($type) }}: {{ $count }}
                        </span>
                    @endforeach
                </div>
            </div>
        @else
            <p style="font-size:0.85rem;color:var(--mist);text-align:center;padding:1.5rem 0;">
                The graph is empty. Connect platforms and run intelligence engines to populate entity relationships.
            </p>
        @endif
    @endif
</div>
