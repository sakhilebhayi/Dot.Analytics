<x-app-layout title="Reports">
    <x-slot name="header">
        <p class="eyebrow">Company intelligence</p>
        <h1>Saved reports.</h1>
        <p class="lede">Reports you can run again, on demand or on a schedule of at most one run an hour, with the newest thirty runs kept.</p>
    </x-slot>

    <div class="ws-frame">
        <div><livewire:analytics.saved-reports-panel /></div>
    </div>
</x-app-layout>
