<div style="padding:1.75rem 2rem 2rem;">
    <input
        wire:model.live.debounce.300ms="query"
        type="text"
        placeholder="Search insights, alerts, recommendations, reports, dashboards, and the knowledge graph…"
        class="w-full rounded-lg px-4 py-3 text-sm focus:outline-none"
        style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);margin-bottom:1.75rem;"
    />

    @if(mb_strlen(trim($query)) < 2)
        <p style="font-size:0.85rem;color:var(--mist);text-align:center;padding:2rem 0;">Type at least 2 characters to search.</p>
    @elseif($this->totalCount === 0)
        <p style="font-size:0.85rem;color:var(--mist);text-align:center;padding:2rem 0;">No results for &ldquo;{{ $query }}&rdquo;.</p>
    @else
        <div style="display:flex;flex-direction:column;gap:1.75rem;">
            @if($this->nodes->isNotEmpty())
                <div>
                    <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.75rem;">Knowledge Graph ({{ $this->nodes->count() }})</p>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach($this->nodes as $node)
                            <a href="{{ route('dashboard') }}" style="display:block;padding:0.75rem 1rem;border:1px solid var(--line);border-radius:0.5rem;text-decoration:none;">
                                <div class="flex items-center gap-2">
                                    <span style="font-size:0.85rem;font-weight:600;color:var(--paper);">{{ $node->label }}</span>
                                    <span style="font-size:0.65rem;color:var(--mist);border:1px solid var(--line);border-radius:0.25rem;padding:0.05rem 0.4rem;">{{ $node->entity_type }}</span>
                                </div>
                                <p style="font-size:0.72rem;color:var(--mist);margin:0.2rem 0 0;">{{ $node->entity_id }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->insights->isNotEmpty())
                <div>
                    <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.75rem;">Cross-Platform Insights ({{ $this->insights->count() }})</p>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach($this->insights as $insight)
                            <a href="{{ route('dashboard') }}" style="display:block;padding:0.75rem 1rem;border:1px solid var(--line);border-radius:0.5rem;text-decoration:none;">
                                <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $insight->title }}</p>
                                <p style="font-size:0.72rem;color:var(--mist);margin:0.2rem 0 0;">{{ \Illuminate\Support\Str::limit($insight->narrative, 140) }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->alerts->isNotEmpty())
                <div>
                    <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--danger-soft);margin:0 0 0.75rem;">Alerts ({{ $this->alerts->count() }})</p>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach($this->alerts as $alert)
                            <a href="{{ route('dashboard') }}" style="display:block;padding:0.75rem 1rem;border:1px solid var(--line);border-radius:0.5rem;text-decoration:none;">
                                <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $alert->title }}</p>
                                <p style="font-size:0.72rem;color:var(--mist);margin:0.2rem 0 0;">{{ \Illuminate\Support\Str::limit($alert->description, 140) }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->recommendations->isNotEmpty())
                <div>
                    <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--gold-soft);margin:0 0 0.75rem;">Recommendations ({{ $this->recommendations->count() }})</p>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach($this->recommendations as $rec)
                            <a href="{{ route('dashboard') }}" style="display:block;padding:0.75rem 1rem;border:1px solid var(--line);border-radius:0.5rem;text-decoration:none;">
                                <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $rec->title }}</p>
                                <p style="font-size:0.72rem;color:var(--mist);margin:0.2rem 0 0;">{{ \Illuminate\Support\Str::limit($rec->rationale, 140) }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->reports->isNotEmpty())
                <div>
                    <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--mist);margin:0 0 0.75rem;">Saved Reports ({{ $this->reports->count() }})</p>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach($this->reports as $report)
                            <a href="{{ route('dashboard') }}" style="display:block;padding:0.75rem 1rem;border:1px solid var(--line);border-radius:0.5rem;text-decoration:none;">
                                <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $report->title }}</p>
                                @if($report->description)
                                    <p style="font-size:0.72rem;color:var(--mist);margin:0.2rem 0 0;">{{ \Illuminate\Support\Str::limit($report->description, 140) }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->dashboards->isNotEmpty())
                <div>
                    <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--gold-soft);margin:0 0 0.75rem;">Dashboards ({{ $this->dashboards->count() }})</p>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach($this->dashboards as $dash)
                            <a href="{{ route('dashboards.index') }}" style="display:block;padding:0.75rem 1rem;border:1px solid var(--line);border-radius:0.5rem;text-decoration:none;">
                                <div class="flex items-center gap-2">
                                    <span style="font-size:0.85rem;font-weight:600;color:var(--paper);">{{ $dash->title }}</span>
                                    @if($dash->visibility === 'team')
                                        <span class="font-mono" style="font-size:0.58rem;background:rgba(43,182,183,0.1);color:var(--teal-soft);padding:0.1rem 0.4rem;border-radius:0.25rem;">shared</span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
