<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_returns_ok_without_auth(): void
    {
        $this->getJson('/api/ping')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_health_returns_service_info(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', 'Dot.Analytics');
    }

    public function test_detailed_health_requires_auth(): void
    {
        $this->getJson('/api/v1/health/detailed')->assertUnauthorized();
    }

    public function test_detailed_health_returns_subsystem_checks(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/health/detailed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'database',
                    'cache',
                    'queue',
                    'ai_providers',
                    'application',
                    'intelligence',
                ],
            ]);
    }

    public function test_detailed_health_includes_database_latency(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/health/detailed');
        $database = $response->json('checks.database');

        $this->assertTrue($database['healthy']);
        $this->assertArrayHasKey('latency_ms', $database);
    }

    public function test_detailed_health_shows_no_ai_provider_configured(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/health/detailed');
        $aiProviders = $response->json('checks.ai_providers');

        // In test env, no API keys are set
        $this->assertTrue($aiProviders['fallback_mode']);
        $this->assertEmpty($aiProviders['configured']);
    }

    public function test_detailed_health_includes_application_version(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/health/detailed');
        $app = $response->json('checks.application');

        $this->assertArrayHasKey('laravel', $app);
        $this->assertArrayHasKey('php_version', $app);
    }
}
