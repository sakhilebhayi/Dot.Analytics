{{--
    intelligence:engine-completed is a browser CustomEvent dispatched by
    resources/js/app.js when Echo receives a broadcast on the team's
    private team.{id}.intelligence channel (App\Events\Analytics\
    IntelligenceEngineCompleted). $wire.$refresh() re-renders this
    component, busting its #[Computed] cache so it reflects the engine
    run without the user having to reload the page.
--}}
<div class="bg-white rounded-xl shadow p-6" x-on:intelligence:engine-completed.window="$wire.$refresh()">
    <div class="mb-5">
        <h3 class="text-lg font-semibold text-gray-800">Universal Intelligence Query</h3>
        <p class="text-sm text-gray-500 mt-0.5">
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
                class="text-xs px-3 py-1 border border-gray-200 rounded-full text-gray-500 hover:border-blue-400 hover:text-blue-600 transition-colors"
            >{{ $example }}</button>
        @endforeach
    </div>

    <form wire:submit="askIntelligence" class="flex gap-3">
        <input
            type="text"
            wire:model="intelligenceQuery"
            placeholder="Ask anything about your business..."
            class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
        />
        <button
            type="submit"
            class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 whitespace-nowrap"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove>Ask Intelligence</span>
            <span wire:loading>Analysing across platforms...</span>
        </button>
    </form>

    @error('intelligenceQuery')
        <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
    @enderror

    @if($queryAnswer)
        <div class="mt-4 bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-2">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                <span class="text-xs font-semibold text-blue-700">Intelligence Response</span>
            </div>
            <p class="text-sm text-gray-700 leading-relaxed">{{ $queryAnswer }}</p>
        </div>
    @endif
</div>
