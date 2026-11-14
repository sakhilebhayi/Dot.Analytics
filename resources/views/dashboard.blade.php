@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $reportingNames = $reporting->pluck('label')->all();
    $waiting = $connected->where('has_data', false)->values();
    $rest = [
        ['workspace.figures', 'Figures', 'Every number your platforms have sent, with its trend, and the boards you arrange them on.'],
        ['workspace.insights', 'Insights', 'Findings drawn from more than one platform at once, each naming the numbers behind it.'],
        ['workspace.briefing', 'Briefing', 'A written summary of the period, made on request and dated.'],
        ['workspace.ask', 'Ask', 'A question answered only from what your platforms have sent.'],
        ['workspace.reports', 'Reports', 'Reports you can run again, on demand or on a schedule.'],
        ['workspace.sources', 'Sources', 'Which platforms send figures here, and how to connect another.'],
        ['me.home', 'My library', 'The public figures you have saved and the ones you watch.'],
    ];
    $severityWord = ['critical' => 'Critical', 'warning' => 'Warning', 'info' => 'For information'];
    $priorityWord = ['critical' => 'Critical', 'high' => 'High priority', 'medium' => 'Medium priority', 'low' => 'Low priority'];
@endphp
<x-app-layout title="Overview">
    <div class="ld" data-state="{{ $state }}">
        <div class="ld-mast">
            <picture>
                <source srcset="{{ asset('images/logo-light.png') }}" media="(prefers-color-scheme: dark)">
                <img class="ld-logo" src="{{ asset('images/logo.png') }}" alt="Dot.Analytics" width="1181" height="886">
            </picture>
            <nav class="ld-back" aria-label="Back to InfoDot">
                <a href="{{ $infodot }}/home">InfoDot</a>
                <a href="{{ $infodot }}/platforms">Platforms</a>
            </nav>
        </div>

        <div class="ld-top">
            <div class="ld-lead">
                <p class="ld-welcome">Welcome, {{ $firstName }}. You are reading for {{ $team->name }}.</p>
                <h1>Every figure shows its working.</h1>
                <p class="ld-say">Dot.Analytics reads the daily figures {{ $team->name }}'s Dot platforms send, and answers from them. Each number names the platform that sent it, the day it is for and when it arrived.</p>

                <div class="ld-act">
                    <a class="ld-primary" data-primary-action href="{{ route($primary['route']) }}">{{ $primary['label'] }}</a>
                    <p>{{ $primary['why'] }}</p>
                </div>
            </div>

            {{-- The working tape: the product itself. It prints stored figures only, and draws what is missing as missing. --}}
            <section class="ld-tape" aria-labelledby="ld-tape-title" data-testid="landing-tape">
                @if($state === 'reporting')
                    <h2 id="ld-tape-title">Latest to post</h2>
                    <ol class="ld-figures">
                        @foreach($figures as $figure)
                            <li data-figure="{{ $figure['key'] }}" data-state="{{ $figure['state'] }}">
                                <p class="ld-fig-label">{{ $figure['label'] }}</p>
                                <p class="ld-fig-value">{{ $figure['value'] }}</p>
                                <p class="ld-fig-move">
                                    @if($figure['delta'] !== null && $figure['direction'] !== 'flat')
                                        <svg viewBox="0 0 10 10" width="10" height="10" aria-hidden="true"><path d="{{ $figure['direction'] === 'up' ? 'M5 1 9 9H1Z' : 'M5 9 1 1h8Z' }}" fill="currentColor"/></svg>
                                        {{ $figure['direction'] === 'up' ? 'Up' : 'Down' }} {{ ltrim($figure['delta'], '+-−') }} on the report before
                                    @elseif($figure['delta'] !== null)
                                        Unchanged on the report before
                                    @else
                                        First report of this figure
                                    @endif
                                </p>
                                <p class="ld-prov">
                                    Sent by {{ $figure['platform_label'] }}, for {{ Carbon::parse($figure['as_of'])->format('j M Y') }}@if($figure['observed_at']), received {{ Carbon::parse($figure['observed_at'])->diffForHumans() }}@endif
                                    @if($figure['state'] === 'stale')<span class="ld-flag ld-stale">Out of date</span>@endif
                                    @if($figure['is_demo'])<span class="ld-flag">Demo data</span>@endif
                                </p>
                            </li>
                        @endforeach
                    </ol>
                    <p class="ld-count" data-testid="landing-sources-count">
                        {{ $reporting->count() }} of {{ $platformTotal }} platforms that can report {{ $reporting->count() === 1 ? 'is' : 'are' }} reporting data: {{ Str::of(collect($reportingNames)->join(', ', ' and ')) }}.
                        @if($waiting->isNotEmpty()) {{ $waiting->pluck('label')->join(', ', ' and ') }} {{ $waiting->count() === 1 ? 'is' : 'are' }} connected and {{ $waiting->count() === 1 ? 'has' : 'have' }} not sent a figure yet. @endif
                        Counted from this company's data sources. <a href="{{ route('workspace.figures') }}">All figures</a>
                    </p>
                @elseif($state === 'waiting')
                    <h2 id="ld-tape-title">Nothing has posted yet</h2>
                    <p class="ld-tape-say">{{ $waiting->pluck('label')->join(', ', ' and ') }} {{ $waiting->count() === 1 ? 'is' : 'are' }} connected as a data source and {{ $waiting->count() === 1 ? 'has' : 'have' }} not sent a figure. No number is shown here until one arrives.</p>
                    <div class="ld-missing" aria-hidden="true"><span></span><span></span><span></span></div>
                    <p class="ld-count">A platform posts once a day after it is given the secret issued when it was connected. <a href="{{ route('workspace.sources') }}">Sources</a></p>
                @else
                    <h2 id="ld-tape-title">No figures yet</h2>
                    <p class="ld-tape-say">No platform reports to this workspace, so no number is shown. Nothing here is estimated or filled in.</p>
                    <div class="ld-missing" aria-hidden="true"><span></span><span></span><span></span></div>
                    <p class="ld-count">When a figure arrives it carries the platform that sent it, the day it is for and when it was received.</p>
                @endif
            </section>
        </div>

        <section class="ld-block" aria-labelledby="ld-wait-title" data-testid="landing-waiting">
            <h2 id="ld-wait-title">Waiting for a decision</h2>
            @if($alerts->isEmpty() && $recommendations->isEmpty())
                <p class="ld-none">Nothing is waiting. Alerts and recommended actions appear here when your platforms' figures raise them.</p>
            @else
                <div class="ld-wait">
                    @if($alerts->isNotEmpty())
                        <div>
                            <h3>{{ $openAlertCount }} {{ Str::plural('alert', $openAlertCount) }} open @if($openAlertCount > $alerts->count())<span>newest {{ $alerts->count() }} shown</span>@endif</h3>
                            <ul>
                                @foreach($alerts as $alert)
                                    <li><a href="{{ route('workspace.figures') }}#decisions">
                                        <span class="ld-row-title">{{ $alert->title }}</span>
                                        <span class="ld-row-meta">{{ $severityWord[$alert->severity] ?? Str::headline((string) $alert->severity) }}@if($alert->triggered_at), raised {{ $alert->triggered_at->diffForHumans() }}@endif</span>
                                    </a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if($recommendations->isNotEmpty())
                        <div>
                            <h3>{{ $pendingRecommendationCount }} recommended {{ Str::plural('action', $pendingRecommendationCount) }} @if($pendingRecommendationCount > $recommendations->count())<span>newest {{ $recommendations->count() }} shown</span>@endif</h3>
                            <ul>
                                @foreach($recommendations as $recommendation)
                                    <li><a href="{{ route('workspace.figures') }}#decisions">
                                        <span class="ld-row-title">{{ $recommendation->title }}</span>
                                        <span class="ld-row-meta">{{ $priorityWord[$recommendation->priority] ?? Str::headline((string) $recommendation->priority) }}@if($recommendation->created_at), raised {{ $recommendation->created_at->diffForHumans() }}@endif</span>
                                    </a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </section>

        @if($showBusinessPlatforms)
            <div class="ld-block"><livewire:analytics.business-platforms-panel /></div>
        @endif

        <section class="ld-block" aria-labelledby="ld-rest-title">
            <h2 id="ld-rest-title">The rest of the workspace</h2>
            <ul class="ld-index">
                @foreach($rest as [$route, $label, $does])
                    <li><a href="{{ route($route) }}"><span class="ld-index-name">{{ $label }}</span><span class="ld-index-does">{{ $does }}</span></a></li>
                @endforeach
            </ul>
        </section>
    </div>
</x-app-layout>
