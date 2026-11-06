<x-app-layout title="Ask">
    <x-slot name="header">
        <p class="eyebrow">Company intelligence</p>
        <h1>Ask your own data.</h1>
        <p class="lede">Questions are answered from what your platforms have sent, and the answer says when there is nothing to say.</p>
    </x-slot>

    <div class="ws-frame">
        <div><livewire:analytics.intelligence-dashboard /></div>
    </div>
</x-app-layout>
