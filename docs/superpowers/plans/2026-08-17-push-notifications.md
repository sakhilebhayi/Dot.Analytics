# Push Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver critical alerts and executive briefings — both already generated with nobody told — via a persistent in-app notification bell and email, reaching every team member.

**Architecture:** Laravel's built-in multi-channel Notification system (`User` already has `Notifiable`) — two classes, `CriticalAlertTriggered` and `ExecutiveBriefingReady`, each declaring `mail` + `database` + `broadcast` channels. `mail` renders through the already-published, already-branded transactional mail theme. `database` uses Laravel's own stock `notifications` table (no custom model — notifications are deliberately user-scoped, not team-scoped). `broadcast` uses Laravel's automatic per-notifiable private channel (`App.Models.User.{id}`, zero new channel-auth code). A new bell Livewire component in the nav bar reads `Auth::user()->unreadNotifications`/`->notifications` directly (built into `Notifiable`). Real-time delivery needs `laravel-echo`/`pusher-js` (already npm dependencies, never initialized) loaded via CDN and a small `config/echo.php` — which also fixes a real, found bug in `SecurityHeaders::reverbWsOrigin()` that would otherwise have the CSP block the very WebSocket connection this feature needs.

**Tech Stack:** Laravel 13, Livewire 3, Laravel Notifications (mail/database/broadcast channels), Reverb (already running), `laravel-echo` 2.4.0 / `pusher-js` 8.5.0 (CDN), PHPUnit.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-17-push-notifications-design.md` — read it first; two corrections were made to it after user review, both documented inline in their own commits, both folded into this plan already:
  1. `SecurityHeaders::reverbWsOrigin()` **is** fixed as part of this plan (an earlier spec draft said not to touch it; left alone, CSP would block the feature's own WebSocket connection).
  2. Recipients use `Team::allUsers()`, not `Team::users()` — the latter (Jetstream's own base class) excludes the team owner unless separately attached as a member, which `withPersonalTeam()` never does.
- No Slack, no per-user preferences, no notification triggers beyond the two named (critical alerts, briefing-ready) — see spec Non-Goals.
- This branch (`feat/push-notifications`) is based directly on `feature/ecosystem-sso` — no dependency on the still-open Custom Dashboards or Global Search PRs, so their nav-bar additions (search box, "My Dashboards" link) do **not** exist on this branch. The bell is placed the same way those were: first item inside the existing `<div class="hidden sm:flex sm:items-center sm:ms-6">` in `navigation-menu.blade.php`, immediately before the `<!-- Teams Dropdown -->` comment.
- Run `vendor/bin/pint --dirty --format agent` after every task, before committing.

---

### Task 1: Foundation — notifications table, Echo config, CSP fix

**Files:**
- Create: a migration via `php artisan notifications:table` (Laravel's own stock stub — run the real command, don't hand-write it)
- Create: `config/echo.php`
- Modify: `app/Http/Middleware/SecurityHeaders.php`
- Modify: `tests/Feature/SecurityHeadersTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: the `notifications` table (consumed by Tasks 2–4), `config('echo.*')` (consumed by Tasks 2, 5, and this task's own CSP fix).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/SecurityHeadersTest.php` (this file currently has 4
tests on this branch — confirmed by reading it directly; it does not yet
have the Sortable.js-CDN test that exists on the separate Custom
Dashboards branch, since neither branch has merged into the other):

```php
    public function test_csp_connect_src_matches_the_echo_client_host_exactly(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        $expectedOrigin = 'ws://'.config('echo.host').':'.config('echo.port');
        $this->assertStringContainsString($expectedOrigin, $csp);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/SecurityHeadersTest.php`
Expected: FAIL — `config('echo.host')`/`config('echo.port')` don't exist
yet (null), so the expected string can't match anything meaningful; more
importantly this documents intent before the fix exists.

- [ ] **Step 3: Publish the notifications table migration**

```bash
php artisan notifications:table
php artisan migrate
```

- [ ] **Step 4: Create `config/echo.php`**

```php
<?php

return [
    'key' => env('REVERB_APP_KEY'),
    'host' => env('REVERB_HOST', 'localhost'),
    'port' => env('REVERB_PORT', 8080),
    'scheme' => env('REVERB_SCHEME', 'http'),
];
```

- [ ] **Step 5: Fix `reverbWsOrigin()` and document why**

