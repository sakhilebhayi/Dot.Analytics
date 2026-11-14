@php
    use App\Support\MetricFormat;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $reportingNames = $reporting->pluck('label')->all();
    $waiting = $connected->where('has_data', false)->values();
    $severityWord = ['critical' => 'Critical', 'warning' => 'Warning', 'info' => 'For information'];
    $priorityWord = ['critical' => 'Critical', 'high' => 'High priority', 'medium' => 'Medium priority', 'low' => 'Low priority'];
    $arrow = ['up' => 'M5 1 9 9H1Z', 'down' => 'M5 9 1 1h8Z', 'flat' => 'M1 4h8v2H1Z'];
    $moveWord = fn (array $t) => match (true) {
        $t['delta'] === null => 'First report',
        $t['direction'] === 'flat' => 'Unchanged',
        default => ($t['direction'] === 'up' ? 'Up ' : 'Down ').ltrim($t['delta'], '+-−'),
    };
    $moveShape = fn (array $t) => $arrow[$t['delta'] === null ? 'flat' : ($t['direction'] ?? 'flat')];
    $kind = fn (array $t) => match ($t['type']) {
        'cents' => 'Money'.($t['currency'] ? ' in '.$t['currency'] : '').', daily',
        'quantity' => 'Quantity, daily',
        'rating' => 'Rating out of 5, daily',
        default => 'Whole number, daily',
    };
    $when = fn (array $t) => Carbon::parse($t['as_of'])->format('j M').($t['observed_at'] ? ', '.Carbon::parse($t['observed_at'])->diffForHumans() : '');
