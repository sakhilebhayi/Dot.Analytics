<?php

namespace Tests\Feature\Integrity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

/**
 * Production already holds plaintext secrets in data_sources.config and copies of
 * them in audit_logs. The migration must move and scrub them.
 */
class LegacySecretMigrationTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private const PATH = 'database/migrations/2026_10_18_000002_add_integrity_columns.php';

    public function test_migration_encrypts_legacy_config_secret_and_scrubs_audit_history(): void
    {
        $teamId = $this->ownerWithTeam()->currentTeam->id;

        // Put the schema back to its pre-fix shape, then seed the legacy data.
        Artisan::call('migrate:rollback', ['--path' => self::PATH, '--force' => true]);

        DB::table('data_sources')->insert([
            'team_id' => $teamId, 'platform' => 'tasks', 'display_name' => 'Dot.Fleet', 'status' => 'connected',
            'config' => json_encode(['webhook_secret' => 'legacy-plain-secret', 'region' => 'za']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('data_sources')->insert([
            'team_id' => $teamId, 'platform' => 'hr', 'display_name' => 'Dot.HR', 'status' => 'connected',
            'config' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('audit_logs')->insert([
            'team_id' => $teamId, 'event' => 'platform.created', 'actor_type' => 'system',
            'new_values' => json_encode(['platform' => 'tasks', 'config' => ['webhook_secret' => 'legacy-plain-secret', 'region' => 'za']]),
            'old_values' => null,
        ]);
        DB::table('audit_logs')->insert([
            'team_id' => $teamId, 'event' => 'platform.updated', 'actor_type' => 'system',
            'old_values' => json_encode(['config' => json_encode(['webhook_secret' => 'legacy-plain-secret'])]),
            'new_values' => json_encode(['status' => 'connected']),
        ]);

        Artisan::call('migrate', ['--path' => self::PATH, '--force' => true]);

        $row = DB::table('data_sources')->where('platform', 'tasks')->first();
        $this->assertNotNull($row->webhook_secret);
        $this->assertStringNotContainsString('legacy-plain-secret', $row->webhook_secret);
        $this->assertSame('legacy-plain-secret', Crypt::decryptString($row->webhook_secret));
        $this->assertSame(['region' => 'za'], json_decode($row->config, true));
        $this->assertNull(DB::table('data_sources')->where('platform', 'hr')->value('webhook_secret'));

        $audit = DB::table('audit_logs')->get()->map(fn ($r) => $r->old_values.$r->new_values)->implode(' ');
        $this->assertStringNotContainsString('legacy-plain-secret', $audit);
        $this->assertStringContainsString('[redacted]', $audit);
        $this->assertStringContainsString('"region":"za"', $audit);
    }
}
