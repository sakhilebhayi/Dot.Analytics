<?php

namespace Tests\Feature\Api;

use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): array
    {
        $user  = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;
        return [$user, $token];
    }

    private function connectedSource(int $teamId, string $platform = 'dot.fleet', array $config = []): DataSource
    {
        return DataSource::factory()->create([
            'team_id'  => $teamId,
            'platform' => $platform,
            'status'   => 'connected',
            'config'   => $config,
        ]);
    }

    public function test_receive_accepts_valid_payload_and_queues_job(): void
    {
        [$user, $token] = $this->actingAsUser();
        $this->connectedSource($user->currentTeam->id);

        $response = $this->withToken($token)->postJson('/api/v1/ingest/dot.fleet', [
            'vehicles' => [['id' => 'V-001', 'fuel' => 45.2]],
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.queued', true);
    }

    public function test_receive_returns_404_for_unconnected_platform(): void
    {
        [$user, $token] = $this->actingAsUser();

        $response = $this->withToken($token)->postJson('/api/v1/ingest/dot.fleet', [
            'data' => [],
        ]);

        $response->assertNotFound();
    }

    public function test_receive_returns_422_for_empty_payload(): void
    {
        [$user, $token] = $this->actingAsUser();
        $this->connectedSource($user->currentTeam->id);

        $response = $this->withToken($token)->postJson('/api/v1/ingest/dot.fleet');

        $response->assertUnprocessable();
    }

    public function test_receive_rejects_invalid_hmac_signature(): void
    {
        [$user, $token] = $this->actingAsUser();
        $this->connectedSource($user->currentTeam->id, 'dot.fleet', ['webhook_secret' => 'secret123']);

        $response = $this->withToken($token)
            ->withHeaders(['X-Analytics-Signature' => 'sha256=invalidsignature'])
            ->postJson('/api/v1/ingest/dot.fleet', ['data' => 'test']);

        $response->assertUnauthorized();
    }

    public function test_receive_accepts_valid_hmac_signature(): void
    {
        [$user, $token] = $this->actingAsUser();
        $secret         = 'my-webhook-secret';
        $this->connectedSource($user->currentTeam->id, 'dot.fleet', ['webhook_secret' => $secret]);

        $payload   = ['vehicles' => [['id' => 'V-001']]];
        $body      = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $response = $this->withToken($token)
            ->withHeaders(['X-Analytics-Signature' => $signature])
            ->postJson('/api/v1/ingest/dot.fleet', $payload);

        $response->assertStatus(202);
    }

    public function test_ping_returns_connection_status(): void
    {
        [$user, $token] = $this->actingAsUser();
        $source         = $this->connectedSource($user->currentTeam->id);

        $response = $this->withToken($token)->getJson('/api/v1/ingest/dot.fleet/ping');

        $response->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.platform', 'dot.fleet');
    }

    public function test_receive_requires_authentication(): void
    {
        $this->postJson('/api/v1/ingest/dot.fleet')->assertUnauthorized();
    }
}
