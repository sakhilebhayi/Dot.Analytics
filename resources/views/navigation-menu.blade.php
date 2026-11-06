@php
    $items = [
        ['dashboard', 'Overview', 'dashboard'],
        ['workspace.insights', 'Insights', 'workspace.insights'],
        ['workspace.briefing', 'Briefing', 'workspace.briefing'],
        ['workspace.ask', 'Ask', 'workspace.ask'],
        ['workspace.reports', 'Reports', 'workspace.reports'],
    ];
    $team = Auth::user()->currentTeam;
@endphp
<header class="ws-top">
    <div class="ws-in">
        <a class="ws-brand" href="{{ route('dashboard') }}" aria-label="Dot.Analytics company intelligence"><img src="{{ asset('images/mark.png') }}" alt="" width="26" height="26"><span>Dot.Analytics</span></a>

        <nav class="ws-nav" aria-label="Company intelligence">
            @foreach($items as [$route, $label, $match])
                <a href="{{ route($route) }}" @if(request()->routeIs($match)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            <a href="{{ route('me.home') }}">My library</a>
        </nav>

        <div class="ws-tools">
            <livewire:analytics.notification-bell />

            @if (Laravel\Jetstream\Jetstream::hasTeamFeatures() && $team)
                <div class="relative ws-team">
                    <x-dropdown align="right" width="60">
                        <x-slot name="trigger">
                            <button type="button" class="press font-mono inline-flex items-center px-3" style="min-height:40px;font-size:13px;color:var(--mist);background:transparent;border:1px solid var(--line);border-radius:4px;">
                                {{ $team->name }}
                                <svg class="ms-2 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" /></svg>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <div class="w-60">
                                <div class="block px-4 py-2 text-xs text-gray-500">{{ __('Manage Team') }}</div>
                                <x-dropdown-link href="{{ route('teams.show', $team->id) }}">{{ __('Team Settings') }}</x-dropdown-link>
                                @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                                    <x-dropdown-link href="{{ route('teams.create') }}">{{ __('Create New Team') }}</x-dropdown-link>
                                @endcan
                                @if (Auth::user()->allTeams()->count() > 1)
                                    <div class="border-t border-gray-200"></div>
                                    <div class="block px-4 py-2 text-xs text-gray-500">{{ __('Switch Teams') }}</div>
                                    @foreach (Auth::user()->allTeams() as $t)
                                        <x-switchable-team :team="$t" />
                                    @endforeach
                                @endif
                            </div>
                        </x-slot>
                    </x-dropdown>
                </div>
            @endif

            <form class="ws-search" method="GET" action="{{ route('search') }}" role="search">
                <input type="search" name="q" aria-label="Search your company's intelligence" maxlength="100" placeholder="Search…" value="{{ request('q') }}">
            </form>

            <div class="relative ws-user">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" class="press" aria-label="Account" style="width:40px;height:40px;border-radius:9999px;background:var(--paper);color:var(--ink);font:700 14px/1 var(--font-display);">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="block px-4 py-2 text-xs text-gray-500">{{ __('Manage Account') }}</div>
                        <x-dropdown-link href="{{ route('profile.show') }}">{{ __('Profile') }}</x-dropdown-link>
                        @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                            <x-dropdown-link href="{{ route('api-tokens.index') }}">{{ __('API Tokens') }}</x-dropdown-link>
                        @endif
                        <div class="border-t border-gray-200"></div>
                        <form method="POST" action="{{ route('logout') }}" x-data>
                            @csrf
                            <x-dropdown-link href="{{ route('logout') }}" @click.prevent="$root.submit();">{{ __('Log Out') }}</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>

        <details class="ws-menu">
            <summary>Menu</summary>
            <div class="ws-menu-panel">
                @foreach($items as [$route, $label, $match])<a href="{{ route($route) }}" @if(request()->routeIs($match)) aria-current="page" @endif>{{ $label }}</a>@endforeach
                <a href="{{ route('me.home') }}">My library</a>
                <a href="{{ route('search') }}">Search</a>
                @if($team)<a href="{{ route('teams.show', $team->id) }}">Team settings</a>@endif
                <a href="{{ route('profile.show') }}">Profile</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Log out</button></form>
            </div>
        </details>
    </div>
</header>
