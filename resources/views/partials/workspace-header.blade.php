@php
    $user = Auth::user();
    $team = $user->currentTeam;
    $unread = 0;
    $nav = [
        ['label' => 'Overview', 'href' => route('dashboard'), 'current' => request()->routeIs('dashboard')],
        ['label' => 'Figures', 'href' => route('workspace.figures'), 'current' => request()->routeIs('workspace.figures')],
        ['label' => 'Insights', 'href' => route('workspace.insights'), 'current' => request()->routeIs('workspace.insights')],
        ['label' => 'Reports', 'href' => route('workspace.reports'), 'current' => request()->routeIs('workspace.reports')],
        ['label' => 'Explore', 'items' => [
            ['label' => 'Briefing', 'href' => route('workspace.briefing'), 'current' => request()->routeIs('workspace.briefing')],
            ['label' => 'Ask', 'href' => route('workspace.ask'), 'current' => request()->routeIs('workspace.ask')],
            ['label' => 'Sources', 'href' => route('workspace.sources'), 'current' => request()->routeIs('workspace.sources')],
            ['label' => 'My library', 'href' => route('me.home'), 'current' => request()->routeIs('me.*')],
        ]],
    ];
    $links = [];
    if ($team) {
        $links[] = ['label' => 'Team settings', 'href' => route('teams.show', $team->id), 'current' => request()->routeIs('teams.show')];
        if (Gate::allows('create', Laravel\Jetstream\Jetstream::newTeamModel())) {
            $links[] = ['label' => 'Create new team', 'href' => route('teams.create')];
        }
        if ($user->allTeams()->count() > 1) {
            $links[] = ['heading' => 'Switch team'];
            foreach ($user->allTeams() as $t) {
                $links[] = ['label' => $t->name, 'current' => $user->isCurrentTeam($t), 'form' => ['action' => route('current-team.update'), 'method' => 'PUT', 'fields' => ['team_id' => $t->id]]];
            }
        }
        $links[] = ['rule' => true];
    }
    $links[] = ['label' => 'Profile', 'href' => route('profile.show'), 'current' => request()->routeIs('profile.*')];
    if (Laravel\Jetstream\Jetstream::hasApiFeatures()) {
        $links[] = ['label' => 'API tokens', 'href' => route('api-tokens.index'), 'current' => request()->routeIs('api-tokens.*')];
    }
    $account = [
        'name' => $user->name,
        'email' => $user->email,
        'team' => $team?->name,
        'links' => $links,
        'signOut' => ['action' => route('logout')],
    ];
@endphp
{{-- The shared Dot header (dot-ui 0.6.0): one row, the search centred by the header's grid on every page, the primary items with the
     rest grouped, the platform switcher and the bell on the right, the account menu (team switch included) behind the avatar. --}}
<x-dot.header id="dh" brand-href="{{ route('dashboard') }}" brand-label="Dot.Analytics company intelligence" :nav="$nav" :account="$account" :context="$team?->name" skip="#main">
    <x-slot:brand>
        <img src="{{ asset('images/mark.png') }}" alt="" width="26" height="26">
        <span class="dh-brand__name">Dot.Analytics</span>
    </x-slot:brand>
    <x-slot:search>
        <livewire:analytics.header-search :query="is_string(request('q')) ? request('q') : ''" />
    </x-slot:search>
    <x-slot:tools>
        <x-dot.switcher platform="analytics" :company="$team && ! $team->personal_team ? $team->infodot_team_uuid : null" :company-name="$team?->name" logos="/dot-ui/img/platforms" />
        <livewire:analytics.notification-bell />
    </x-slot:tools>
</x-dot.header>
