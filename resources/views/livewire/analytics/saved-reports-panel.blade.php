<div style="padding:2rem 2.25rem;">
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--gold-soft);margin:0 0 0.35rem;">Reports</p>
            <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">Saved Reports</h3>
            <p style="font-size:0.75rem;color:var(--mist);margin:0.3rem 0 0;">Define, run, and re-run analytics reports on demand</p>
        </div>
        <button
            wire:click="$toggle('showCreate')"
            class="press"
            style="font-size:0.72rem;padding:0.4rem 0.85rem;background:{{ $showCreate ? 'transparent' : 'var(--gold)' }};color:{{ $showCreate ? 'var(--paper)' : 'var(--ink)' }};border:1px solid {{ $showCreate ? 'var(--line)' : 'transparent' }};border-radius:0.4rem;font-weight:600;"
        >{{ $showCreate ? 'Cancel' : '+ New Report' }}</button>
    </div>

    @if($showCreate)
        <form wire:submit="create" class="mb-5 rounded-xl p-4 space-y-3" style="border:1px solid var(--line);background:var(--panel);">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Report name</label>
                    <input wire:model="title" type="text" placeholder="Weekly Risk Summary" class="w-full rounded-lg px-3 py-2 text-sm focus:outline-none" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);" />
                    @error('title') <p style="color:var(--danger);font-size:0.7rem;margin-top:0.25rem;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Report type</label>
                    <select wire:model="reportType" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);">
                        <option value="insights">Cross-Platform Insights</option>
                        <option value="alerts">Intelligence Alerts</option>
                        <option value="recommendations">Recommendations</option>
                        <option value="metrics">Computed Metrics</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Description (optional)</label>
                <input wire:model="description" type="text" placeholder="Brief description of this report" class="w-full rounded-lg px-3 py-2 text-sm focus:outline-none" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);" />
            </div>
            <button type="submit" class="press" style="background:var(--gold);color:var(--ink);padding:0.5rem 1rem;border-radius:0.4rem;font-size:0.8rem;font-weight:600;">Save Report</button>
        </form>
    @endif

    @if($this->reports->isEmpty())
        <p style="font-size:0.85rem;color:var(--mist);text-align:center;padding:1.5rem 0;">No saved reports yet. Create one to enable scheduled runs and history tracking.</p>
    @else
        <div class="space-y-2">
            @foreach($this->reports as $report)
                @php $lastRun = $report->latestRun; @endphp
                <div class="rounded-xl p-4 flex items-center justify-between gap-4" style="border:1px solid var(--line);">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;" class="truncate">{{ $report->title }}</p>
                            <span style="font-size:0.68rem;padding:0.1rem 0.5rem;background:rgba(43,182,183,0.08);color:var(--teal-soft);border-radius:9999px;flex-shrink:0;">
                                {{ ucfirst($report->config['report_type'] ?? 'insights') }}
                            </span>
                        </div>
                        @if($report->description)
                            <p style="font-size:0.72rem;color:var(--mist);opacity:0.8;" class="truncate">{{ $report->description }}</p>
                        @endif
                        @if($lastRun)
                            <p style="font-size:0.7rem;color:var(--mist);margin-top:0.2rem;">
                                Last run: {{ $lastRun->completed_at?->diffForHumans() ?? 'in progress' }}
                                &middot;
                                <span style="color:{{ $lastRun->status === 'completed' ? 'var(--teal-soft)' : 'var(--danger-soft)' }};">{{ $lastRun->status }}</span>
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
                                class="press"
                                style="font-size:0.68rem;padding:0.25rem 0.6rem;border:1px solid var(--line);border-radius:0.3rem;color:var(--mist);text-decoration:none;"
                                target="_blank"
                            >CSV</a>
                        @endif
                        <button
                            wire:click="run({{ $report->id }})"
                            wire:loading.attr="disabled"
                            class="press"
                            style="font-size:0.72rem;padding:0.35rem 0.75rem;background:var(--gold);color:var(--ink);border-radius:0.35rem;font-weight:600;"
                        >
                            @if($running && $runningId === $report->id)
                                <span wire:loading wire:target="run({{ $report->id }})">Running&hellip;</span>
                                <span wire:loading.remove wire:target="run({{ $report->id }})">Run</span>
                            @else
                                Run
                            @endif
                        </button>
                        <button
                            wire:click="delete({{ $report->id }})"
                            wire:confirm="Delete '{{ $report->title }}'?"
                            style="font-size:0.75rem;color:var(--mist);background:none;border:none;cursor:pointer;padding:0 0.25rem;"
                            onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--mist)'"
                        >&#10005;</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