In `app/Http/Middleware/SecurityHeaders.php`, change the numbered CSP
comment block from "Three real mismatches" to "Four", and add a fourth
entry after the existing three (before the `$csp = implode(...)` line):

```php
        // 4. connect-src must match the host the browser's own Echo client
        //    actually connects to, not the Reverb server's bind address.
        //    reverbWsOrigin() previously read config('reverb.servers.reverb.host'),
        //    which is 0.0.0.0 in this dev environment (confirmed via
        //    `php artisan config:show reverb`) -- the server's listen
        //    address, not something a browser can connect to. Never
        //    exercised before Push Notifications, since nothing opened a
        //    browser-side WebSocket connection until now. Both this CSP
        //    header and the Echo client (layouts/app.blade.php) now read
        //    the same config/echo.php, so there's one source of truth
        //    instead of two values that can silently drift apart.
```

Also update the opening line from `"Three real mismatches found while..."`
to `"Four real mismatches found while..."`.

Replace the `reverbWsOrigin()` method:

```php
    private function reverbWsOrigin(): string
    {
        $scheme = config('echo.scheme') === 'https' ? 'wss' : 'ws';
        $host = config('echo.host');
        $port = config('echo.port');

        return "{$scheme}://{$host}:{$port}";
    }
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/SecurityHeadersTest.php`
Expected: PASS, 5 tests, 0 failures.

- [ ] **Step 7: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/*_create_notifications_table.php config/echo.php \
  app/Http/Middleware/SecurityHeaders.php tests/Feature/SecurityHeadersTest.php
git commit -m "$(cat <<'EOF'
feat: notifications table + fix CSP/Echo host mismatch

Foundation for Push Notifications (see docs/superpowers/specs/2026-08-17-
push-notifications-design.md). Publishes Laravel's own stock
notifications table (User already has Notifiable). Adds config/echo.php
as the one place REVERB_HOST/PORT/SCHEME get read for anything
browser-facing, and fixes SecurityHeaders::reverbWsOrigin() to read
the same config -- it previously read the server's bind address
(0.0.0.0 in this dev environment), which would have made CSP block
this feature's own WebSocket connection the moment it existed. Never
exercised before now, since nothing ever opened a browser-side
connection.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: `CriticalAlertTriggered` notification

**Files:**
- Create: `app/Notifications/CriticalAlertTriggered.php`
- Modify: `app/Listeners/Analytics/NotifyOnCriticalInsight.php`
- Test: `tests/Unit/Listeners/NotifyOnCriticalInsightTest.php` (extend — this file already exists, confirmed by reading it directly; its two existing tests call `$listener->handle()` directly without faking notifications, and stay green unaffected since `phpunit.xml` sets `MAIL_MAILER=array`, `BROADCAST_CONNECTION=null`, `QUEUE_CONNECTION=sync` — all safe no-op/in-memory drivers, so the new `Notification::send()` call is harmless to them)
- Test: `tests/Unit/Notifications/CriticalAlertTriggeredTest.php` (new)

**Interfaces:**
- Consumes: `notifications` table (Task 1), the existing `AnalyticsAlert` created inside the listener.
- Produces: `CriticalAlertTriggered` notification class — consumed by Task 4's bell (reads generically via `Notifiable`, no per-type coupling needed).

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Notifications/CriticalAlertTriggeredTest.php`:

```php
<?php

namespace Tests\Unit\Notifications;

use App\Models\AnalyticsAlert;
use App\Models\User;
use App\Notifications\CriticalAlertTriggered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalAlertTriggeredTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_declares_mail_database_and_broadcast(): void
    {
        $user = User::factory()->create();
        $alert = AnalyticsAlert::factory()->create();
        $notification = new CriticalAlertTriggered($alert);

        $this->assertSame(['mail', 'database', 'broadcast'], $notification->via($user));
    }

    public function test_to_mail_includes_the_alert_title_and_description(): void
    {
        $user = User::factory()->create();
        $alert = AnalyticsAlert::factory()->create([
            'title' => 'Overtime spike detected',
            'description' => 'Costs are up 30% this week.',
        ]);
        $notification = new CriticalAlertTriggered($alert);

        $mail = $notification->toMail($user);

        $this->assertStringContainsString('Overtime spike detected', $mail->subject);
        $this->assertContains('Costs are up 30% this week.', $mail->introLines);
    }

    public function test_to_array_includes_a_url_to_the_dashboard(): void
    {
        $user = User::factory()->create();
        $alert = AnalyticsAlert::factory()->create(['title' => 'Test Alert', 'description' => 'Detail']);
        $notification = new CriticalAlertTriggered($alert);

        $data = $notification->toArray($user);

        $this->assertSame('critical_alert', $data['type']);
        $this->assertSame('Test Alert', $data['title']);
        $this->assertSame(route('dashboard'), $data['url']);
    }
}
```

`$notifiable` is typed `object $notifiable` (non-nullable) on both
notification classes above, so every test here and in
`ExecutiveBriefingReadyTest` below passes a real `User` rather than
`null` — passing `null` would throw a `TypeError` at the type boundary.

Extend `tests/Unit/Listeners/NotifyOnCriticalInsightTest.php` with one more
test, appended inside the existing class:

```php
    public function test_listener_notifies_every_team_member_including_the_owner(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);

        $insight = CrossPlatformInsight::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'severity' => 'critical',
        ]);

        $listener = new NotifyOnCriticalInsight;
        $listener->handle(new CriticalInsightDiscovered($insight));

        \Illuminate\Support\Facades\Notification::assertSentTo($owner, \App\Notifications\CriticalAlertTriggered::class);
        \Illuminate\Support\Facades\Notification::assertSentTo($member, \App\Notifications\CriticalAlertTriggered::class);
    }
```

This is the test that specifically exercises the `Team::allUsers()` vs
`Team::users()` distinction from Global Constraints — using `->users()`
here would fail to notify `$owner`, since `$owner` was never separately
attached as a pivot member of their own personal team.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Unit/Notifications/CriticalAlertTriggeredTest.php tests/Unit/Listeners/NotifyOnCriticalInsightTest.php`
Expected: the 3 new `CriticalAlertTriggeredTest` tests FAIL (class doesn't
exist); the new listener test FAILS (`Notification::assertSentTo` finds
nothing sent); the 2 pre-existing listener tests still PASS.

- [ ] **Step 3: Write the notification**

Create `app/Notifications/CriticalAlertTriggered.php`:

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

- [ ] **Step 4: Wire it into the listener**

Replace `app/Listeners/Analytics/NotifyOnCriticalInsight.php`:

```php
<?php

namespace App\Listeners\Analytics;

use App\Events\Analytics\CriticalInsightDiscovered;
use App\Models\AnalyticsAlert;
use App\Notifications\CriticalAlertTriggered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * When a critical cross-platform insight is discovered, create a
 * high-severity alert so it surfaces in the UI immediately, and notify
 * every team member (mail + in-app bell + real-time broadcast).
 */
