<x-app-layout title="Search">
    <x-slot name="header">
        <p class="eyebrow">Company intelligence</p>
        <h1>Search your workspace.</h1>
        <p class="lede">Alerts, insights, recommendations and reports for the company you are signed in to. The public library has its own search.</p>
    </x-slot>

    <div class="ws-frame">
        <div><livewire:analytics.search-panel :query="request('q', '')" /></div>
    </div>
</x-app-layout>
