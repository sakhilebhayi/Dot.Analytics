<div style="padding:2rem 2.25rem;">
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.35rem;">Admin</p>
            <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">Feature Flags</h3>
            <p style="font-size:0.75rem;color:var(--mist);margin:0.3rem 0 0;">Control feature rollouts without deploying code</p>
        </div>
        @can('manage-platforms')
            <button
                wire:click="$toggle('showCreate')"
                class="press"
                style="font-size:0.72rem;padding:0.4rem 0.85rem;background:{{ $showCreate ? 'transparent' : 'var(--gold)' }};color:{{ $showCreate ? 'var(--paper)' : 'var(--ink)' }};border:1px solid {{ $showCreate ? 'var(--line)' : 'transparent' }};border-radius:0.4rem;font-weight:600;"
            >{{ $showCreate ? 'Cancel' : '+ New Flag' }}</button>
        @endcan
    </div>

    @if($showCreate)
        <form wire:submit="create" class="mb-5 rounded-xl p-4 space-y-3" style="border:1px solid var(--line);background:var(--panel);">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Key (slug)</label>
                    <input wire:model="newKey" type="text" placeholder="new-ai-engine" class="w-full rounded-lg px-3 py-2 text-sm focus:outline-none" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);" />
                    @error('newKey') <p style="color:var(--danger);font-size:0.7rem;margin-top:0.25rem;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Display name</label>
                    <input wire:model="newName" type="text" placeholder="New AI Engine" class="w-full rounded-lg px-3 py-2 text-sm focus:outline-none" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);" />
                    @error('newName') <p style="color:var(--danger);font-size:0.7rem;margin-top:0.25rem;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Environment</label>
                    <select wire:model="newEnv" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);">
                        <option value="all">All environments</option>
                        <option value="production">Production only</option>
                        <option value="local">Local only</option>
                    </select>
                </div>
            </div>
            <input wire:model="newDesc" type="text" placeholder="Optional description" class="w-full rounded-lg px-3 py-2 text-sm focus:outline-none" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);" />
            <button type="submit" class="press" style="background:var(--gold);color:var(--ink);padding:0.5rem 1rem;border-radius:0.4rem;font-size:0.8rem;font-weight:600;">Create Flag</button>
        </form>
    @endif

    @if($this->flags->isEmpty())
        <p style="font-size:0.85rem;color:var(--mist);text-align:center;padding:1.5rem 0;">No feature flags defined. Create flags to manage feature rollouts without deployments.</p>
    @else
        <div class="space-y-2">
            @foreach($this->flags as $flag)
                <div class="rounded-xl p-4" style="border:1px solid var(--line);">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $flag->name }}</p>
                                <code class="font-mono" style="font-size:0.68rem;background:rgba(255,255,255,0.06);padding:0.1rem 0.4rem;border-radius:0.25rem;color:var(--mist);">{{ $flag->key }}</code>
                                @if($flag->environment !== 'all')
                                    <span style="font-size:0.68rem;background:rgba(241,198,46,0.06);color:var(--gold-soft);border:1px solid rgba(241,198,46,0.3);padding:0.1rem 0.4rem;border-radius:0.25rem;">{{ $flag->environment }}</span>
                                @endif
                            </div>
                            @if($flag->description)
                                <p style="font-size:0.72rem;color:var(--mist);opacity:0.8;margin-top:0.2rem;">{{ $flag->description }}</p>
                            @endif
                            @if($flag->rollout_percentage > 0 && !$flag->enabled_globally)
                                <p style="font-size:0.72rem;color:var(--teal-soft);margin-top:0.3rem;">Rollout: {{ $flag->rollout_percentage }}% of users</p>
                            @endif
                        </div>
                        @can('manage-platforms')
                            <div class="flex items-center gap-3 shrink-0">
                                {{-- Rollout slider --}}
                                @if(!$flag->enabled_globally)
                                    <div class="flex items-center gap-1.5">
                                        <span style="font-size:0.68rem;color:var(--mist);">{{ (int)$flag->rollout_percentage }}%</span>
                                        <input
                                            type="range"
                                            min="0" max="100" step="5"
                                            value="{{ $flag->rollout_percentage }}"
                                            wire:change="setRollout({{ $flag->id }}, $event.target.value)"
                                            class="w-20 h-1"
                                            style="accent-color:var(--gold);"
                                        />
                                    </div>
                                @endif
                                {{-- Toggle --}}
                                <button
                                    wire:click="toggle({{ $flag->id }})"
                                    class="press relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
                                    style="background:{{ $flag->enabled_globally ? 'var(--gold)' : 'rgba(255,255,255,0.08)' }};"
                                >
                                    <span class="inline-block h-3.5 w-3.5 transform rounded-full shadow transition-transform" style="background:{{ $flag->enabled_globally ? 'var(--ink)' : 'var(--paper)' }};transform:translateX({{ $flag->enabled_globally ? '1.15rem' : '0.15rem' }});"></span>
                                </button>
                                <span style="font-size:0.7rem;color:{{ $flag->enabled_globally ? 'var(--teal-soft)' : 'var(--mist)' }};">
                                    {{ $flag->enabled_globally ? 'On' : 'Off' }}
                                </span>
                            </div>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
