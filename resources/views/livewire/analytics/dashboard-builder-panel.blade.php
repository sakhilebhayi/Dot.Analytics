<div class="bg-white rounded-xl shadow">
    {{-- Header --}}
    <div class="flex items-center justify-between p-6 border-b border-gray-100">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Dashboard Builder</h3>
            <p class="text-xs text-gray-500 mt-0.5">Compose custom intelligence dashboards from widget building blocks</p>
        </div>
        <button wire:click="$toggle('showNewDashboard')" class="text-xs px-3 py-1.5 bg-gray-800 text-white rounded-lg hover:bg-gray-700">
            {{ $showNewDashboard ? 'Cancel' : '+ New Dashboard' }}
        </button>
    </div>

    {{-- Create dashboard form --}}
    @if($showNewDashboard)
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <form wire:submit="createDashboard" class="flex gap-3">
                <input wire:model="newDashboardTitle" type="text" placeholder="Dashboard name"
                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                @error('newDashboardTitle') <span class="text-red-500 text-xs self-center">{{ $message }}</span> @enderror
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">Create</button>
            </form>
        </div>
    @endif

    <div class="flex h-[520px]">
        {{-- Sidebar: dashboard list --}}
        <div class="w-56 border-r border-gray-100 overflow-y-auto">
            @if($this->dashboards->isEmpty())
                <p class="text-xs text-gray-400 p-4 text-center">No dashboards yet.</p>
            @else
                @foreach($this->dashboards as $dash)
                    <button
                        wire:click="selectDashboard({{ $dash->id }})"
                        class="w-full text-left px-4 py-3 text-sm border-b border-gray-50 transition-colors {{ ($this->activeDashboard?->id === $dash->id) ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-gray-50' }}"
                    >
                        <div class="flex items-center justify-between">
                            <span class="truncate">{{ $dash->title }}</span>
                            @if($dash->is_default)
                                <span class="text-xs bg-green-100 text-green-600 px-1 rounded ml-1">Default</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $dash->widgets()->count() }} widgets</p>
                    </button>
                @endforeach
            @endif
        </div>

        {{-- Main canvas --}}
        <div class="flex-1 p-5 overflow-y-auto">
            @if(! $this->activeDashboard)
                <div class="flex items-center justify-center h-full">
                    <p class="text-sm text-gray-400">Select or create a dashboard to start building.</p>
                </div>
            @else
                {{-- Dashboard toolbar --}}
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <h4 class="text-sm font-semibold text-gray-700">{{ $this->activeDashboard->title }}</h4>
                        @if(! $this->activeDashboard->is_default)
                            <button wire:click="setDefault({{ $this->activeDashboard->id }})" class="text-xs text-gray-400 hover:text-indigo-600">Set default</button>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="$toggle('showAddWidget')" class="text-xs px-3 py-1.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            {{ $showAddWidget ? 'Cancel' : '+ Add Widget' }}
                        </button>
                        <button
                            wire:click="deleteDashboard({{ $this->activeDashboard->id }})"
                            wire:confirm="Delete '{{ $this->activeDashboard->title }}'? All widgets will be removed."
                            class="text-xs text-red-400 hover:text-red-600 px-2"
                        >Delete</button>
                    </div>
                </div>

                {{-- Add widget form --}}
                @if($showAddWidget)
                    <form wire:submit="addWidget" class="mb-4 border border-indigo-100 bg-indigo-50 rounded-xl p-4 grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Widget type</label>
                            <select wire:model="widgetType" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                @foreach(\App\Livewire\Analytics\DashboardBuilderPanel::WIDGET_TYPES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Widget title (optional)</label>
                            <input wire:model="widgetTitle" type="text" placeholder="Auto-filled from type"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="col-span-2">
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">Add to Dashboard</button>
                        </div>
                    </form>
                @endif

                {{-- Widget grid --}}
                @if($this->widgets->isEmpty())
                    <div class="border-2 border-dashed border-gray-200 rounded-xl flex items-center justify-center h-48">
                        <p class="text-sm text-gray-400">Add your first widget above to start building.</p>
                    </div>
                @else
                    <div
                        class="grid grid-cols-3 gap-3"
                        x-data="{ sortable: null }"
                        x-init="
                            sortable = new Sortable($el, {
                                animation: 150,
                                ghostClass: 'opacity-30',
                                onEnd: (evt) => {
                                    const ids = Array.from($el.querySelectorAll('[data-widget-id]'))
                                        .map(el => parseInt(el.dataset.widgetId));
                                    $wire.updatePositions(ids);
                                }
                            });
                        "
                    >
                        @foreach($this->widgets as $widget)
                            <div
                                data-widget-id="{{ $widget->id }}"
                                class="bg-gray-50 border border-gray-200 rounded-xl p-4 cursor-grab active:cursor-grabbing"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <p class="text-xs font-semibold text-gray-700">{{ $widget->title ?? 'Untitled Widget' }}</p>
                                        <p class="text-xs text-gray-400">{{ ucwords(str_replace('_', ' ', $widget->widget_type)) }}</p>
                                    </div>
                                    <button
                                        wire:click="removeWidget({{ $widget->id }})"
                                        class="text-gray-300 hover:text-red-400 text-xs"
                                    >✕</button>
                                </div>
                                <div class="h-8 bg-gray-100 rounded flex items-center justify-center">
                                    <span class="text-xs text-gray-400">{{ $widget->widget_type }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
