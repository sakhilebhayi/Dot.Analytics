<?php

namespace Tests\Feature\Api;

use App\Actions\Analytics\ConnectPlatformAction;
use App\Models\DataSource;
use App\Models\User;
use App\Services\IntelligenceEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ConnectPlatformAction never wrote a webhook_secret to DataSource.config
 * before this pass, so IngestController::receive()'s HMAC signature check
 * ("if a secret is configured") was silently dead for every connected
 * platform -- it never had one to check against.
 */
class WebhookSecretTest extends TestCase
{
    use RefreshDatabase;

    public function test_connecting_a_platform_generates_a_webhook_secret(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $action = new ConnectPlatformAction(new IntelligenceEngineService);

        $source = $action->handle($team, 'dot.fleet');

        $this->assertNotEmpty($source->config['webhook_secret'] ?? null);
    }

    public function test_reconnecting_the_same_platform_preserves_the_existing_secret(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $action = new ConnectPlatformAction(new IntelligenceEngineService);

        $first = $action->handle($team, 'dot.fleet');
        $firstSecret = $first->config['webhook_secret'];

        $second = $action->handle($team, 'dot.fleet', 'https://updated.example.com');

        $this->assertSame($firstSecret, $second->config['webhook_secret']);
    }

    public function test_the_connect_api_response_surfaces_the_secret_once(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/platforms/dot.fleet/connect');

        $response->assertCreated();
        $this->assertNotEmpty($response->json('data.webhook_secret'));
    }

    public function test_the_secret_never_appears_in_the_catalog_endpoint(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;
        DataSource::factory()->create([
            'team_id' => $user->currentTeam->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
            'config' => ['webhook_secret' => 'super-secret-value'],
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/platforms');

        $response->assertOk();
        $this->assertStringNotContainsString('super-secret-value', $response->getContent());
    }

    public function test_the_secret_never_appears_in_the_show_endpoint(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;
        DataSource::factory()->create([
            'team_id' => $user->currentTeam->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
            'config' => ['webhook_secret' => 'super-secret-value'],
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/platforms/dot.fleet');

        $response->assertOk();
        $this->assertStringNotContainsString('super-secret-value', $response->getContent());
    }

    public function test_a_webhook_push_with_a_valid_signature_is_accepted(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;
        $secret = 'test-secret-value';
        DataSource::factory()->create([
            'team_id' => $user->currentTeam->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
            'config' => ['webhook_secret' => $secret],
        ]);

        $data = ['metric' => 'value'];
        // postJson() json_encode()s $data the same way internally --
        // signing that same encoding is what makes this comparable to a
        // real caller's request, where the signature is computed over the
        // exact bytes of the body they send.
        $signature = 'sha256='.hash_hmac('sha256', json_encode($data), $secret);

        $response = $this->withToken($token)
            ->withHeaders(['X-Analytics-Signature' => $signature])
            ->postJson('/api/v1/ingest/dot.fleet', $data);

        $response->assertAccepted();
    }

    public function test_a_webhook_push_with_an_invalid_signature_is_rejected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;
        DataSource::factory()->create([
            'team_id' => $user->currentTeam->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
            'config' => ['webhook_secret' => 'the-real-secret'],
        ]);

        $response = $this->withToken($token)
            ->withHeaders(['X-Analytics-Signature' => 'sha256=wrong'])
            ->postJson('/api/v1/ingest/dot.fleet', ['metric' => 'value']);

        $response->assertUnauthorized();
    }
}
