# AI UX Patterns — How to Present Intelligence Outputs

**Why this matters:** Dot.Analytics is an AI-native platform. How AI outputs are
presented determines whether users trust, act on, or ignore the intelligence.
Poor AI UX leads to alert fatigue, ignored recommendations, and distrust.

---

## Core Principles

1. **Always show confidence** — Never present AI output as fact. Always show the confidence score.
2. **Explain the chain** — Show which platforms contributed to the insight.
3. **Separate AI from data** — Users must know what was computed vs. what was inferred.
4. **Graceful degradation** — When no AI key is configured, fall back to deterministic outputs with honest labelling.
5. **Human in the loop** — High-impact recommendations require human acknowledgement before being considered "actioned".

---

## Confidence Score Display

### When to show confidence

Show confidence on every AI-generated output: insights, recommendations, DNA profiles, briefings.

### How to display confidence

```html
<!-- Inline badge — for list items -->
<span class="text-xs text-gray-400 ml-auto">{{ round($insight->confidence * 100) }}% confidence</span>

<!-- Progress bar — for DNA profile and summary panels -->
<div class="flex items-center justify-between mb-1">
    <span class="text-xs text-gray-500">Confidence</span>
    <span class="text-xs font-semibold
        {{ $profile->confidence_score >= 0.8 ? 'text-green-600' :
           ($profile->confidence_score >= 0.5 ? 'text-amber-600' : 'text-gray-400') }}">
        {{ round($profile->confidence_score * 100) }}%
    </span>
</div>
<div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
    <div class="h-1.5 rounded-full transition-all duration-500
        {{ $profile->confidence_score >= 0.8 ? 'bg-green-500' :
           ($profile->confidence_score >= 0.5 ? 'bg-amber-500' : 'bg-gray-400') }}"
         style="width: {{ round($profile->confidence_score * 100) }}%">
    </div>
</div>
```

### Confidence thresholds

| Range | Label | Color | Treatment |
|---|---|---|---|
| 90–100% | High confidence | Green | Show prominently |
| 70–89% | Confident | Amber | Show as-is |
| 50–69% | Moderate confidence | Gray | Add caveat label |
| < 50% | Low confidence | Gray/muted | Consider not surfacing |

---

## Cross-Platform Evidence Display

Every AI insight must show which platforms contributed. This is the core differentiator.

```html
<!-- Platform source chips — use on every insight card -->
<div class="flex flex-wrap gap-1.5 mb-2">
    @foreach($insight->platforms_involved as $platform)
        <span class="text-xs px-1.5 py-0.5 bg-white border border-gray-200 rounded text-gray-600">
            {{ \App\Services\IntelligenceEngineService::PLATFORMS[$platform]['label'] ?? $platform }}
        </span>
    @endforeach
</div>
```

When showing a recommendation:

```html
<p class="text-xs text-gray-500 mt-1">
    Based on data from
    <span class="font-medium text-gray-700">{{ implode(', ', $rec->supporting_data['platforms_referenced'] ?? []) }}</span>
</p>
```

---

## Insight Type Labelling

Each insight type has a distinct visual treatment. Be consistent:

| Type | Color | Icon/Label | Meaning to user |
|---|---|---|---|
| `causation` | Red | `→ Cause` | "A caused B" — high action urgency |
| `correlation` | Blue | `~ Correlated` | "A and B moved together" — investigate |
| `prediction` | Purple | `◉ Predicted` | "This will happen" — prepare |
| `risk` | Orange | `! Risk` | "This could go wrong" — mitigate |
| `opportunity` | Green | `↑ Opportunity` | "You could benefit from this" — act |

```html
@php
    $typeConfig = match($insight->insight_type) {
        'causation'   => ['bg-red-100 text-red-700',    'Cause'],
        'correlation' => ['bg-blue-100 text-blue-700',  'Correlated'],
        'prediction'  => ['bg-purple-100 text-purple-700', 'Predicted'],
        'risk'        => ['bg-orange-100 text-orange-700', 'Risk'],
        'opportunity' => ['bg-green-100 text-green-700',  'Opportunity'],
        default       => ['bg-gray-100 text-gray-600',  ucfirst($insight->insight_type)],
    };
@endphp
<span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $typeConfig[0] }}">
    {{ $typeConfig[1] }}
</span>
```

---

## AI vs. Computed Data Distinction

Users must always know the difference:

