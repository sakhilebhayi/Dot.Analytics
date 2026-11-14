<x-app-layout title="Sources">
    <x-slot name="header">
        <h1>Where your figures come from.</h1>
        <p class="lede">The Dot platforms that can send this company's figures here, and which of them do. Connecting a platform as a data source is separate from having access to it: access is managed in InfoDot.</p>
    </x-slot>

    <div class="ws-frame">
        <div id="connected-platforms"><livewire:analytics.ecosystem-map-panel /></div>
    </div>
</x-app-layout>
