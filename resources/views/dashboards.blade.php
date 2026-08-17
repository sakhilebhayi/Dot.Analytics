<x-app-layout>
    <x-slot name="header">
        <p class="font-mono" style="font-size:0.7rem;letter-spacing:0.18em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.6rem;">My Dashboards</p>
        <h1 class="font-display" style="font-size:1.75rem;font-weight:700;color:var(--paper);letter-spacing:-0.01em;margin:0;">Custom Intelligence Views</h1>
    </x-slot>

    <div style="padding:2rem 2.5rem 4rem;">
        <div style="max-width:1180px;margin:0 auto;border:1px solid var(--line);border-radius:14px;overflow:hidden;background:var(--ink-soft);">
            <livewire:analytics.dashboard-builder-panel />
        </div>
    </div>
</x-app-layout>