```html
<!-- For AI-generated content -->
<div class="flex items-center gap-1.5 mb-2">
    <span class="w-1.5 h-1.5 rounded-full bg-violet-400" aria-hidden="true"></span>
    <span class="text-xs text-violet-600 font-medium">AI Generated</span>
    <span class="text-xs text-gray-400">via {{ $model ?? 'intelligence engine' }}</span>
</div>

<!-- For computed/deterministic data -->
<div class="flex items-center gap-1.5 mb-2">
    <span class="w-1.5 h-1.5 rounded-full bg-blue-400" aria-hidden="true"></span>
    <span class="text-xs text-blue-600 font-medium">Computed</span>
</div>
```

---

## Fallback Mode (No AI Key Configured)

When `AiModelRouter` falls back to deterministic mock responses, show an honest indicator:

```html
@if(config('services.ai.primary_provider') && !config('services.anthropic.key')
    && !config('services.openai.key'))
    <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-700 mb-4">
        <span class="font-medium">AI not configured.</span>
        Intelligence is using rule-based fallbacks.
        <a href="#" class="underline">Configure an AI provider</a> for live intelligence.
    </div>
@endif
```

---

## Recommendations UX

### Priority visual hierarchy

```html
<!-- Critical — prominent, requires attention -->
<div class="border-l-4 border-red-500 bg-red-50 p-4">
    <div class="flex items-center gap-2 mb-1">
        <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse" aria-hidden="true"></span>
        <span class="text-xs font-semibold text-red-700 uppercase">Critical Action Required</span>
    </div>
    ...
</div>

<!-- High — elevated but not urgent -->
<div class="border-l-4 border-amber-400 bg-amber-50 p-4">...</div>

<!-- Medium/Low — standard card -->
<div class="border border-gray-200 rounded-xl p-4">...</div>
```

### Action vs. Dismiss framing

Never label a recommendation action button just "Act". Be specific:

```html
<!-- Before -->
<button wire:click="action({{ $rec->id }})">Act</button>

<!-- After — derive action from engine context -->
@php
    $actionLabel = match($rec->engine) {
        'operational' => 'Review Operations',
        'financial'   => 'Review Finances',
        'risk'        => 'Assess Risk',
        'customer'    => 'Review Customer',
        default       => 'Take Action',
    };
@endphp
<button wire:click="action({{ $rec->id }})" class="...">{{ $actionLabel }}</button>
```

---

## Business DNA Panel UX

The DNA profile builds trust through progressive disclosure:

1. **Confidence meter first** — users need to know how reliable the profile is before acting on it
2. **Growth signals before risks** — positive first, then problems
3. **"Based on X platforms"** — always show data provenance
4. **"Updated X ago"** — freshness matters for decisions

```html
<!-- DNA data provenance footer -->
<p class="text-xs text-gray-400 mt-4 pt-3 border-t border-gray-50">
    Profile based on {{ count($connectedPlatforms) }} connected platforms.
    @if($profile->last_computed_at)
        Updated {{ $profile->last_computed_at->diffForHumans() }}.
    @endif
</p>
```

---

## Intelligence Query UX

The universal query interface should guide users to ask good questions:

```html
<!-- Example prompts that teach users what the system can answer -->
<p class="text-xs text-gray-500 mb-2">Try asking:</p>
<div class="flex flex-wrap gap-2 mb-4">
    @foreach([
        'Why did costs increase this month?',
        'Which customers are at risk of churning?',
        'What is causing the support spike?',
        'Which assets need attention?',
    ] as $example)
        <button wire:click="$set('intelligenceQuery', '{{ $example }}')"
                class="text-xs px-3 py-1 border border-gray-200 rounded-full text-gray-500
                       hover:border-indigo-400 hover:text-indigo-600 transition-colors
                       focus:outline-none focus:ring-2 focus:ring-indigo-300"
                aria-label="Use example query: {{ $example }}">
            {{ $example }}
        </button>
    @endforeach
</div>
```

---

## PR Checklist (AI UX)

- [ ] All AI outputs show confidence score
- [ ] Insights show `platforms_involved` chips
- [ ] AI-generated content is visually distinguished from computed data
- [ ] Fallback mode indicator shown when no AI key is configured
- [ ] Recommendation action buttons have specific labels (not generic "Act")
- [ ] Low-confidence outputs (< 50%) are visually de-emphasised or suppressed
- [ ] DNA panel shows data provenance and last-computed timestamp