class NotifyOnCriticalInsight implements ShouldQueue
{
    public function handle(CriticalInsightDiscovered $event): void
    {
        $insight = $event->insight;

        $alert = AnalyticsAlert::create([
            'team_id' => $insight->team_id,
            'title' => $insight->title,
            'description' => $insight->narrative,
            'severity' => 'critical',
            'status' => 'open',
            'context' => [
                'platforms_involved' => $insight->platforms_involved,
                'insight_type' => $insight->insight_type,
                'confidence' => $insight->confidence,
                'source' => 'cross_platform_intelligence',
                'insight_id' => $insight->id,
            ],
            'triggered_at' => now(),
        ]);

        Notification::send($insight->team->allUsers(), new CriticalAlertTriggered($alert));
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Unit/Notifications/CriticalAlertTriggeredTest.php tests/Unit/Listeners/NotifyOnCriticalInsightTest.php`
Expected: PASS, 6 tests total (3 new notification tests + 2 pre-existing +
1 new listener test), 0 failures.

- [ ] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 7: Commit**

```bash
git add app/Notifications/CriticalAlertTriggered.php \
  app/Listeners/Analytics/NotifyOnCriticalInsight.php \
  tests/Unit/Notifications/CriticalAlertTriggeredTest.php \
  tests/Unit/Listeners/NotifyOnCriticalInsightTest.php
git commit -m "$(cat <<'EOF'
feat: CriticalAlertTriggered notification, finishing NotifyOnCriticalInsight

The listener's own name has promised this since it was written --
until now it only ever created a silent in-app AnalyticsAlert row.
Notifies every team member (Team::allUsers(), not users() -- see
Global Constraints) via mail, the new database-backed bell, and a
real-time broadcast.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: `ExecutiveBriefingReady` notification

**Files:**
- Create: `app/Notifications/ExecutiveBriefingReady.php`
- Create: `database/factories/ExecutiveBriefingFactory.php`
- Modify: `app/Models/ExecutiveBriefing.php` (add `HasFactory` — confirmed absent by reading the file directly during spec/plan research)
- Modify: `app/Jobs/Analytics/GenerateExecutiveBriefingJob.php`
- Test: `tests/Unit/Notifications/ExecutiveBriefingReadyTest.php` (new)
- Test: `tests/Unit/Jobs/GenerateExecutiveBriefingJobNotificationTest.php` (new — no existing test file for this job was found during research, confirmed by `find tests -iname '*GenerateExecutiveBriefing*'` returning nothing)

**Interfaces:**
- Consumes: `notifications` table (Task 1).
- Produces: `ExecutiveBriefingReady` notification class, `ExecutiveBriefing::factory()` — consumed by Task 4's bell tests if needed.

- [ ] **Step 1: Write the failing tests**

Create `database/factories/ExecutiveBriefingFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\ExecutiveBriefing;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExecutiveBriefing>
 */
class ExecutiveBriefingFactory extends Factory
{
    protected $model = ExecutiveBriefing::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'period' => 'weekly',
            'period_date' => now()->toDateString(),
            'status' => 'ready',
            'summary' => $this->faker->paragraph(),
            'highlights' => [$this->faker->sentence()],
            'risks' => [$this->faker->sentence()],
            'recommendations' => [],
            'engines_consulted' => ['business', 'operational'],
            'insight_count' => $this->faker->numberBetween(0, 20),
        ];
    }
}
```

In `app/Models/ExecutiveBriefing.php`, add `HasFactory`:

```php
use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutiveBriefing extends Model
{
    use HasFactory, HasTeamScope;
```

Create `tests/Unit/Notifications/ExecutiveBriefingReadyTest.php`:

```php
<?php

namespace Tests\Unit\Notifications;

use App\Models\ExecutiveBriefing;
use App\Models\User;
use App\Notifications\ExecutiveBriefingReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveBriefingReadyTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_declares_mail_database_and_broadcast(): void
    {
        $user = User::factory()->create();
        $briefing = ExecutiveBriefing::factory()->create();
        $notification = new ExecutiveBriefingReady($briefing);

        $this->assertSame(['mail', 'database', 'broadcast'], $notification->via($user));
    }

    public function test_to_mail_names_the_period_and_includes_the_summary(): void
    {
        $user = User::factory()->create();
        $briefing = ExecutiveBriefing::factory()->create([
            'period' => 'weekly',
            'summary' => 'Productivity is up 5% this week.',
        ]);
        $notification = new ExecutiveBriefingReady($briefing);

        $mail = $notification->toMail($user);

        $this->assertStringContainsString('weekly', $mail->subject);
        $this->assertContains('Productivity is up 5% this week.', $mail->introLines);
    }

    public function test_to_array_includes_a_url_to_the_dashboard(): void
    {
        $user = User::factory()->create();
        $briefing = ExecutiveBriefing::factory()->create(['period' => 'monthly']);
        $notification = new ExecutiveBriefingReady($briefing);

        $data = $notification->toArray($user);

        $this->assertSame('briefing_ready', $data['type']);
        $this->assertSame('Monthly briefing ready', $data['title']);
        $this->assertSame(route('dashboard'), $data['url']);
    }
}
```

Create `tests/Unit/Jobs/GenerateExecutiveBriefingJobNotificationTest.php`:

```php
<?php

namespace Tests\Unit\Jobs;

use App\Jobs\Analytics\GenerateExecutiveBriefingJob;
use App\Models\Team;
use App\Models\User;
use App\Notifications\ExecutiveBriefingReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GenerateExecutiveBriefingJobNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_a_briefing_notifies_every_team_member_including_the_owner(): void
    {
        Notification::fake();

        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);

        (new GenerateExecutiveBriefingJob($owner->currentTeam->id, 'weekly', now()->toDateString()))->handle(
            app(\App\Services\AiModelRouter::class),
            app(\App\Services\IntelligenceEngineService::class),
        );

        Notification::assertSentTo($owner, ExecutiveBriefingReady::class);
        Notification::assertSentTo($member, ExecutiveBriefingReady::class);
    }
}
```

This test relies on `AiModelRouter::complete()` behaving safely with no AI
key configured in the test environment — the same safe-no-op behavior
`test_recommendations_panel_generate_creates_recommendations` in
`ExtendedLivewireTest.php` already documents and relies on ("With no AI
key, mock returns [], so no DB entries" — confirmed by reading that test
directly). If this assumption turns out wrong when the test actually runs,
fall back to asserting on `$team->executiveBriefings()->latest()->first()`
directly rather than constructing the job inline, and note the deviation.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Unit/Notifications/ExecutiveBriefingReadyTest.php tests/Unit/Jobs/GenerateExecutiveBriefingJobNotificationTest.php`
Expected: FAIL — `ExecutiveBriefingReady` doesn't exist yet;
`ExecutiveBriefing::factory()` doesn't exist yet; the job doesn't send any
notification yet.

- [ ] **Step 3: Write the notification**

Create `app/Notifications/ExecutiveBriefingReady.php`:

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

- [ ] **Step 4: Wire it into the job**

In `app/Jobs/Analytics/GenerateExecutiveBriefingJob.php`, add imports:

```php
use App\Notifications\ExecutiveBriefingReady;
use Illuminate\Support\Facades\Notification;
```

Change the end of `handle()` from:

```php
        $briefing->update([
            'status' => 'ready',
            'summary' => $parsed['summary'] ?? 'Intelligence briefing generated.',
            'highlights' => $parsed['highlights'] ?? [],
            'risks' => $parsed['risks'] ?? [],
            'recommendations' => $parsed['recommendations'] ?? [],
            'engines_consulted' => array_keys($activeEngines),
            'insight_count' => $team->crossPlatformInsights()->count(),
        ]);
    }
```

to:

```php
        $briefing->update([
            'status' => 'ready',
            'summary' => $parsed['summary'] ?? 'Intelligence briefing generated.',
            'highlights' => $parsed['highlights'] ?? [],
            'risks' => $parsed['risks'] ?? [],
            'recommendations' => $parsed['recommendations'] ?? [],
            'engines_consulted' => array_keys($activeEngines),
            'insight_count' => $team->crossPlatformInsights()->count(),
        ]);

