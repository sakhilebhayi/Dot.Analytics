<?php

namespace Tests\Feature\Metrics;

use App\Models\AnalyticsSnapshot;
use App\Models\DataSource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

class IngestContractTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private const SECRET = 'publisher-secret-for-tests-1f7a';

    private User $owner;

    private Team $team;

    private string $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->ownerWithTeam();
        $this->team = $this->owner->currentTeam;
        $this->company = $this->linkCompany($this->team);
    }

    private function sourceFor(string $platform, ?Team $team = null, ?string $secret = self::SECRET): DataSource
    {
        return DataSource::factory()->create([
            'team_id' => ($team ?? $this->team)->id,
            'platform' => $platform,
            'status' => 'connected',
            'webhook_secret' => $secret,
        ]);
    }

    private function snapshots(): Collection
    {
        return AnalyticsSnapshot::withoutGlobalScopes()->get();
    }

    public function test_a_valid_signed_snapshot_is_stored_synchronously_for_the_mapped_team(): void
    {
        $source = $this->sourceFor('tasks');
        $body = $this->snapshotBody($this->company, 'tasks', ['open' => 12, 'overdue' => 3]);

        $response = $this->postSnapshot($this->owner, 'tasks', $body, self::SECRET);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.replayed', false)
            ->assertJsonPath('data.platform', 'tasks')
            ->assertJsonPath('data.period', 'day')
            ->assertJsonPath('data.taken_at', '2026-10-17T06:00:00+00:00')
            ->assertJsonPath('data.accepted_keys', ['open', 'overdue'])
            ->assertJsonPath('data.dropped_keys', []);

        $stored = $this->snapshots();
        $this->assertCount(1, $stored);
        $snapshot = $stored->first();
        $this->assertSame($this->team->id, $snapshot->team_id);
        $this->assertSame($source->id, $snapshot->data_source_id);
        $this->assertSame('day', $snapshot->period);
        $this->assertSame('2026-10-17 06:00:00', $snapshot->taken_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(['open' => 12, 'overdue' => 3], $snapshot->payload['metrics']);
        $this->assertSame($snapshot->id, $response->json('data.snapshot_id'));
        $this->assertNotNull($source->fresh()->last_synced_at);
    }

    public function test_the_mapping_comes_from_the_company_uuid_not_the_tokens_current_team(): void
    {
        $other = Team::factory()->create(['user_id' => $this->owner->id, 'personal_team' => false]);
        $this->owner->teams()->attach($other, ['role' => 'admin']);
        $this->sourceFor('tasks', $other);
        $otherCompany = $this->linkCompany($other);
        // The token's user still has $this->team as the current team.
        $this->assertSame($this->team->id, $this->owner->fresh()->current_team_id);

        $this->postSnapshot($this->owner, 'tasks', $this->snapshotBody($otherCompany, 'tasks', ['open' => 4]), self::SECRET)
            ->assertStatus(201);

        $this->assertSame($other->id, $this->snapshots()->first()->team_id);
    }

    public function test_an_unknown_company_gets_the_same_generic_404_as_an_unconnected_platform(): void
    {
        $this->sourceFor('tasks');
        $unknown = $this->snapshotBody('11111111-2222-4333-8444-555555555555', 'tasks', ['open' => 1]);
        $unconnected = $this->snapshotBody($this->company, 'billing', ['open_invoices' => 1]);

        $a = $this->postSnapshot($this->owner, 'tasks', $unknown, self::SECRET);
        $b = $this->postSnapshot($this->owner, 'billing', $unconnected, self::SECRET);

        $a->assertNotFound();
        $b->assertNotFound();
        $this->assertSame(
            str_replace('billing', 'tasks', $b->json('message')),
            $a->json('message'),
            'unknown company and unconnected platform must not be distinguishable',
        );
        $this->assertStringNotContainsString('company', strtolower($a->json('message')));
        $this->assertCount(0, $this->snapshots());
    }

    public function test_a_user_cannot_push_into_a_company_they_do_not_belong_to(): void
    {
        $victim = $this->ownerWithTeam();
        $victimCompany = $this->linkCompany($victim->currentTeam);
        $this->sourceFor('tasks', $victim->currentTeam, 'victim-secret-0000');

        $response = $this->postSnapshot($this->owner, 'tasks', $this->snapshotBody($victimCompany, 'tasks', ['open' => 999]), 'victim-secret-0000');

        $response->assertNotFound();
        $this->assertCount(0, $this->snapshots());
    }

    public function test_a_company_uuid_is_required_and_must_look_like_a_uuid(): void
    {
        $this->sourceFor('tasks');

        $body = $this->snapshotBody($this->company, 'tasks', ['open' => 1]);
        unset($body['company_uuid']);
        $this->postSnapshot($this->owner, 'tasks', $body, self::SECRET)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_uuid');

        $body['company_uuid'] = 'not-a-uuid';
        $this->postSnapshot($this->owner, 'tasks', $body, self::SECRET)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_uuid');
    }

    public function test_a_bad_signature_is_rejected_and_nothing_is_stored(): void
    {
        $this->sourceFor('tasks');
        $body = $this->snapshotBody($this->company, 'tasks', ['open' => 1]);

        $this->postSnapshot($this->owner, 'tasks', $body, self::SECRET, 'some-other-secret')->assertUnauthorized();
        $this->assertCount(0, $this->snapshots());
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        $this->sourceFor('tasks');

        $this->asApi($this->owner)
            ->postJson('/api/v1/ingest/tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 1]))
            ->assertUnauthorized();
        $this->assertCount(0, $this->snapshots());
    }

    public function test_a_source_without_a_secret_cannot_ingest(): void
    {
        $this->sourceFor('tasks', null, null);

        $this->postSnapshot($this->owner, 'tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 1]), 'anything')
            ->assertUnauthorized();
        $this->assertCount(0, $this->snapshots());
    }

    public function test_the_signature_covers_the_raw_body_so_a_tampered_body_fails(): void
    {
        $this->sourceFor('tasks');
        $signed = $this->snapshotBody($this->company, 'tasks', ['open' => 1]);
        $signature = 'sha256='.hash_hmac('sha256', json_encode($signed), self::SECRET);
        $tampered = $this->snapshotBody($this->company, 'tasks', ['open' => 100000]);

        $this->asApi($this->owner)->withHeaders(['X-Analytics-Signature' => $signature])
            ->postJson('/api/v1/ingest/tasks', $tampered)
            ->assertUnauthorized();
    }

    public function test_unknown_keys_are_dropped_and_reported_not_stored(): void
    {
        $this->sourceFor('tasks');

        $response = $this->postSnapshot($this->owner, 'tasks', $this->snapshotBody($this->company, 'tasks', [
            'open' => 5, 'secret_internal_kpi' => 42,
        ]), self::SECRET);

        $response->assertStatus(201)
            ->assertJsonPath('data.accepted_keys', ['open'])
            ->assertJsonPath('data.dropped_keys', ['secret_internal_kpi']);
        $this->assertSame(['open' => 5], $this->snapshots()->first()->payload['metrics']);
        $this->assertStringNotContainsString('secret_internal_kpi', json_encode($this->snapshots()->first()->payload));
    }

    public function test_a_non_numeric_value_is_rejected_with_422(): void
    {
        $this->sourceFor('tasks');

        $this->postSnapshot($this->owner, 'tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 'lots']), self::SECRET)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metrics.open');
        $this->assertCount(0, $this->snapshots());
    }

    public function test_money_needs_a_currency_and_is_stored_as_integer_cents(): void
    {
        $this->sourceFor('billing');

        $this->postSnapshot($this->owner, 'billing', $this->snapshotBody($this->company, 'billing', ['mrr_cents' => 100]), self::SECRET)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metrics.currency');

        $this->postSnapshot($this->owner, 'billing', $this->snapshotBody($this->company, 'billing', ['mrr_cents' => 1250000, 'currency' => 'ZAR']), self::SECRET)
            ->assertStatus(201);

        $payload = $this->snapshots()->first()->payload;
        $this->assertSame(1250000, $payload['metrics']['mrr_cents']);
        $this->assertSame('ZAR', $payload['currency']);
    }

    public function test_the_body_platform_must_match_the_url(): void
    {
        $this->sourceFor('tasks');

        $this->postSnapshot($this->owner, 'tasks', $this->snapshotBody($this->company, 'billing', ['open' => 1]), self::SECRET)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('platform');
    }

    public function test_a_future_taken_at_is_rejected(): void
    {
        $this->sourceFor('tasks');
        $future = now()->addHours(2)->toIso8601String();

        $this->postSnapshot($this->owner, 'tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 1], $future), self::SECRET)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('taken_at');
    }

    public function test_replaying_a_snapshot_returns_the_original_and_does_not_overwrite_it(): void
    {
        $this->sourceFor('tasks');
        $first = $this->postSnapshot($this->owner, 'tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 12]), self::SECRET);
        $first->assertStatus(201);

        // Same data source, platform, taken_at (written in another zone) and period, different numbers.
        $replay = $this->postSnapshot($this->owner, 'tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 9999], '2026-10-17T08:00:00+02:00'), self::SECRET);

        $replay->assertOk()
            ->assertJsonPath('data.replayed', true)
            ->assertJsonPath('data.snapshot_id', $first->json('data.snapshot_id'))
            ->assertJsonPath('data.accepted_keys', ['open']);
        $this->assertCount(1, $this->snapshots());
        $this->assertSame(12, $this->snapshots()->first()->payload['metrics']['open']);
    }

    public function test_a_different_taken_at_is_a_new_snapshot(): void
    {
        $this->sourceFor('tasks');

        $this->postSnapshot($this->owner, 'tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 12], '2026-10-16T06:00:00Z'), self::SECRET)->assertStatus(201);
        $this->postSnapshot($this->owner, 'tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 13], '2026-10-17T06:00:00Z'), self::SECRET)->assertStatus(201);

        $this->assertCount(2, $this->snapshots());
    }

    public function test_the_caller_cannot_label_a_snapshot_as_demo_data(): void
    {
        $this->sourceFor('tasks');
        $body = $this->snapshotBody($this->company, 'tasks', ['open' => 1]);
        $signature = 'sha256='.hash_hmac('sha256', json_encode($body), self::SECRET);

        $this->asApi($this->owner)
            ->withHeaders(['X-Analytics-Signature' => $signature, 'X-Analytics-Snapshot-Type' => 'demo'])
            ->postJson('/api/v1/ingest/tasks', $body)
            ->assertStatus(201);

        $this->assertSame('webhook', $this->snapshots()->first()->snapshot_type);
    }

    public function test_ingest_still_requires_authentication(): void
    {
        $this->postJson('/api/v1/ingest/tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 1]))->assertUnauthorized();
    }

    public function test_a_disconnected_platform_cannot_ingest(): void
    {
        $source = $this->sourceFor('tasks');
        $source->update(['status' => 'pending']);

        $this->postSnapshot($this->owner, 'tasks', $this->snapshotBody($this->company, 'tasks', ['open' => 1]), self::SECRET)
            ->assertNotFound();
    }
}
