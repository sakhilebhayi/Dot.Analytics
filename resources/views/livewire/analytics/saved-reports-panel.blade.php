<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Saved Reports</h3>
            <p class="text-xs text-gray-500 mt-0.5">Define, run, and re-run analytics reports on demand</p>
        </div>
        <button
            wire:click="$toggle('showCreate')"
            class="text-xs px-3 py-1.5 bg-gray-800 text-white rounded-lg hover:bg-gray-700"
        >{{ $showCreate ? 'Cancel' : '+ New Report' }}</button>
    </div>

    @if($showCreate)
        <form wire:submit="create" class="mb-5 border border-gray-200 rounded-xl p-4 space-y-3 bg-gray-50">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Report name</label>
                    <input wire:model="title" type="text" placeholder="Weekly Risk Summary" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                    @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Report type</label>
                    <select wire:model="reportType" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="insights">Cross-Platform Insights</option>
                        <option value="alerts">Intelligence Alerts</option>
                        <option value="recommendations">Recommendations</option>
                        <option value="metrics">Computed Metrics</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Description (optional)</label>
                <input wire:model="description" type="text" placeholder="Brief description of this report" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">Save Report</button>
        </form>
    @endif

    @if($this->reports->isEmpty())
        <p class="text-sm text-gray-400 text-center py-6">No saved reports yet. Create one to enable scheduled runs and history tracking.</p>
    @else
        <div class="space-y-2">
            @foreach($this->reports as $report)
                @php $lastRun = $report->latestRun; @endphp
                <div class="border border-gray-100 rounded-xl p-4 flex items-center justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $report->title }}</p>
                            <span class="text-xs px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full shrink-0">
                                {{ ucfirst($report->config['report_type'] ?? 'insights') }}
                            </span>
                        </div>
                        @if($report->description)
                            <p class="text-xs text-gray-400 truncate">{{ $report->description }}</p>
                        @endif
                        @if($lastRun)
                            <p class="text-xs text-gray-400 mt-0.5">
                                Last run: {{ $lastRun->completed_at?->diffForHumans() ?? 'in progress' }}
                                &middot;
                                <span class="{{ $lastRun->status === 'completed' ? 'text-green-600' : 'text-red-500' }}">{{ $lastRun->status }}</span>
                                @if($lastRun->status === 'completed')
                                    &middot; {{ $lastRun->output['row_count'] ?? 0 }} rows
                                @endif
                            </p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if($lastRun?->status === 'completed')
                            <a
                                href="{{ route('reports.download', ['id' => $report->id]) }}"
                                class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded text-gray-600"
                                target="_blank"
                            >CSV</a>
                        @endif
                        <button
                            wire:click="run({{ $report->id }})"
                            wire:loading.attr="disabled"
                            class="text-xs px-3 py-1.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                        >
                            @if($running && $runningId === $report->id)
                                <span wire:loading wire:target="run({{ $report->id }})">Running...</span>
                                <span wire:loading.remove wire:target="run({{ $report->id }})">Run</span>
                            @else
                                Run
                            @endif
                        </button>
                        <button
                            wire:click="delete({{ $report->id }})"
                            wire:confirm="Delete '{{ $report->title }}'?"
                            class="text-xs text-red-400 hover:text-red-600 px-1"
                        >✕</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
