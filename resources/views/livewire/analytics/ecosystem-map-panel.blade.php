<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Ecosystem Intelligence Map</h3>
            <p class="text-xs text-gray-500 mt-0.5">
                {{ $this->connectedCount }} of {{ count($this->platformCatalog) }} platforms connected
                &middot; {{ count($this->activeEngines) }} intelligence engines active
            </p>
        </div>
    </div>

    {{-- Connect modal --}}
    @if($connectingPlatform)
        @php $cp = $this->platformCatalog[$connectingPlatform]; @endphp
        <div class="mb-6 border border-blue-200 bg-blue-50 rounded-xl p-4">
            <p class="text-sm font-semibold text-blue-800 mb-1">Connecting {{ $cp['label'] }}</p>
            <p class="text-xs text-gray-600 mb-3">{{ $cp['description'] }}</p>
            <div class="flex gap-3 items-end">
                <div class="flex-1">
                    <label class="block text-xs text-gray-500 mb-1">Base URL (optional)</label>
                    <input
                        wire:model="connectUrl"
                        type="url"
                        placeholder="https://fleet.infodot.app"
                        class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                    />
                    @error('connectUrl') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <button wire:click="confirmConnect" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">Connect</button>
                <button wire:click="cancelConnect" class="bg-gray-100 text-gray-600 px-4 py-2 rounded text-sm hover:bg-gray-200">Cancel</button>
            </div>
        </div>
    @endif

    {{-- Platform grid --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
        @foreach($this->platformCatalog as $key => $platform)
            @php
                $connected = $platform['status'] === 'connected';
                $colors    = $platform['colors'];
            @endphp
            <div class="border rounded-xl p-3 flex flex-col gap-2 {{ $connected ? $colors['border'] . ' ' . $colors['bg'] : 'border-gray-200 bg-gray-50' }}">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full {{ $connected ? $colors['dot'] : 'bg-gray-300' }}"></span>
                        <span class="text-xs font-semibold {{ $connected ? $colors['text'] : 'text-gray-500' }}">
                            {{ $platform['label'] }}
                        </span>
                    </div>
                </div>
                <p class="text-xs text-gray-500 leading-tight">{{ $platform['description'] }}</p>
                <div class="mt-auto">
                    @if($connected)
                        <button
                            wire:click="disconnect('{{ $key }}')"
                            wire:confirm="Disconnect {{ $platform['label'] }}? Intelligence data already collected will be preserved."
                            class="text-xs text-gray-400 hover:text-red-500"
                        >Disconnect</button>
                    @else
                        <button
                            wire:click="startConnect('{{ $key }}')"
                            class="text-xs font-medium {{ $colors['text'] }} hover:underline"
                        >+ Connect</button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Active Intelligence Engines --}}
    @if(count($this->activeEngines) > 0)
        <div>
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Active Intelligence Engines</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach($this->activeEngines as $key => $engine)
                    <div class="border border-gray-200 rounded-lg px-3 py-2 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-700">{{ $engine['label'] }}</p>
                            <p class="text-xs text-gray-400">{{ count($engine['connected_sources']) }} of {{ count($engine['sources']) }} sources</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-semibold {{ $engine['coverage'] >= 75 ? 'text-green-600' : ($engine['coverage'] >= 40 ? 'text-amber-600' : 'text-gray-400') }}">
                                {{ $engine['coverage'] }}%
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <p class="text-sm text-gray-400 text-center py-2">Connect at least one platform to activate intelligence engines.</p>
    @endif
</div>
