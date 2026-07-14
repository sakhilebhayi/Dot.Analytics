<?php

namespace Tests\Feature\Api;

use App\Models\MetricDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): array
    {
        $user  = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;
        return [$user, $token];
    }

    public function test_definitions_returns_all_metric_definitions(): void
    {
        [$user, $token] = $this->actingAsUser();

        // Seed a few definitions
        MetricDefinition::factory()->count(5)->create();

        $response = $this->withToken($token)->getJson('/api/v1/metrics/definitions');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);

        $this->assertGreaterThanOrEqual(5, count($response->json('data')));
    }

    public function test_definitions_filters_by_engine(): void
    {
        [$user, $token] = $this->actingAsUser();

        MetricDefinition::factory()->create(['engine' => 'financial']);
        MetricDefinition::factory()->create(['engine' => 'operational']);

        $response = $this->withToken($token)->getJson('/api/v1/metrics/definitions?engine=financial');

        $response->assertOk();
        foreach ($response->json('data') as $def) {
            $this->assertEquals('financial', $def['engine']);
        }
    }

    public function test_metrics_index_returns_empty_for_new_team(): void
    {
        [$user, $token] = $this->actingAsUser();

        $response = $this->withToken($token)->getJson('/api/v1/metrics');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_ai_usage_returns_summary(): void
    {
        [$user, $token] = $this->actingAsUser();

        $response = $this->withToken($token)->getJson('/api/v1/metrics/ai-usage');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['by_model', 'total_cost', 'total_calls'],
            ]);
    }

    public function test_definitions_filters_by_platform(): void
    {
        [$user, $token] = $this->actingAsUser();

        MetricDefinition::factory()->create(['source_platform' => 'dot.fleet']);
        MetricDefinition::factory()->create(['source_platform' => 'dot.crm']);

        $response = $this->withToken($token)->getJson('/api/v1/metrics/definitions?platform=dot.fleet');

        $response->assertOk();
        foreach ($response->json('data') as $def) {
            $this->assertEquals('dot.fleet', $def['source_platform']);
        }
    }

    public function test_metrics_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/metrics')->assertUnauthorized();
        $this->getJson('/api/v1/metrics/definitions')->assertUnauthorized();
        $this->getJson('/api/v1/metrics/ai-usage')->assertUnauthorized();
    }
}
