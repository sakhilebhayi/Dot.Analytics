# Onboarding — First-Run Experience

**Why this matters:** A new team connecting their first platform will see every
panel in its empty state simultaneously. Without guidance, the platform looks
broken. A good first-run experience converts sceptics into champions.

---

## The First-Run Problem

When a team signs up:

1. Dashboard shows 4 KPIs all at `0`
2. Ecosystem map shows 15 unconnected platforms
3. Every panel shows an empty state simultaneously
4. No direction on what to do first

This creates a "blank canvas problem" — too much choice, no clear starting point.

---

## Onboarding Phases

### Phase 1 — Connect (0 platforms)
**Goal:** Get the user to connect their first platform.
**Time to value:** The moment the first platform connects.

### Phase 2 — Activate (1–2 platforms)
**Goal:** Run first intelligence engine and see first insight.
**Time to value:** First cross-platform insight discovered.

### Phase 3 — Engage (3+ platforms)
**Goal:** User has a Business DNA profile and is receiving weekly briefings.
**Time to value:** AI recommendations start appearing.

### Phase 4 — Commit (5+ platforms)
**Goal:** Dashboard is customised, reports are saved, team members are using it.
**Time to value:** Platform becomes a habit.

---

## Fix 1 — Onboarding Banner (Phase 1)

Show a contextual onboarding callout until the user dismisses it or reaches Phase 3.

Add to `resources/views/dashboard.blade.php`:

```html
@if($connectedCount === 0)
    {{-- First-run onboarding guidance --}}
    <div class="bg-gradient-to-r from-indigo-600 to-blue-600 rounded-xl p-6 text-white">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-200 mb-1">
                    Welcome to Dot.Analytics
                </p>
                <h2 class="text-xl font-bold mb-2">Connect your first platform to start</h2>
                <p class="text-indigo-100 text-sm max-w-xl">
                    Dot.Analytics discovers intelligence by connecting data from multiple Dot platforms.
                    Start with the platform you use most — results appear within minutes.
                </p>
                <div class="flex gap-3 mt-4">
                    <a href="#ecosystem-map"
                       class="bg-white text-indigo-600 px-4 py-2 rounded-lg text-sm font-semibold
                              hover:bg-indigo-50 transition-colors">
                        Connect a Platform →
                    </a>
                    <a href="#"
                       class="text-indigo-200 hover:text-white text-sm py-2">
                        Watch 2-min tour
                    </a>
                </div>
            </div>
            <div class="text-4xl" aria-hidden="true">◎</div>
        </div>
    </div>
@elseif($connectedCount === 1 && $activeEngineCount === 0)
    {{-- Phase 2: first platform connected, no engines run yet --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <p class="text-sm font-medium text-blue-800">
            ✓ First platform connected. Run the intelligence engines to discover your first insights.
        </p>
        <livewire:analytics.cross-platform-insight-panel />
    </div>
@endif
```

---

## Fix 2 — Progress Indicator for New Teams

Show onboarding progress in the top of the Ecosystem Map panel:

```html
@if($this->connectedCount < 3)
    <div class="mb-5 bg-indigo-50 border border-indigo-100 rounded-xl p-4">
        <p class="text-xs font-semibold text-indigo-700 mb-2">Getting started</p>
        <div class="space-y-2">
            @php
                $steps = [
                    ['done' => $this->connectedCount >= 1, 'label' => 'Connect your first platform'],
                    ['done' => $this->connectedCount >= 2, 'label' => 'Connect a second platform to enable cross-platform intelligence'],
                    ['done' => $this->connectedCount >= 3, 'label' => 'Connect 3+ platforms to unlock the Business DNA profile'],
                ];
            @endphp
            @foreach($steps as $step)
                <div class="flex items-center gap-2">
                    <span class="{{ $step['done'] ? 'text-green-500' : 'text-gray-300' }} text-sm">
                        {{ $step['done'] ? '✓' : '○' }}
                    </span>
                    <span class="text-xs {{ $step['done'] ? 'text-gray-400 line-through' : 'text-gray-700' }}">
                        {{ $step['label'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
@endif
```