        Notification::send($team->allUsers(), new ExecutiveBriefingReady($briefing));
    }
```

Note the existing early return above this (`if ($briefing->status ===
'ready') { return; }`) already prevents a duplicate notification if the
job somehow runs twice for the same period — confirmed by reading the
job's current full body during spec/plan research.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Unit/Notifications/ExecutiveBriefingReadyTest.php tests/Unit/Jobs/GenerateExecutiveBriefingJobNotificationTest.php`
Expected: PASS, 4 tests, 0 failures. If the job test fails specifically due
to the `AiModelRouter` assumption noted in Step 1, apply that step's
documented fallback and note the deviation here before continuing.

- [ ] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 7: Commit**

```bash
git add app/Notifications/ExecutiveBriefingReady.php \
  database/factories/ExecutiveBriefingFactory.php \
  app/Models/ExecutiveBriefing.php \
  app/Jobs/Analytics/GenerateExecutiveBriefingJob.php \
  tests/Unit/Notifications/ExecutiveBriefingReadyTest.php \
  tests/Unit/Jobs/GenerateExecutiveBriefingJobNotificationTest.php
git commit -m "$(cat <<'EOF'
feat: ExecutiveBriefingReady notification, finishing the briefing pipeline

GenerateExecutiveBriefingJob already runs on a real cron schedule
(daily 06:00, weekly Monday 06:30, monthly on the 1st) and already
marks briefings status: ready -- nobody was ever told one existed
unless they opened /dashboard and looked. Notifies every team member
via mail, the bell, and real-time broadcast the moment a briefing
completes.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Notification bell

**Files:**
- Create: `app/Livewire/Analytics/NotificationBell.php`
- Create: `resources/views/livewire/analytics/notification-bell.blade.php`
- Modify: `resources/views/navigation-menu.blade.php`
- Test: `tests/Feature/Livewire/NotificationBellTest.php`

**Interfaces:**
- Consumes: `Auth::user()->unreadNotifications`/`->notifications` (built into `Notifiable`, both notification classes from Tasks 2–3).
- Produces: the bell UI — reachable from here on, though not yet real-time (Task 5).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/NotificationBellTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Analytics\NotificationBell;
use App\Models\AnalyticsAlert;
use App\Models\User;
use App\Notifications\CriticalAlertTriggered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_count_reflects_real_unread_notifications(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $alert = AnalyticsAlert::factory()->create(['team_id' => $user->currentTeam->id]);
        $user->notify(new CriticalAlertTriggered($alert));
        $user->notify(new CriticalAlertTriggered($alert));

        $component = Livewire::actingAs($user)->test(NotificationBell::class);

        $this->assertSame(2, $component->get('unreadCount'));
    }

    public function test_clicking_a_notification_marks_it_as_read(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $alert = AnalyticsAlert::factory()->create(['team_id' => $user->currentTeam->id]);
        $user->notify(new CriticalAlertTriggered($alert));
        $notificationId = $user->fresh()->notifications->first()->id;

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('markAsRead', $notificationId);

        $this->assertNotNull($user->fresh()->notifications->first()->read_at);
    }

    public function test_mark_all_as_read_clears_the_unread_count(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $alert = AnalyticsAlert::factory()->create(['team_id' => $user->currentTeam->id]);
        $user->notify(new CriticalAlertTriggered($alert));
        $user->notify(new CriticalAlertTriggered($alert));

        $component = Livewire::actingAs($user)->test(NotificationBell::class)->call('markAllAsRead');

        $this->assertSame(0, $component->get('unreadCount'));
    }

    public function test_a_notification_for_a_different_user_never_appears(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();
        $alert = AnalyticsAlert::factory()->create(['team_id' => $otherUser->currentTeam->id]);
        $otherUser->notify(new CriticalAlertTriggered($alert));

        $component = Livewire::actingAs($user)->test(NotificationBell::class);

        $this->assertSame(0, $component->get('unreadCount'));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Livewire/NotificationBellTest.php`
