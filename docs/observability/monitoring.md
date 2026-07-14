# Monitoring & Observability — Setup Guide

**Scorecard domain:** Monitoring & Detection (currently 58/100)
**Target:** 80/100

---

## Fix 1 — OpenTelemetry Distributed Tracing

```bash
composer require open-telemetry/opentelemetry-php open-telemetry/sdk
```

Create `app/Providers/OpenTelemetryServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Globals;
use OpenTelemetry\SDK\Trace\TracerProviderFactory;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('services.otel.enabled', false)) {
            return;
        }

        $tracerProvider = (new TracerProviderFactory())->create();
        Globals::registerInitializer(fn ($propagator) => $tracerProvider);

        $this->app->singleton('tracer', fn () =>
            $tracerProvider->getTracer('dot-analytics', '1.0.0')
        );
    }
}
```

Add to `config/services.php`:

```php
'otel' => [
    'enabled'  => env('OTEL_ENABLED', false),
    'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:4318'),
],
```

Instrument `AiModelRouter::complete()`:

```php
$tracer = app()->bound('tracer') ? app('tracer') : null;
$span   = $tracer?->spanBuilder("ai.complete.{$capability}")->startSpan();

try {
    $result = $this->callProvider(...);
    $span?->setAttribute('ai.provider', $provider);
    $span?->setAttribute('ai.model', $model);
    $span?->setAttribute('ai.cost_usd', $cost);
    return $result;
} finally {
    $span?->end();
}
```

---

## Fix 2 — Laravel Horizon (Queue Monitoring)

Once `ext-pcntl` is available (Linux production server):

```bash
composer require laravel/horizon
php artisan horizon:install
```

Add Horizon dashboard route to `routes/web.php`:

```php
use Laravel\Horizon\Horizon;

Horizon::auth(function (Request $request) {
    return $request->user()?->currentTeam?->user_id === $request->user()?->id;
});
```

Add to `routes/web.php`:
```php
Route::get('/horizon', function () {
    return view('horizon::index');
})->middleware(['auth', 'verified']);
```

Queue monitoring metrics available:
- Job throughput (jobs/minute)
- Failed job rate
- Queue depth
- Worker count
- Memory usage

---

## Fix 3 — Structured Log Aggregation

Configure Laravel to output JSON logs in production:

`config/logging.php`:

```php
'channels' => [
    'production' => [
        'driver'    => 'monolog',
        'handler'   => \Monolog\Handler\StreamHandler::class,
        'with'      => ['stream' => 'php://stdout'],
        'formatter' => \Monolog\Formatter\JsonFormatter::class,
        'level'     => 'info',
    ],
],
```

Set in `.env` for production:

```env
LOG_CHANNEL=production
LOG_LEVEL=info
```

This makes every `StructuredLogger` call output parseable JSON to stdout, which
is collected by Docker logging drivers and forwarded to your SIEM.

---

## Fix 4 — Health Check Enhancements

Update `HealthController::detailed()` to include:

```php
// Add to existing checks:
'queue_health' => $this->checkQueueHealth(),
'ai_providers' => $this->checkAiProviders(),

private function checkQueueHealth(): array
{
    $failedCount = \DB::table('failed_jobs')->count();
    $pendingCount = \DB::table('jobs')->count();

    return [
        'healthy'       => $failedCount === 0,
        'pending_jobs'  => $pendingCount,
        'failed_jobs'   => $failedCount,
        'connection'    => config('queue.default'),
    ];
}
```

---

## Fix 5 — SLA Dashboard Metrics

Expose key SLA metrics via the metrics API:

```
GET /api/v1/metrics/sla
```

Suggested SLA targets:

| Metric | Target | Alert threshold |
|---|---|---|
| API p99 latency | < 500ms | > 1000ms |
| Intelligence engine run time | < 30s | > 120s |
| AI response time | < 5s | > 15s |
| Health check response | < 200ms | > 500ms |
| Queue processing lag | < 60s | > 300s |
| Failed job rate | < 0.1% | > 1% |

---

## Fix 6 — Alerting on Anomalies

The `AnomalyDetectionService` already detects statistical anomalies.
Connect it to notifications:

```php
// In AnomalyDetectionService::createAlert():
// After creating the AnalyticsAlert, send notification:
if ($anomaly['severity'] === 'critical') {
    \Notification::send(
        $team->owner,
        new \App\Notifications\CriticalAnomalyDetected($team, $label, $anomaly)
    );
}
```

Create `app/Notifications/CriticalAnomalyDetected.php`:

```php
public function via(object $notifiable): array
{
    return ['mail', 'database'];
}

public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject("⚠️ Critical anomaly detected: {$this->label}")
        ->line("An unusual pattern was detected in your intelligence data.")
        ->line("Metric: {$this->label}")
        ->line("Detection method: {$this->anomaly['method']}")
        ->action('View in Dashboard', url('/dashboard'));
}
```

---

## Checklist

- [ ] OpenTelemetry SDK installed and configured
- [ ] Critical code paths instrumented (AiModelRouter, PipelineExecution)
- [ ] Laravel Horizon installed (when ext-pcntl available)
- [ ] JSON log formatter configured for production
- [ ] Queue health added to health check endpoint
- [ ] SLA targets defined and monitored
- [ ] Critical anomaly email notification implemented
- [ ] Incident response runbook created (see `docs/observability/incident-response.md`)
- [ ] Scorecard updated
