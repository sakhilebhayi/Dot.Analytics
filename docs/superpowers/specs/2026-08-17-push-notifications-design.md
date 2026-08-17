# Push Notifications — Design Spec

## Context

The third and final direction from the original reach-features brainstorm
(dashboard builder, global search, push notifications). The user asked for
an explanation before committing to build, same as global search; that
discussion narrowed "email/Slack digest + in-app inbox" down through three
decisions:

- **Scope**: in-app bell + email. Slack deferred entirely (no existing
  package, no per-team webhook storage anywhere — meaningfully heavier than
  the other two, not bundled in here).
- **Bell behavior**: a persistent inbox with an unread count (not a
  live-toast-only, no-history design).
- **Email recipients**: every team member, matching the bell's own reach —
  no separate, narrower audience for email.

**What a read of the actual code found, before any design started:**

1. **`NotifyOnCriticalInsight` doesn't notify anyone.** Despite the name
   (`app/Listeners/Analytics/NotifyOnCriticalInsight.php`), it only creates
   an in-app `AnalyticsAlert` row when `CriticalInsightDiscovered` fires. No
   email, no push, no database notification — silent unless a user happens
   to be looking at the dashboard.
2. **Executive briefings already generate on a real, live cron schedule**
   with zero delivery. `routes/console.php` schedules
   `GenerateBriefingsCommand` daily at 06:00, weekly on Mondays at 06:30,
   and monthly on the 1st at 07:00. `GenerateExecutiveBriefingJob` (queued)
   does the actual generation and marks the row `status: ready`. Nobody is
   ever told a briefing exists; it only surfaces if a user opens
   `/dashboard` and looks at the Executive Briefing panel.
3. **Two events already broadcast on real, authorized private channels with
   nothing listening.** `CriticalInsightDiscovered` and
   `IntelligenceEngineCompleted` both implement `ShouldBroadcast` and fire
   on `team.{teamId}.alerts` / `team.{teamId}.intelligence`
   (`routes/channels.php` authorizes both). `laravel-echo` and `pusher-js`
   are both in `package.json` — but grepping every Blade view and every
   Livewire component finds zero `Echo.private(...)` calls and zero
   `#[On(...)]` listeners anywhere. The exact same "installed but never
   wired up" shape as Scout before the previous PR.
4. **`User` already has Laravel's `Notifiable` trait** (standard Jetstream
   scaffold, confirmed by reading `app/Models/User.php`) — Laravel's
   built-in multi-channel Notification system (mail + database + broadcast
   from one class) is usable with zero model changes.
5. **A branded transactional email theme already exists and is unused
   outside Jetstream's own team-invitation email.**
   `resources/views/vendor/mail/html/themes/default.css` uses this app's
   real ink/teal/paper tokens (`#eef2f0`, `#2bb6b7`, `#11201e` — confirmed
   by reading the file directly). Laravel's standard `MailMessage` builder
   renders through this theme automatically; no new email template needed.
6. **A real, subtler bug found while designing the real-time bell**:
   `SecurityHeaders::reverbWsOrigin()` (added in the earlier CSP fix, see
   `56002bf`) reads `config('reverb.servers.reverb.host')` for its CSP
   `connect-src` value — but that config key is the **server's bind
   address** (`0.0.0.0` in this dev environment, confirmed via
   `php artisan config:show reverb`), not the address a browser can
   actually connect to. This was never exercised before, since nothing
   ever opened a browser-side WebSocket connection — the CSP entry has been
   silently wrong-but-unused. This spec's new Echo client uses the
   client-facing `REVERB_HOST` env value directly rather than reusing that
   helper; fixing the helper itself is a separate, smaller follow-up not
   done here (see Non-Goals).

## Goal

Two things that already generate real content with nobody told about
them — critical alerts and executive briefings — get delivered: a
persistent in-app notification bell (finishing the broadcasting
infrastructure that already exists) and email, both reaching every team
member.

## Non-Goals

