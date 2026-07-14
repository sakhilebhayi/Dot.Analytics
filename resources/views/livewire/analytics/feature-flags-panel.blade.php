<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Feature Flags</h3>
            <p class="text-xs text-gray-500 mt-0.5">Control feature rollouts without deploying code</p>
        </div>
        @can('manage-platforms')
            <button
                wire:click="$toggle('showCreate')"
                class="text-xs px-3 py-1.5 bg-gray-800 text-white rounded-lg hover:bg-gray-700"
            >{{ $showCreate ? 'Cancel' : '+ New Flag' }}</button>
        @endcan
    </div>

    @if($showCreate)
        <form wire:submit="create" class="mb-5 border border-gray-200 rounded-xl p-4 space-y-3 bg-gray-50">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Key (slug)</label>
                    <input wire:model="newKey" type="text" placeholder="new-ai-engine" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    @error('newKey') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Display name</label>
                    <input wire:model="newName" type="text" placeholder="New AI Engine" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    @error('newName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Environment</label>
                    <select wire:model="newEnv" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="all">All environments</option>
                        <option value="production">Production only</option>
                        <option value="local">Local only</option>
                    </select>
                </div>
            </div>
            <input wire:model="newDesc" type="text" placeholder="Optional description" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">Create Flag</button>
        </form>
    @endif

    @if($this->flags->isEmpty())
        <p class="text-sm text-gray-400 text-center py-6">No feature flags defined. Create flags to manage feature rollouts without deployments.</p>
    @else
        <div class="space-y-2">
            @foreach($this->flags as $flag)
                <div class="border border-gray-100 rounded-xl p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-gray-800">{{ $flag->name }}</p>
                                <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded text-gray-600">{{ $flag->key }}</code>
                                @if($flag->environment !== 'all')
                                    <span class="text-xs bg-amber-50 text-amber-600 border border-amber-200 px-1.5 py-0.5 rounded">{{ $flag->environment }}</span>
                                @endif
                            </div>
                            @if($flag->description)
                                <p class="text-xs text-gray-400 mt-0.5">{{ $flag->description }}</p>
                            @endif
                            @if($flag->rollout_percentage > 0 && !$flag->enabled_globally)
                                <p class="text-xs text-indigo-500 mt-1">Rollout: {{ $flag->rollout_percentage }}% of users</p>
                            @endif
                        </div>
                        @can('manage-platforms')
                            <div class="flex items-center gap-3 shrink-0">
                                {{-- Rollout slider --}}
                                @if(!$flag->enabled_globally)
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs text-gray-400">{{ (int)$flag->rollout_percentage }}%</span>
                                        <input
                                            type="range"
                                            min="0" max="100" step="5"
                                            value="{{ $flag->rollout_percentage }}"
                                            wire:change="setRollout({{ $flag->id }}, $event.target.value)"
                                            class="w-20 h-1 accent-indigo-600"
                                        />
                                    </div>
                                @endif
                                {{-- Toggle --}}
                                <button
                                    wire:click="toggle({{ $flag->id }})"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors {{ $flag->enabled_globally ? 'bg-indigo-600' : 'bg-gray-200' }}"
                                >
                                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform {{ $flag->enabled_globally ? 'translate-x-4.5' : 'translate-x-0.5' }}"></span>
                                </button>
                                <span class="text-xs {{ $flag->enabled_globally ? 'text-green-600' : 'text-gray-400' }}">
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
