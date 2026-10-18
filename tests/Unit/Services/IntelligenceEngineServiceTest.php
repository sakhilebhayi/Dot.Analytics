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

    public function test_platform_catalog_is_exactly_the_real_platforms_with_a_metric_catalogue(): void
    {
        $platforms = $this->service->getPlatformCatalog();

        $this->assertEqualsCanonicalizing(array_keys(config('metrics.platforms')), array_keys($platforms));
        $this->assertCount(11, $platforms);
        foreach (['tasks', 'auction', 'ehail', 'hr', 'farms', 'billing', 'emall', 'tutor', 'pulse', 'notify', 'design'] as $key) {
            $this->assertArrayHasKey($key, $platforms);
        }
    }

    public function test_platform_keys_and_names_are_the_ecosystem_registry_ones_not_the_retired_roster(): void
    {
        foreach ($this->service->getPlatformCatalog() as $key => $platform) {
            $this->assertArrayHasKey($key, config('ecosystem.platforms'), "{$key} is not a platform in config/ecosystem.php");
            $this->assertSame(config("ecosystem.platforms.{$key}.name"), $platform['label']);
            $this->assertStringStartsNotWith('dot.', $key);
        }

        foreach (['dot.fleet', 'dot.crm', 'dot.documents', 'dot.hear', 'dot.vault', 'dot.security'] as $retired) {
            $this->assertNull($this->service->getPlatform($retired));
        }
    }

    public function test_every_engine_source_is_a_real_platform_and_platform_engines_agree(): void
    {
        $platforms = $this->service->getPlatformCatalog();

        foreach ($this->service->getEngineRegistry() as $engineKey => $engine) {
            foreach ($engine['sources'] as $source) {
                $this->assertArrayHasKey($source, $platforms, "Engine {$engineKey} consumes unknown platform {$source}");
                $this->assertContains($engineKey, $platforms[$source]['engines'], "{$source} does not list the {$engineKey} engine it feeds");
            }
        }

        foreach ($platforms as $key => $platform) {
            foreach ($platform['engines'] as $engineKey) {
                $this->assertContains($key, $this->service->getEngine($engineKey)['sources'], "{$engineKey} does not list {$key}");
            }
        }
    }

    public function test_an_engine_no_real_platform_can_feed_is_never_active(): void
    {
        $everything = array_keys($this->service->getPlatformCatalog());
        $active = $this->service->getActiveEngines($everything);

        foreach ($this->service->getEngineRegistry() as $engineKey => $engine) {
            if ($engine['sources'] === []) {
                $this->assertArrayNotHasKey($engineKey, $active, "{$engineKey} has no source but is active");
            }
        }
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
            $this->assertIsArray($engine['sources'], "Engine {$key} sources must be a list (empty means dormant)");
        }
    }

    public function test_get_active_engines_returns_engines_with_at_least_one_connected_platform(): void
    {
        $active = $this->service->getActiveEngines(['tasks', 'hr']);

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
        // The 'community' engine only uses pulse
        $active = $this->service->getActiveEngines(['pulse']);

        $this->assertArrayHasKey('community', $active);
        $this->assertEquals(100, $active['community']['coverage']);
    }

    public function test_get_platform_returns_correct_definition(): void
    {
        $platform = $this->service->getPlatform('tasks');

        $this->assertNotNull($platform);
        $this->assertEquals('Dot.Tasks', $platform['label']);
    }

    public function test_get_platform_returns_null_for_unknown_key(): void
    {
        $this->assertNull($this->service->getPlatform('dot.unknown'));
    }

    public function test_get_platform_label_returns_label(): void
    {
        $label = $this->service->getPlatformLabel('emall');
        $this->assertEquals('Dot.Emall', $label);
    }

    public function test_get_platform_label_returns_key_for_unknown_platform(): void
    {
        $label = $this->service->getPlatformLabel('dot.whatever');
        $this->assertEquals('dot.whatever', $label);
    }

    public function test_get_platform_color_classes_returns_valid_tailwind_classes(): void
    {
        $classes = $this->service->getPlatformColorClasses('tasks');

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
            'platform' => 'tasks',
            'status' => 'connected',
        ]);

        $context = $this->service->buildEcosystemContext($team);

        $this->assertStringContainsString('Dot.Tasks', $context);
        $this->assertStringContainsString($team->name, $context);
    }

    public function test_build_ecosystem_context_handles_no_connected_platforms(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $context = $this->service->buildEcosystemContext($user->currentTeam);

        $this->assertStringContainsString('No platforms', $context);
    }
}