Expected: FAIL — `App\Livewire\Analytics\NotificationBell` doesn't exist
yet.

- [ ] **Step 3: Write the component**

Create `app/Livewire/Analytics/NotificationBell.php`:

```php
<?php

namespace App\Livewire\Analytics;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Notification Bell
 *
 * Reads Auth::user()->notifications/unreadNotifications directly --
 * both built into Laravel's Notifiable trait (already on User), no new
 * query code. Real-time refresh is wired in a later task via the
 * 'notification-received' Livewire event, dispatched from a browser-side
 * Echo listener.
 */
class NotificationBell extends Component
{
    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    #[Computed]
    public function recentNotifications(): Collection
    {
        return Auth::user()->notifications()->latest()->limit(10)->get();
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = Auth::user()->notifications()->where('id', $notificationId)->first();
        $notification?->markAsRead();

        unset($this->unreadCount, $this->recentNotifications);
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();

        unset($this->unreadCount, $this->recentNotifications);
    }

    #[On('notification-received')]
    public function refresh(): void
    {
        unset($this->unreadCount, $this->recentNotifications);
    }

    public function render(): View
    {
        return view('livewire.analytics.notification-bell');
    }
}
```

- [ ] **Step 4: Write the view**

Create `resources/views/livewire/analytics/notification-bell.blade.php`:

