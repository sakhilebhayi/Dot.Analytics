@php
    $infodot = rtrim((string) config('ecosystem.platforms.infodot.url', 'https://infodot.co.za'), '/');
    $titles = ['in_use' => 'In use', 'available' => 'Available to activate', 'attention' => 'Needs attention'];
    $order = ['attention', 'in_use', 'available'];
@endphp
<section class="bp" data-testid="business-platforms" aria-labelledby="bp-title">
    <div class="bp-head">
        <h2 id="bp-title">Your business platforms</h2>
        <p>Which Dot platforms {{ Auth::user()?->currentTeam?->name ?? 'your company' }} has. This is about access, and it is kept in InfoDot. Which platforms send figures here is a separate matter, under Sources.</p>
    </div>

    @if($loading)
        <p class="bp-note" data-testid="business-platforms-loading">Reading from InfoDot. <a href="{{ $infodot }}/platforms">See your platforms in InfoDot</a>.</p>
    @else
        @php $answer = $this->platforms; @endphp

        @if($answer['state'] === 'unlinked')
            <p class="bp-note" data-testid="business-platforms-unlinked">This workspace is not linked to a company in InfoDot, so there is nothing to read. <a href="{{ $infodot }}/platforms">Open Platforms in InfoDot</a>.</p>
        @elseif($answer['state'] === 'personal')
            <p class="bp-note" data-testid="business-platforms-personal">This is a personal workspace. It has the platforms included for everyone, and the rest need a company. <a href="{{ $infodot }}/platforms">See what a company can use in InfoDot</a>.</p>
        @elseif($answer['state'] === 'unavailable')
            <p class="bp-note" data-testid="business-platforms-unavailable">Your platforms could not be loaded just now. Nothing has changed on your account. <a href="{{ $infodot }}/platforms">See them in InfoDot</a>, or try again in a minute.</p>
        @elseif(collect($answer['groups'])->flatten(1)->isEmpty())
            <p class="bp-note" data-testid="business-platforms-empty">InfoDot lists no platforms for this company yet. <a href="{{ $infodot }}/platforms">Open Platforms in InfoDot</a>.</p>
        @else
            <div class="bp-groups">
                @foreach($order as $group)
                    @continue($answer['groups'][$group] === [])
                    <div class="bp-group" data-group="{{ $group }}">
                        <h3>{{ $titles[$group] }}</h3>
                        <ul>
                            @foreach($answer['groups'][$group] as $platform)
                                <li>
                                    <a href="{{ $platform['url'] }}" data-platform="{{ $platform['key'] }}">
                                        <span class="bp-name">{{ $platform['name'] }}</span>
                                        <span class="bp-state">{{ $platform['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <p class="bp-source">Source: InfoDot's record for this company, read {{ $answer['read_at']->diffForHumans() }}. <a href="{{ $infodot }}/platforms">All platforms in InfoDot</a></p>
        @endif
    @endif
</section>
