<?php

namespace Tests\Feature\Api;

use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

/**
 * The transport-level behaviour of POST /api/v1/ingest/{platform}. What a
 * snapshot may contain is covered by Tests\Feature\Metrics\IngestContractTest.
 */
class WebhookTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    private User $user;

    private string $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->ownerWithTeam();
        $this->company = $this->linkCompany($this->user->currentTeam);
    }

    private function connectedSource(string $platform = 'tasks', ?string $secret = self::SECRET): DataSource
    {
        return DataSource::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'platform' => $platform,
            'status' => 'connected',
            'webhook_secret' => $secret,
        ]);
    }

    public function test_receive_accepts_a_signed_snapshot(): void
    {
        $this->connectedSource();

        $this->postSnapshot($this->user, 'tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 4]), self::SECRET)
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_receive_returns_404_for_unconnected_platform(): void
    {
        $this->postSnapshot($this->user, 'tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 4]), self::SECRET)
            ->assertNotFound();
    }

    public function test_receive_returns_422_for_an_empty_body(): void
    {
        $this->connectedSource();

        $this->asApi($this->user)
            ->withHeaders(['X-Analytics-Signature' => 'sha256='.hash_hmac('sha256', '[]', self::SECRET)])
            ->postJson('/api/v1/ingest/tasks')
            ->assertUnprocessable();
    }

    public function test_receive_rejects_an_invalid_hmac_signature(): void
    {
        $this->connectedSource();

        $this->asApi($this->user)
            ->withHeaders(['X-Analytics-Signature' => 'sha256=invalidsignature'])
            ->postJson('/api/v1/ingest/tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 4]))
            ->assertUnauthorized();
    }

    public function test_a_legacy_secret_in_config_is_honoured_after_the_model_moves_it(): void
    {
        $source = DataSource::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'platform' => 'tasks',
            'status' => 'connected',
            'config' => ['webhook_secret' => 'my-webhook-secret'],
        ]);
        $this->assertSame('my-webhook-secret', $source->fresh()->webhook_secret);

        $this->postSnapshot($this->user, 'tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 4]), 'my-webhook-secret')
            ->assertStatus(201);
    }

    public function test_ping_returns_connection_status(): void
    {
        $this->connectedSource();

        $this->asApi($this->user)->getJson('/api/v1/ingest/tasks/ping')
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.platform', 'tasks');
    }

    public function test_receive_requires_authentication(): void
    {
        $this->postJson('/api/v1/ingest/tasks')->assertUnauthorized();
    }
}