```blade
<div class="ms-3 relative">
    <x-dropdown align="right" width="80">
        <x-slot name="trigger">
            <button type="button" class="press relative" style="width:36px;height:36px;border-radius:9999px;background:transparent;border:1px solid var(--line);display:flex;align-items:center;justify-content:center;">
                <span class="material-symbols-outlined" style="font-size:18px;color:var(--mist);">notifications</span>
                @if($this->unreadCount > 0)
                    <span class="font-mono" style="position:absolute;top:-4px;right:-4px;background:var(--gold);color:var(--ink);font-size:0.6rem;font-weight:700;border-radius:9999px;min-width:16px;height:16px;display:flex;align-items:center;justify-content:center;padding:0 3px;">{{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}</span>
                @endif
            </button>
        </x-slot>

        <x-slot name="content">
            <div style="width:20rem;max-height:24rem;overflow-y:auto;">
                <div class="flex items-center justify-between" style="padding:0.6rem 1rem;border-bottom:1px solid var(--line);">
                    <span class="font-mono" style="font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);">Notifications</span>
                    @if($this->unreadCount > 0)
                        <button wire:click="markAllAsRead" style="font-size:0.68rem;color:var(--teal-soft);background:none;border:none;cursor:pointer;">Mark all as read</button>
                    @endif
                </div>

                @if($this->recentNotifications->isEmpty())
                    <p style="font-size:0.8rem;color:var(--mist);text-align:center;padding:1.5rem 1rem;">No notifications yet.</p>
                @else
                    @foreach($this->recentNotifications as $notification)
                        <a
                            href="{{ $notification->data['url'] ?? route('dashboard') }}"
                            wire:click="markAsRead('{{ $notification->id }}')"
                            style="display:block;padding:0.6rem 1rem;border-bottom:1px solid var(--line);text-decoration:none;background:{{ $notification->read_at ? 'transparent' : 'rgba(241,198,46,0.05)' }};"
                        >
                            <p style="font-size:0.8rem;font-weight:600;color:var(--paper);margin:0;">{{ $notification->data['title'] ?? 'Notification' }}</p>
                            @if(!empty($notification->data['description']))
                                <p style="font-size:0.7rem;color:var(--mist);margin:0.2rem 0 0;">{{ \Illuminate\Support\Str::limit($notification->data['description'], 100) }}</p>
                            @endif
                            <p style="font-size:0.62rem;color:var(--mist);opacity:0.6;margin:0.25rem 0 0;">{{ $notification->created_at->diffForHumans() }}</p>
                        </a>
                    @endforeach
                @endif
            </div>
        </x-slot>
    </x-dropdown>
</div>
```

