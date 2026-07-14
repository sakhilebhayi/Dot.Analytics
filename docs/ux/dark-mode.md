# Dark Mode — Strategy & Decision

**Current state:** Dark mode is **partially implemented** — the welcome page and
auth pages use dark mode classes, but the main application dashboard does not.
This inconsistency must be resolved.

---

## Decision Required

Choose one of the following:

| Option | Effort | When to choose |
|---|---|---|
| **A — Remove partial dark mode** | Low | Ship fast, clean up inconsistency, revisit later |
| **B — Extend full dark mode** | High | Full polish, enterprise branding, premium feel |
| **C — System-preference only** | Medium | Honour OS setting, no manual toggle |

**Current recommendation: Option A (Remove)** until the dashboard is stable.
The current partial implementation creates jarring inconsistency — dark landing page,
light app — which looks unfinished.

---

## If Choosing Option A — Remove Partial Dark Mode

Audit and remove `dark:` classes from welcome and auth pages that don't affect the
logged-in app experience:

Files to clean:
```
resources/views/welcome.blade.php         — remove dark: variants on text colors
resources/views/layouts/guest.blade.php   — keep white background, remove dark: variants
resources/views/components/*.blade.php    — remove dark: on form components
resources/views/auth/*.blade.php          — simplify to light-only
```

Keep:
```
resources/views/welcome.blade.php         — keep dark landing page (slate-950 bg)
                                           but make it intentional, not system-responsive
```

---

## If Choosing Option B — Full Dark Mode

### Step 1 — Enable class-based dark mode in Tailwind

`tailwind.config.js`:
```js
module.exports = {
    darkMode: 'class',  // or 'media' for OS preference
    // ...
}
```

### Step 2 — Toggle mechanism

Add to `resources/views/layouts/app.blade.php`:

```html
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{ dark: localStorage.getItem('theme') === 'dark' }"
      :class="{ 'dark': dark }">
<head>
    ...
    <script>
        // Apply before page renders to prevent flash
        if (localStorage.getItem('theme') === 'dark' ||
            (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
```

Dark mode toggle button (add to navigation):
```html
<button
    @click="dark = !dark; localStorage.setItem('theme', dark ? 'dark' : 'light')"
    :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'"
    class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">
    <span x-show="!dark" aria-hidden="true">🌙</span>
    <span x-show="dark"  aria-hidden="true">☀</span>
</button>
```

### Step 3 — Dark mode color mapping

Every light color needs a dark equivalent. Add these to `design-system.md`:

| Light | Dark | Usage |
|---|---|---|
| `bg-white` | `dark:bg-gray-800` | Panel backgrounds |
| `bg-gray-50` | `dark:bg-gray-900` | Page background |
| `text-gray-800` | `dark:text-gray-100` | Headings |
| `text-gray-700` | `dark:text-gray-300` | Body text |
| `text-gray-500` | `dark:text-gray-400` | Secondary text |
| `text-gray-400` | `dark:text-gray-500` | Muted text |
| `border-gray-200` | `dark:border-gray-700` | Borders |
| `border-gray-100` | `dark:border-gray-700/50` | Subtle borders |
| `bg-gray-100` | `dark:bg-gray-700` | Input backgrounds, hover states |
| `shadow` | `dark:shadow-gray-900/50` | Card shadows |

### Step 4 — Component dark mode additions

For each component in `resources/views/components/` and `resources/views/livewire/analytics/`, add dark mode variants following the mapping above.

Panel container:
```html
<!-- Before -->
<div class="bg-white rounded-xl shadow p-6">

<!-- After -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow dark:shadow-gray-900/50 p-6">
```

---

## If Choosing Option C — System Preference Only

`tailwind.config.js`:
```js
module.exports = {
    darkMode: 'media',  // Respects prefers-color-scheme only
    // ...
}
```

No toggle needed. Apply the dark mode color mapping (Step 3 above) but without a user toggle.

This is the lowest-effort route to consistent dark mode.

---

## Current File Inventory

### Has dark mode classes (partial)
```
resources/views/welcome.blade.php           — uses bg-slate-950 (always dark)
resources/views/layouts/guest.blade.php
resources/views/components/input.blade.php
resources/views/components/label.blade.php
resources/views/components/button.blade.php
resources/views/components/secondary-button.blade.php
resources/views/components/dropdown.blade.php
resources/views/components/nav-link.blade.php
resources/views/components/banner.blade.php
resources/views/components/validation-errors.blade.php
resources/views/auth/login.blade.php
resources/views/auth/register.blade.php
```

### No dark mode (light only)
```
All resources/views/livewire/analytics/*.blade.php
resources/views/dashboard.blade.php
resources/views/layouts/app.blade.php
```

---

## Action: Make a Decision

Before the next sprint, answer:

> **Does Dot.Analytics support dark mode? Yes / No / Not yet.**

Document the decision here, then either:
- **Option A:** Remove `dark:` classes from welcome/auth to make it consistently light
- **Option B:** Add dark mode to all dashboard components
- **Option C:** Add `darkMode: 'media'` and apply mapping to all files

Until this decision is made, new files should use **light mode only** (no `dark:` classes).

---

## PR Checklist (Dark Mode)

Until the decision is made:
- [ ] New Blade files do NOT add `dark:` classes
- [ ] Existing `dark:` classes on auth pages are not extended to new pages

Once Option B or C is chosen:
- [ ] New components include `dark:` variants
- [ ] Dark mode tested in Chrome DevTools (Force Dark Mode)
- [ ] Contrast ratios meet WCAG AA (4.5:1 text, 3:1 UI components)
