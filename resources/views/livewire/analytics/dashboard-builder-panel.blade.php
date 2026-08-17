<div style="padding:1.75rem 2rem 2rem;">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.35rem;">My Dashboards</p>
            <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">Dashboard Builder</h3>
            <p style="font-size:0.75rem;color:var(--mist);margin:0.3rem 0 0;">Compose custom intelligence views from widget building blocks</p>
        </div>
        <button wire:click="$toggle('showNewDashboard')" class="press" style="font-size:0.72rem;padding:0.4rem 0.85rem;background:{{ $showNewDashboard ? 'transparent' : 'var(--gold)' }};color:{{ $showNewDashboard ? 'var(--paper)' : 'var(--ink)' }};border:1px solid {{ $showNewDashboard ? 'var(--line)' : 'transparent' }};border-radius:0.4rem;font-weight:600;">
            {{ $showNewDashboard ? 'Cancel' : '+ New Dashboard' }}
        </button>
    </div>

    {{-- Create dashboard form --}}
    @if($showNewDashboard)
        <div class="mb-5 rounded-xl p-4" style="border:1px solid var(--line);background:var(--panel);">
            <form wire:submit="createDashboard" class="flex gap-3 items-end flex-wrap">
                <div class="flex-1" style="min-width:200px;">
                    <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Dashboard name</label>
                    <input wire:model="newDashboardTitle" type="text" placeholder="e.g. Ops Overview" class="w-full rounded-lg px-3 py-2 text-sm focus:outline-none" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);" />
                    @error('newDashboardTitle') <span style="color:var(--danger);font-size:0.7rem;">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Visibility</label>
                    <select wire:model="newDashboardVisibility" class="rounded-lg px-3 py-2 text-sm" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);">
                        <option value="private">Private — only me</option>
                        <option value="team">Shared with team</option>
                    </select>
                </div>
                <button type="submit" class="press" style="background:var(--gold);color:var(--ink);padding:0.5rem 1.25rem;border-radius:0.4rem;font-size:0.8rem;font-weight:600;">Create</button>
            </form>
        </div>
    @endif

    <div class="flex" style="min-height:480px;border:1px solid var(--line);border-radius:12px;overflow:hidden;">
        {{-- Sidebar: dashboard list --}}
        <div style="width:224px;border-right:1px solid var(--line);overflow-y:auto;flex-shrink:0;">
            @if($this->dashboards->isEmpty())
                <p style="font-size:0.75rem;color:var(--mist);padding:1.5rem 1rem;text-align:center;">No dashboards yet. Create your first one above.</p>
            @else
                @foreach($this->dashboards as $dash)
                    <button
                        wire:click="selectDashboard({{ $dash->id }})"
                        class="press"
                        style="width:100%;text-align:left;padding:0.75rem 1rem;font-size:0.8rem;border-bottom:1px solid var(--line);background:{{ ($this->activeDashboard?->id === $dash->id) ? 'rgba(241,198,46,0.06)' : 'transparent' }};color:{{ ($this->activeDashboard?->id === $dash->id) ? 'var(--gold-soft)' : 'var(--paper)' }};"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate" style="{{ ($this->activeDashboard?->id === $dash->id) ? 'font-weight:600;' : '' }}">{{ $dash->title }}</span>
                            @if($dash->is_default)
                                <span class="font-mono" style="font-size:0.58rem;background:rgba(43,182,183,0.1);color:var(--teal-soft);padding:0.1rem 0.4rem;border-radius:0.25rem;flex-shrink:0;">Default</span>
                            @endif
                        </div>
                        <p style="font-size:0.68rem;color:var(--mist);margin:0.2rem 0 0;">
                            {{ $dash->widgets()->count() }} widgets
                            @if($dash->visibility === 'team')
                                &middot; shared
                            @endif
                        </p>
                    </button>
                @endforeach
            @endif
        </div>

        {{-- Main canvas --}}
        <div class="flex-1 p-5 overflow-y-auto">
            @if(! $this->activeDashboard)
                <div class="flex items-center justify-center h-full">
                    <p style="font-size:0.85rem;color:var(--mist);">Select or create a dashboard to start building.</p>
                </div>
            @else
                {{-- Dashboard toolbar --}}
                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <h4 class="font-display" style="font-size:0.95rem;font-weight:700;color:var(--paper);">{{ $this->activeDashboard->title }}</h4>
                        @if(! $this->activeDashboard->is_default)
                            <button wire:click="setDefault({{ $this->activeDashboard->id }})" style="font-size:0.68rem;color:var(--mist);background:none;border:none;cursor:pointer;">Set as my default</button>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="$toggle('showAddWidget')" class="press" style="font-size:0.72rem;padding:0.4rem 0.85rem;background:var(--gold);color:var(--ink);border-radius:0.4rem;font-weight:600;">
                            {{ $showAddWidget ? 'Cancel' : '+ Add Widget' }}
                        </button>
                        <button
                            wire:click="deleteDashboard({{ $this->activeDashboard->id }})"
                            wire:confirm="Delete '{{ $this->activeDashboard->title }}'? All widgets will be removed."
                            style="font-size:0.72rem;color:var(--mist);background:none;border:1px solid var(--line);padding:0.4rem 0.7rem;border-radius:0.4rem;cursor:pointer;"
                            onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--mist)'"
                        >Delete</button>
                    </div>
                </div>

                {{-- Add widget form --}}
                @if($showAddWidget)
                    <form wire:submit="addWidget" class="mb-4 rounded-xl p-4 grid grid-cols-2 gap-3" style="border:1px solid var(--teal-soft);background:var(--panel);">
                        <div>
                            <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Widget type</label>
                            <select wire:model.live="widgetType" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);">
                                @foreach(\App\Livewire\Analytics\DashboardBuilderPanel::WIDGET_TYPES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Widget title (optional)</label>
                            <input wire:model="widgetTitle" type="text" placeholder="Auto-filled from type" class="w-full rounded-lg px-3 py-2 text-sm focus:outline-none" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);" />
                        </div>
                        @if(in_array($widgetType, \App\Livewire\Analytics\DashboardBuilderPanel::METRIC_WIDGET_TYPES))
                            <div class="col-span-2">
                                <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Metric</label>
                                <select wire:model="widgetMetricDefinitionId" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);">
                                    <option value="">Choose a metric&hellip;</option>
                                    @foreach($this->metricDefinitions as $definition)
                                        <option value="{{ $definition->id }}">{{ $definition->label }}</option>
                                    @endforeach
                                </select>
                                @error('widgetMetricDefinitionId') <span style="color:var(--danger);font-size:0.7rem;">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        <div class="col-span-2">
                            <button type="submit" class="press" style="background:var(--gold);color:var(--ink);padding:0.5rem 1.25rem;border-radius:0.4rem;font-size:0.8rem;font-weight:600;">Add to Dashboard</button>
                        </div>
                    </form>
                @endif

                {{-- Widget grid --}}
                @if($this->widgets->isEmpty())
                    <div class="rounded-xl flex items-center justify-center" style="border:2px dashed var(--line);height:12rem;">
                        <p style="font-size:0.85rem;color:var(--mist);">Add your first widget above to start building.</p>
                    </div>
                @else
                    <div
                        class="grid grid-cols-1 lg:grid-cols-2 gap-4"
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
                            <div data-widget-id="{{ $widget->id }}" class="rounded-xl" style="border:1px solid var(--line);overflow:hidden;">
                                <div class="flex items-center justify-between cursor-grab active:cursor-grabbing" style="padding:0.6rem 0.9rem;border-bottom:1px solid var(--line);background:rgba(255,255,255,0.02);">
                                    <div class="flex items-center gap-2" style="min-width:0;">
                                        <span class="material-symbols-outlined" style="font-size:16px;color:var(--mist);">{{ match($widget->widget_type) {
                                            'metric_card' => 'speed',
                                            'chart' => 'show_chart',
                                            'alert_feed' => 'warning',
                                            'recommendation_feed' => 'lightbulb',
                                            'insight_feed' => 'insights',
                                            'dna_snapshot' => 'biotech',
                                            'briefing_summary' => 'summarize',
                                            default => 'widgets',
                                        } }}</span>
                                        <p class="truncate" style="font-size:0.75rem;font-weight:600;color:var(--paper);margin:0;">{{ $widget->title ?? 'Untitled Widget' }}</p>
                                    </div>
                                    <button
                                        wire:click="removeWidget({{ $widget->id }})"
                                        style="color:var(--mist);background:none;border:none;cursor:pointer;font-size:0.75rem;flex-shrink:0;"
                                        onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--mist)'"
                                    >&#10005;</button>
                                </div>
                                <div style="padding:0.75rem 0.9rem;max-height:420px;overflow-y:auto;">
                                    @switch($widget->widget_type)
                                        @case('alert_feed')
                                            <livewire:analytics.alerts-panel :key="'widget-'.$widget->id" />
                                            @break
                                        @case('recommendation_feed')
                                            <livewire:analytics.recommendations-panel :key="'widget-'.$widget->id" />
                                            @break
                                        @case('insight_feed')
                                            <livewire:analytics.cross-platform-insight-panel :key="'widget-'.$widget->id" />
                                            @break
                                        @case('dna_snapshot')
                                            <livewire:analytics.business-dna-panel :key="'widget-'.$widget->id" />
                                            @break
                                        @case('briefing_summary')
                                            <livewire:analytics.executive-briefing-panel :key="'widget-'.$widget->id" />
                                            @break
                                        @case('metric_card')
                                            @include('livewire.analytics.widgets.metric-card', ['widget' => $widget])
                                            @break
                                        @case('chart')
                                            @include('livewire.analytics.widgets.metric-chart', ['widget' => $widget])
                                            @break
                                    @endswitch
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
