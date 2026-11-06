<x-app-layout title="Overview">
    <x-slot name="header">
        <p class="eyebrow">Company intelligence</p>
        <h1>{{ Auth::user()->currentTeam->name }}</h1>
        <p class="lede">What your connected Dot platforms report, and what needs a decision. Nothing on this page is estimated or filled in: a platform's numbers appear after it sends its first daily snapshot.</p>
    </x-slot>

    <div class="ws-frame">
        <div class="ws-counts" role="group" aria-label="Where the workspace stands">
            <div><div class="n">{{ $connectedCount }}</div><p class="k">Platforms connected</p><p class="s">of {{ $platformTotal }} that can report</p></div>
            <div><div class="n">{{ $activeEngineCount }}</div><p class="k">Engines running</p><p class="s">of {{ $engineTotal }} that can run</p></div>
            <div><div class="n {{ $openAlertCount > 0 ? 'alert' : '' }}">{{ $openAlertCount }}</div><p class="k">Alerts open</p><p class="s">need review</p></div>
            <div><div class="n">{{ $pendingRecommendationCount }}</div><p class="k">Actions recommended</p><p class="s">waiting for a decision</p></div>
        </div>

        <div><livewire:analytics.metrics-panel /></div>
        <div><livewire:analytics.dashboard-builder-panel /></div>
        <div id="connected-platforms"><livewire:analytics.ecosystem-map-panel /></div>

        <div class="ws-pair">
            <div><livewire:analytics.alerts-panel /></div>
            <div><livewire:analytics.recommendations-panel /></div>
        </div>
    </div>
</x-app-layout>
