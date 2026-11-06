<x-app-layout title="Briefing">
    <x-slot name="header">
        <p class="eyebrow">Company intelligence</p>
        <h1>Your briefing.</h1>
        <p class="lede">A written summary of the period, built from your connected platforms. It is generated on request and shows when it was made.</p>
    </x-slot>

    <div class="ws-frame">
        <div><livewire:analytics.executive-briefing-panel /></div>
    </div>
</x-app-layout>
