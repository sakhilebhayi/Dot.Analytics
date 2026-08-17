<div style="padding:2rem 2.25rem;">
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.35rem;">Ecosystem Map</p>
            <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">Connected Platforms</h3>
            <p style="font-size:0.75rem;color:var(--mist);margin:0.3rem 0 0;">
                {{ $this->connectedCount }} of {{ count($this->platformCatalog) }} platforms connected
                &middot; {{ count($this->activeEngines) }} intelligence engines active
            </p>
        </div>
    </div>

    {{-- Connect modal --}}
    @if($connectingPlatform)
        @php $cp = $this->platformCatalog[$connectingPlatform]; @endphp
        <div class="mb-6 rounded-xl p-4" style="background:var(--panel);border:1px solid var(--teal-soft);">
            <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0 0 0.2rem;">Connecting {{ $cp['label'] }}</p>
            <p style="font-size:0.75rem;color:var(--mist);margin:0 0 0.75rem;">{{ $cp['description'] }}</p>
            <div class="flex gap-3 items-end">
                <div class="flex-1">
                    <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Base URL (optional)</label>
                    <input
                        wire:model="connectUrl"
                        type="url"
                        placeholder="https://fleet.infodot.app"
                        class="w-full rounded px-3 py-2 text-sm focus:outline-none"
                        style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);"
                    />
                    @error('connectUrl') <p style="color:#f08a6c;font-size:0.7rem;margin-top:0.25rem;">{{ $message }}</p> @enderror
                </div>
                <button wire:click="confirmConnect" class="press" style="background:var(--gold);color:var(--ink);padding:0.5rem 1rem;border-radius:0.35rem;font-size:0.8rem;font-weight:600;">Connect</button>
                <button wire:click="cancelConnect" class="press" style="background:transparent;color:var(--mist);border:1px solid var(--line);padding:0.5rem 1rem;border-radius:0.35rem;font-size:0.8rem;">Cancel</button>
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
            <div class="border rounded-xl p-3 flex flex-col gap-2 {{ $connected ? $colors['border'] . ' ' . $colors['bg'] : '' }}" style="{{ $connected ? '' : 'border-color:var(--line);background:rgba(255,255,255,0.02);' }}">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full {{ $connected ? $colors['dot'] : '' }}" style="{{ $connected ? '' : 'background:var(--mist);' }}"></span>
                        <span class="text-xs font-semibold {{ $connected ? $colors['text'] : '' }}" style="{{ $connected ? '' : 'color:var(--mist);' }}">
                            {{ $platform['label'] }}
                        </span>
                    </div>
                </div>
                <p style="font-size:0.72rem;color:var(--mist);line-height:1.3;">{{ $platform['description'] }}</p>
                <div class="mt-auto">
                    @if($connected)
                        <button
                            wire:click="disconnect('{{ $key }}')"
                            wire:confirm="Disconnect {{ $platform['label'] }}? Intelligence data already collected will be preserved."
                            style="font-size:0.7rem;color:var(--mist);background:none;border:none;cursor:pointer;"
                            onmouseover="this.style.color='#f08a6c'" onmouseout="this.style.color='var(--mist)'"
                        >Disconnect</button>
                    @else
                        <button
                            wire:click="startConnect('{{ $key }}')"
                            class="press {{ $colors['text'] }}"
                            style="font-size:0.7rem;font-weight:600;background:none;border:none;cursor:pointer;"
                        >+ Connect</button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Active Intelligence Engines --}}
    @if(count($this->activeEngines) > 0)
        <div>
            <h4 class="font-mono" style="font-size:0.65rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--mist);margin-bottom:0.75rem;">Active Intelligence Engines</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach($this->activeEngines as $key => $engine)
                    <div class="rounded-lg px-3 py-2 flex items-center justify-between" style="border:1px solid var(--line);">
                        <div>
                            <p style="font-size:0.75rem;font-weight:500;color:var(--paper);margin:0;">{{ $engine['label'] }}</p>
                            <p style="font-size:0.68rem;color:var(--mist);margin:0;">{{ count($engine['connected_sources']) }} of {{ count($engine['sources']) }} sources</p>
                        </div>
                        <div class="text-right">
                            <span style="font-size:0.75rem;font-weight:600;color:{{ $engine['coverage'] >= 75 ? 'var(--teal-soft)' : ($engine['coverage'] >= 40 ? 'var(--gold-soft)' : 'var(--mist)') }};">
                                {{ $engine['coverage'] }}%
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <p style="font-size:0.8rem;color:var(--mist);text-align:center;padding:0.5rem 0;">Connect at least one platform to activate intelligence engines.</p>
    @endif
</div>
