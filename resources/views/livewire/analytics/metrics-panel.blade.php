@php
    $dash = $this->dashboard;
    $summary = $dash['summary'];
    $connected = collect($dash['platforms'])->where('connected', true);
    $notConnected = collect($dash['platforms'])->where('connected', false);
    $series = $this->series;
@endphp
<div id="metrics" data-testid="metrics-panel" style="padding:2rem 2.25rem;">
    <div style="margin-bottom:1.25rem;">
        <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.35rem;">Platform Metrics</p>
        <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">What your platforms report</h3>
    </div>

    {{-- State bar: the answer in words first, the counts behind it. --}}
    <div data-testid="metrics-state" style="display:flex;flex-wrap:wrap;align-items:baseline;gap:0.5rem 1.5rem;padding:0.75rem 1rem;border:1px solid var(--line);border-bottom:0;background:var(--panel);">
        <span class="font-display" style="font-size:0.95rem;font-weight:600;color:{{ $summary['open_anomalies'] > 0 ? 'var(--danger-soft)' : ($summary['stale_metrics'] > 0 ? 'var(--gold-soft)' : 'var(--paper)') }};">{{ $summary['headline'] }}</span>
        <span class="font-mono" style="font-size:0.66rem;color:var(--mist);letter-spacing:0.04em;">
            {{ $summary['connected'] }} of {{ count($dash['platforms']) }} platforms connected
            &middot; {{ $summary['reporting_platforms'] }} reporting
            &middot; {{ $summary['metrics_reporting'] }} {{ \Illuminate\Support\Str::plural('metric', $summary['metrics_reporting']) }} with data
            @if($summary['has_demo']) &middot; <span style="color:var(--gold-soft);">includes DEMO DATA</span> @endif
        </span>
    </div>

    @if($connected->isEmpty())
        <div data-testid="metrics-empty" style="border:1px solid var(--line);padding:1.5rem 1.25rem;background:var(--ink-soft);">
            <p style="font-size:0.9rem;font-weight:600;color:var(--paper);margin:0 0 0.4rem;">No platform is connected, so there are no numbers to show.</p>
            <p style="font-size:0.8rem;color:var(--mist);margin:0 0 1rem;max-width:62ch;line-height:1.55;">
                Nothing here is estimated or filled in. A platform's numbers appear after two things happen: it is connected, and it sends its first signed daily snapshot.
            </p>
            <ol style="margin:0 0 1.25rem;padding-left:1.1rem;font-size:0.8rem;color:var(--paper);line-height:1.7;">
                <li>
                    @can('manage-platforms')
                        <a href="#connected-platforms" style="color:var(--teal-soft);">Connect a platform in Connected Platforms</a> below. You are shown its webhook secret once.
                    @else
                        Ask an owner or admin to connect a platform; they are shown its webhook secret once.
                    @endcan
                </li>
                <li>Give the platform that secret. It signs and posts a snapshot of its own headline numbers every day.</li>
            </ol>
            <p style="font-size:0.72rem;color:var(--mist);margin:0 0 1.25rem;">Platforms that can report: {{ $notConnected->pluck('label')->implode(', ') }}.</p>
            @include('livewire.analytics.partials.metrics-contract', ['open' => true])
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:1.5rem;margin-top:0;border:1px solid var(--line);">
            @foreach($connected as $platform)
                <section data-platform="{{ $platform['key'] }}">
                    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;padding:0.7rem 1rem;background:var(--panel);border-bottom:1px solid var(--line);">
                        <h4 class="font-display" style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $platform['label'] }}</h4>
                        <span class="font-mono" style="font-size:0.62rem;color:var(--mist);">
                            @if($platform['last_synced_at'])
                                last snapshot received {{ \Illuminate\Support\Carbon::parse($platform['last_synced_at'])->diffForHumans() }}
                            @else
                                has not sent a snapshot yet
                            @endif
                        </span>
                    </div>
                    @unless($platform['has_data'])
                        <p style="font-size:0.78rem;color:var(--mist);margin:0;padding:0.7rem 1rem;background:var(--ink-soft);border-bottom:1px solid var(--line);">
                            {{ $platform['label'] }} is connected and has not sent a snapshot yet. Until it does, every metric below stays at "no data yet".
                        </p>
                    @endunless
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));">
                        @foreach($platform['tiles'] as $tile)
                            <x-metrics.kpi :tile="$tile" :selectable="true" :selected="$this->selectedKey === $tile['key']" />
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        @if($notConnected->isNotEmpty())
            <p style="font-size:0.72rem;color:var(--mist);margin:0.75rem 0 0;">
                Not connected: {{ $notConnected->pluck('label')->implode(', ') }}.
                @can('manage-platforms')
                    <a href="#connected-platforms" style="color:var(--teal-soft);">Connect a platform in Connected Platforms</a>.
                @else
                    Ask an owner or admin to connect them.
                @endcan
            </p>
        @endif

        {{-- Trend --}}
        <div data-testid="trend-panel" style="margin-top:1.5rem;border:1px solid var(--line);background:var(--ink-soft);">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:0.75rem;padding:0.7rem 1rem;background:var(--panel);border-bottom:1px solid var(--line);">
                <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                    <h4 class="font-display" style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">Trend</h4>
                    <label class="sr-only" for="metrics-trend-select" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);">Metric</label>
                    <select id="metrics-trend-select" wire:model.live="selectedKey" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);font-size:0.75rem;padding:0.25rem 0.5rem;border-radius:0.3rem;max-width:100%;">
                        <option value="">Choose a metric</option>
                        @foreach($this->pickable as $platformLabel => $metrics)
                            <optgroup label="{{ $platformLabel }}">
                                @foreach($metrics as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div role="group" aria-label="Trend range" style="display:flex;gap:0.25rem;">
                    @foreach(\App\Livewire\Analytics\MetricsPanel::RANGES as $range)
                        <button type="button" wire:click="setDays({{ $range }})" aria-pressed="{{ $days === $range ? 'true' : 'false' }}" class="press font-mono"
                                style="font-size:0.66rem;padding:0.2rem 0.55rem;border-radius:0.3rem;background:{{ $days === $range ? 'rgba(241,198,46,0.1)' : 'transparent' }};color:{{ $days === $range ? 'var(--gold)' : 'var(--mist)' }};border:1px solid {{ $days === $range ? 'var(--gold)' : 'var(--line)' }};">{{ $range }}d</button>
                    @endforeach
                </div>
            </div>
            <div style="padding:1rem 1.1rem 1.1rem;">
                @if($this->selectedKey === '')
                    <p style="font-size:0.85rem;color:var(--mist);margin:0.5rem 0;">No metric has reported yet. Pick any metric above to see its stored points once it does.</p>
                @elseif($series === null)
                    <p style="font-size:0.85rem;color:var(--mist);margin:0.5rem 0;">That metric is not available.</p>
                @elseif(count($series['points']) === 0)
                    <p data-testid="trend-empty" style="font-size:0.85rem;color:var(--mist);margin:0.5rem 0;">No data yet for {{ $series['label'] }}. {{ $series['label'] }} has not been reported in the last {{ $days }} days.</p>
                @else
                    <p class="font-mono" style="font-size:0.66rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--mist);margin:0 0 0.5rem;">{{ $series['label'] }} &middot; last {{ $days }} days</p>
                    <x-metrics.trend :series="$series" />
                @endif
            </div>
        </div>

        <div style="margin-top:1rem;">@include('livewire.analytics.partials.metrics-contract', ['open' => false])</div>
    @endif
</div>