- [ ] **Step 5: Add it to the nav bar**

In `resources/views/navigation-menu.blade.php`, immediately before the
`<!-- Teams Dropdown -->` comment (first item inside the existing
`<div class="hidden sm:flex sm:items-center sm:ms-6">`):

```blade
                <!-- Notifications -->
                <livewire:analytics.notification-bell />

                <!-- Teams Dropdown -->
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Livewire/NotificationBellTest.php`
Expected: PASS, 4 tests, 0 failures.

- [ ] **Step 7: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 8: Commit**

```bash
git add app/Livewire/Analytics/NotificationBell.php \
  resources/views/livewire/analytics/notification-bell.blade.php \
  resources/views/navigation-menu.blade.php \
  tests/Feature/Livewire/NotificationBellTest.php
git commit -m "$(cat <<'EOF'
feat: notification bell in the nav bar

Persistent unread-count bell, reads Auth::user()->notifications/
unreadNotifications directly (built into Notifiable, zero new query
code). Reuses the existing <x-dropdown> component, same as the team
switcher and account menu. Not yet real-time -- unread count reflects
what's in the database as of page load; Task 5 wires the live push.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Real-time wiring

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

**Interfaces:**
- Consumes: `config('echo.*')` (Task 1), the `notification-received` Livewire event the bell already listens for (Task 4).
- Produces: live bell updates — no PHPUnit-testable surface (a browser-side WebSocket connection can't be exercised by PHPUnit); verified in Task 6's browser pass.

- [ ] **Step 1: Add the CDN scripts and Echo initialization**

In `resources/views/layouts/app.blade.php`, find the existing Sortable.js
comment/script area — **this branch does not have that addition** (it
only exists on the separate Custom Dashboards branch), so instead find the
`@livewireScripts` / closing `</head>` area directly. Add, immediately
before `</head>`:

```blade
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.5.0/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@2.4.0/dist/echo.iife.js"></script>
    <script>
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: '{{ config('echo.key') }}',
            wsHost: '{{ config('echo.host') }}',
            wsPort: {{ config('echo.port') }},
            forceTLS: {{ config('echo.scheme') === 'https' ? 'true' : 'false' }},
            enabledTransports: ['ws', 'wss'],
        });

        document.addEventListener('livewire:init', () => {
            Echo.private('App.Models.User.{{ Auth::id() }}')
                .notification(() => {
                    Livewire.dispatch('notification-received');
                });
        });
    </script>
```

The `document.addEventListener('livewire:init', ...)` wrapper ensures
`Livewire.dispatch` exists before it's called — Livewire's own JS bundle
loads via `@livewireScripts` at the bottom of `<body>`, so without this
guard the listener registration could run before `Livewire` is defined on
`window`, depending on script execution order.

- [ ] **Step 2: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed` (no PHP changed in this task, but run for consistency).

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "$(cat <<'EOF'
feat: real-time notification delivery via Echo

