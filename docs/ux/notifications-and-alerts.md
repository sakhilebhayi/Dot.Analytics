# Notifications & Alerts — Delivery Patterns

**Purpose:** Define how and where every type of notification surfaces in the platform.
Without this, alerts pile up in one panel while critical events go unnoticed.

---

## Notification Taxonomy

| Type | Channel | Urgency | User action required? |
|---|---|---|---|
| **Critical insight** | In-app alert panel + real-time broadcast | Immediate | Yes — review + dismiss |
| **Warning insight** | In-app alert panel | Within session | Yes — review |
| **Info insight** | Insights panel only | Low | Optional |
| **AI recommendation (critical)** | Alert panel + email | Within 24h | Yes — approve/dismiss |
| **AI recommendation (standard)** | Recommendations panel | Within session | Optional |
| **Executive briefing ready** | In-app indicator + optional email | Weekly review | Optional |
| **Anomaly detected** | Alert panel + Reverb broadcast | Immediate | Yes — investigate |
| **Platform disconnected** | Banner notification | Immediate | Reconnect recommended |
| **AI budget exceeded** | Banner + email | Immediate | Review AI usage |
| **System health degraded** | Health endpoint (no UI alert) | Ops team | Ops response |

---

## In-App Banner Notifications

Use the existing `x-banner` component for session-level, dismissable notices:

```php
// In Livewire component after an important state change:
$this->banner('Dot.Fleet connected — intelligence engines are running.');

// For warnings:
$this->bannerStyle('warning');
$this->banner('AI budget is 80% consumed for today.');

// For errors:
$this->bannerStyle('danger');
$this->banner('Dot.CRM disconnected unexpectedly. Reconnect to resume intelligence.');
```

### When to use banners vs. panels

| Use banner | Use panel |
|---|---|
| Platform connect/disconnect | Intelligence insights |
| AI budget warning | Recommendations |
| Briefing generated | Anomaly history |
| Successful bulk actions | Audit-trail alerts |

---

## Real-Time Notifications via Reverb

The Reverb WebSocket is already wired. Use it for immediate surface-level alerts.

### What is already broadcast

`IntelligenceEngineCompleted` broadcasts to `team.{id}.intelligence` when an engine finishes.

### What needs to be broadcast (add these)

```php
// 1. Critical insight discovered → alert the user
class CriticalInsightDiscovered implements ShouldBroadcast
{
    public function broadcastOn(): array
    {
        return [new PrivateChannel("team.{$this->insight->team_id}.alerts")];
    }

    public function broadcastAs(): string { return 'alert.triggered'; }

    public function broadcastWith(): array
    {
        return [
            'title'    => $this->insight->title,
            'severity' => 'critical',
            'type'     => 'cross_platform_insight',
        ];
    }
}
```

### Frontend listener (already in app.js — extend it)

```js
window.Echo.private(`team.${window.teamId}.alerts`)
    .listen('.alert.triggered', (data) => {
        // Show toast notification
        showToast(data.title, data.severity);
        // Dispatch for Livewire panels to refresh
        window.dispatchEvent(new CustomEvent('intelligence:alert', { detail: data }));
    });
```

---

## Toast Notification System

The platform currently has no toast system. Add a lightweight Alpine.js component:

### `resources/views/components/toast-container.blade.php`

```html
<div
    x-data="{
        toasts: [],
        add(message, type = 'info') {
            const id = Date.now();
            this.toasts.push({ id, message, type });
            setTimeout(() => this.remove(id), 5000);
        },
        remove(id) { this.toasts = this.toasts.filter(t => t.id !== id); }
    }"
    x-on:show-toast.window="add($event.detail.message, $event.detail.type)"
    class="fixed bottom-4 right-4 z-50 space-y-2"
    aria-live="assertive"
    aria-atomic="false"
    role="status"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            :class="{
                'bg-red-600 text-white':   toast.type === 'critical',
                'bg-amber-500 text-white': toast.type === 'warning',
                'bg-green-600 text-white': toast.type === 'success',
                'bg-gray-800 text-white':  toast.type === 'info',
            }"
            class="flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg min-w-64 max-w-sm"
        >
            <p class="text-sm font-medium flex-1" x-text="toast.message"></p>
            <button @click="remove(toast.id)"
                    class="text-white/70 hover:text-white"
                    aria-label="Dismiss notification">✕</button>
        </div>
    </template>
</div>
```

Include in `resources/views/layouts/app.blade.php` before `</body>`:

```html
<x-toast-container />
```

Trigger from anywhere:

```js
// From JavaScript (Reverb listener):
window.dispatchEvent(new CustomEvent('show-toast', {
    detail: { message: 'Critical insight detected.', type: 'critical' }
}));
```

```php
// From Livewire (emit to frontend):
$this->dispatch('show-toast', message: 'Report generated.', type: 'success');
```

---

## Email Notification Priorities

| Event | Email? | When | Template needed |
|---|---|---|---|
| Critical insight | Yes | Immediately | `critical-insight-notification` |
| Weekly briefing ready | Yes (opt-in) | When status = ready | `weekly-briefing-ready` |
| AI budget 80% consumed | Yes | On threshold | `ai-budget-warning` |
| Platform disconnected | Yes | On disconnect | `platform-disconnected` |
| Anomaly detected | Yes (critical only) | On detection | `anomaly-detected` |
| Account security events | Yes | Always | Fortify handles this |

### Email template location

Create in `resources/views/emails/analytics/`:

```php
// app/Notifications/CriticalInsightNotification.php
public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject("⚠ Critical: {$this->insight->title}")
        ->markdown('emails.analytics.critical-insight', [
            'insight'    => $this->insight,
            'dashboardUrl' => url('/dashboard'),
            'teamName'   => $this->insight->team->name,
        ]);
}
```

---

## Notification Fatigue Prevention

**Rule:** Never create two alerts for the same causal chain within one engine run.

In `CrossPlatformIntelligenceService`, before creating an alert:

```php
// Check for existing unresolved alert of same type within 24h
$exists = AnalyticsAlert::where('team_id', $team->id)
    ->where('title', $alert['title'])
    ->where('status', 'open')
    ->where('triggered_at', '>=', now()->subDay())
    ->exists();

if (! $exists) {
    AnalyticsAlert::create([...]);
}
```

**Rule:** Group insights of the same type from the same engine run into a single alert when count > 3.

---

## PR Checklist (Notifications)

- [ ] Critical insights broadcast via Reverb `CriticalInsightDiscovered`
- [ ] Toast container component added to app layout
- [ ] Email notifications triggered for critical events
- [ ] No duplicate alerts for same event within 24h
- [ ] All toasts have `aria-live="assertive"` container
- [ ] Toast auto-dismiss after 5 seconds
- [ ] Users can manually dismiss notifications
- [ ] Email opt-in/opt-out settings exist for non-critical notifications
