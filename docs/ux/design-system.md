# Design System — Dot.Analytics

**Purpose:** Single source of truth for visual language. Follow this on every new Blade/Livewire file.

---

## Brand Identity

| Element | Value |
|---|---|
| Product name | Dot.Analytics |
| Tagline | The intelligence layer of the Dot Ecosystem |
| Font | Figtree (loaded from Bunny CDN) |
| Primary accent | Indigo (`indigo-600`) |
| Brand mark | `D` monogram in a rounded square (`rounded-lg bg-indigo-600`) |

---

## Color Palette

### Semantic Colors (use these — never arbitrary hex)

| Purpose | Tailwind class | When to use |
|---|---|---|
| **Primary action** | `bg-indigo-600` | Main CTAs, active states, primary buttons |
| **Primary hover** | `hover:bg-indigo-700` | Hover on primary buttons |
| **Primary light** | `bg-indigo-50 text-indigo-600` | Badges, active tab highlights |
| **Critical / Error** | `bg-red-50 border-red-300 text-red-700` | Destructive actions, critical alerts |
| **Warning** | `bg-amber-50 border-amber-300 text-amber-700` | Warnings, high-risk items |
| **Success / Positive** | `bg-green-50 text-green-700` | Highlights, connected status, positive trends |
| **Info** | `bg-blue-50 text-blue-700` | Informational items, neutral insights |
| **Surface** | `bg-white` | All card/panel backgrounds |
| **Muted text** | `text-gray-400` | Empty states, secondary labels |
| **Body text** | `text-gray-700` | Primary content text |
| **Headings** | `text-gray-800` | Panel titles, section headings |
| **Borders** | `border-gray-100` or `border-gray-200` | Panel dividers, card outlines |
| **Page background** | `bg-gray-50` or `bg-slate-950` (dark landing) | App shell backgrounds |

### Platform Color System

Each Dot platform has a dedicated color. Never use these for generic UI elements.

```
dot.fleet     → blue      dot.crm       → green     dot.hr        → purple
dot.documents → yellow    dot.hear      → pink       dot.support   → orange
dot.inventory → teal      dot.payments  → emerald   dot.security  → red
dot.api       → indigo    dot.flow      → cyan       dot.assets    → slate
dot.agents    → violet    dot.finance   → lime       dot.vault     → amber
```

### Severity System (use consistently across all panels)

```
critical → red-300 border + red-50 bg + red-700 text
warning  → amber-300 border + amber-50 bg + amber-700 text
info     → gray-200 border (default) + white bg + gray-700 text
```

---

## Typography

| Element | Classes | Notes |
|---|---|---|
| Page title (h1) | `text-xl font-semibold text-gray-800` | Dashboard header |
| Panel title (h3) | `text-lg font-semibold text-gray-800` | Every panel heading |
| Section heading | `text-xs font-semibold text-gray-500 uppercase tracking-wide` | Sub-sections within panels |
| Body text | `text-sm text-gray-700 leading-relaxed` | Descriptions, narratives |
| Secondary text | `text-xs text-gray-500` | Timestamps, metadata |
| Empty state text | `text-sm text-gray-400 text-center` | No-data messages |
| Badge text | `text-xs font-medium px-2 py-0.5 rounded-full` | Status, type, priority badges |
| Code/key | `text-xs bg-gray-100 px-1.5 py-0.5 rounded text-gray-600` | Feature flag keys, platform keys |

---

## Spacing & Layout

### Panel Container (standard — use on every card/panel)
```html
<div class="bg-white rounded-xl shadow p-6">
```

### Panel Header Pattern
```html
<div class="flex items-center justify-between mb-4">
    <div>
        <h3 class="text-lg font-semibold text-gray-800">Title</h3>
        <p class="text-xs text-gray-500 mt-0.5">Subtitle describing the panel's purpose</p>
    </div>
    <!-- Optional action button -->
</div>
```

### Grid Layouts

| Context | Classes |
|---|---|
| Dashboard KPIs | `grid grid-cols-2 md:grid-cols-4 gap-4` |
| Side-by-side panels | `grid grid-cols-1 lg:grid-cols-2 gap-6` |
| 3-column content | `grid grid-cols-1 lg:grid-cols-3 gap-4` |
| Platform card grid | `grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3` |
| Page content width | `max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6` |

### Spacing Scale (standard gaps)
- Between panels on dashboard: `space-y-6`
- Within a panel between sections: `mb-4` or `mb-5`
- Between list items: `space-y-2` or `space-y-3`
- Inline gaps: `gap-2` or `gap-3`

---

## Component Patterns

### Primary Button
```html
<button class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
    Action
</button>
```

### Secondary Button
```html
<button class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200">
    Secondary Action
</button>
```

### Danger Button
```html
<button class="text-xs text-red-400 hover:text-red-600">
    Delete
</button>
```

### Badge
```html
<!-- Status badge -->
<span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Connected</span>
<!-- Priority badge -->
<span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700">Critical</span>
```

### Status Indicator Dot
```html
<span class="w-2 h-2 rounded-full bg-green-500"></span>  <!-- active -->
<span class="w-2 h-2 rounded-full bg-gray-300"></span>    <!-- inactive -->
<span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span> <!-- processing -->
```

### Form Input
```html
<input type="text"
    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
           focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
```

### Select
```html
<select class="border border-gray-300 rounded text-xs px-2 py-1">
```

---

## Icon Usage

No icon library is installed. Use:
1. **Unicode symbols** for simple indicators: `↑`, `↓`, `!`, `✕`, `•`
2. **SVG inline** for nav and brand (already in components)
3. **Tailwind pseudo-elements** via border/background shapes for dots and indicators

Do NOT add icon libraries without a design decision — it increases bundle size significantly.

---

## Anti-Patterns (never do these)

- ❌ `style="color: red"` — use Tailwind classes
- ❌ Custom hex colors — use the palette above
- ❌ `text-base` on panel content — use `text-sm`
- ❌ `p-4` on main panels — use `p-6`
- ❌ `rounded` on main cards — use `rounded-xl`
- ❌ Arbitrary `mb-3` between panels — use `space-y-6` on the parent
- ❌ Platform colors (`bg-blue-600`) for non-platform UI elements
