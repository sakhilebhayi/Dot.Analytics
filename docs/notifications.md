# Notifications: who is told, what they are told, and what has to be running

Dot.Analytics tells a team's members when something real and important happens. It never
tells them about nothing.

## What notifies

| Event | Notification | Only when |
|---|---|---|
| A **critical cross-platform insight** is discovered | `CriticalAlertTriggered` (the alert is created first) | the insight's provenance names the ingested snapshots it was derived from |
| A **metric anomaly** reaches *critical* (at least twice the MAD threshold) | `CriticalAlertTriggered` | it is not built from demo data; once per metric per day (the alert's dedupe key) |
| An **executive briefing** becomes `ready` | `ExecutiveBriefingReady` | it was written from real insights or computed metrics; once per briefing |

Everything the engines record when there is nothing truthful to say creates nothing and
tells nobody: `no_data`, `skipped` and `failed` engine runs, `unavailable` briefings,
warning and info alerts, demo-data anomalies, insights without provenance.

## Who is told

Every member of **that team**, and nobody else: the owner, admins, editors and **viewers**.
A viewer is read-only, so they trigger no AI work, but they are exactly who an alert should
reach, and reading or clearing one's own notifications changes nothing the team relies on.

The bell shows the signed-in user's own notifications for the team they are **currently
working in** (a user on several companies' teams sees one at a time; a removed member never
sees that team's alerts again).

## What a notification contains

A title, a description cut to 300 characters, a link to the dashboard and the team id. That
is all: no alert `context`, no provenance, no snapshot payloads, no webhook or API secrets,
in the mail, the stored row or the real-time push.

## What has to be running

| Component | Needed for | If it is missing |
|---|---|---|
| **A queue worker** (`php artisan queue:work`, `QUEUE_CONNECTION=database` or `redis`; the Docker image runs two under supervisor) | Every notification channel is a queued job | Nothing is sent until a worker runs. Alerts, insights and briefings are still created and shown on the dashboard. With `QUEUE_CONNECTION=sync` notifications are sent inline, still without ever failing the work that produced them |
| **A mail transport** (`MAIL_MAILER`, `MAIL_*`) | The email copy | No mailer configured, or a user with no address: the mail channel is simply left out and the in-app copy is still written. A transport that is down fails only that mail job (retried 3 times with backoff); the bell is unaffected |
| **Reverb** (`php artisan reverb:start`) | The bell updating *instantly* | The bell updates itself by **polling every 60 seconds** instead. No error reaches a request or a queue worker: the push is attempted inline, a failure is logged as a warning (exception class only, because a broadcaster's message embeds the request URL and app key) and swallowed |

### Reverb and the shared cPanel host

Reverb is a long-running WebSocket server. A shared cPanel host cannot run one, so there:

* leave `REVERB_APP_KEY` blank (or set `REALTIME_ENABLED=false`); the page then loads **no** Echo or
  pusher-js script, the Content-Security-Policy names **no** CDN and **no** WebSocket origin, and
  nothing tries to connect;
* the bell still works: new notifications appear within 60 seconds, or on any page load;
* the same applies to the dashboard's "engine finished" push (`IntelligenceEngineCompleted`), which is
  now also best-effort.

Anywhere Reverb can run (the Docker/compose setup does), set `BROADCAST_CONNECTION=reverb`, the
`REVERB_*` values and `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` to what the **browser** connects to.
Real-time is then on automatically (`config/echo.php`). The browser loads `laravel-echo` and
`pusher-js` from cdn.jsdelivr.net at pinned versions (there is no Vite bundle on the authenticated
layout); that origin is allowed by the CSP only while real-time is on. Only the public app key reaches
the page, never `REVERB_APP_SECRET`.

### Cron

`schedule:run` is unchanged; nothing in the notification path needs its own schedule.