---

## Fix 3 — Recommended Starting Platforms

On the Ecosystem Map, surface a "Recommended for you" section before the full grid:

```html
@if($this->connectedCount === 0)
    <div class="mb-5">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
            Start here — most popular platforms
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @foreach(['dot.fleet', 'dot.crm', 'dot.hr'] as $key)
                @php $platform = $this->platformCatalog[$key]; @endphp
                <div class="border-2 border-indigo-200 bg-indigo-50 rounded-xl p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2 h-2 rounded-full {{ $platform['colors']['dot'] }}"></span>
                        <p class="text-sm font-semibold {{ $platform['colors']['text'] }}">
                            {{ $platform['label'] }}
                        </p>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">{{ $platform['description'] }}</p>
                    <button wire:click="startConnect('{{ $key }}')"
                            class="text-xs font-medium text-indigo-600 hover:underline">
                        Connect →
                    </button>
                </div>
            @endforeach
        </div>
    </div>
@endif
```

---

## Fix 4 — Contextual Empty States with Next Step

Replace generic empty states with guided ones for first-run:

```php
// In IntelligenceDashboard Livewire component:
public function getFirstRunStep(): string
{
    $connected = DataSource::where('team_id', Auth::user()->currentTeam->id)
        ->where('status', 'connected')->count();

    return match(true) {
        $connected === 0  => 'connect_first_platform',
        $connected === 1  => 'run_engines',
        $connected >= 2   => 'ready',
        default           => 'ready',
    };
}
```

```html
@if($this->firstRunStep === 'connect_first_platform')
    <div class="text-center py-10">
        <p class="text-sm font-medium text-gray-700 mb-1">No intelligence data yet</p>
        <p class="text-xs text-gray-400 mb-4">Connect your first Dot platform to start generating insights.</p>
        <a href="#ecosystem-map" class="text-xs bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
            Go to Ecosystem Map
        </a>
    </div>
@elseif($this->firstRunStep === 'run_engines')
    <div class="text-center py-10">
        <p class="text-sm font-medium text-gray-700 mb-1">Platform connected — run engines to see insights</p>
        <p class="text-xs text-gray-400 mb-4">Your intelligence engines are ready. Run them to discover your first insights.</p>
        <livewire:analytics.cross-platform-insight-panel />
    </div>
@endif
```

---

## Fix 5 — Post-Connection Celebration

When a platform connects for the first time, show a brief positive reinforcement:

```php
// In EcosystemMapPanel::confirmConnect():
public function confirmConnect(): void
{
    // ... existing logic ...

    // First platform connected? Show celebration
    if (DataSource::where('team_id', Auth::user()->currentTeam->id)
            ->where('status', 'connected')->count() === 1) {
        $this->dispatch('show-toast',
            message: '✓ First platform connected! Intelligence engines are running.',
            type: 'success'
        );
    }
}
```

---

## Onboarding Email Sequence

| Trigger | Email | When |
|---|---|---|
| Signup | Welcome + "Connect your first platform" | Immediately |
| First platform connected | "Your first insight is running" | 5 minutes after |
| First insight discovered | "Your first cross-platform insight" | On first insight |
| 7 days, 0 platforms | "You haven't connected a platform yet" | Day 7 |
| 3+ platforms, no briefing | "Try your first weekly briefing" | Day 3 |

---

## PR Checklist (Onboarding)

- [ ] Dashboard shows onboarding banner when 0 platforms connected
- [ ] Ecosystem map shows "Start here" recommended platforms for new teams
- [ ] Progress indicator shown until 3+ platforms connected
- [ ] Empty states include next-step CTAs for first-run state
- [ ] Post-connection toast celebration shown on first connect
- [ ] Onboarding email sequence defined in `app/Notifications/`
