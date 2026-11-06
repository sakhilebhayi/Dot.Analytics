<x-app-layout title="Insights">
    <x-slot name="header">
        <p class="eyebrow">Company intelligence</p>
        <h1>What the platforms say together.</h1>
        <p class="lede">Findings drawn from more than one platform at once, and the profile of how your business tends to behave. Each finding names the numbers behind it.</p>
    </x-slot>

    <div class="ws-frame">
        <div><livewire:analytics.cross-platform-insight-panel /></div>
        <div><livewire:analytics.business-dna-panel /></div>
    </div>
</x-app-layout>
