# Test Coverage Guide — Reaching 95%

**Scorecard domain:** Secure SDLC (currently 68/100)
**Current coverage:** 77.5%
**Target coverage:** ≥95%
**Gap:** ~17.5 points (~60 targeted tests needed)

---

## Coverage Priority Map

Run this to see exact gaps:

```bash
XDEBUG_MODE=coverage php artisan test --coverage 2>&1 | grep -v "100\.0%" | grep -v Xdebug | sort -t'/' -k2 -n
```

### Classes with the most uncovered lines (highest ROI first)

| Class | Coverage | Tests Needed |
|---|---|---|
| `Services/AiModelRouter` | ~22% | Fallback chain, mock responses, cost tracking |
| `Services/PipelineExecutionService` | ~50% | All transform branches, load stage |
| `Services/AnomalyDetectionService` | ~61% | `detectForTeam()`, alert creation |
| `Services/KnowledgeGraphService` | ~70% | `findPath()`, `explainEntity()` edge cases |
| `Services/ReportGenerationService` | ~77% | All 4 report types, edge cases |
| `Livewire/IntelligenceDashboard` | ~50% | `connectedSources`, `openAlerts` computed |
| `Livewire/ExecutiveBriefingPanel` | ~50% | `generate()`, `briefing` computed |

---

## Test Writing Rules

Every test must validate **business behaviour**, not just line coverage.

### ✅ Good test
```php
public function test_anomaly_detection_creates_alert_for_critical_spike(): void
{
    $team = User::factory()->withPersonalTeam()->create()->currentTeam;
    // Create 30 days of metric history
    // Add a spike at 5 standard deviations
    // Assert: AnalyticsAlert was created with severity 'critical'
    // Assert: Alert narrative mentions the metric name
    // Assert: Alert context includes the z-score
}
```

### ❌ Bad test (coverage-padding)
```php
public function test_method_exists(): void
{
    $service = new AnomalyDetectionService();
    $this->assertTrue(method_exists($service, 'detectForTeam'));
}
```

---

## AiModelRouter Tests to Write

The router is at 22% — these are the business-critical paths:

```php
// tests/Unit/Services/AiModelRouterExtendedTest.php

public function test_falls_back_to_next_provider_when_first_fails(): void
{
    // Mock HTTP: Anthropic returns 500, OpenAI returns 200
    // Assert: response comes from OpenAI
    // Assert: fallback_used = true in ai_model_usage
}

public function test_tier_1_capability_uses_premium_model(): void
{
    // 'insight' capability requires tier 1
    // Assert: model selected is claude-sonnet-4-6 or gpt-4o (not haiku/mini)
}

public function test_tracks_usage_with_correct_cost(): void
{
    // Mock a known response with token counts
    // Assert: ai_model_usage row has correct cost_usd
}

public function test_complete_returns_mock_for_briefing_without_key(): void
{
    // No API keys configured
    // Assert: returns valid JSON with summary/highlights/risks/recommendations keys
}

public function test_complete_never_throws_regardless_of_ai_errors(): void
{
    // Mock all providers to fail
    // Assert: returns mock response, no exception thrown
}
```

---

## PipelineExecutionService Tests to Write

```php
// tests/Unit/Services/PipelineExecutionServiceExtendedTest.php

public function test_execute_creates_pipeline_run_record(): void

public function test_execute_marks_run_failed_on_connector_exception(): void

public function test_execute_stores_lineage_in_run_record(): void

public function test_transform_applies_multiple_operations_in_sequence(): void
// field_map → type_cast → drop_fields → computed_fields → filter

public function test_load_stage_creates_analytics_snapshot(): void

public function test_incremental_load_uses_watermark_from_last_run(): void

public function test_full_load_ignores_watermark(): void
```

---

## AnomalyDetectionService Tests to Write

```php
public function test_detect_for_team_creates_alert_when_spike_found(): void
// Requires: ComputedMetric factory, 30+ data points, one outlier
// Assert: AnalyticsAlert created with correct severity

public function test_detect_for_team_returns_zero_when_insufficient_history(): void

public function test_detect_for_team_is_team_scoped(): void
// Team A has a spike — Team B should get 0 alerts
```

---

## Livewire Tests to Write

```php
// tests/Feature/Livewire/IntelligenceDashboardExtendedTest.php

public function test_connected_sources_computed_returns_only_connected(): void
public function test_open_alerts_computed_returns_only_open(): void
public function test_pending_recommendations_computed_sorted_by_priority(): void
```

---

## CI Coverage Gate

Update `.github/workflows/ci.yml` test job:

```yaml
- name: Run test suite with coverage
  run: php artisan test --coverage --min=85  # Increase to 90, then 95
  env:
    DB_CONNECTION: sqlite
    DB_DATABASE: ":memory:"
```

Start at 85% to pass CI, increase by 2-3 points per sprint until 95%.

---

## Test File Structure Convention

```
tests/
  Unit/
    Services/         # Pure unit tests — no DB, no HTTP
    Actions/          # Action unit tests with mocked dependencies
    Data/             # DTO tests
    Models/           # Model relationship + cast tests
    Security/         # SSRF, injection, auth tests
    Middleware/        # Middleware unit tests
  Feature/
    Api/              # All API endpoint tests (auth, validation, response shape)
    Livewire/         # All Livewire component tests
    Authorization/    # Gate + policy tests with cross-tenant isolation
```

---

## Checklist

- [ ] All classes above 70% coverage raised to 95%+
- [ ] `AiModelRouter` fallback chain fully tested
- [ ] `PipelineExecutionService` full ETL path tested
- [ ] `AnomalyDetectionService.detectForTeam()` tested
- [ ] All Livewire `#[Computed]` properties tested
- [ ] CI minimum coverage raised to 85%, target 95%
- [ ] No tests using `$this->assertTrue(true)` as assertion
- [ ] Scorecard updated