laravel-echo/pusher-js have been npm dependencies with zero
initialization this whole time (confirmed during spec research --
grep found no Echo.private(...) call and no #[On(...)] listener
anywhere in this codebase before this). Loaded via CDN, matching how
this layout already loads Tailwind/Alpine -- no build pipeline here.
Listens on Laravel's own automatic per-user private channel
(App.Models.User.{id}, zero new channel-auth code) and dispatches the
Livewire event the bell (previous commit) already listens for.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Full regression + browser verification

**Files:** none (verification only).

**Interfaces:**
- Consumes: everything from Tasks 1–5.
- Produces: nothing new — confirms the whole feature works together.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS, 0 failures. Baseline on this branch (`feature/ecosystem-sso`,
before this plan — confirm the actual number by running the suite once at
the very start of Task 1, since this branch's history differs from the two
open PRs' branches). This plan adds roughly 1 (CSP) + 4 (Task 2) + 4 (Task
3) + 4 (Task 4) = 13 new tests. 0 failures is what matters, not the exact
total.

- [ ] **Step 2: Run Pint across the whole plan's changes**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 3: Browser verification**

As an authenticated team member, on the `dot-analytics` dev server:
1. Confirm the bell renders in the nav bar, styled to match, zero unread.
2. Trigger a critical insight (or directly call
   `Notification::send(Auth::user(), new CriticalAlertTriggered($alert))`
   via `php artisan tinker` against a real seeded `AnalyticsAlert`, since
   this repo's own Laravel Boost guidelines prefer tests over tinker for
   verifying *behavior* — this is verifying *delivery/UI*, which tests
   can't observe visually) and confirm the bell's unread badge updates
   **live, without a page reload** — this is the real proof Echo is wired
   correctly end to end, not just that the backend logic works (already
   proven by Task 2–4's tests).
3. Open the dropdown, confirm the notification renders with title/snippet/
   timestamp, click it, confirm it's marked read and the badge decrements.
4. Check `storage/logs/laravel.log` (since `MAIL_MAILER=log` in this dev
   environment) for the rendered email — confirm it uses the branded mail
   theme (ink/teal colors, not generic Laravel blue) and the subject/body
   match what Task 2/3's tests already assert.
5. Check the browser console for errors, especially any CSP violation on
   the WebSocket connection — zero expected, confirming Task 1's fix
   actually closed the gap it was meant to close.

- [ ] **Step 4: Finish the branch**

Announce: "I'm using the finishing-a-development-branch skill to complete
this work." Follow that skill: verify tests (Step 1 already confirmed
green), detect environment, present the standard menu, execute the chosen
option.

---

## Self-Review Notes

- **Spec coverage:** §1 (two Notification classes) → Tasks 2–3. §2
  (trigger points, including the `allUsers()` correction) → Tasks 2–3.
  §3 (the bell) → Task 4. §4 (real-time, including the `reverbWsOrigin()`
  fix) → Tasks 1 and 5. §5 (testing) → present throughout. Non-Goals (no
  Slack, no preferences UI, no other event triggers, no real SMTP, no Vite
  migration) — no task touches any of those.
- **Placeholder scan:** none found. Every step has real, complete code.
  One explicitly-flagged uncertainty (Task 3 Step 1's `AiModelRouter`
  no-key assumption) has a concrete, actionable fallback written out
  in-line rather than being left as an open question.
- **Bug caught during this plan's own self-review**: the first draft of
  every `via()`/`toMail()`/`toArray()` test in Tasks 2–3 called those
  methods with a literal `null` argument, against methods typed
  `object $notifiable` (non-nullable) on the notification classes those
  same tasks define — a `TypeError` at the type boundary, not valid PHP.
  Fixed by passing a real `User::factory()->create()` instead, in every
  affected test.
- **Type consistency:** `toArray()`'s shape (`type`/`title`/`description`/
  `url` string keys) is identical across both notification classes and is
  exactly what Task 4's view reads (`$notification->data['title']` etc.) —
  no drift between producer and consumer. `Team::allUsers()` (not
  `users()`) is used consistently in both trigger points and in every test
  that constructs a personal-team owner + separately-attached member.
