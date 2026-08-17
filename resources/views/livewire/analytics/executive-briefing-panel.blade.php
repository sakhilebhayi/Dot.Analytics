<div style="padding:2rem 2.25rem;" wire:poll.30s>
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <div>
            <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.35rem;">Executive Briefing</p>
            <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">AI-Synthesised Signals</h3>
            <p style="font-size:0.75rem;color:var(--mist);margin:0.3rem 0 0;">Across every connected platform</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex rounded-lg overflow-hidden" style="border:1px solid var(--line);">
                @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $key => $label)
                    <button
                        wire:click="$set('period', '{{ $key }}')"
                        class="press"
                        style="padding:0.4rem 0.8rem;font-size:0.72rem;font-weight:600;transition:all 0.15s;background:{{ $period === $key ? 'var(--gold)' : 'transparent' }};color:{{ $period === $key ? 'var(--ink)' : 'var(--mist)' }};"
                    >{{ $label }}</button>
                @endforeach
            </div>
            <button
                wire:click="generate"
                wire:loading.attr="disabled"
                wire:target="generate"
                class="press"
                style="font-size:0.72rem;padding:0.4rem 0.85rem;background:transparent;border:1px solid var(--line);color:var(--paper);border-radius:0.4rem;font-weight:600;"
            >
                <span wire:loading.remove wire:target="generate">Generate</span>
                <span wire:loading wire:target="generate">Queuing&hellip;</span>
            </button>
        </div>
    </div>

    @if(! $this->briefing)
        <div class="text-center py-10 rounded-xl" style="border:1px dashed var(--line);">
            <p style="font-size:0.85rem;color:var(--mist);">No {{ $period }} briefing available.</p>
            <p style="font-size:0.75rem;color:var(--mist);margin-top:0.25rem;">Click <strong style="color:var(--paper);">Generate</strong> to create one, or it will run automatically on schedule.</p>
        </div>
    @elseif($this->briefing->status === 'generating')
        <div class="flex items-center gap-3 py-8 justify-center">
            <div style="width:1rem;height:1rem;border:2px solid var(--gold);border-top-color:transparent;border-radius:9999px;" class="animate-spin"></div>
            <p style="font-size:0.85rem;color:var(--mist);">Synthesising intelligence across all engines&hellip;</p>
        </div>
    @else
        @php $b = $this->briefing; @endphp

        {{-- Meta --}}
        <div class="flex items-center gap-3 mb-5 pb-4" style="border-bottom:1px solid var(--line);">
            <span style="font-size:0.72rem;color:var(--mist);">{{ ucfirst($b->period) }} briefing &middot; {{ \Carbon\Carbon::parse($b->period_date)->format('D, d M Y') }}</span>
            @if($b->insight_count > 0)
                <span style="font-size:0.68rem;background:rgba(43,182,183,0.08);color:var(--teal-soft);padding:0.15rem 0.55rem;border-radius:9999px;">{{ $b->insight_count }} insights analysed</span>
            @endif
            @if(!empty($b->engines_consulted))
                <span style="font-size:0.68rem;color:var(--mist);">{{ count($b->engines_consulted) }} engines</span>
            @endif
        </div>

        {{-- Executive summary --}}
        @if($b->summary)
            <p style="font-size:0.85rem;color:var(--paper);line-height:1.6;margin-bottom:1.25rem;">{{ $b->summary }}</p>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Highlights --}}
            @if(!empty($b->highlights))
                <div class="rounded-xl p-4" style="background:rgba(43,182,183,0.06);border:1px solid rgba(43,182,183,0.3);">
                    <p class="font-mono" style="font-size:0.62rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--teal-soft);margin-bottom:0.5rem;">Highlights</p>
                    <ul class="space-y-1.5">
                        @foreach($b->highlights as $h)
                            <li style="font-size:0.75rem;color:var(--paper);display:flex;gap:0.5rem;">
                                <span style="color:var(--teal-soft);margin-top:0.1rem;">&uarr;</span>
                                <span>{{ $h }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Risks --}}
            @if(!empty($b->risks))
                <div class="rounded-xl p-4" style="background:rgba(240,138,108,0.06);border:1px solid rgba(240,138,108,0.3);">
                    <p class="font-mono" style="font-size:0.62rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--danger-soft);margin-bottom:0.5rem;">Risks</p>
                    <ul class="space-y-1.5">
                        @foreach($b->risks as $r)
                            <li style="font-size:0.75rem;color:var(--paper);display:flex;gap:0.5rem;">
                                <span style="color:var(--danger-soft);margin-top:0.1rem;">!</span>
                                <span>{{ $r }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Recommendations --}}
            @if(!empty($b->recommendations))
                <div class="rounded-xl p-4" style="background:rgba(241,198,46,0.06);border:1px solid rgba(241,198,46,0.3);">
                    <p class="font-mono" style="font-size:0.62rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--gold-soft);margin-bottom:0.5rem;">Top Actions</p>
                    <ul class="space-y-2">
                        @foreach($b->recommendations as $rec)
                            <li style="font-size:0.75rem;">
                                <div class="flex items-center gap-1.5 mb-0.5">
                                    @php $pri = $rec['priority'] ?? 'medium'; @endphp
                                    <span class="w-1.5 h-1.5 rounded-full" style="background:{{ $pri === 'high' || $pri === 'critical' ? 'var(--danger)' : 'var(--gold)' }};"></span>
                                    <span style="font-weight:600;color:var(--paper);">{{ $rec['title'] ?? '' }}</span>
                                </div>
                                @if(!empty($rec['rationale']))
                                    <p style="color:var(--mist);padding-left:0.75rem;">{{ $rec['rationale'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>
