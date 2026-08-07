<?php

namespace Tests\Unit\Services;

use App\Models\DataSource;
use App\Models\User;
use App\Services\IntelligenceEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntelligenceEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private IntelligenceEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IntelligenceEngineService;
    }

    public function test_platform_catalog_contains_all_fifteen_platforms(): void
    {
        $platforms = $this->service->getPlatformCatalog();

        $this->assertCount(15, $platforms);
        $this->assertArrayHasKey('dot.fleet', $platforms);
        $this->assertArrayHasKey('dot.crm', $platforms);
        $this->assertArrayHasKey('dot.hr', $platforms);
        $this->assertArrayHasKey('dot.hear', $platforms);
        $this->assertArrayHasKey('dot.vault', $platforms);
    }

    public function test_each_platform_has_required_keys(): void
    {
        foreach ($this->service->getPlatformCatalog() as $key => $platform) {
            $this->assertArrayHasKey('label', $platform, "Platform {$key} missing 'label'");
            $this->assertArrayHasKey('contributions', $platform, "Platform {$key} missing 'contributions'");
            $this->assertArrayHasKey('engines', $platform, "Platform {$key} missing 'engines'");
            $this->assertArrayHasKey('produces', $platform, "Platform {$key} missing 'produces'");
            $this->assertNotEmpty($platform['contributions'], "Platform {$key} has empty contributions");
        }
    }

    public function test_engine_registry_contains_at_least_seventeen_engines(): void
    {
        $engines = $this->service->getEngineRegistry();

        $this->assertGreaterThanOrEqual(17, count($engines));
        $this->assertArrayHasKey('business', $engines);
        $this->assertArrayHasKey('operational', $engines);
        $this->assertArrayHasKey('financial', $engines);
        $this->assertArrayHasKey('predictive', $engines);
        $this->assertArrayHasKey('risk', $engines);
        $this->assertArrayHasKey('prescriptive', $engines);
    }

    public function test_each_engine_has_required_keys(): void
    {
        foreach ($this->service->getEngineRegistry() as $key => $engine) {
            $this->assertArrayHasKey('label', $engine, "Engine {$key} missing 'label'");
            $this->assertArrayHasKey('sources', $engine, "Engine {$key} missing 'sources'");
            $this->assertArrayHasKey('produces', $engine, "Engine {$key} missing 'produces'");
            $this->assertNotEmpty($engine['sources'], "Engine {$key} has empty sources");
        }
    }

    public function test_get_active_engines_returns_engines_with_at_least_one_connected_platform(): void
    {
        $active = $this->service->getActiveEngines(['dot.fleet', 'dot.hr']);

        $this->assertNotEmpty($active);
        foreach ($active as $engineKey => $engine) {
            $this->assertArrayHasKey('connected_sources', $engine);
            $this->assertArrayHasKey('coverage', $engine);
            $this->assertNotEmpty($engine['connected_sources']);
            $this->assertGreaterThan(0, $engine['coverage']);
        }
    }

    public function test_get_active_engines_returns_empty_when_no_platforms_connected(): void
    {
        $active = $this->service->getActiveEngines([]);
        $this->assertEmpty($active);
    }

    public function test_get_active_engines_coverage_is_100_when_all_sources_connected(): void
    {
        // The 'community' engine only uses dot.hear
        $active = $this->service->getActiveEngines(['dot.hear']);

        $this->assertArrayHasKey('community', $active);
        $this->assertEquals(100, $active['community']['coverage']);
    }

    public function test_get_platform_returns_correct_definition(): void
    {
        $platform = $this->service->getPlatform('dot.fleet');

        $this->assertNotNull($platform);
        $this->assertEquals('Dot.Fleet', $platform['label']);
    }

    public function test_get_platform_returns_null_for_unknown_key(): void
    {
        $this->assertNull($this->service->getPlatform('dot.unknown'));
    }

    public function test_get_platform_label_returns_label(): void
    {
        $label = $this->service->getPlatformLabel('dot.crm');
        $this->assertEquals('Dot.CRM', $label);
    }

    public function test_get_platform_label_returns_key_for_unknown_platform(): void
    {
        $label = $this->service->getPlatformLabel('dot.whatever');
        $this->assertEquals('dot.whatever', $label);
    }

    public function test_get_platform_color_classes_returns_valid_tailwind_classes(): void
    {
        $classes = $this->service->getPlatformColorClasses('dot.fleet');

        $this->assertArrayHasKey('bg', $classes);
        $this->assertArrayHasKey('text', $classes);
        $this->assertArrayHasKey('border', $classes);
        $this->assertArrayHasKey('dot', $classes);
        $this->assertStringStartsWith('bg-', $classes['bg']);
    }

    public function test_build_ecosystem_context_includes_platform_names(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
        ]);

        $context = $this->service->buildEcosystemContext($team);

        $this->assertStringContainsString('Dot.Fleet', $context);
        $this->assertStringContainsString($team->name, $context);
    }

    public function test_build_ecosystem_context_handles_no_connected_platforms(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $context = $this->service->buildEcosystemContext($user->currentTeam);

        $this->assertStringContainsString('No platforms', $context);
    }
}
