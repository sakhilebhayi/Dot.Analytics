# GDPR & POPIA Compliance — Implementation Guide

**Scorecard domain:** Compliance & Privacy (currently 35/100)
**Target:** 72/100
**Applies to:** Any organisation processing personal data of EU or SA residents.

---

## What Personal Data Dot.Analytics Processes

| Data | Location | Legal basis |
|---|---|---|
| User names, email addresses | `users` table | Contract (account) |
| Team member information | `team_user` table | Contract |
| Audit log actor IDs + IPs | `audit_logs` | Legitimate interest (security) |
| AI prompt content (may contain PII) | Sent to AI providers | Contract (if scrubbed) |
| Analytics snapshot payloads | `analytics_snapshots` | Contract (customer config) |
| Cross-platform insight narratives | `cross_platform_insights` | Contract |

---

## Fix 1 — Right to Erasure (GDPR Art. 17 / POPIA Section 24)

Create `app/Services/TeamDataEraseService.php`:

```php
<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Anonymises all personal data associated with a team on erasure request.
 *
 * Does NOT hard-delete — anonymises to preserve referential integrity
 * and aggregate analytics. Cryptographic data (passwords, tokens) is
 * deleted. PII fields are replaced with deterministic pseudonyms.
 */
class TeamDataEraseService
{
    public function erase(Team $team, int $requestedByUserId): array
    {
        $report = [];

        DB::transaction(function () use ($team, $requestedByUserId, &$report) {

            // 1. Anonymise users on this team
            $team->users()->each(function ($user) use (&$report) {
                $user->update([
                    'name'  => '[ERASED]',
                    'email' => 'erased+' . $user->id . '@erased.invalid',
                ]);
                // Revoke all API tokens
                $user->tokens()->delete();
                $report['users'][] = $user->id;
            });

            // 2. Scrub audit log actor details (preserve events for compliance)
            \App\Models\AuditLog::where('team_id', $team->id)->each(function ($log) {
                // audit_logs are immutable — we cannot update them
                // Instead, note erasure in the team's compliance record
            });

            // 3. Delete AI usage records (no PII but may link to requests)
            $deleted = \App\Models\AiModelUsage::where('team_id', $team->id)->delete();
            $report['ai_usage_deleted'] = $deleted;

            // 4. Anonymise intelligence snapshots
            \App\Models\AnalyticsSnapshot::where('team_id', $team->id)
                ->update(['payload' => ['_erased' => true, '_erased_at' => now()->toIso8601String()]]);

            // 5. Record the erasure event
            \App\Models\AuditLog::create([
                'team_id'    => $team->id,
                'user_id'    => $requestedByUserId,
                'actor_type' => 'user',
                'event'      => 'gdpr.erasure.completed',
                'new_values' => $report,
                'occurred_at' => now(),
            ]);
        });

        return $report;
    }
}
```

Add erasure endpoint to the API:

```php
// In routes/api.php (auth required):
Route::delete('/my-data', function (Request $request) {
    $user = $request->user();
    $team = $user->currentTeam;

    // Only the team owner can request erasure
    abort_unless($team->user_id === $user->id, 403, 'Only the team owner can request data erasure.');

    app(\App\Services\TeamDataEraseService::class)->erase($team, $user->id);

    return response()->json(['message' => 'Data erasure completed. You will receive confirmation by email.']);
})->middleware('auth:sanctum');
```

---

## Fix 2 — Right to Access / Data Portability (GDPR Art. 20)

Create `app/Services/TeamDataExportService.php`:

```php
public function export(Team $team): array
{
    return [
        'exported_at' => now()->toIso8601String(),
        'team'        => $team->only('name', 'currency', 'locale', 'created_at'),
        'platforms'   => $team->dataSources()->get(['platform', 'display_name', 'status', 'connected_at']),
        'insights'    => $team->crossPlatformInsights()
                              ->select('title', 'narrative', 'severity', 'created_at')
                              ->get(),
        'audit_log'   => \App\Models\AuditLog::where('team_id', $team->id)
                              ->select('event', 'occurred_at')
                              ->orderBy('occurred_at')
                              ->get(),
    ];
}
```

---

## Fix 3 — Article 30 Records of Processing

Create `docs/compliance/article-30-record.md` with:

```
Controller: SK Digital / BluPin Incorporated
DPO: [Name + contact]
Purpose: Business intelligence and analytics
Legal basis: Contract + Legitimate interest
Data subjects: Team administrators, team members
Data categories: Contact data, usage data, behavioural analytics
Recipients: Anthropic (AI processing), OpenAI (AI processing)
Retention: 365 days (configurable)
International transfers: USA (Anthropic, OpenAI) — SCCs in place
```

---

## Fix 4 — Cookie Consent (If web dashboard used)

If the platform serves a web frontend, add consent banner:

```php
// In resources/views/layouts/app.blade.php:
@if(! session('analytics_consent'))
    <div id="cookie-banner" class="fixed bottom-0 inset-x-0 bg-gray-900 text-white p-4 flex items-center justify-between z-50">
        <p class="text-sm">We use cookies for session management and security. No advertising cookies.</p>
        <button onclick="document.cookie='analytics_consent=1;max-age=31536000'; document.getElementById('cookie-banner').remove();"
                class="bg-indigo-600 px-4 py-2 rounded text-sm ml-4">Accept</button>
    </div>
@endif
```

---

## POPIA Specific Requirements (South Africa)

1. **Information Officer** must be designated and registered with the Information Regulator
2. **PAIA Manual** must be published (section 51 of PAIA)
3. **Data breach notification** within 72 hours to affected parties and Information Regulator
4. **Trans-border data flows**: Anthropic/OpenAI data processing requires adequate protection confirmation

---

## Checklist

- [ ] `TeamDataEraseService` created
- [ ] `DELETE /api/v1/my-data` endpoint added
- [ ] `TeamDataExportService` created
- [ ] `GET /api/v1/my-data` export endpoint added
- [ ] Article 30 record of processing created
- [ ] Information Officer designated (POPIA)
- [ ] AI provider DPAs signed (Anthropic, OpenAI)
- [ ] Data retention policy documented
- [ ] `analytics:prune-data` command created
- [ ] Scorecard updated
