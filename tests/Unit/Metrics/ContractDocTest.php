<?php

namespace Tests\Unit\Metrics;

use Tests\TestCase;

/**
 * docs/metrics-contract.md is what a publisher team reads. It must list every
 * platform and every key the endpoint will actually accept, or it is lying.
 */
class ContractDocTest extends TestCase
{
    public function test_the_contract_page_lists_every_platform_and_metric_key_in_the_catalogue(): void
    {
        $doc = file_get_contents(base_path('docs/metrics-contract.md'));

        foreach (config('metrics.platforms') as $platform => $definition) {
            $this->assertStringContainsString("#### `{$platform}`", $doc, "docs do not list platform {$platform}");

            foreach ($definition['metrics'] as $key => $metric) {
                $this->assertStringContainsString("| `{$key}` | {$metric['label']} |", $doc, "docs do not list {$platform}.{$key}");
            }
        }
    }

    public function test_the_contract_page_documents_the_stale_window_and_period_in_use(): void
    {
        $doc = file_get_contents(base_path('docs/metrics-contract.md'));

        $this->assertSame(['day'], config('metrics.periods'));
        $this->assertSame(2, config('metrics.stale_after_periods'));
        $this->assertStringContainsString('older than two daily periods', $doc);
    }

    public function test_the_contract_page_states_the_anomaly_rule_that_is_configured(): void
    {
        $doc = file_get_contents(base_path('docs/metrics-contract.md'));

        $this->assertSame(3.5, config('metrics.anomaly.mad_threshold'));
        $this->assertSame(7, config('metrics.anomaly.min_prior_points'));
        $this->assertSame(30, config('metrics.anomaly.window_days'));
        $this->assertStringContainsString('flag when deviation > 3.5', $doc);
        $this->assertStringContainsString('at least 7 prior daily points', $doc);
        $this->assertStringContainsString('previous 30 days', $doc);
    }
}
