<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Intelligence Overview</h2>
                <p class="text-sm text-gray-500 mt-0.5">Enterprise Intelligence Platform &mdash; Dot Ecosystem</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Hero KPI strip --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow p-5 border-l-4 border-blue-500">
                    <div class="text-xs font-medium text-gray-400 uppercase tracking-wide">Connected Platforms</div>
                    <div class="text-3xl font-bold text-blue-600 mt-1">{{ $connectedCount }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">of 15 available</div>
                </div>
                <div class="bg-white rounded-xl shadow p-5 border-l-4 border-indigo-500">
                    <div class="text-xs font-medium text-gray-400 uppercase tracking-wide">Active Engines</div>
                    <div class="text-3xl font-bold text-indigo-600 mt-1">{{ $activeEngineCount }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">of 17 engines</div>
                </div>
                <div class="bg-white rounded-xl shadow p-5 border-l-4 border-red-400">
                    <div class="text-xs font-medium text-gray-400 uppercase tracking-wide">Open Alerts</div>
                    <div class="text-3xl font-bold text-red-500 mt-1">{{ $openAlertCount }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">require attention</div>
                </div>
                <div class="bg-white rounded-xl shadow p-5 border-l-4 border-amber-400">
                    <div class="text-xs font-medium text-gray-400 uppercase tracking-wide">Pending Actions</div>
                    <div class="text-3xl font-bold text-amber-500 mt-1">{{ $pendingRecommendationCount }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">recommended</div>
                </div>
            </div>

            {{-- Ecosystem Intelligence Map --}}
            <livewire:analytics.ecosystem-map-panel />

            {{-- Universal Intelligence Query --}}
            <livewire:analytics.intelligence-dashboard />

            {{-- Cross-Platform Insights --}}
            <livewire:analytics.cross-platform-insight-panel />

            {{-- Alerts + Recommendations --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <livewire:analytics.alerts-panel />
                <livewire:analytics.recommendations-panel />
            </div>

            {{-- Knowledge Graph --}}
            <livewire:analytics.knowledge-graph-panel />

            {{-- Business DNA Profile --}}
            <livewire:analytics.business-dna-panel />

            {{-- Executive Intelligence Briefing --}}
            <livewire:analytics.executive-briefing-panel />

            {{-- Saved Reports --}}
            <livewire:analytics.saved-reports-panel />

            {{-- Feature Flags (admin) --}}
            @can('manage-platforms')
                <livewire:analytics.feature-flags-panel />
            @endcan

        </div>
    </div>
</x-app-layout>
