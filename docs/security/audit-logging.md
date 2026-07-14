# Audit Logging — Hardening Guide

**Scorecard domain:** Logging & Audit (currently 70/100)
**Target:** 90/100

---

## Current State

- `AuditLog` model is immutable (throws on update/delete) ✅
- `DataSourceObserver` writes platform events ✅
- `StructuredLogger` provides structured context ✅

**Missing:** Login events, audit log export, tamper-proof hash chain, retention.

---

## Fix 1 — Cryptographic Hash Chain (Tamper Detection)

Migration:

```php
Schema::table('audit_logs', function (Blueprint $table) {
    $table->string('chain_hash', 64)->nullable()->after('occurred_at');
    $table->unsignedBigInteger('chain_sequence')->default(0)->after('chain_hash');
});
```

Update `AuditLog::create()` via an observer or static method override:

```php
public static function record(array $attributes): static
{
    $last = static::where('team_id', $attributes['team_id'] ?? null)
        ->orderByDesc('chain_sequence')
        ->first();

    $sequence    = ($last?->chain_sequence ?? 0) + 1;
    $prevHash    = $last?->chain_hash ?? str_repeat('0', 64);

    $payload     = json_encode(array_merge($attributes, ['sequence' => $sequence]));
    $chainHash   = hash('sha256', $prevHash . $payload);

    return static::create(array_merge($attributes, [
        'chain_hash'      => $chainHash,
        'chain_sequence'  => $sequence,
    ]));
}
```

Verification endpoint — add to `HealthController` or a dedicated controller:

```php
public function verifyAuditChain(int $teamId): JsonResponse
{
    $logs    = AuditLog::where('team_id', $teamId)->orderBy('chain_sequence')->get();
    $broken  = [];
    $prevHash = str_repeat('0', 64);

    foreach ($logs as $log) {
        $payload  = json_encode($log->only(['team_id', 'event', 'new_values', 'occurred_at'])
            + ['sequence' => $log->chain_sequence]);
        $expected = hash('sha256', $prevHash . $payload);

        if ($expected !== $log->chain_hash) {
            $broken[] = ['id' => $log->id, 'sequence' => $log->chain_sequence];
        }

        $prevHash = $log->chain_hash;
    }

    return response()->json([
        'verified'       => empty($broken),
        'total_entries'  => $logs->count(),
        'broken_entries' => $broken,
    ]);
}
```

---

## Fix 2 — Audit Log Export Endpoint

Add to `routes/api.php`:

```php
Route::prefix('audit-logs')->middleware('throttle:analytics-api')->group(function () {
    Route::get('/',        [AuditLogController::class, 'index']);
    Route::get('/export',  [AuditLogController::class, 'export']); // CSV
    Route::get('/verify',  [AuditLogController::class, 'verify']); // Chain integrity
});
```

Create `app/Http/Controllers/Api/V1/AuditLogController.php`:

```php
public function index(Request $request): JsonResponse
{
    Gate::authorize('view-audit-logs');

    $logs = AuditLog::where('team_id', Auth::user()->currentTeam->id)
        ->when($request->input('event'), fn ($q) => $q->where('event', 'like', $request->input('event') . '%'))
        ->when($request->input('from'),  fn ($q) => $q->where('occurred_at', '>=', $request->input('from')))
        ->when($request->input('to'),    fn ($q) => $q->where('occurred_at', '<=', $request->input('to')))
        ->orderByDesc('occurred_at')
        ->paginate(100);

    return $this->paginated($logs);
}

public function export(Request $request): StreamedResponse
{
    Gate::authorize('view-audit-logs');

    $team = Auth::user()->currentTeam;

    return response()->streamDownload(function () use ($team, $request) {
        $handle = fopen('php://output', 'w');
        fputcsv($handle, ['Sequence', 'Event', 'Actor', 'IP', 'Old Values', 'New Values', 'Occurred At']);

        AuditLog::where('team_id', $team->id)
            ->orderBy('chain_sequence')
            ->each(function ($log) use ($handle) {
                fputcsv($handle, [
                    $log->chain_sequence,
                    $log->event,
                    "{$log->actor_type}:{$log->actor_id}",
                    $log->ip_address,
                    json_encode($log->old_values),
                    json_encode($log->new_values),
                    $log->occurred_at->toIso8601String(),
                ]);
            });

        fclose($handle);
    }, "audit-log-{$team->id}-" . now()->format('Ymd') . '.csv');
}
```

---

## Fix 3 — Log Retention Command

Create `app/Console/Commands/PruneAuditLogsCommand.php`:

```php
protected $signature = 'analytics:prune-logs {--days=365 : Retention period in days}';

public function handle(): int
{
    $days    = (int) $this->option('days');
    $cutoff  = now()->subDays($days);

    // Soft-delete: archive to cold storage first in production
    $deleted = \App\Models\AuditLog::where('occurred_at', '<', $cutoff)->count();

    $this->info("Would prune {$deleted} audit log entries older than {$days} days.");
    $this->info("Run with --execute to actually delete (add flag after reviewing).");

    return self::SUCCESS;
}
```

Schedule in `routes/console.php`:

```php
Schedule::command('analytics:prune-logs', ['--days=365'])->monthly();
```

---

## Fix 4 — What Every Log Entry Should Contain

Every `AuditLog::record()` call must include:

| Field | Required | Example |
|---|---|---|
| `team_id` | Yes | `1` |
| `user_id` | Yes (null for system) | `42` |
| `actor_type` | Yes | `user`, `system`, `agent`, `api` |
| `event` | Yes | `platform.connected` |
| `auditable_type` | Recommended | `App\Models\DataSource` |
| `auditable_id` | Recommended | `7` |
| `old_values` | When updating | `{"status":"pending"}` |
| `new_values` | When creating/updating | `{"status":"connected"}` |
| `ip_address` | Yes | `196.25.1.100` |
| `occurred_at` | Auto | `2026-07-14T08:00:00Z` |

---

## Checklist

- [ ] `chain_hash` and `chain_sequence` columns added to `audit_logs`
- [ ] Hash chain computed on every `AuditLog::record()` call
- [ ] `GET /api/v1/audit-logs/verify` endpoint added
- [ ] `GET /api/v1/audit-logs/export` CSV endpoint added
- [ ] `analytics:prune-logs` command created and scheduled
- [ ] Login/MFA events captured via Fortify listeners
- [ ] Tests written for chain verification
- [ ] Scorecard updated