- **Slack.** No existing package, no per-team webhook storage. A genuinely
  separate, heavier follow-up.
- **Per-user notification preferences / opt-out.** Every team member gets
  both channels for both event types — no settings UI, no `notification_preferences`
  table. A real, common expectation eventually, but not asked for here.
- **Fixing `SecurityHeaders::reverbWsOrigin()`'s bind-vs-client-host
  confusion.** Documented in Context as a real, found bug — this spec's own
  Echo client avoids it by using `REVERB_HOST` directly, but the existing
  CSP helper method itself is untouched. A one-line follow-up, not bundled
  into this feature.
- **Notifying on every event type that exists.** Only
  `CriticalInsightDiscovered` (→ critical alerts) and briefing-ready. Other
  real events (`PlatformConnected`, `PlatformDisconnected`,
  `IntelligenceEngineCompleted`, `BusinessDnaRecomputed`) stay exactly as
  they are today — administrative/operational signals, not the kind of
  thing that should land in an inbox or an email for every occurrence.
- **A real SMTP mailer for this dev environment.** `MAIL_MAILER=log` stays
  as-is; sending real email in production is a deployment/ops concern
  outside this feature's scope, not something the feature itself needs to
  configure.
- **Vite/build-pipeline migration for the authenticated layout.** Echo and
  Pusher load via CDN, matching how Sortable.js was added for Custom
  Dashboards — this layout has no build step and this spec doesn't
  introduce one.

## Design

### 1. Two Notification classes

`php artisan notifications:table` + migrate — Laravel's own built-in
polymorphic `notifications` table. No custom model. Notifications are
**user-scoped, not team-scoped** — deliberately: like GitHub notifications,
they should follow a user regardless of which team they currently have
active, not disappear or reappear as they switch teams.

`app/Notifications/CriticalAlertTriggered.php`:

```php
<?php

namespace App\Notifications;

use App\Models\AnalyticsAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CriticalAlertTriggered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AnalyticsAlert $alert) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Critical alert: {$this->alert->title}")
            ->line($this->alert->description)
            ->action('View in Dot.Analytics', route('dashboard'))
            ->line('This is a critical-severity alert on your team\'s intelligence dashboard.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'critical_alert',
            'title' => $this->alert->title,
            'description' => $this->alert->description,
            'url' => route('dashboard'),
        ];
    }
}
```

`app/Notifications/ExecutiveBriefingReady.php`:

```php
<?php

namespace App\Notifications;

use App\Models\ExecutiveBriefing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExecutiveBriefingReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ExecutiveBriefing $briefing) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $period = ucfirst($this->briefing->period);

        return (new MailMessage)
            ->subject("Your {$this->briefing->period} intelligence briefing is ready")
            ->line("{$period} briefing for {$this->briefing->period_date}:")
            ->line($this->briefing->summary)
            ->action('View full briefing', route('dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'briefing_ready',
            'title' => ucfirst($this->briefing->period).' briefing ready',
            'description' => $this->briefing->summary,
            'url' => route('dashboard'),
        ];
    }
}
```

### 2. Trigger points

`NotifyOnCriticalInsight::handle()` — after creating the `AnalyticsAlert`
row it already creates, add:

```php
Notification::send($insight->team->users, new CriticalAlertTriggered($alert));
```

Finishes what the listener's own name already promised, rather than adding
a second, differently-named listener for the same event.

`GenerateExecutiveBriefingJob::handle()` — after the existing
`$briefing->update([...'status' => 'ready'...])` call, add:

```php
Notification::send($team->users, new ExecutiveBriefingReady($briefing));
```

`$team->users` is Jetstream's own existing relation (already used
elsewhere in this codebase, e.g. `RemoveTeamMemberTest`'s fixtures) — no
new query needed.

### 3. The bell

