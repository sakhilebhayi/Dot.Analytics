# Mobile & Responsive Design

**Approach:** Desktop-first with mobile-friendly layouts.
Enterprise intelligence platforms are primarily used on desktop — but reports,
alerts, and briefings must be readable on mobile.

---

## Decision: What Works on Mobile vs. Desktop-Only

| Feature | Mobile | Notes |
|---|---|---|
| Dashboard overview (KPIs) | ✅ Priority | Executives check KPIs on mobile |
| Alerts panel | ✅ Priority | Urgency requires mobile access |
| Recommendations panel | ✅ Priority | Quick review on mobile |
| Executive briefing | ✅ Priority | Designed to be read, not operated |
| Ecosystem map | ⚠️ Limited | Show connected count + status only |
| Cross-platform insights | ✅ Readable | Cards adapt naturally |
| Knowledge graph traversal | ❌ Desktop only | Input form is too complex for mobile |
| Dashboard builder | ❌ Desktop only | Drag-and-drop requires pointer device |
| Pipeline management | ❌ Desktop only | Configuration-heavy |
| Feature flags | ❌ Desktop only | Admin function |

---

## Breakpoint Strategy

| Breakpoint | px | Use case |
|---|---|---|
| Base (mobile) | 0–639px | Single-column, large touch targets |
| `sm:` | 640px+ | 2-column grids start here |
| `md:` | 768px+ | 3-4 column layouts, sidebar |
| `lg:` | 1024px+ | Full dashboard layout, side-by-side panels |

---

## Current Breakpoint Audit

### ✅ Already responsive
```html
<!-- Dashboard KPIs: 2 col → 4 col -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">

<!-- Ecosystem map: 2 → 3 → 5 col -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">

<!-- Alerts + Recs: stacked → side-by-side -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
```

### ⚠️ Needs improvement
```html
<!-- Executive briefing 3-col collapses badly on mobile -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
<!-- Fix: use 1 col on mobile, stacked vertically -->

<!-- Intelligence query: input + button on same line — wraps badly -->
<form class="flex gap-3">
<!-- Fix: flex-col on mobile, flex-row on sm -->
<form class="flex flex-col sm:flex-row gap-3">
```

---

## Fix 1 — Intelligence Query Form

```html
<!-- Before -->
<form wire:submit="askIntelligence" class="flex gap-3">
    <input class="flex-1 ..." />
    <button class="... whitespace-nowrap">Ask Intelligence</button>
</form>

<!-- After — stacks on mobile -->
<form wire:submit="askIntelligence" class="flex flex-col sm:flex-row gap-3">
    <input class="flex-1 w-full ..." />
    <button class="w-full sm:w-auto ... whitespace-nowrap">Ask Intelligence</button>
</form>
```

---

## Fix 2 — Panel Headers on Mobile

```html
<!-- Before — title + controls on one line (breaks on mobile) -->
<div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-semibold">Cross-Platform Intelligence</h3>
    <div class="flex items-center gap-2">
        <select>...</select>
        <select>...</select>
        <button>Run Engines</button>
    </div>
</div>

<!-- After — stack on mobile -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
    <div>
        <h3 class="text-lg font-semibold text-gray-800">Cross-Platform Intelligence</h3>
        <p class="text-xs text-gray-500 mt-0.5">Insights requiring multiple platforms</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <select class="text-xs ...">...</select>
        <select class="text-xs ...">...</select>
        <button class="text-xs ...">Run Engines</button>
    </div>
</div>
```

---

## Fix 3 — Minimum Touch Targets

All interactive elements need a minimum `min-h-[44px]` on mobile, or use padding:

```html
<!-- Before — too small on mobile -->
<button class="text-xs px-2 py-1">Ack</button>

<!-- After — adequate on all devices -->
<button class="text-xs px-2 py-1 sm:py-1 py-2.5">Ack</button>
<!-- Or use min-height: -->
<button class="text-xs px-3 py-1.5 min-h-[36px]">Ack</button>
```

---

## Fix 4 — Ecosystem Map on Mobile

The 5-column platform grid is unreadable on mobile. Show a simplified view:

```html
@if($this->connectedCount > 0)
    {{-- Mobile: show summary only --}}
    <div class="block sm:hidden">
        <div class="grid grid-cols-3 gap-2">
            @foreach($this->platformCatalog->take(6) as $key => $platform)
                <div class="border rounded-lg p-2 text-center
                    {{ $platform['status'] === 'connected' ? $platform['colors']['bg'] . ' ' . $platform['colors']['border'] : 'border-gray-200 bg-gray-50' }}">
                    <p class="text-xs font-medium {{ $platform['status'] === 'connected' ? $platform['colors']['text'] : 'text-gray-400' }} truncate">
                        {{ str_replace('Dot.', '', $platform['label']) }}
                    </p>
                </div>
            @endforeach
            @if($this->platformCatalog->count() > 6)
                <div class="border border-gray-200 rounded-lg p-2 text-center">
                    <p class="text-xs text-gray-400">+{{ $this->platformCatalog->count() - 6 }} more</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Desktop: full grid --}}
    <div class="hidden sm:block">
        {{-- existing full grid --}}
    </div>
@endif
```

---

## Navigation on Mobile

The existing Jetstream navigation already has a hamburger menu. Ensure the analytics
navigation items are in the mobile menu:

```html
{{-- In navigation-menu.blade.php, responsive section --}}
<x-responsive-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
    Intelligence Dashboard
</x-responsive-nav-link>
```

---

## Print Styles for Reports

HTML reports generated by `ReportGenerationService` should be print-optimised:

```html
{{-- Already in the HTML report template --}}
<style>
    @media print {
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .no-print { display: none; }
        table { page-break-inside: avoid; }
        th { background: #f3f4f6 !important; }
    }
</style>
```

---

## PR Checklist (Mobile)

- [ ] New forms use `flex-col sm:flex-row` for input+button rows
- [ ] Panel headers stack on mobile (`flex-col sm:flex-row`)
- [ ] Touch targets are at least 36px tall on mobile
- [ ] New grids follow pattern: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-N`
- [ ] No horizontal overflow on 375px viewport (test with DevTools)
- [ ] Desktop-only features hidden on mobile with `hidden sm:block`
- [ ] Mobile summary shown for complex components with `block sm:hidden`
