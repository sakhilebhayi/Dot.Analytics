# Accessibility — WCAG 2.2 AA Compliance Guide

**Target:** WCAG 2.2 Level AA
**Scorecard impact:** Compliance & Privacy (+4 pts when complete)
**Current status:** ⚠️ Significant gaps — no `sr-only`, no `aria-live`, incomplete focus management

---

## Current Gaps (from audit)

| Issue | Severity | Affected Components |
|---|---|---|
| No `aria-live` on dynamic alert updates | High | `AlertsPanel`, `CrossPlatformInsightPanel` |
| No `aria-expanded` on toggle buttons | High | All panels with `showForm` / `showCreate` toggles |
| No `aria-describedby` linking errors to fields | Medium | All auth forms, report creation form |
| No `sr-only` labels on icon-only buttons | High | Disconnect buttons (`✕`), resolve/ack buttons |
| No focus trap in modals | High | `confirmation-modal`, `dialog-modal` |
| Colour alone conveys severity | Medium | Alert severity (red/amber/blue with no shape/text backup) |
| Touch targets too small | Medium | `text-xs` buttons like `Ack`, `Resolve` |
| `wire:confirm` not accessible | Medium | Native browser confirm has no ARIA context |

---

## Fix 1 — Screen Reader Labels for Icon Buttons

Every button whose text is not self-describing needs an `aria-label`:

```html
<!-- Before (inaccessible) -->
<button wire:click="disconnect('dot.fleet')">✕</button>

<!-- After -->
<button wire:click="disconnect('dot.fleet')" aria-label="Disconnect Dot.Fleet">✕</button>
```

```html
<!-- Before -->
<button wire:click="acknowledge({{ $alert->id }})">Ack</button>

<!-- After -->
<button wire:click="acknowledge({{ $alert->id }})"
        aria-label="Acknowledge alert: {{ $alert->title }}">Ack</button>
```

---

## Fix 2 — Live Regions for Dynamic Content

When Livewire updates part of the page, screen readers need to be told:

```html
<!-- Alerts panel — announce when new alerts appear -->
<div aria-live="polite" aria-label="Intelligence alerts">
    @foreach($this->alerts as $alert)
        <!-- alert content -->
    @endforeach
</div>

<!-- Cross-platform insights — announce when engines run -->
<div aria-live="assertive" id="insight-status">
    @if($running)
        <span class="sr-only">Running intelligence engines, please wait...</span>
    @endif
</div>
```

Use `assertive` only for critical/urgent updates. Use `polite` for everything else.

---

## Fix 3 — `aria-expanded` on Toggle Buttons

```html
<!-- Before -->
<button wire:click="$toggle('showCreate')">+ New Flag</button>

<!-- After -->
<button wire:click="$toggle('showCreate')"
        :aria-expanded="$wire.showCreate"
        aria-controls="create-flag-form">
    {{ $showCreate ? 'Cancel' : '+ New Flag' }}
</button>

<div id="create-flag-form" :hidden="!$wire.showCreate">
    <!-- form content -->
</div>
```

---

## Fix 4 — Error Association with Fields

```html
<!-- Before -->
<input wire:model="title" id="title" type="text" ... />
@error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror

<!-- After -->
<input wire:model="title"
       id="title"
       type="text"
       aria-describedby="title-error"
       :aria-invalid="$wire->hasError('title') ? 'true' : 'false'"
       ... />
<p id="title-error" role="alert" class="text-red-500 text-xs mt-1">
    @error('title') {{ $message }} @enderror
</p>
```

---

## Fix 5 — `sr-only` Utility Class

Tailwind includes this by default. Use it to add context visible only to screen readers:

```html
<!-- Confidence percentage without visual label -->
<span>{{ round($insight->confidence * 100) }}%</span>
<span class="sr-only">confidence</span>

<!-- Status dot -->
<span class="w-2 h-2 rounded-full bg-green-500" aria-hidden="true"></span>
<span class="sr-only">Connected</span>

<!-- Loading spinner -->
<div class="animate-spin..." aria-hidden="true"></div>
<span class="sr-only" aria-live="polite">Loading, please wait</span>
```

---

## Fix 6 — Touch Target Sizes (WCAG 2.5.8)

Minimum touch target: **24×24px**. Recommended: **44×44px** for primary actions.

```html
<!-- Before — too small -->
<button class="text-xs px-2 py-1">Ack</button>

<!-- After — minimum interactive area -->
<button class="text-xs px-3 py-2 min-h-[36px] min-w-[36px]">Ack</button>
```

On mobile, use `sm:` to restore compact sizes where screen real estate allows:
```html
<button class="px-4 py-3 text-sm sm:px-2 sm:py-1 sm:text-xs">Ack</button>
```

---

## Fix 7 — Colour + Shape, Never Colour Alone

Severity must be communicated by text or shape, not colour alone:

```html
<!-- Before — colour only -->
<div class="border-red-300 bg-red-50">...</div>

<!-- After — colour + label -->
<div class="border-red-300 bg-red-50">
    <span class="text-xs font-semibold text-red-700 uppercase tracking-wide" aria-label="Severity: Critical">
        ⚠ Critical
    </span>
    ...
</div>
```

---

## Fix 8 — Accessible `wire:confirm` Replacement

Native `window.confirm()` (used by `wire:confirm`) is not accessible. Replace with an accessible confirmation component:

```html
<!-- Replace this pattern: -->
<button wire:click="delete({{ $id }})"
        wire:confirm="Are you sure?">Delete</button>

<!-- With the existing confirmation-modal component: -->
<button wire:click="$set('confirmingDeleteId', {{ $id }})"
        aria-haspopup="dialog">Delete</button>

@if($confirmingDeleteId)
    <x-confirmation-modal wire:model="confirmingDeleteId">
        <x-slot name="title">Delete Report</x-slot>
        <x-slot name="content">Are you sure you want to delete this report? This action cannot be undone.</x-slot>
        <x-slot name="footer">
            <x-secondary-button wire:click="$set('confirmingDeleteId', null)">Cancel</x-secondary-button>
            <x-danger-button wire:click="delete({{ $confirmingDeleteId }})">Delete</x-danger-button>
        </x-slot>
    </x-confirmation-modal>
@endif
```

---

## Fix 9 — Keyboard Navigation

Every interactive element must be keyboard-reachable. Test with Tab, Shift+Tab, Enter, Space, Escape.

```html
<!-- Escape closes modals/dropdowns -->
<div x-data="{ open: false }"
     @keydown.escape.window="open = false">
```

```html
<!-- Dropdown items — Arrow key navigation -->
<div x-data="{ open: false }"
     @keydown.arrow-down.prevent="$focus.wrap().next()"
     @keydown.arrow-up.prevent="$focus.wrap().previous()">
```

---

## PR Checklist (Accessibility)

Add to every PR that touches Blade/Livewire files:

- [ ] Icon-only buttons have `aria-label`
- [ ] Dynamic content regions have `aria-live`
- [ ] Toggle buttons have `aria-expanded`
- [ ] Form errors linked with `aria-describedby`
- [ ] `sr-only` added to visual-only indicators
- [ ] Touch targets ≥ 24px on all interactive elements
- [ ] Severity communicated by text/icon, not colour alone
- [ ] Tab through all new UI with keyboard — nothing trapped
- [ ] Test with VoiceOver (macOS) or NVDA (Windows) on critical flows