New Livewire component, nav bar, next to the search box and team switcher
added in the previous two PRs. Uses `Auth::user()->unreadNotifications` and
`Auth::user()->notifications` (both built into `Notifiable`, zero new
query code) for the unread badge and the dropdown list. Clicking a
notification calls `->markAsRead()` (built in) and navigates via the
stored `url`. A "Mark all as read" action calls
`Auth::user()->unreadNotifications->markAsRead()` (also built in).

### 4. Real-time

Echo + Pusher-js loaded via CDN in `resources/views/layouts/app.blade.php`,
next to the existing Sortable.js `<script>` tag:

```html
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.5.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@2.4.0/dist/echo.iife.js"></script>
```

Version pins match `package.json`'s own `pusher-js: ^8.5.0` /
`laravel-echo: ^2.4.0` entries exactly (confirmed by reading the file
directly — an earlier draft of this section guessed older, wrong numbers
before that check), matched to their CDN-hosted equivalents. Confirm the
CDN build actually exposes a global `Echo`/`Pusher` and that
`broadcaster: 'reverb'` is supported (native Reverb support landed in
`laravel-echo` 1.16, so 2.4.0 has it) before relying on this in the
implementation plan.

**Calling `env()` directly in a Blade view would be a real, if easy to
miss, bug**: it silently returns `null` for every key once `php artisan
config:cache` has run (a normal production step) — `.env` isn't even read
at that point. `env()` is only safe inside `config/*.php` files. Reverb's
own package config has no client-facing host value to reuse either way (its
`apps.apps.0.options.host` resolves to the same `0.0.0.0` bind address as
`servers.reverb.host` — checked directly, not assumed); Laravel's own
official pattern solves this via Vite's `VITE_REVERB_HOST` injection, which
doesn't apply here since this layout has no Vite build. The fix: a small
new `config/echo.php`, the one place `env()` is meant to be called:

```php
<?php

return [
    'key' => env('REVERB_APP_KEY'),
    'host' => env('REVERB_HOST', 'localhost'),
    'port' => env('REVERB_PORT', 8080),
    'scheme' => env('REVERB_SCHEME', 'http'),
];
```

Then in `resources/views/layouts/app.blade.php`, config-backed (not raw
`env()`) and cache-safe:

```html
<script>
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: '{{ config('echo.key') }}',
        wsHost: '{{ config('echo.host') }}',
        wsPort: {{ config('echo.port') }},
        forceTLS: {{ config('echo.scheme') === 'https' ? 'true' : 'false' }},
        enabledTransports: ['ws', 'wss'],
    });

    Echo.private('App.Models.User.{{ Auth::id() }}')
        .notification(() => {
            Livewire.dispatch('notification-received');
        });
</script>
```

`App.Models.User.{id}` is Laravel's own default per-notifiable broadcast
channel (automatic once a model uses `Notifiable` — no entry needed in
`routes/channels.php`, Laravel authorizes it against the authenticated user
automatically). The bell component listens for the `notification-received`
Livewire event via `#[On('notification-received')]` and busts its own
computed unread-count/list cache — no polling.

### 5. Testing

- **Trigger tests**: `CriticalInsightDiscovered` firing results in every
  team member having an unread `CriticalAlertTriggered` notification
  (`Notification::fake()` + `Notification::assertSentTo()`, Laravel's
  standard pattern). Same shape for `GenerateExecutiveBriefingJob`
  reaching `status: ready`.
- **Notification content tests**: `toMail()`/`toArray()` produce the
  expected subject/fields for each class, direct unit-style tests (no
  fake needed — just instantiate and call the methods).
- **Bell component tests**: unread count reflects real
  `unreadNotifications`; clicking marks one as read; "mark all as read"
  clears the badge; a notification for a *different* user never appears
  (mirrors the cross-tenant test shape from every other feature this
  session).
- Full existing suite stays green.

## Out of Scope

- Slack, per-user preferences, the `reverbWsOrigin()` bind-address fix
  (Non-Goals).
- Notification triggers for any event beyond the two named here (Non-Goals).
- Real SMTP configuration for any environment (Non-Goals).
