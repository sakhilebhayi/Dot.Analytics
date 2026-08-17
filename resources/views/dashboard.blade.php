<x-app-layout>
    <x-slot name="header">
        <p class="font-mono" style="font-size:0.7rem;letter-spacing:0.18em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.6rem;">Intelligence Overview</p>
        <h1 class="font-display" style="font-size:1.75rem;font-weight:700;color:var(--paper);letter-spacing:-0.01em;margin:0;">Enterprise Intelligence — Dot Ecosystem</h1>
    </x-slot>

    <div style="padding:2rem 2.5rem 4rem;">
        <div style="max-width:1180px;margin:0 auto;display:flex;flex-direction:column;gap:1px;background:var(--line);border:1px solid var(--line);border-radius:14px;overflow:hidden;">

            {{-- Signature KPI strip: platforms + engines feed into alerts +
                 actions, drawn as a small node graph (gold nodes, teal
                 edges) — the same visual grammar as the welcome page's
                 hero signature, which echoes this platform's own
                 IntelligenceNode/IntelligenceEdge model. Not decorative:
                 the arrows genuinely encode "these two numbers produce
                 those two numbers." --}}
            <div style="background:var(--ink-soft);padding:2rem 2.25rem;">
                <svg viewBox="0 0 760 130" style="width:100%;height:auto;max-width:720px;display:block;margin:0 auto;" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <g stroke="var(--teal)" stroke-width="1.25" opacity="0.45">
                        <line x1="70" y1="30" x2="380" y2="65" />
                        <line x1="70" y1="100" x2="380" y2="65" />
                        <line x1="380" y1="65" x2="690" y2="30" />
                        <line x1="380" y1="65" x2="690" y2="100" />
                    </g>
                    <circle cx="380" cy="65" r="5" fill="var(--teal-soft)" />
                    <circle cx="70" cy="30" r="4" fill="var(--gold)" />
                    <circle cx="70" cy="100" r="4" fill="var(--gold)" />
                    <circle cx="690" cy="30" r="4" fill="var(--gold)" />
                    <circle cx="690" cy="100" r="4" fill="var(--gold)" />
                </svg>

                <div class="grid grid-cols-2 md:grid-cols-4" style="max-width:720px;margin:0.5rem auto 0;gap:1.5rem;text-align:center;">
                    <div>
                        <div class="font-display" style="font-size:2rem;font-weight:700;color:var(--paper);line-height:1;">{{ $connectedCount }}</div>
                        <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--gold-soft);margin:0.5rem 0 0.2rem;">Platforms</p>
                        <p style="font-size:0.72rem;color:var(--mist);margin:0;">of 15 connected</p>
                    </div>
                    <div>
                        <div class="font-display" style="font-size:2rem;font-weight:700;color:var(--paper);line-height:1;">{{ $activeEngineCount }}</div>
                        <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--gold-soft);margin:0.5rem 0 0.2rem;">Engines</p>
                        <p style="font-size:0.72rem;color:var(--mist);margin:0;">of 17 active</p>
                    </div>
                    <div>
                        <div class="font-display" style="font-size:2rem;font-weight:700;color:{{ $openAlertCount > 0 ? '#f08a6c' : 'var(--paper)' }};line-height:1;">{{ $openAlertCount }}</div>
                        <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--teal-soft);margin:0.5rem 0 0.2rem;">Alerts</p>
                        <p style="font-size:0.72rem;color:var(--mist);margin:0;">open, need review</p>
                    </div>
                    <div>
                        <div class="font-display" style="font-size:2rem;font-weight:700;color:var(--paper);line-height:1;">{{ $pendingRecommendationCount }}</div>
                        <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--teal-soft);margin:0.5rem 0 0.2rem;">Actions</p>
                        <p style="font-size:0.72rem;color:var(--mist);margin:0;">recommended</p>
                    </div>
                </div>
            </div>

            {{-- Ecosystem Intelligence Map --}}
            <div style="background:var(--ink-soft);">
                <livewire:analytics.ecosystem-map-panel />
            </div>

            {{-- Universal Intelligence Query --}}
            <div style="background:var(--ink-soft);">
                <livewire:analytics.intelligence-dashboard />
            </div>

            {{-- Cross-Platform Insights --}}
            <div style="background:var(--ink-soft);">
                <livewire:analytics.cross-platform-insight-panel />
            </div>

            {{-- Alerts + Recommendations --}}
            <div class="grid grid-cols-1 lg:grid-cols-2" style="gap:1px;background:var(--line);">
                <div style="background:var(--ink-soft);">
                    <livewire:analytics.alerts-panel />
                </div>
                <div style="background:var(--ink-soft);">
                    <livewire:analytics.recommendations-panel />
                </div>
            </div>

            {{-- Knowledge Graph --}}
            <div style="background:var(--ink-soft);">
                <livewire:analytics.knowledge-graph-panel />
            </div>

            {{-- Business DNA Profile --}}
            <div style="background:var(--ink-soft);">
                <livewire:analytics.business-dna-panel />
            </div>

            {{-- Executive Intelligence Briefing --}}
            <div style="background:var(--ink-soft);">
                <livewire:analytics.executive-briefing-panel />
            </div>

            {{-- Saved Reports --}}
            <div style="background:var(--ink-soft);">
                <livewire:analytics.saved-reports-panel />
            </div>

            {{-- Feature Flags (admin) --}}
            @can('manage-platforms')
                <div style="background:var(--ink-soft);">
                    <livewire:analytics.feature-flags-panel />
                </div>
            @endcan

        </div>
    </div>
</x-app-layout>
