<?php

namespace Tests\Unit\Services;

use App\Services\IntelligenceEngineService;
use Tests\TestCase;

/**
 * Reconciliation (wiki.md §7 roadmap item): IntelligenceEngineService::PLATFORMS
 * predates the current ecosystem and uses invented keys. Each entry now
 * carries a real_platform_id -- the matching Dot.Brain-registered platform,
 * or null where no real platform serves that domain yet. Additive only
 * (see PLATFORMS' own doc comment for why the keys themselves aren't
 * renamed): every existing catalog key/behavior stays intact.
 */
class RealPlatformIdMappingTest extends TestCase
{
    private IntelligenceEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IntelligenceEngineService;
    }

    public function test_every_platform_entry_declares_a_real_platform_id_key(): void
    {
        foreach ($this->service->getPlatformCatalog() as $key => $platform) {
            $this->assertArrayHasKey('real_platform_id', $platform, "Platform {$key} missing 'real_platform_id'");
        }
    }

    public function test_platforms_with_a_confident_real_match_are_mapped(): void
    {
        $expected = [
            'dot.fleet' => 'dot-mines',
            'dot.hr' => 'dot-hr',
            'dot.documents' => 'dot-engage',
            'dot.hear' => 'dot-pulse',
            'dot.inventory' => 'dot-emall',
            'dot.payments' => 'dot-billing',
            'dot.api' => 'dot-plug',
            'dot.flow' => 'dot-tasks',
            'dot.assets' => 'dot-farms',
            'dot.agents' => 'dot-agents',
            'dot.finance' => 'dot-finance',
        ];

        foreach ($expected as $key => $realId) {
            $this->assertSame($realId, $this->service->getRealPlatformId($key), "Expected {$key} -> {$realId}");
        }
    }

    public function test_platforms_with_no_real_analog_map_to_null(): void
    {
        foreach (['dot.crm', 'dot.support', 'dot.security', 'dot.vault'] as $key) {
            $this->assertNull($this->service->getRealPlatformId($key), "Expected {$key} to have no real platform match");
        }
    }

    public function test_unknown_key_returns_null_rather_than_erroring(): void
    {
        $this->assertNull($this->service->getRealPlatformId('dot.does-not-exist'));
    }

    public function test_the_catalog_still_has_all_fifteen_original_keys_unrenamed(): void
    {
        // The whole point of the additive approach: existing consumers
        // (MetricDefinitionSeeder, CrossPlatformIntelligenceService,
        // ConnectPlatformAction, and ~24 existing tests) all still work
        // against these exact original keys.
        $this->assertCount(15, $this->service->getPlatformCatalog());
        $this->assertNotNull($this->service->getPlatform('dot.fleet'));
        $this->assertNotNull($this->service->getPlatform('dot.crm'));
    }
}
