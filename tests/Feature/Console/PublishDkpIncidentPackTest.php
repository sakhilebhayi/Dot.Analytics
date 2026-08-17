<?php

namespace Tests\Feature\Console;

use App\Models\AnalyticsAlert;
use App\Models\MetricDefinition;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * dkp:publish-incident, this platform's Dot Knowledge Protocol pipeline
 * (see storage/app/private/README.md, platform.dkp.json, config/dkp.php).
 * Mirrors the real, already-proven Dot.Emall precedent (same envelope
 * shape, same Ed25519 signing scheme) but as a generic command over any
 * real, resolved, critical AnalyticsAlert row rather than one hardcoded
 * incident.
 */
class PublishDkpIncidentPackTest extends TestCase
{
    use RefreshDatabase;

    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a real Ed25519 seed for the test run so signature
        // verification below is genuine, not mocked.
        $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $keyPath = storage_path('app/private/dkp-signing-test.key');
        File::put($keyPath, base64_encode($seed));
        config(['dkp.signing_key_path' => $keyPath]);

        $this->outputDir = storage_path('app/dkp/packs-test');
        config(['dkp.output_path' => $this->outputDir]);
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/private/dkp-signing-test.key'));
        File::deleteDirectory($this->outputDir);

        parent::tearDown();
    }

    private function requiredOptions(): array
    {
        return [
            '--root-cause' => 'The alerting threshold was breached because of a real underlying condition.',
            '--corrective-action' => 'Adjusted the threshold and fixed the underlying condition.',
            '--corrective-owner' => 'Analytics Platform Lead',
            '--corrective-due' => '2026-08-11',
            '--lesson' => 'Metric thresholds need periodic review against real traffic patterns.',
        ];
    }

    public function test_it_publishes_a_signed_incident_report_for_a_resolved_critical_alert(): void
    {
        $metric = MetricDefinition::factory()->create(['source_platform' => 'dot.fleet', 'label' => 'Fleet Utilization']);
        $team = Team::factory()->create();
        $alert = AnalyticsAlert::factory()->create([
            'team_id' => $team->id,
            'metric_definition_id' => $metric->id,
            'title' => 'Fleet utilization dropped below threshold',
            'description' => 'Fleet utilization fell below the configured critical threshold for 3 consecutive periods.',
            'severity' => 'critical',
            'status' => 'resolved',
            'triggered_at' => now()->subDay(),
            'resolved_at' => now(),
        ]);

        $this->artisan('dkp:publish-incident', [
            'alert' => $alert->id,
            ...$this->requiredOptions(),
        ])->assertExitCode(0);

        $files = File::files($this->outputDir);
        $this->assertCount(1, $files);

        $pack = json_decode(File::get($files[0]->getPathname()), true);

        $this->assertSame('dot-analytics', $pack['platform']);
        $this->assertSame('incident_report', $pack['payloads'][0]['payload_type']);
        $this->assertSame("dot-analytics:alert-{$alert->id}", $pack['payloads'][0]['body']['incident_id']);
        $this->assertSame(['dot.fleet'], $pack['payloads'][0]['body']['impact']['systems']);
        $this->assertSame($alert->description, $pack['payloads'][0]['body']['impact']['description']);
        $this->assertSame(
            'The alerting threshold was breached because of a real underlying condition.',
            $pack['payloads'][0]['body']['root_cause']['statement'],
        );
        $this->assertCount(1, $pack['signatures']);
    }

    public function test_the_written_pack_signature_verifies_against_the_committed_public_key_format(): void
    {
        $alert = AnalyticsAlert::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'severity' => 'critical',
            'status' => 'resolved',
            'triggered_at' => now()->subHours(2),
            'resolved_at' => now(),
        ]);

        $this->artisan('dkp:publish-incident', [
            'alert' => $alert->id,
            ...$this->requiredOptions(),
        ])->assertExitCode(0);

        $files = File::files($this->outputDir);
        $pack = json_decode(File::get($files[0]->getPathname()), true);

        $seed = base64_decode(trim(File::get(config('dkp.signing_key_path'))), strict: true);
        $keypair = sodium_crypto_sign_seed_keypair($seed);
        $publicKey = sodium_crypto_sign_publickey($keypair);

        $signable = $pack;
        unset($signable['signatures']);
        $canonical = json_encode($this->sortKeysRecursive($signable), JSON_UNESCAPED_SLASHES);

        $signature = base64_decode($pack['signatures'][0]['value']);

        $this->assertTrue(sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey));
    }

    public function test_it_refuses_to_publish_a_non_critical_alert(): void
    {
        $alert = AnalyticsAlert::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'severity' => 'warning',
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $this->artisan('dkp:publish-incident', [
            'alert' => $alert->id,
            ...$this->requiredOptions(),
        ])->assertExitCode(1);

        $this->assertCount(0, File::exists($this->outputDir) ? File::files($this->outputDir) : []);
    }

    public function test_it_refuses_to_publish_an_unresolved_alert(): void
    {
        $alert = AnalyticsAlert::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'severity' => 'critical',
            'status' => 'open',
            'resolved_at' => null,
        ]);

        $this->artisan('dkp:publish-incident', [
            'alert' => $alert->id,
            ...$this->requiredOptions(),
        ])->assertExitCode(1);
    }

    public function test_it_refuses_to_publish_without_required_postmortem_fields(): void
    {
        $alert = AnalyticsAlert::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'severity' => 'critical',
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $this->artisan('dkp:publish-incident', ['alert' => $alert->id])
            ->assertExitCode(1);

        $this->assertCount(0, File::exists($this->outputDir) ? File::files($this->outputDir) : []);
    }

    public function test_it_errors_on_an_unknown_alert_id(): void
    {
        $this->artisan('dkp:publish-incident', [
            'alert' => 999999,
            ...$this->requiredOptions(),
        ])->assertExitCode(1);
    }

    private function sortKeysRecursive(array $value): array
    {
        $isList = array_is_list($value);

        if (! $isList) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeysRecursive($item);
            }
        }

        return $value;
    }
}
