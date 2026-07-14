# AI Security & Governance — Implementation Guide

**Scorecard domain:** AI Security & Governance (currently 62/100)
**Target:** 88/100

---

## Fix 1 — PII Scrubber (Highest Risk)

PII in prompts sent to third-party AI providers (Anthropic, OpenAI, etc.)
is a GDPR and data processing agreement violation.

Create `app/Services/PiiScrubber.php`:

```php
<?php

namespace App\Services;

/**
 * Scrubs personally identifiable information from strings before
 * they are sent to external AI model providers.
 *
 * Replaces PII with deterministic tokens so the AI can still reason
 * about relationships without seeing real personal data.
 */
class PiiScrubber
{
    private array $replacements = [];

    /** Patterns that match common PII — extend as needed */
    private const PATTERNS = [
        'email'   => '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
        'phone'   => '/(\+27|0)[6-8][0-9]{8}|(\+1)?[2-9]\d{2}[2-9]\d{6}/',
        'id_number' => '/\b[0-9]{13}\b/',          // SA ID number
        'name'    => null,                          // Handled via entity registry
    ];

    /**
     * Scrub a prompt string, replacing PII with tokens.
     * Returns the scrubbed string. Call restore() to reverse.
     */
    public function scrub(string $text): string
    {
        foreach (self::PATTERNS as $type => $pattern) {
            if (! $pattern) {
                continue;
            }

            $text = preg_replace_callback($pattern, function (array $matches) use ($type) {
                $token = strtoupper($type) . '_' . (count($this->replacements) + 1);
                $this->replacements[$token] = $matches[0];
                return "[{$token}]";
            }, $text);
        }

        return $text;
    }

    /**
     * Restore tokens in an AI response back to original values.
     */
    public function restore(string $text): string
    {
        foreach ($this->replacements as $token => $original) {
            $text = str_replace("[{$token}]", $original, $text);
        }
        return $text;
    }

    public function getReplacementCount(): int
    {
        return count($this->replacements);
    }
}
```

Integrate in `AiModelRouter::complete()`:

```php
public function complete(string $prompt, string $capability = 'query', ...): string
{
    $scrubber = new PiiScrubber();
    $safePrompt = $scrubber->scrub($prompt);

    if ($scrubber->getReplacementCount() > 0) {
        Log::info('ai.pii_scrubbed', ['tokens_replaced' => $scrubber->getReplacementCount()]);
    }

    $response = $this->callProvider($safePrompt, ...);

    return $scrubber->restore($response);
}
```

---

## Fix 2 — Prompt Injection Detection

Add to `AiModelRouter` before sending any prompt:

```php
private const INJECTION_PATTERNS = [
    '/\bIGNORE\s+(ALL\s+)?PREVIOUS\s+INSTRUCTIONS?\b/i',
    '/\bSYSTEM\s*:\s*/i',
    '/\bjailbreak\b/i',
    '/\bDAN\s+mode\b/i',
    '/\bact\s+as\s+(?:an?\s+)?(?:evil|unrestricted|unfiltered)\b/i',
    '/\bforget\s+(?:your\s+)?(?:training|instructions|rules)\b/i',
];

private function detectPromptInjection(string $prompt): bool
{
    foreach (self::INJECTION_PATTERNS as $pattern) {
        if (preg_match($pattern, $prompt)) {
            return true;
        }
    }
    return false;
}
```

Usage:

```php
if ($this->detectPromptInjection($prompt)) {
    Log::warning('security.prompt_injection_detected', [
        'capability' => $capability,
        'team_id'    => $teamId,
        'prompt_hash' => hash('sha256', $prompt),
    ]);
    return $this->mockResponse($capability); // Return safe fallback
}
```

---

## Fix 3 — AI Cost Budget Cap

Migration to add daily budget to teams:

```php
Schema::table('teams', function (Blueprint $table) {
    $table->decimal('daily_ai_budget_usd', 8, 4)->default(10.00)->after('currency');
});
```

Guard in `AiModelRouter::complete()`:

```php
private function checkBudget(?int $teamId): bool
{
    if (! $teamId) {
        return true; // No team = no limit
    }

    $team = \App\Models\Team::find($teamId);
    if (! $team || ! $team->daily_ai_budget_usd) {
        return true;
    }

    $todayCost = \App\Models\AiModelUsage::where('team_id', $teamId)
        ->whereDate('created_at', today())
        ->sum('cost_usd');

    if ($todayCost >= $team->daily_ai_budget_usd) {
        Log::warning('ai.budget_exceeded', [
            'team_id'    => $teamId,
            'today_cost' => $todayCost,
            'budget'     => $team->daily_ai_budget_usd,
        ]);
        return false; // Block the call
    }

    return true;
}
```

---

## Fix 4 — Human Approval for High-Impact Recommendations

Add `requires_approval` to recommendations migration:

```php
$table->boolean('requires_approval')->default(false)->after('status');
$table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('requires_approval');
$table->timestamp('approved_at')->nullable()->after('approved_by');
```

Set automatically for critical/decision-engine recommendations:

```php
// In RecommendationsPanel or RunIntelligenceEngineJob:
Recommendation::create([
    // ...
    'requires_approval' => in_array($rec['priority'], ['critical']) 
                        || $rec['engine'] === 'decision',
]);
```

Approval in Livewire:

```php
public function approve(int $id): void
{
    Gate::authorize('manage-platforms');

    Recommendation::where('team_id', Auth::user()->currentTeam->id)
        ->findOrFail($id)
        ->update([
            'requires_approval' => false,
            'approved_by'       => Auth::id(),
            'approved_at'       => now(),
        ]);
}
```

---

## Fix 5 — AI Output Schema Validation

Wrap all `json_decode` calls from AI responses with validation:

```php
private function parseInsightResponse(string $raw): array
{
    preg_match('/\[.*\]/s', $raw, $matches);
    $parsed = json_decode($matches[0] ?? '[]', true) ?? [];

    return array_filter($parsed, function (array $item): bool {
        return isset($item['title'], $item['narrative'], $item['confidence'])
            && is_string($item['title'])
            && is_string($item['narrative'])
            && is_float($item['confidence'])
            && $item['confidence'] >= 0.0
            && $item['confidence'] <= 1.0
            && in_array($item['severity'] ?? '', ['info', 'warning', 'critical']);
    });
}
```

---

## Checklist

- [ ] `PiiScrubber` service created
- [ ] PII scrubbing wired into `AiModelRouter::complete()`
- [ ] Prompt injection detection added
- [ ] Daily AI budget column added to teams
- [ ] Budget cap enforced in `AiModelRouter`
- [ ] `requires_approval` added to recommendations
- [ ] Approval gate added to `RecommendationsPanel`
- [ ] AI output schema validation added to all JSON parse calls
- [ ] Tests written for each
- [ ] Scorecard updated
