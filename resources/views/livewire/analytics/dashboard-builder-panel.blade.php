@php $active = $this->activeDashboard; $widgets = $this->widgets; @endphp
<div data-testid="dashboard-builder" style="padding:2rem 2.25rem;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem;">
        <div>
            <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--gold-soft);margin:0 0 0.35rem;">Dashboards</p>
            <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">Dashboard Builder</h3>
            <p style="font-size:0.75rem;color:var(--mist);margin:0.3rem 0 0;max-width:62ch;">Save the metrics you watch together. Widgets show real numbers from connected platforms, as a KPI tile or a trend.</p>
        </div>
        @can('edit-dashboards')
            <button wire:click="$toggle('showNewDashboard')" class="press" style="font-size:0.72rem;padding:0.4rem 0.85rem;background:transparent;border:1px solid var(--line);color:var(--paper);border-radius:0.4rem;">
                {{ $showNewDashboard ? 'Cancel' : '+ New dashboard' }}
            </button>
        @endcan
    </div>

    @if($showNewDashboard)
        <form wire:submit="createDashboard" style="display:flex;gap:0.6rem;align-items:flex-start;flex-wrap:wrap;margin-bottom:1rem;padding:0.9rem 1rem;background:var(--panel);border:1px solid var(--line);">
            <div style="flex:1;min-width:200px;">
                <label for="new-dashboard-title" class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Dashboard name</label>
                <input id="new-dashboard-title" wire:model="newDashboardTitle" type="text" class="w-full rounded px-3 py-2 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);" />
                @error('newDashboardTitle') <p style="color:var(--danger);font-size:0.7rem;margin:0.25rem 0 0;">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="press" style="margin-top:1.45rem;background:var(--gold);color:var(--ink);padding:0.5rem 1rem;border-radius:0.35rem;font-size:0.8rem;font-weight:600;">Create</button>
        </form>
    @endif

    @if($this->dashboards->isEmpty())
        <div style="border:1px solid var(--line);padding:1.25rem;">
            <p style="font-size:0.85rem;color:var(--mist);margin:0;">
                No dashboards yet.
                @can('edit-dashboards') Create one, then add KPI tiles and trends from your connected platforms. @else Someone who can edit dashboards has not made one yet. @endcan
            </p>
        </div>
    @else
        {{-- Dashboard switcher --}}
        <div role="tablist" aria-label="Dashboards" style="display:flex;flex-wrap:wrap;gap:1px;background:var(--line);border:1px solid var(--line);border-bottom:0;">
            @foreach($this->dashboards as $dash)
                <button type="button" role="tab" aria-selected="{{ $active?->id === $dash->id ? 'true' : 'false' }}" wire:click="selectDashboard({{ $dash->id }})"
                        style="background:{{ $active?->id === $dash->id ? 'var(--panel)' : 'var(--ink-soft)' }};color:{{ $active?->id === $dash->id ? 'var(--gold)' : 'var(--mist)' }};border:0;padding:0.55rem 1rem;font-size:0.75rem;cursor:pointer;">
                    {{ $dash->title }}
                    @if($dash->is_default)<span class="font-mono" style="font-size:0.55rem;letter-spacing:0.1em;text-transform:uppercase;margin-left:0.4rem;opacity:0.8;">default</span>@endif
                </button>
            @endforeach
        </div>

        <div style="border:1px solid var(--line);background:var(--ink-soft);">
            @if($active)
                <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;flex-wrap:wrap;padding:0.65rem 1rem;background:var(--panel);border-bottom:1px solid var(--line);">
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <h4 class="font-display" style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $active->title }}</h4>
                        <span class="font-mono" style="font-size:0.62rem;color:var(--mist);">{{ $widgets->count() }} {{ \Illuminate\Support\Str::plural('widget', $widgets->count()) }}</span>
                        @unless($active->is_default)
                            @can('edit-dashboards')
                                <button wire:click="setDefault({{ $active->id }})" class="press" style="font-size:0.68rem;color:var(--mist);background:none;border:none;cursor:pointer;text-decoration:underline;">Make default</button>
                            @endcan
                        @endunless
                    </div>
                    @can('edit-dashboards')
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <button wire:click="$toggle('showAddWidget')" class="press" style="font-size:0.72rem;padding:0.35rem 0.8rem;background:{{ $showAddWidget ? 'transparent' : 'var(--gold)' }};color:{{ $showAddWidget ? 'var(--mist)' : 'var(--ink)' }};border:1px solid {{ $showAddWidget ? 'var(--line)' : 'var(--gold)' }};border-radius:0.35rem;font-weight:600;">
                                {{ $showAddWidget ? 'Cancel' : '+ Add widget' }}
                            </button>
                            <button wire:click="deleteDashboard({{ $active->id }})" wire:confirm="Delete '{{ $active->title }}'? All its widgets will be removed." class="press" style="font-size:0.7rem;color:var(--danger-soft);background:none;border:none;cursor:pointer;">Delete</button>
                        </div>
                    @endcan
                </div>

                @can('edit-dashboards')
                    @if($showAddWidget)
                        @if(empty($this->pickable))
                            <div style="padding:1rem 1.1rem;border-bottom:1px solid var(--line);">
                                <p style="font-size:0.8rem;color:var(--mist);margin:0;">Widgets show real metrics from connected platforms, and none is connected yet. <a href="#connected-platforms" style="color:var(--teal-soft);">Connect a platform in Connected Platforms</a>, let it send a snapshot, then add its metrics here.</p>
                            </div>
                        @else
                            <form wire:submit="addWidget" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem;align-items:end;padding:0.9rem 1.1rem;background:var(--panel);border-bottom:1px solid var(--line);">
                                <div>
                                    <label for="widget-type" class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Widget</label>
                                    <select id="widget-type" wire:model="widgetType" class="w-full rounded px-3 py-2 text-sm" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);">
                                        @foreach(\App\Livewire\Analytics\DashboardBuilderPanel::WIDGET_TYPES as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('widgetType') <p style="color:var(--danger);font-size:0.7rem;margin:0.25rem 0 0;">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="widget-metric" class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Metric</label>
                                    <select id="widget-metric" wire:model="widgetMetric" class="w-full rounded px-3 py-2 text-sm" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);">
                                        <option value="">Choose a metric</option>
                                        @foreach($this->pickable as $platformLabel => $metrics)
                                            <optgroup label="{{ $platformLabel }}">
                                                @foreach($metrics as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                    @error('widgetMetric') <p style="color:var(--danger);font-size:0.7rem;margin:0.25rem 0 0;">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="widget-title" class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Title (optional)</label>
                                    <input id="widget-title" wire:model="widgetTitle" type="text" placeholder="Defaults to the metric name" class="w-full rounded px-3 py-2 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);" />
                                    @error('widgetTitle') <p style="color:var(--danger);font-size:0.7rem;margin:0.25rem 0 0;">{{ $message }}</p> @enderror
                                </div>
                                <button type="submit" class="press" style="background:var(--gold);color:var(--ink);padding:0.55rem 1rem;border-radius:0.35rem;font-size:0.8rem;font-weight:600;">Add to dashboard</button>
                            </form>
                        @endif
                    @endif
                @endcan

                @if($widgets->isEmpty())
                    <div style="padding:1.5rem 1.1rem;">
                        <p style="font-size:0.85rem;color:var(--mist);margin:0;">
                            @can('edit-dashboards') This dashboard has no widgets. Use "+ Add widget" to pick a metric from a connected platform. @else This dashboard has no widgets yet. @endcan
                        </p>
                    </div>
                @else
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));">
                        @foreach($widgets as $widget)
                            @php $view = $this->rendered[$widget->id]; @endphp
                            <div data-widget-id="{{ $widget->id }}" data-widget-type="{{ $widget->widget_type }}" style="background:var(--ink-soft);display:flex;flex-direction:column;box-shadow:inset -1px -1px 0 var(--line);{{ $widget->widget_type === 'trend' ? 'grid-column:span 2;' : '' }}">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;padding:0.45rem 0.8rem;border-bottom:1px solid var(--line);">
                                    <span class="font-mono" style="font-size:0.6rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--mist);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $widget->title ?? 'Untitled widget' }}</span>
                                    @can('edit-dashboards')
                                        <span style="display:flex;gap:0.15rem;flex-shrink:0;">
                                            @if(! $loop->first)
                                                <button type="button" wire:click="moveWidget({{ $widget->id }}, 'earlier')" aria-label="Move {{ $widget->title }} earlier" class="press" style="background:none;border:0;color:var(--mist);cursor:pointer;font-size:0.8rem;padding:0.1rem 0.35rem;">&larr;</button>
                                            @endif
                                            @if(! $loop->last)
                                                <button type="button" wire:click="moveWidget({{ $widget->id }}, 'later')" aria-label="Move {{ $widget->title }} later" class="press" style="background:none;border:0;color:var(--mist);cursor:pointer;font-size:0.8rem;padding:0.1rem 0.35rem;">&rarr;</button>
                                            @endif
                                            <button type="button" wire:click="removeWidget({{ $widget->id }})" wire:confirm="Remove this widget?" aria-label="Remove {{ $widget->title }}" class="press" style="background:none;border:0;color:var(--mist);cursor:pointer;font-size:0.8rem;padding:0.1rem 0.35rem;">&#10005;</button>
                                        </span>
                                    @endcan
                                </div>

                                @if($view['kind'] === 'kpi')
                                    <x-metrics.kpi :tile="$view['tile']" :bare="true" />
                                @elseif($view['kind'] === 'trend')
                                    <div style="padding:0.9rem 1rem 1rem;">
                                        @if($view['series'] && count($view['series']['points']) > 0)
                                            <x-metrics.trend :series="$view['series']" />
                                        @else
                                            <p data-testid="trend" style="font-size:0.85rem;color:var(--mist);margin:0.4rem 0;font-style:italic;">no data yet for {{ $view['tile']['label'] }}</p>
                                        @endif
                                    </div>
                                @elseif($view['kind'] === 'unavailable')
                                    <div style="padding:1rem;">
                                        <p style="font-size:0.8rem;color:var(--gold-soft);margin:0;">This widget is not available: its platform is not connected, or the metric no longer exists. No number is shown. @can('edit-dashboards') Remove it, or reconnect the platform. @endcan</p>
                                        @if($view['metric_key'] !== '')<p class="font-mono" style="font-size:0.62rem;color:var(--mist);margin:0.4rem 0 0;">{{ $view['metric_key'] }}</p>@endif
                                    </div>
                                @else
                                    <div style="padding:1rem;">
                                        <p style="font-size:0.8rem;color:var(--mist);margin:0;"><strong style="color:var(--paper);">Retired widget type</strong> ({{ $widget->widget_type }}). It only ever showed its own name, never data, so it is no longer supported. @can('edit-dashboards') Remove it and add a KPI tile or trend instead. @endcan</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <div style="padding:1.25rem 1.1rem;">
                    <p style="font-size:0.85rem;color:var(--mist);margin:0;">Select a dashboard above.</p>
                </div>
            @endif
        </div>
    @endif
</div>
