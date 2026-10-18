<?php

namespace Tests\Feature\Metrics;

use App\Models\AnalyticsSnapshot;
use App\Models\AuditLog;
use App\Models\ComputedMetric;
use App\Models\DataSource;
use App\Models\Team;
use App\Services\Metrics\MetricReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

/**
 * POST /api/ingest/snapshots: a Dot platform publishes its own snapshot with no
 * user token, proving itself with an HMAC over "{timestamp}.{raw body}" made with
 * its own secret (config analytics.ingest.secrets). What a snapshot may contain is
 * the same contract the user-token endpoint enforces (IngestContractTest).
 */
class ServerIngestTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private const URL = '/api/ingest/snapshots';

    private const TASKS_SECRET = 'tasks-publisher-secret-3c91d2e47b';

    private const HR_SECRET = 'hr-publisher-secret-77a0e4c1f9';

    private Team $team;

    private string $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(9, 0));

        config(['analytics.ingest.secrets' => array_merge(config('analytics.ingest.secrets'), [
            'tasks' => self::TASKS_SECRET,
            'hr' => self::HR_SECRET,
            'billing' => '',
        ])]);

        $this->team = $this->ownerWithTeam()->currentTeam;
        $this->company = $this->linkCompany($this->team);
    }

    /** @return array<string, mixed> */
    private function body(?string $platform = 'tasks', array $metrics = ['open' => 12, 'overdue' => 3], ?string $company = null, string $takenAt = '2026-10-17T06:00:00Z'): array
    {
        return $this->snapshotBody($company ?? $this->company, (string) $platform, $metrics, $takenAt);
    }

    private function sign(string $raw, string $secret, ?int $timestamp = null): string
    {
        return 'sha256='.hash_hmac('sha256', ($timestamp ?? now()->timestamp).'.'.$raw, $secret);
    }

    /** POST exactly $raw with the given headers; no user, no token. */
    private function postRaw(string $raw, array $headers): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', self::URL, [], [], [], $server, $raw);
    }

    /** A correctly signed call by a platform. */
    private function publish(array $body, string $secret = self::TASKS_SECRET, ?int $timestamp = null): TestResponse
    {
        $raw = json_encode($body);
        $timestamp ??= now()->timestamp;

        return $this->postRaw($raw, [
            'X-InfoDot-Timestamp' => (string) $timestamp,
            'X-InfoDot-Signature' => $this->sign($raw, $secret, $timestamp),
        ]);
    }

    private function snapshots()
    {
        return AnalyticsSnapshot::withoutGlobalScopes()->get();
    }

    private function sources()
    {
        return DataSource::withoutGlobalScopes()->get();
    }

    // ─── Accepting a publisher ────────────────────────────────────────────────

    public function test_a_valid_signed_snapshot_is_accepted_without_any_user_token(): void
    {
        $response = $this->publish($this->body());

        $response->assertStatus(201)
            ->assertJsonPath('status', 'stored')
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('platform', 'tasks')
            ->assertJsonPath('period', 'day')
            ->assertJsonPath('taken_at', '2026-10-17T06:00:00+00:00')
            ->assertJsonPath('accepted_keys', ['open', 'overdue'])
            ->assertJsonPath('dropped_keys', [])
            ->assertJsonPath('success', true);

        $stored = $this->snapshots();
        $this->assertCount(1, $stored);
        $snapshot = $stored->first();
        $this->assertSame($snapshot->id, $response->json('id'));
        $this->assertSame($snapshot->id, $response->json('data.snapshot_id'));
        $this->assertSame($this->team->id, $snapshot->team_id);
        $this->assertSame('day', $snapshot->period);
        $this->assertSame('2026-10-17 06:00:00', $snapshot->taken_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(['open' => 12, 'overdue' => 3], $snapshot->payload['metrics']);
        $this->assertSame('push', $snapshot->snapshot_type);
    }

    public function test_the_bare_hex_digest_is_accepted_like_notifys_ingest(): void
    {
        $raw = json_encode($this->body());
        $timestamp = now()->timestamp;

        $this->postRaw($raw, [
            'X-InfoDot-Timestamp' => (string) $timestamp,
            'X-InfoDot-Signature' => substr($this->sign($raw, self::TASKS_SECRET, $timestamp), strlen('sha256=')),
        ])->assertStatus(201);
    }

    public function test_every_signed_byte_counts_so_a_pretty_printed_body_signs_as_sent(): void
    {
        $raw = json_encode($this->body(), JSON_PRETTY_PRINT);
        $timestamp = now()->timestamp;

        $this->postRaw($raw, [
            'X-InfoDot-Timestamp' => (string) $timestamp,
            'X-InfoDot-Signature' => $this->sign($raw, self::TASKS_SECRET, $timestamp),
        ])->assertStatus(201);
    }

    public function test_the_signature_is_never_stored_or_echoed(): void
    {
        $raw = json_encode($this->body());
        $timestamp = now()->timestamp;
        $signature = $this->sign($raw, self::TASKS_SECRET, $timestamp);

        $response = $this->postRaw($raw, ['X-InfoDot-Timestamp' => (string) $timestamp, 'X-InfoDot-Signature' => $signature]);
        $response->assertStatus(201);

        $hex = substr($signature, strlen('sha256='));
        $this->assertStringNotContainsString($hex, $response->getContent());
        $this->assertStringNotContainsString($hex, json_encode($this->snapshots()->toArray()));
        $this->assertStringNotContainsString($hex, json_encode($this->sources()->makeVisible('webhook_secret')->toArray()));
        $this->assertStringNotContainsString($hex, json_encode(AuditLog::withoutGlobalScopes()->get()->toArray()));
        $this->assertStringNotContainsString(self::TASKS_SECRET, json_encode(AuditLog::withoutGlobalScopes()->get()->toArray()));
    }

    // ─── Authentication ───────────────────────────────────────────────────────

    public function test_a_tampered_body_is_refused(): void
    {
        $raw = json_encode($this->body());
        $timestamp = now()->timestamp;
        $signature = $this->sign($raw, self::TASKS_SECRET, $timestamp);
        $tampered = json_encode($this->body('tasks', ['open' => 9999, 'overdue' => 3]));

        $this->postRaw($tampered, ['X-InfoDot-Timestamp' => (string) $timestamp, 'X-InfoDot-Signature' => $signature])
            ->assertStatus(401);

        $this->assertCount(0, $this->snapshots());
        $this->assertCount(0, $this->sources());
    }

    public function test_the_signature_covers_the_company_so_it_cannot_be_redirected_to_another_company(): void
    {
        $victim = $this->ownerWithTeam()->currentTeam;
        $victimCompany = $this->linkCompany($victim);

        $raw = json_encode($this->body());
        $timestamp = now()->timestamp;
        $signature = $this->sign($raw, self::TASKS_SECRET, $timestamp);
        $redirected = str_replace($this->company, $victimCompany, $raw);
        $this->assertNotSame($raw, $redirected);

        $this->postRaw($redirected, ['X-InfoDot-Timestamp' => (string) $timestamp, 'X-InfoDot-Signature' => $signature])
            ->assertStatus(401);

        $this->assertCount(0, $this->snapshots());
    }

    public function test_the_signature_covers_the_timestamp(): void
    {
        $raw = json_encode($this->body());
        $signed = now()->timestamp - 100;

        $this->postRaw($raw, [
            'X-InfoDot-Timestamp' => (string) now()->timestamp,
            'X-InfoDot-Signature' => $this->sign($raw, self::TASKS_SECRET, $signed),
        ])->assertStatus(401);
    }

    public function test_a_wrong_secret_is_refused(): void
    {
        $this->publish($this->body(), 'not-the-tasks-secret')->assertStatus(401);

        $this->assertCount(0, $this->snapshots());
    }

    public function test_missing_or_blank_headers_are_refused(): void
    {
        $raw = json_encode($this->body());
        $timestamp = now()->timestamp;
        $signature = $this->sign($raw, self::TASKS_SECRET, $timestamp);

        $this->postRaw($raw, [])->assertStatus(401);
        $this->postRaw($raw, ['X-InfoDot-Signature' => $signature])->assertStatus(401);
        $this->postRaw($raw, ['X-InfoDot-Timestamp' => (string) $timestamp])->assertStatus(401);
        $this->postRaw($raw, ['X-InfoDot-Timestamp' => '', 'X-InfoDot-Signature' => ''])->assertStatus(401);
        $this->postRaw($raw, ['X-InfoDot-Timestamp' => 'yesterday', 'X-InfoDot-Signature' => $signature])->assertStatus(401);
        $this->postRaw($raw, ['X-InfoDot-Timestamp' => '0', 'X-InfoDot-Signature' => $this->sign($raw, self::TASKS_SECRET, 0)])->assertStatus(401);

        $this->assertCount(0, $this->snapshots());
    }

    public function test_a_stale_timestamp_is_refused_and_one_inside_the_window_is_not(): void
    {
        $this->publish($this->body(), self::TASKS_SECRET, now()->timestamp - 301)->assertStatus(401);
        $this->assertCount(0, $this->snapshots());

        $this->publish($this->body(), self::TASKS_SECRET, now()->timestamp - 299)->assertStatus(201);
    }

    public function test_a_future_timestamp_is_refused_and_one_inside_the_window_is_not(): void
    {
        $this->publish($this->body(), self::TASKS_SECRET, now()->timestamp + 301)->assertStatus(401);
        $this->assertCount(0, $this->snapshots());

        $this->publish($this->body(), self::TASKS_SECRET, now()->timestamp + 299)->assertStatus(201);
    }

    public function test_a_platform_key_that_is_not_in_the_catalogue_is_refused_even_if_a_secret_is_configured_for_it(): void
    {
        config(['analytics.ingest.secrets.fleet' => 'a-secret-for-a-retired-platform']);

        $this->publish($this->body('fleet'), 'a-secret-for-a-retired-platform')->assertStatus(401);
        $this->publish($this->body('infodot'), self::TASKS_SECRET)->assertStatus(401);
        $this->publish($this->body(null), self::TASKS_SECRET)->assertStatus(401);

        $this->assertCount(0, $this->snapshots());
    }

    public function test_the_platform_key_is_matched_exactly_not_case_folded(): void
    {
        $this->publish($this->body('TASKS'), self::TASKS_SECRET)->assertStatus(401);
    }

    public function test_a_platform_with_a_blank_secret_cannot_call_even_with_a_signature_made_from_nothing(): void
    {
        $body = $this->body('billing', ['open_invoices' => 4]);

        $this->publish($body, '')->assertStatus(401);
        $this->publish($body, 'guess')->assertStatus(401);

        // A platform that is not mentioned in config at all behaves the same.
        config(['analytics.ingest.secrets' => ['tasks' => self::TASKS_SECRET]]);
        $this->publish($this->body('hr', ['headcount' => 3]), self::HR_SECRET)->assertStatus(401);

        $this->assertCount(0, $this->snapshots());
        $this->assertCount(0, $this->sources());
    }

    public function test_a_platform_cannot_post_another_platforms_key(): void
    {
        // Tasks holds the tasks secret and signs a body that claims to be HR.
        $this->publish($this->body('hr', ['headcount' => 40]), self::TASKS_SECRET)->assertStatus(401);
        // Tasks signs as billing, whose secret is blank.
        $this->publish($this->body('billing', ['open_invoices' => 40]), self::TASKS_SECRET)->assertStatus(401);

        $this->assertCount(0, $this->snapshots());
        $this->assertCount(0, $this->sources());
    }

    public function test_every_authentication_failure_looks_exactly_alike(): void
    {
        $raw = json_encode($this->body());
        $timestamp = now()->timestamp;
        $good = $this->sign($raw, self::TASKS_SECRET, $timestamp);

        $failures = [
            'wrong secret' => $this->publish($this->body(), 'nope'),
            'no headers' => $this->postRaw($raw, []),
            'stale' => $this->publish($this->body(), self::TASKS_SECRET, now()->timestamp - 4000),
            'future' => $this->publish($this->body(), self::TASKS_SECRET, now()->timestamp + 4000),
            'tampered' => $this->postRaw($raw.' ', ['X-InfoDot-Timestamp' => (string) $timestamp, 'X-InfoDot-Signature' => $good]),
            'unknown platform' => $this->publish($this->body('fleet'), self::TASKS_SECRET),
            'blank-secret platform' => $this->publish($this->body('billing', ['open_invoices' => 1]), ''),
            'other platform key' => $this->publish($this->body('hr', ['headcount' => 1]), self::TASKS_SECRET),
            'not json' => $this->postRaw('not json at all', ['X-InfoDot-Timestamp' => (string) $timestamp, 'X-InfoDot-Signature' => $good]),
        ];

        $reference = $failures['wrong secret'];
        foreach ($failures as $name => $response) {
            $response->assertStatus(401);
            $this->assertSame($reference->getContent(), $response->getContent(), "{$name} is distinguishable from a wrong secret");
        }
        $this->assertSame(['error' => 'invalid_request'], $reference->json());
    }

    public function test_authentication_happens_before_the_company_is_looked_up(): void
    {
        $unknown = $this->body('tasks', ['open' => 1], '11111111-2222-4333-8444-555555555555');

        // Bad signature + unknown company: still the generic 401, never a 404 that reveals a company check.
        $this->publish($unknown, 'wrong')->assertStatus(401)->assertExactJson(['error' => 'invalid_request']);
        // Bad signature + a real company: the same.
        $this->publish($this->body(), 'wrong')->assertStatus(401)->assertExactJson(['error' => 'invalid_request']);
    }

    public function test_an_oversized_body_is_refused_before_anything_else(): void
    {
        config(['analytics.ingest.max_body_bytes' => 200]);

        $body = $this->body();
        $body['padding'] = str_repeat('x', 400);

        $this->publish($body)->assertStatus(413)->assertExactJson(['error' => 'payload_too_large']);
        $this->assertCount(0, $this->snapshots());
    }

    // ─── Company mapping and isolation ────────────────────────────────────────

    public function test_an_unknown_company_gets_a_generic_404_after_authentication(): void
    {
        $unknown = $this->body('tasks', ['open' => 1], '11111111-2222-4333-8444-555555555555');

        $this->publish($unknown)->assertStatus(404)->assertExactJson(['error' => 'not_found']);

        $this->assertCount(0, $this->snapshots());
        $this->assertCount(0, $this->sources(), 'an unknown company must not provision anything');
    }

    public function test_a_snapshot_lands_only_in_the_team_the_company_uuid_maps_to(): void
    {
        $other = $this->ownerWithTeam()->currentTeam;
        $otherCompany = $this->linkCompany($other);
        $bystander = $this->ownerWithTeam()->currentTeam;
        $this->linkCompany($bystander);

        // The platform publishes for company X, then for company Y, each correctly signed.
        $this->publish($this->body('tasks', ['open' => 5]))->assertStatus(201);
        $this->publish($this->body('tasks', ['open' => 77], $otherCompany))->assertStatus(201);

        $mine = $this->snapshots()->firstWhere('team_id', $this->team->id);
        $theirs = $this->snapshots()->firstWhere('team_id', $other->id);
        $this->assertSame(5, $mine->payload['metrics']['open']);
        $this->assertSame(77, $theirs->payload['metrics']['open']);
        $this->assertCount(2, $this->snapshots());
        $this->assertNull($this->snapshots()->firstWhere('team_id', $bystander->id));

        // And each team's own data source holds only its own snapshot.
        $this->assertSame(1, $this->sources()->firstWhere('team_id', $this->team->id)->snapshots()->count());
        $this->assertSame(1, $this->sources()->firstWhere('team_id', $other->id)->snapshots()->count());
        $this->assertNull($this->sources()->firstWhere('team_id', $bystander->id));
    }

    public function test_the_company_uuid_is_matched_case_insensitively_like_the_user_token_path(): void
    {
        $this->publish($this->body('tasks', ['open' => 2], strtoupper($this->company)))->assertStatus(201);

        $this->assertSame($this->team->id, $this->snapshots()->first()->team_id);
    }

    // ─── Idempotency ──────────────────────────────────────────────────────────

    public function test_a_replay_returns_200_with_the_original_id_and_changes_nothing(): void
    {
        $first = $this->publish($this->body('tasks', ['open' => 12]));
        $first->assertStatus(201);

        // The retry carries a fresh timestamp and signature, and even different numbers.
        $replay = $this->publish($this->body('tasks', ['open' => 999]), self::TASKS_SECRET, now()->timestamp + 5);

        $replay->assertStatus(200)
            ->assertJsonPath('id', $first->json('id'))
            ->assertJsonPath('status', 'replayed')
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('data.snapshot_id', $first->json('id'));

        $this->assertCount(1, $this->snapshots());
        $this->assertSame(12, $this->snapshots()->first()->payload['metrics']['open'], 'a replay must never restate a day');
    }

    public function test_a_different_taken_at_is_a_new_snapshot(): void
    {
        $this->publish($this->body('tasks', ['open' => 1], null, '2026-10-16T06:00:00Z'))->assertStatus(201);
        $this->publish($this->body('tasks', ['open' => 2], null, '2026-10-17T06:00:00Z'))->assertStatus(201);

        $this->assertCount(2, $this->snapshots());
    }

    public function test_the_user_token_path_and_this_one_share_one_identity(): void
    {
        // A snapshot stored through this path is a replay when the owner's webhook posts the same one.
        $first = $this->publish($this->body());
        $first->assertStatus(201);

        $source = $this->sources()->first();
        $source->forceFill(['webhook_secret' => 'owner-secret'])->save();
        $owner = $this->team->owner;
        $body = $this->body();

        $this->postSnapshot($owner, 'tasks', $body, 'owner-secret')
            ->assertStatus(200)
            ->assertJsonPath('data.snapshot_id', $first->json('id'));
        $this->assertCount(1, $this->snapshots());
    }

    // ─── Rate limit ───────────────────────────────────────────────────────────

    public function test_a_platform_is_rate_limited_and_other_platforms_are_not(): void
    {
        config(['analytics.ingest.rate_limit_per_minute' => 3]);

        foreach (['2026-10-10', '2026-10-11', '2026-10-12'] as $day) {
            $this->publish($this->body('tasks', ['open' => 1], null, "{$day}T06:00:00Z"))->assertStatus(201);
        }

        $limited = $this->publish($this->body('tasks', ['open' => 1], null, '2026-10-13T06:00:00Z'));
        $limited->assertStatus(429)->assertJsonPath('error', 'rate_limited');
        $this->assertGreaterThan(0, (int) $limited->headers->get('Retry-After'));
        $this->assertCount(3, $this->snapshots(), 'the limited call must store nothing');

        $this->publish($this->body('hr', ['headcount' => 8]), self::HR_SECRET)->assertStatus(201);

        // The window is a minute.
        $this->travel(61)->seconds();
        $this->publish($this->body('tasks', ['open' => 1], null, '2026-10-13T06:00:00Z'))->assertStatus(201);
    }

    public function test_calls_that_fail_authentication_do_not_use_up_a_platforms_allowance(): void
    {
        config(['analytics.ingest.rate_limit_per_minute' => 2]);

        foreach (range(1, 6) as $i) {
            $this->publish($this->body(), 'forged-by-a-stranger')->assertStatus(401);
        }

        $this->publish($this->body('tasks', ['open' => 1], null, '2026-10-15T06:00:00Z'))->assertStatus(201);
        $this->publish($this->body('tasks', ['open' => 1], null, '2026-10-16T06:00:00Z'))->assertStatus(201);
        $this->publish($this->body('tasks', ['open' => 1], null, '2026-10-17T06:00:00Z'))->assertStatus(429);
    }

    // ─── Validation: the same contract as the user-token path ─────────────────

    public function test_contract_violations_are_422_with_field_messages_and_store_nothing(): void
    {
        $cases = [
            'metrics.open' => $this->body('tasks', ['open' => 'lots']),
            'metrics.overdue' => $this->body('tasks', ['open' => 4, 'overdue' => -1]),
            'taken_at' => array_merge($this->body(), ['taken_at' => 'yesterday']),
            'period' => array_merge($this->body(), ['period' => 'week']),
            'metrics' => array_merge($this->body(), ['metrics' => []]),
            'company_uuid' => array_merge($this->body(), ['company_uuid' => 'not-a-uuid']),
        ];

        foreach ($cases as $field => $body) {
            $response = $this->publish($body);
            $response->assertStatus(422)->assertJsonPath('error', 'invalid_request');
            $this->assertArrayHasKey($field, $response->json('errors'), "no message for {$field}");
        }

        $this->assertCount(0, $this->snapshots());
        $this->assertCount(0, $this->sources(), 'a rejected snapshot must not provision a data source');
    }

    public function test_a_future_taken_at_is_a_422_not_a_401(): void
    {
        $response = $this->publish($this->body('tasks', ['open' => 1], null, now()->addHour()->toIso8601ZuluString()));

        $response->assertStatus(422);
        $this->assertArrayHasKey('taken_at', $response->json('errors'));
    }

    public function test_money_needs_a_currency_and_unknown_keys_are_dropped(): void
    {
        config(['analytics.ingest.secrets.billing' => 'billing-publisher-secret-9d2']);

        $response = $this->publish($this->body('billing', ['mrr_cents' => 125000]), 'billing-publisher-secret-9d2');
        $response->assertStatus(422);
        $this->assertArrayHasKey('metrics.currency', $response->json('errors'));

        $ok = $this->publish($this->body('billing', ['mrr_cents' => 125000, 'currency' => 'zar', 'made_up' => 4]), 'billing-publisher-secret-9d2');
        $ok->assertStatus(201)->assertJsonPath('dropped_keys', ['made_up']);
        $this->assertSame('ZAR', $this->snapshots()->first()->payload['currency']);
    }

    // ─── The data source ──────────────────────────────────────────────────────

    public function test_the_first_accepted_snapshot_provisions_a_connected_push_data_source_once(): void
    {
        $this->assertCount(0, $this->sources());

        $this->publish($this->body('tasks', ['open' => 1], null, '2026-10-16T06:00:00Z'))->assertStatus(201);
        $this->publish($this->body('tasks', ['open' => 2], null, '2026-10-17T06:00:00Z'))->assertStatus(201);
        $this->publish($this->body('tasks', ['open' => 2], null, '2026-10-17T06:00:00Z'))->assertStatus(200);

        $sources = $this->sources();
        $this->assertCount(1, $sources);
        $source = $sources->first();
        $this->assertSame($this->team->id, $source->team_id);
        $this->assertSame('tasks', $source->platform);
        $this->assertSame('connected', $source->status);
        $this->assertSame('Dot.Tasks', $source->display_name);
        $this->assertSame('push', $source->config['source_type']);
        $this->assertNotNull($source->connected_at);
        $this->assertNotNull($source->last_synced_at);
        $this->assertNull($source->webhook_secret, 'this path needs no owner-issued webhook secret');
        $this->assertNotEmpty($source->capabilities);
        $this->assertCount(2, $source->snapshots);
    }

    public function test_provisioning_is_audited_as_the_system_and_leaks_no_secret(): void
    {
        $this->publish($this->body())->assertStatus(201);

        $log = AuditLog::withoutGlobalScopes()->where('event', 'platform.created')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->team->id, $log->team_id);
        $this->assertSame('system', $log->actor_type);
        $this->assertStringNotContainsString(self::TASKS_SECRET, json_encode($log->toArray()));
    }

    public function test_an_existing_data_source_is_reused_and_keeps_its_secret(): void
    {
        $existing = DataSource::factory()->connected()->create([
            'team_id' => $this->team->id,
            'platform' => 'tasks',
            'webhook_secret' => 'owner-issued-secret',
            'config' => ['note' => 'made by the owner'],
        ]);

        $this->publish($this->body())->assertStatus(201);

        $this->assertCount(1, $this->sources());
        $fresh = $existing->fresh();
        $this->assertSame('owner-issued-secret', $fresh->webhook_secret);
        $this->assertSame('made by the owner', $fresh->config['note']);
        $this->assertSame($fresh->id, $this->snapshots()->first()->data_source_id);
    }

    public function test_a_pending_data_source_becomes_connected_on_the_first_accepted_snapshot(): void
    {
        $pending = DataSource::factory()->create(['team_id' => $this->team->id, 'platform' => 'tasks', 'status' => 'pending']);

        $this->publish($this->body())->assertStatus(201);

        $this->assertCount(1, $this->sources());
        $this->assertSame('connected', $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->connected_at);
    }

    public function test_each_platform_gets_its_own_data_source_for_the_company(): void
    {
        $this->publish($this->body('tasks'))->assertStatus(201);
        $this->publish($this->body('hr', ['headcount' => 20]), self::HR_SECRET)->assertStatus(201);

        $this->assertEqualsCanonicalizing(['tasks', 'hr'], $this->sources()->pluck('platform')->all());
        $this->assertCount(2, $this->sources());
    }

    // ─── The one pipeline ─────────────────────────────────────────────────────

    public function test_metrics_are_computed_and_show_on_the_dashboard_query(): void
    {
        $this->publish($this->body('tasks', ['open' => 10, 'overdue' => 2], null, '2026-10-16T06:00:00Z'))->assertStatus(201);
        $this->publish($this->body('tasks', ['open' => 15], null, '2026-10-17T06:00:00Z'))->assertStatus(201);

        $this->assertGreaterThan(0, ComputedMetric::withoutGlobalScopes()->where('team_id', $this->team->id)->count());

        $dashboard = app(MetricReader::class)->dashboard($this->team);
        $tasks = collect($dashboard['platforms'])->firstWhere('key', 'tasks');
        $open = collect($tasks['tiles'])->firstWhere('key', 'tasks.open');

        $this->assertTrue($tasks['connected'], 'a pushing platform must show as connected');
        $this->assertTrue($tasks['has_data']);
        $this->assertSame('ok', $open['state']);
        $this->assertSame('15', $open['value']);
        $this->assertSame('+5', $open['delta']);
        $this->assertSame('10', $open['previous']);
        $this->assertFalse($open['is_demo']);
        $this->assertSame('no_data', collect($tasks['tiles'])->firstWhere('key', 'tasks.completed_7d')['state']);
    }

    public function test_another_teams_dashboard_is_untouched(): void
    {
        $other = $this->ownerWithTeam()->currentTeam;
        $this->linkCompany($other);

        $this->publish($this->body())->assertStatus(201);

        $this->assertSame(0, ComputedMetric::withoutGlobalScopes()->where('team_id', $other->id)->count());
        $dashboard = app(MetricReader::class)->dashboard($other);
        $this->assertSame(0, $dashboard['summary']['connected']);
    }

    public function test_a_replay_does_not_dispatch_a_second_computation(): void
    {
        $this->publish($this->body())->assertStatus(201);
        $rows = ComputedMetric::withoutGlobalScopes()->count();

        $this->publish($this->body())->assertStatus(200);

        $this->assertSame($rows, ComputedMetric::withoutGlobalScopes()->count());
    }

    // ─── Existing path unchanged ──────────────────────────────────────────────

    public function test_the_user_token_endpoint_still_demands_a_token_and_the_per_source_hmac(): void
    {
        $owner = $this->team->owner;
        $source = DataSource::factory()->connected()->create([
            'team_id' => $this->team->id,
            'platform' => 'tasks',
            'webhook_secret' => 'owner-secret',
        ]);
        $body = $this->body();
        $raw = json_encode($body);

        // No token: the S2S headers do not stand in for one.
        $this->postJson('/api/v1/ingest/tasks', $body, [
            'X-InfoDot-Timestamp' => (string) now()->timestamp,
            'X-InfoDot-Signature' => $this->sign($raw, self::TASKS_SECRET),
        ])->assertStatus(401);

        // A token with the platform secret instead of the per-source secret: still refused.
        $this->postSnapshot($owner, 'tasks', $body, self::TASKS_SECRET)->assertStatus(401);

        // A token with the right per-source HMAC works as before.
        $this->postSnapshot($owner, 'tasks', $body, 'owner-secret')->assertStatus(201)->assertJsonPath('data.replayed', false);
        $this->assertSame($source->id, $this->snapshots()->first()->data_source_id);
        $this->assertSame('webhook', $this->snapshots()->first()->snapshot_type);
    }

    public function test_a_user_token_does_not_authenticate_the_server_endpoint(): void
    {
        $owner = $this->team->owner;
        $raw = json_encode($this->body());

        $response = $this->asApi($owner)->call('POST', self::URL, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $raw);

        $response->assertStatus(401)->assertExactJson(['error' => 'invalid_request']);
        $this->assertCount(0, $this->snapshots());
    }

    // ─── Logging ──────────────────────────────────────────────────────────────

    public function test_refusals_are_logged_without_secrets_or_signatures(): void
    {
        Log::spy();

        $raw = json_encode($this->body());
        $timestamp = now()->timestamp;
        $signature = $this->sign($raw, 'wrong-secret-value', $timestamp);
        $this->postRaw($raw, ['X-InfoDot-Timestamp' => (string) $timestamp, 'X-InfoDot-Signature' => $signature])->assertStatus(401);

        $hex = substr($signature, strlen('sha256='));
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context = []) use ($hex, $raw) {
            $dump = $message.' '.json_encode($context);

            return str_contains(strtolower($message), 'refused')
                && ($context['platform'] ?? null) === 'tasks'
                && isset($context['reason'])
                && ! str_contains($dump, $hex)
                && ! str_contains($dump, 'wrong-secret-value')
                && ! str_contains($dump, self::TASKS_SECRET)
                && ! str_contains($dump, $raw);
        })->once();
    }

    public function test_an_unrecognised_platform_name_is_not_written_to_the_log_verbatim(): void
    {
        Log::spy();

        $body = $this->body("evil\nINJECTED line");
        $this->publish($body)->assertStatus(401);

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context = []) {
            return ! str_contains(json_encode($context), 'INJECTED');
        })->once();
    }
}
