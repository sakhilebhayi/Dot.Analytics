<x-app-layout title="Figures">
    <x-slot name="header">
        <h1>What your platforms report.</h1>
        <p class="lede">Every number a connected platform has sent, the boards you arrange them on, and what they have raised for a decision. Nothing here is estimated or filled in: a platform's numbers appear after it sends its first daily snapshot.</p>
    </x-slot>

    <div class="ws-frame">
        <div><livewire:analytics.metrics-panel /></div>
        <div><livewire:analytics.dashboard-builder-panel /></div>

        <div class="ws-pair" id="decisions">
            <div><livewire:analytics.alerts-panel /></div>
            <div><livewire:analytics.recommendations-panel /></div>
        </div>
    </div>
</x-app-layout>
