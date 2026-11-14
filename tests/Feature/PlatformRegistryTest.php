<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Services\IntelligenceEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

/** The app's own lists of Dot platforms name only platforms that exist, and agree with each other. */
class PlatformRegistryTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    /** The platforms of the ecosystem today. A key outside this list has been discontinued or never existed. */
    private const CURRENT = [
        'agents', 'analytics', 'auction', 'central', 'charts', 'design', 'docs', 'dopemine', 'ehail', 'emall', 'engage',
        'files', 'finance', 'forms', 'hr', 'memory', 'mines', 'notify', 'press', 'projects', 'pulse', 'sheet', 'tasks',
        'tutor', 'infodot',
    ];

    public function test_every_list_of_platforms_holds_only_current_platforms(): void
    {
        $lists = [
            'config/ecosystem.php' => array_keys(config('ecosystem.platforms')),
            'config/metrics.php' => array_keys(config('metrics.platforms')),
            'config/intelligence_ecosystem.php' => array_column(config('intelligence_ecosystem.platforms'), 'key'),
            'IntelligenceEngineService::PLATFORMS' => array_keys(IntelligenceEngineService::PLATFORMS),
            'config/analytics.php ingest secrets' => array_keys(config('analytics.ingest.secrets')),
        ];

        foreach ($lists as $where => $keys) {
            $this->assertSame([], array_values(array_diff($keys, self::CURRENT)), $where.' names a platform that is not current');
        }
    }

    public function test_the_platform_list_matches_the_infodot_registry_for_the_hosts_that_had_drifted(): void
    {
        // Defaults only: a host may still override any address through its DOT_*_URL variable.
        $platforms = config('ecosystem.platforms');

        $this->assertSame(['Dot.Press', 'https://pres.infodot.co.za'], [$platforms['press']['name'], $platforms['press']['url']]);
        $this->assertSame(['Dot.Sheet', 'https://sheets.infodot.co.za'], [$platforms['sheet']['name'], $platforms['sheet']['url']]);
        $this->assertSame(['Dot.docs', 'https://doc.infodot.co.za'], [$platforms['docs']['name'], $platforms['docs']['url']]);
        $this->assertTrue($platforms['charts']['active']);
        $this->assertSame('https://charts.infodot.co.za', $platforms['charts']['url']);

        foreach ($platforms as $key => $platform) {
            $this->assertMatchesRegularExpression('#^https://([a-z]+\.)?infodot\.co\.za$#', $platform['url'], $key);
        }
    }

    public function test_the_library_and_the_registry_name_and_address_each_platform_the_same_way(): void
    {
        $registry = config('ecosystem.platforms');

        foreach (config('intelligence_ecosystem.platforms') as $platform) {
            $this->assertSame($registry[$platform['key']]['name'], $platform['name'], $platform['key']);
            $this->assertSame($registry[$platform['key']]['url'], $platform['url'], $platform['key']);
        }

        $this->assertSame('Dot.Sheet', config('metrics.platforms.sheet.label'));
    }

    public function test_stored_rows_under_the_old_sheet_key_are_renamed_and_nothing_is_lost(): void
    {
        $db = DB::connection(config('intelligence.connection'));
        $db->table('platform_checks')->insert(['key' => 'sheets', 'url' => 'https://sheets.infodot.co.za', 'status' => 200, 'checked_at' => now(), 'last_ok_at' => now()]);
        $db->table('referrals')->insert([
            ['day' => '2026-11-10', 'kind' => 'platform', 'target' => 'sheets', 'clicks' => 4],
            ['day' => '2026-11-11', 'kind' => 'platform', 'target' => 'sheets', 'clicks' => 2],
            ['day' => '2026-11-11', 'kind' => 'platform', 'target' => 'sheet', 'clicks' => 1],
        ]);

        $migration = require database_path('migrations/2026_11_14_100000_rename_the_sheet_platform_key_in_the_library_store.php');
        $migration->up();
        $migration->up();

        $this->assertSame(['sheet'], $db->table('platform_checks')->pluck('key')->all());
        $this->assertSame(4, (int) $db->table('referrals')->where('day', '2026-11-10')->where('target', 'sheet')->value('clicks'));
        $this->assertSame(3, $db->table('referrals')->count(), 'a day that already has the new key keeps both rows');

        $migration->down();
        $this->assertSame(['sheets'], $db->table('platform_checks')->pluck('key')->all());
    }

    public function test_every_engine_reads_only_platforms_that_can_report(): void
    {
        foreach (IntelligenceEngineService::ENGINES as $key => $engine) {
            $this->assertSame([], array_values(array_diff($engine['sources'], array_keys(IntelligenceEngineService::PLATFORMS))), $key.' reads a platform that cannot report');
        }
    }

    public function test_a_data_source_left_over_from_a_removed_platform_is_ignored_not_fatal(): void
    {
        config(['ecosystem.sso_only' => false]);
        $owner = $this->ownerWithTeam();
        DataSource::factory()->create(['team_id' => $owner->currentTeam->id, 'platform' => 'no-longer-a-platform', 'status' => 'connected']);

        $this->actingAs($owner)->get('/dashboard')->assertOk()->assertViewHas('connectedCount', 0);
        $this->actingAs($owner)->get('/workspace/ask')->assertOk();
        $this->actingAs($owner)->get('/workspace/insights')->assertOk();
    }
}
