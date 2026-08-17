<div style="padding:2rem 2.25rem;">
    <div class="mb-5">
        <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.35rem;">Universal Query</p>
        <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">Ask the Ecosystem</h3>
        <p style="font-size:0.8rem;color:var(--mist);margin:0.3rem 0 0;">
            Ask any question. Dot.Analytics traces relationships across every connected platform to answer what no single tool can see.
        </p>
    </div>

    {{-- Example prompts --}}
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach([
            'Why is productivity down this month?',
            'Which customers are becoming unprofitable?',
            'What is causing the support ticket spike?',
            'Which assets are at highest replacement risk?',
        ] as $example)
            <button
                wire:click="$set('intelligenceQuery', '{{ $example }}')"
                class="press"
                style="font-size:0.72rem;padding:0.35rem 0.8rem;border:1px solid var(--line);border-radius:9999px;color:var(--mist);background:transparent;transition:all 0.15s;"
                onmouseover="this.style.borderColor='var(--teal-soft)';this.style.color='var(--teal-soft)'" onmouseout="this.style.borderColor='var(--line)';this.style.color='var(--mist)'"
            >{{ $example }}</button>
        @endforeach
    </div>

    <form wire:submit="askIntelligence" class="flex gap-3">
        <input
            type="text"
            wire:model="intelligenceQuery"
            placeholder="Ask anything about your business..."
            class="flex-1 rounded-lg px-4 py-2 text-sm focus:outline-none"
            style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);"
        />
        <button
            type="submit"
            class="press"
            style="background:var(--gold);color:var(--ink);padding:0.55rem 1.25rem;border-radius:0.5rem;font-size:0.8rem;font-weight:600;white-space:nowrap;"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove>Ask Intelligence</span>
            <span wire:loading>Analysing across platforms&hellip;</span>
        </button>
    </form>

    @error('intelligenceQuery')
        <p style="color:#f08a6c;font-size:0.8rem;margin-top:0.5rem;">{{ $message }}</p>
    @enderror

    @if($queryAnswer)
        <div class="mt-4 rounded-xl p-5" style="background:var(--panel);border:1px solid var(--teal-soft);">
            <div class="flex items-center gap-2 mb-2">
                <span class="w-2 h-2 rounded-full" style="background:var(--teal-soft);"></span>
                <span class="font-mono" style="font-size:0.65rem;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;color:var(--teal-soft);">Intelligence Response</span>
            </div>
            <p style="font-size:0.85rem;color:var(--paper);line-height:1.6;">{{ $queryAnswer }}</p>
        </div>
    @endif
</div>
