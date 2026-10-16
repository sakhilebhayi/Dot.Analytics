<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AiModelRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiModelRouterTest extends TestCase
{
    use RefreshDatabase;

    private AiModelRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new AiModelRouter;
    }

    public function test_complete_returns_mock_response_when_no_api_keys_configured(): void
    {
        // In test env no API keys are set — should return mock
        $response = $this->router->complete('Test prompt', 'query');

        $this->assertIsString($response);
        $this->assertNotEmpty($response);
    }

    public function test_complete_returns_empty_array_string_for_insight_capability_without_key(): void
    {
        $response = $this->router->complete('Generate insights', 'insight');

        $this->assertEquals('[]', $response);
    }

    public function test_complete_returns_empty_array_string_for_recommendation_capability_without_key(): void
    {
        $response = $this->router->complete('Generate recommendations', 'recommendation');

        $this->assertEquals('[]', $response);
    }

    public function test_complete_returns_json_for_briefing_capability_without_key(): void
    {
        $response = $this->router->complete('Generate briefing', 'briefing');

        $parsed = json_decode($response, true);
        $this->assertIsArray($parsed);
        $this->assertArrayHasKey('summary', $parsed);
    }

    public function test_complete_returns_string_for_generic_capability_without_key(): void
    {
        $response = $this->router->complete('Summarise this', 'summarisation');

        $this->assertIsString($response);
        $this->assertNotEmpty($response);
    }

    public function test_complete_tracks_no_usage_when_team_id_is_null(): void
    {
        $this->router->complete('Test prompt', 'query', null);

        $this->assertDatabaseCount('ai_model_usage', 0);
    }

    public function test_complete_tracks_no_usage_for_a_real_team_when_no_provider_responds(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $this->router->complete('Test prompt', 'query', $team->id);

        $this->assertEquals(0, \App\Models\AiModelUsage::where('team_id', $team->id)->count());
    }

    public function test_complete_works_across_all_capability_tiers(): void
    {
        // Exercises CAPABILITY_TIERS' full mapping and buildFallbackChain()'s
        // tier-filtering for tier 1, 2, and 3 alike.
        foreach (['insight', 'root_cause', 'query', 'sql', 'classification'] as $capability) {
            $response = $this->router->complete('x', $capability);
            $this->assertIsString($response);
        }
    }

    public function test_complete_uses_default_tier_for_an_unmapped_capability(): void
    {
        $response = $this->router->complete('x', 'totally_unmapped_capability');

        $this->assertIsString($response);
        $this->assertNotEmpty($response);
    }

    public function test_complete_respects_a_non_default_primary_provider(): void
    {
        config(['services.ai.primary_provider' => 'openai']);

        $response = $this->router->complete('x', 'query');

        $this->assertIsString($response);
    }
}
