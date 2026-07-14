# Empty States, Loading States & Error States

**Why this matters:** For a new team, every panel starts empty. Empty states are
the first thing enterprise users see. They either guide users toward value or
confirm that the product "doesn't work yet".

---

## Empty State Hierarchy

Not all empty states are equal. Match the design to the context:

| Type | When | Design |
|---|---|---|
| **First-run** | Panel has never had data | Illustrative, encouraging, with a direct CTA |
| **Filtered empty** | Data exists but filters hide it | Simple message, clear reset action |
| **Genuinely empty** | No data will ever match | Explain why, no CTA needed |
| **Error** | Something failed | Error message, retry option |

---

## Fix 1 — First-Run Empty State Pattern

The current pattern (plain gray text) is insufficient for first-run. Use this instead:

```html
<!-- First-run empty — guides the user to take action -->
<div class="border-2 border-dashed border-gray-200 rounded-xl flex flex-col items-center
            justify-center py-12 px-6 text-center">
    {{-- Illustration area (SVG or placeholder) --}}
    <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center mb-4"
         aria-hidden="true">
        <span class="text-indigo-400 text-2xl">◎</span>
    </div>

    <p class="text-sm font-medium text-gray-700 mb-1">No insights yet</p>
    <p class="text-xs text-gray-400 max-w-xs mb-4">
        Cross-platform insights appear once you connect at least two Dot platforms
        and run the intelligence engines.
    </p>

    <button wire:click="..." class="text-xs bg-indigo-600 text-white px-4 py-2 rounded-lg
                                    hover:bg-indigo-700 font-medium">
        Connect a Platform
    </button>
</div>
```

### Icons for empty state illustrations

Use the platform letter or intelligence engine symbol as the icon:

```
Intelligence  →  ◎     Platforms  →  ⬡     Graph     →  ○—○
DNA Profile   →  ◈     Reports    →  ☰     Briefing  →  ✉
Anomaly       →  ◉     Flags      →  ⚑     Dashboard →  ⊞
```

---

## Fix 2 — Filtered Empty State

```html
<!-- When filters produce no results — explain and offer reset -->
<div class="text-center py-8">
    <p class="text-sm text-gray-500 mb-2">No alerts match the selected filters.</p>
    <button wire:click="$set('filterSeverity', ''); $set('filterStatus', 'open')"
            class="text-xs text-indigo-600 hover:underline">
        Clear filters
    </button>
</div>
```

---

## Fix 3 — Standard Empty State (current pattern — keep as-is for non-critical panels)

```html
<div class="text-center py-6">
    <p class="text-sm text-gray-400">
        No {{ $label }} yet.
        <span class="block text-xs mt-1">{{ $hint }}</span>
    </p>
</div>
```

---

## Skeleton Screens (Loading Placeholders)

For panels that load remote data, show skeletons instead of empty space during load.

### Skeleton Card
```html
<div class="animate-pulse">
    <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
    <div class="h-3 bg-gray-100 rounded w-1/2 mb-1"></div>
    <div class="h-3 bg-gray-100 rounded w-5/6"></div>
</div>
```

### Skeleton List (for alerts/recommendations)
```html
@if($loading)
    <div class="space-y-3 animate-pulse">
        @for($i = 0; $i < 3; $i++)
            <div class="border border-gray-100 rounded-xl p-4">
                <div class="h-4 bg-gray-200 rounded w-2/3 mb-2"></div>
                <div class="h-3 bg-gray-100 rounded w-full mb-1"></div>
                <div class="h-3 bg-gray-100 rounded w-4/5"></div>
            </div>
        @endfor
    </div>
@endif
```

### When to use skeletons vs. spinners

| Use skeleton | Use spinner |
|---|---|
| Panels loading initial data | Buttons waiting for an action to complete |
| Page transitions | Form submit |
| Data refreshing in background | "Generate", "Run Engines", "Compute" actions |

---

## Loading Button Pattern (current — keep consistent)

```html
<button
    wire:click="generate"
    wire:loading.attr="disabled"
    wire:target="generate"
    class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium
           hover:bg-indigo-700 disabled:opacity-50"
>
    <span wire:loading.remove wire:target="generate">Generate</span>
    <span wire:loading wire:target="generate" class="flex items-center gap-2">
        <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
             aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        Running...
    </span>
</button>
```

---

## Error States

### Inline field error (form validation — current pattern, keep consistent)
```html
@error('fieldName')
    <p class="text-red-500 text-xs mt-1" role="alert">{{ $message }}</p>
@enderror
```

### Panel-level error (service failure, AI error, connector failure)
```html
<div class="bg-red-50 border border-red-200 rounded-xl p-4" role="alert">
    <div class="flex items-start gap-3">
        <span class="text-red-500 text-lg" aria-hidden="true">!</span>
        <div>
            <p class="text-sm font-medium text-red-700">Something went wrong</p>
            <p class="text-xs text-red-600 mt-0.5">{{ $errorMessage }}</p>
            <button wire:click="retry" class="text-xs text-red-700 underline mt-2">Try again</button>
        </div>
    </div>
</div>
```

### Warning (non-critical — AI fallback mode, partial data)
```html
<div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-700 mb-4">
    <span class="font-medium">⚠ Limited intelligence.</span> {{ $warningMessage }}
</div>
```

---

## Empty State Copy Guide

Write empty states in this format:

1. **What's missing** (noun, not "no data") — e.g., "No cross-platform insights"
2. **Why it's missing** (one sentence) — "You need at least 2 connected platforms to discover correlations."
3. **What to do** (CTA, if there's a clear action) — "Connect a Platform →"

| Panel | Heading | Explanation | CTA |
|---|---|---|---|
| Cross-Platform Insights | No insights discovered yet | Connect 2+ platforms then run the intelligence engines. | Run Engines |
| Business DNA | No DNA profile computed | The profile builds as more data accumulates across platforms. | Recompute DNA |
| Knowledge Graph | Graph is empty | Entities appear as platforms contribute intelligence data. | Run Engines |
| Executive Briefing | No briefing for this period | Briefings are generated daily/weekly/monthly automatically. | Generate Now |
| Saved Reports | No reports saved | Save a report definition to re-run it with one click. | New Report |
| Dashboard Builder | No dashboards | Create your first custom intelligence dashboard. | New Dashboard |
| Alerts | No open alerts | Your intelligence network is monitoring for anomalies. | — |
| Recommendations | No pending recommendations | Run intelligence engines to generate AI recommendations. | Run Engines |

---

## PR Checklist (States)

- [ ] New panels have all three states: empty, loading, populated
- [ ] First-run empty states have a CTA guiding users to their next action
- [ ] Filtered empty states offer a "clear filters" escape
- [ ] Loading buttons use `wire:loading.remove` / `wire:loading` pattern
- [ ] Service errors show a user-friendly message with retry option
- [ ] Skeleton screens used for panels loading initial data
- [ ] All `role="alert"` added to error messages for screen readers
