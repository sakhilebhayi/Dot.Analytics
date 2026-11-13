<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Services\IntelligenceEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'IntelligenceEngineService::PLATFORMS' => array_keys(IntelligenceEngineService::PLATFORMS),
            'config/analytics.php ingest secrets' => array_keys(config('analytics.ingest.secrets')),
        ];

        foreach ($lists as $where => $keys) {
            $this->assertSame([], array_values(array_diff($keys, self::CURRENT)), $where.' names a platform that is not current');
        }
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
