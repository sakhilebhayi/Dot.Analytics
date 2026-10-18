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
}
