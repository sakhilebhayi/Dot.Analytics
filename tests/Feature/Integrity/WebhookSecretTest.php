<?php

namespace Tests\Feature\Integrity;

use App\Models\AnalyticsSnapshot;
use App\Models\AuditLog;
use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

class WebhookSecretTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private const SECRET = 'plain-secret-do-not-leak-9f2c';

    private function sourceWithSecret(User $owner, ?string $secret = self::SECRET): DataSource
    {
        return DataSource::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'platform' => 'tasks',
            'status' => 'connected',
            'webhook_secret' => $secret,
        ]);
    }

    private function signed(array $payload, string $secret = self::SECRET): array
    {
        return ['X-Analytics-Signature' => 'sha256='.hash_hmac('sha256', json_encode($payload), $secret)];
    }

    // ─── encryption at rest ──────────────────────────────────────────────────

    public function test_secret_is_encrypted_at_rest_and_round_trips(): void
    {
        $source = $this->sourceWithSecret($this->ownerWithTeam());

        $raw = DB::table('data_sources')->where('id', $source->id)->value('webhook_secret');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString(self::SECRET, $raw);
        $this->assertSame(self::SECRET, $source->fresh()->webhook_secret);
    }

    public function test_secret_never_serialises(): void
    {
        $source = $this->sourceWithSecret($this->ownerWithTeam());

        $this->assertStringNotContainsString(self::SECRET, $source->fresh()->toJson());
        $this->assertArrayNotHasKey('webhook_secret', $source->fresh()->toArray());
    }

    public function test_legacy_config_secret_is_moved_into_the_encrypted_column(): void
    {
        $owner = $this->ownerWithTeam();
        $source = DataSource::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'platform' => 'tasks',
            'status' => 'connected',
            'config' => ['webhook_secret' => self::SECRET, 'region' => 'za'],
        ]);

        $fresh = $source->fresh();
        $this->assertSame(self::SECRET, $fresh->webhook_secret);
        $this->assertArrayNotHasKey('webhook_secret', $fresh->config);
        $this->assertSame('za', $fresh->config['region']);
        $this->assertStringNotContainsString(self::SECRET, DB::table('data_sources')->where('id', $source->id)->value('config'));
    }

    // ─── audit payloads ──────────────────────────────────────────────────────

    public function test_audit_log_never_contains_the_secret(): void
    {
        $owner = $this->ownerWithTeam();
        $source = $this->sourceWithSecret($owner);
        $source->update(['webhook_secret' => 'rotated-secret-77aa', 'config' => ['api_token' => 'tok-12345', 'ok' => 1]]);
        $source->delete();

        $dump = AuditLog::withoutGlobalScopes()->get()->map(fn ($l) => json_encode([$l->old_values, $l->new_values]))->implode(' ');

        $this->assertNotSame('', $dump);
        $this->assertStringNotContainsString(self::SECRET, $dump);
        $this->assertStringNotContainsString('rotated-secret-77aa', $dump);
        $this->assertStringNotContainsString('tok-12345', $dump);
        $this->assertStringContainsString('[redacted]', $dump);
        $this->assertStringContainsString('"ok":1', $dump);
        // ciphertext must not be copied either
        $raw = DB::table('data_sources')->value('webhook_secret');
        $this->assertTrue($raw === null || ! str_contains($dump, $raw));
    }

    public function test_api_responses_do_not_expose_the_secret(): void
    {
        $owner = $this->ownerWithTeam();
        $this->sourceWithSecret($owner);
        $token = $owner->createToken('t')->plainTextToken;

        foreach (['/api/v1/platforms', '/api/v1/platforms/connected', '/api/v1/platforms/tasks'] as $url) {
            $this->assertStringNotContainsString(self::SECRET, $this->withToken($token)->getJson($url)->getContent(), $url);
        }
    }

    // ─── ingest HMAC is required ─────────────────────────────────────────────

    private function company(User $owner): string
    {
        return $this->linkCompany($owner->currentTeam);
    }

    public function test_unsigned_ingest_is_rejected_with_401(): void
    {
        $owner = $this->ownerWithTeam();
        $this->sourceWithSecret($owner);
        $body = $this->snapshotBody($this->company($owner), 'tasks', ['open' => 1]);

        $this->asApi($owner)->postJson('/api/v1/ingest/tasks', $body)->assertUnauthorized();

        $this->assertSame(0, AnalyticsSnapshot::withoutGlobalScopes()->count());
    }

    public function test_ingest_for_source_without_a_secret_is_rejected(): void
    {
        $owner = $this->ownerWithTeam();
        $this->sourceWithSecret($owner, null);
        $body = $this->snapshotBody($this->company($owner), 'tasks', ['open' => 1]);

        $this->postSnapshot($owner, 'tasks', $body, 'any-secret')->assertUnauthorized();

        $this->assertSame(0, AnalyticsSnapshot::withoutGlobalScopes()->count());
    }

    public function test_wrong_signature_is_rejected(): void
    {
        $owner = $this->ownerWithTeam();
        $this->sourceWithSecret($owner);
        $body = $this->snapshotBody($this->company($owner), 'tasks', ['open' => 1]);

        $this->postSnapshot($owner, 'tasks', $body, self::SECRET, 'some-other-secret')->assertUnauthorized();
    }

    public function test_correctly_signed_ingest_is_accepted(): void
    {
        $owner = $this->ownerWithTeam();
        $this->sourceWithSecret($owner);
        $body = $this->snapshotBody($this->company($owner), 'tasks', ['open' => 1]);

        $this->postSnapshot($owner, 'tasks', $body, self::SECRET)->assertStatus(201);

        $this->assertSame(1, AnalyticsSnapshot::withoutGlobalScopes()->count());
    }

    // ─── provisioning ────────────────────────────────────────────────────────

    public function test_connecting_a_platform_issues_a_secret_shown_once(): void
    {
        $owner = $this->ownerWithTeam();
        $company = $this->company($owner);
        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/platforms/tasks/connect')->assertCreated();

        $secret = $response->json('data.webhook_secret');
        $this->assertIsString($secret);
        $this->assertGreaterThanOrEqual(32, strlen($secret));
        $this->assertNotNull(DataSource::first()->webhook_secret);
        $this->assertStringNotContainsString($secret, $this->withToken($token)->getJson('/api/v1/platforms/tasks')->getContent());

        $this->postSnapshot($owner, 'tasks', $this->snapshotBody($company, 'tasks', ['open' => 1]), $secret)
            ->assertStatus(201);
    }

    public function test_rotate_endpoint_is_admin_only_and_invalidates_the_old_secret(): void
    {
        $owner = $this->ownerWithTeam();
        $member = $this->memberOf($owner, 'editor');
        $this->sourceWithSecret($owner);

        $this->asApi($member)
            ->postJson('/api/v1/platforms/tasks/webhook-secret')->assertForbidden();

        $new = $this->asApi($owner)
            ->postJson('/api/v1/platforms/tasks/webhook-secret')->assertOk()->json('data.webhook_secret');

        $this->assertNotSame(self::SECRET, $new);
        $this->assertSame($new, DataSource::first()->webhook_secret);
    }
}
