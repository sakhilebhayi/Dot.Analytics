<?php

namespace Tests\Unit\Services;

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
}