@endphp
<x-app-layout title="Overview">
    <div class="ld" data-state="{{ $state }}">
        {{-- Arrival: the logo, a headline that says what is true now, the one filled action. --}}
        <section class="arrive" aria-label="Where things stand">
            <picture>
                <source srcset="{{ asset('images/logo-light.png') }}" media="(prefers-color-scheme: dark)">
                <img src="{{ asset('images/logo.png') }}" alt="Dot.Analytics" width="1181" height="886">
            </picture>
            <div>
                <h1>{{ $summary['headline'] }}</h1>
                @if($state === 'reporting')
                    <p class="say">{{ $team->name }}: {{ $reporting->count() }} of {{ $platformTotal }} platforms that can report {{ $reporting->count() === 1 ? 'is' : 'are' }} reporting.@if($newestObservedAt) The newest figure arrived {{ Carbon::parse($newestObservedAt)->diffForHumans() }}.@endif</p>
                @else
                    <p class="say">{{ $primary['why'] }}</p>
                @endif
            </div>
            <div class="act">
                <a class="primary" data-primary-action href="{{ route($primary['route']) }}">{{ $primary['label'] }}</a>
                <nav class="back" aria-label="Back to InfoDot">
                    <a href="{{ $infodot }}/home">InfoDot</a>
                    <a href="{{ $infodot }}/platforms">Platforms</a>
                </nav>
            </div>
            <div class="days" aria-hidden="true"></div>
        </section>

        <div class="desk">
            <section class="ledger" aria-labelledby="ld-ledger-title">
                <div class="head">
                    <h2 id="ld-ledger-title">Latest figures @if($hasDemo)<span class="chip">Demo data</span>@endif</h2>
                    @if($state === 'reporting')<a href="{{ route('workspace.figures') }}">All {{ $figureCount }} figures</a>@endif
                </div>
                @if($state === 'reporting')
                    <table>
                        <thead><tr>
                            <th scope="col" data-sort="Fig">Figure</th>
                            <th scope="col" class="r" data-sort="Val">Latest</th>
                            <th scope="col" class="r hide-sm">Change</th>
                            <th scope="col" class="hide-sm" data-sort="For">Reported</th>
                        </tr></thead>
                        <tbody>
                            @foreach($figures as $figure)
                                <tr data-figure="{{ $figure['key'] }}" data-state="{{ $figure['state'] }}" @if($selected && $selected['key'] === $figure['key']) aria-current="true" @endif
                                    @class(['is-stale' => $figure['state'] === 'stale']) data-s-fig="{{ $figure['label'] }}" data-s-val="{{ $figure['raw'] }}" data-s-for="{{ $figure['as_of'] }}">
                                    <td class="fig-cell"><a href="{{ route('dashboard', ['figure' => $figure['key']]) }}#working">{{ $figure['label'] }}</a><small>{{ $figure['platform_label'] }}<span class="sm-only">, {{ Carbon::parse($figure['as_of'])->format('j M') }}</span></small></td>
                                    <td class="val">{{ $figure['value'] }}@if($figure['state'] === 'stale')<span class="chip stale">Out of date</span>@endif<small><svg viewBox="0 0 10 10" width="10" height="10" aria-hidden="true"><path d="{{ $moveShape($figure) }}" fill="currentColor"/></svg>{{ $moveWord($figure) }}</small></td>
                                    <td class="chg hide-sm"><svg viewBox="0 0 10 10" width="10" height="10" aria-hidden="true"><path d="{{ $moveShape($figure) }}" fill="currentColor"/></svg>{{ $moveWord($figure) }}</td>
                                    <td class="soft hide-sm">{{ $when($figure) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="more" data-testid="landing-sources-count">
                        {{ $reporting->count() }} of {{ $platformTotal }} platforms that can report {{ $reporting->count() === 1 ? 'is' : 'are' }} reporting data: {{ collect($reportingNames)->join(', ', ' and ') }}.
                        @if($waiting->isNotEmpty()) {{ $waiting->pluck('label')->join(', ', ' and ') }} {{ $waiting->count() === 1 ? 'is' : 'are' }} connected and {{ $waiting->count() === 1 ? 'has' : 'have' }} not sent a figure yet. @endif
                        Counted from this company's data sources.
                        @if($notConnected->isNotEmpty())
                            @if($canConnect) <a href="{{ route('workspace.sources') }}">Sources</a> shows what else can be connected. @else An owner or admin connects more platforms. @endif
                        @endif
                    </p>
                @else
                    <div class="missing" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                    <p class="none soft">
                        @if($state === 'waiting')
                            No figure to list yet.
                        @else
                            No platform reports to this workspace, so no number is shown.
                        @endif
                    </p>
                @endif
            </section>

            {{-- The stage: the selected figure, its chart and its working (the dark tape, this product's signature object). --}}
            <section class="stage" id="working" aria-labelledby="ld-stage-title" data-testid="landing-tape">
                @if($state === 'reporting' && $selected)
                    @php
                        $pts = $series['points'] ?? [];
                        $before = count($pts) > 1 ? $pts[count($pts) - 2] : null;
                        $beforeText = $selected['previous'] !== null ? $selected['previous'].(($before && MetricFormat::value($before['value'], $selected['type'], $selected['currency']) === $selected['previous']) ? ' on '.Carbon::parse($before['date'])->format('j M Y') : '') : null;
                    @endphp
                    <h2 id="ld-stage-title">{{ $selected['label'] }}</h2>
                    <p class="fig">{{ $selected['value'] }}</p>
                    <p class="move">
                        @if($selected['delta'] !== null && $selected['direction'] !== 'flat')
                            <svg viewBox="0 0 10 10" width="10" height="10" aria-hidden="true"><path d="{{ $moveShape($selected) }}" fill="currentColor"/></svg>
                            {{ $moveWord($selected) }}@if($selected['delta_pct']) ({{ $selected['delta_pct'] }})@endif on the report before.
                        @elseif($selected['delta'] !== null)
                            Unchanged on the report before.
                        @else
                            First report of this figure.
                        @endif
                    </p>
                    @if($series)<x-metrics.stage-chart :series="$series" />@endif
                    <div class="tape" role="group" aria-labelledby="ld-tape-title">
                        <h3 id="ld-tape-title">Show working</h3>
                        <dl>
                            <div class="row"><dt>Sent by</dt><dd>{{ $selected['platform_label'] }}</dd></div>
                            <div class="row"><dt>For the day</dt><dd>{{ Carbon::parse($selected['as_of'])->format('j M Y') }}</dd></div>
                            @if($selected['observed_at'])<div class="row"><dt>Received</dt><dd>{{ Carbon::parse($selected['observed_at'])->diffForHumans() }}</dd></div>@endif
                            <div class="row"><dt>Report before</dt><dd>{{ $beforeText ?? 'None: this is the first report' }}</dd></div>
                            <div class="row"><dt>Kind</dt><dd>{{ $kind($selected) }}</dd></div>
                            <div class="row"><dt>Data</dt><dd>{{ $selected['is_demo'] ? 'Demo data' : 'Reported by the platform' }}@if($selected['state'] === 'stale') <span class="chip stale">Out of date</span>@endif</dd></div>
                        </dl>
                        @if($series && count($pts) > 0)
                            <details data-testid="trend-values">
                                <summary>Values as a table</summary>
                                <table>
                                    <thead><tr><th scope="col">Date</th><th scope="col" class="n">{{ $series['label'] }}</th></tr></thead>
                                    <tbody>
                                        @foreach(array_reverse($pts) as $p)
                                            <tr><td>{{ Carbon::parse($p['date'])->format('j M Y') }}</td><td class="n">{{ MetricFormat::value($p['value'], $series['type'], $series['currency']) }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </details>
                        @endif
                    </div>
                    <div class="btns"><a class="btn" href="{{ route('workspace.figures') }}">Open in Figures</a></div>
                @elseif($state === 'waiting')
                    <h2 id="ld-stage-title">Nothing has posted yet</h2>
                    <p class="none">{{ $waiting->pluck('label')->join(', ', ' and ') }} {{ $waiting->count() === 1 ? 'is' : 'are' }} connected as a data source and {{ $waiting->count() === 1 ? 'has' : 'have' }} not sent a figure. No number is shown here until one arrives.</p>
                    <div class="tape">
                        <h3>What every figure will carry</h3>
                        <ul class="expect"><li><span>Sent by</span><span>The platform that reported it</span></li><li><span>For the day</span><span>The day the figure describes</span></li><li><span>Received</span><span>When it arrived here</span></li><li><span>Report before</span><span>The last value, to show the change</span></li></ul>
                        <p class="none">A platform posts once a day after it is given the secret issued when it was connected. <a href="{{ route('workspace.sources') }}">Sources</a></p>
                    </div>
                @else
                    <h2 id="ld-stage-title">No figures yet</h2>
                    <p class="none">No platform reports to this workspace, so no number is shown. Nothing here is estimated or filled in.</p>
                    <div class="tape">
                        <h3>What every figure will carry</h3>
                        <ul class="expect"><li><span>Sent by</span><span>The platform that reported it</span></li><li><span>For the day</span><span>The day the figure describes</span></li><li><span>Received</span><span>When it arrived here</span></li><li><span>Report before</span><span>The last value, to show the change</span></li></ul>
                        <p class="none">When a figure arrives it carries all of this.</p>
                    </div>
                @endif
            </section>
        </div>

        <div class="lower">
            <section aria-labelledby="ld-wait-title" data-testid="landing-waiting">
                <div class="head"><h2 id="ld-wait-title">Waiting for a decision</h2>@if($alerts->isNotEmpty() || $recommendations->isNotEmpty())<a href="{{ route('workspace.figures') }}#decisions">Open the list</a>@endif</div>
                @if($alerts->isEmpty() && $recommendations->isEmpty())
                    <p class="none soft">Nothing is waiting. Alerts and recommended actions appear here when your platforms' figures raise them.</p>
                @else
                    <div class="wgrid">
                        @if($alerts->isNotEmpty())
                            <div>
                                <h3>{{ $openAlertCount }} {{ Str::plural('alert', $openAlertCount) }} open @if($openAlertCount > $alerts->count())<span>newest {{ $alerts->count() }} shown</span>@endif</h3>
                                <ul class="rows">
                                    @foreach($alerts as $alert)
                                        <li><a href="{{ route('workspace.figures') }}#decisions"><span class="t">{{ $alert->title }}</span><span class="m"><span class="sev">{{ $severityWord[$alert->severity] ?? Str::headline((string) $alert->severity) }}</span>@if($alert->triggered_at), raised {{ $alert->triggered_at->diffForHumans() }}@endif</span></a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if($recommendations->isNotEmpty())
                            <div>
                                <h3>{{ $pendingRecommendationCount }} recommended {{ Str::plural('action', $pendingRecommendationCount) }} @if($pendingRecommendationCount > $recommendations->count())<span>newest {{ $recommendations->count() }} shown</span>@endif</h3>
                                <ul class="rows">
                                    @foreach($recommendations as $recommendation)
                                        <li><a href="{{ route('workspace.figures') }}#decisions"><span class="t">{{ $recommendation->title }}</span><span class="m">{{ $priorityWord[$recommendation->priority] ?? Str::headline((string) $recommendation->priority) }}@if($recommendation->created_at), raised {{ $recommendation->created_at->diffForHumans() }}@endif</span></a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
            </section>
            @if($showBusinessPlatforms)
                <div><livewire:analytics.business-platforms-panel /></div>
            @endif
        </div>

        <p class="foot">
            @if($state === 'nothing-connected')
                <span>Nothing is connected to this workspace yet.</span>
            @else
                <span><b>{{ $reporting->count() }}</b> of {{ $platformTotal }} platforms reporting</span>
                <span><b>{{ $summary['metrics_reporting'] }}</b> {{ Str::plural('figure', $summary['metrics_reporting']) }}, <b>{{ $summary['stale_metrics'] }}</b> out of date</span>
                <span><b>{{ $openAlertCount }}</b> {{ Str::plural('alert', $openAlertCount) }} open</span>
            @endif
            <span>Counted from this company's data sources.</span>
        </p>
    </div>
    @vite(['resources/js/dashboard.js'])
</x-app-layout>
