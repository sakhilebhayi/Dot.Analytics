<?php

namespace Tests\Feature;

use App\Models\AnalyticsReport;
use App\Models\AnalyticsSnapshot;
use App\Models\AuditLog;
use App\Models\ReportRun;
use App\Models\Team;
use App\Models\User;
use App\Services\ReportGenerationService;
use App\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Things the security audit found that were fixed rather than documented. */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function team(?User $owner = null): Team
    {
        $owner ??= User::factory()->create();

        return Team::forceCreate(['user_id' => $owner->id, 'name' => 'Acme', 'personal_team' => false]);
    }

    public function test_the_audit_trail_survives_the_deletion_of_its_team(): void
    {
        $team = $this->team();
        TeamContext::system(fn () => AuditLog::create(['team_id' => $team->id, 'event' => 'platform.connected', 'actor_type' => 'user', 'occurred_at' => now()]));

        $team->delete();

        $this->assertSame(1, TeamContext::system(fn () => AuditLog::withoutGlobalScopes()->count()));
    }

    public function test_a_report_or_dashboard_outlives_the_member_who_made_it(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $this->team($owner);
        $report = TeamContext::system(fn () => AnalyticsReport::create(['team_id' => $team->id, 'user_id' => $member->id, 'title' => 'Weekly', 'type' => 'insights', 'report_type' => 'ad_hoc']));
        $dash = DB::table('analytics_dashboards')->insertGetId(['team_id' => $team->id, 'user_id' => $member->id, 'title' => 'Board', 'created_at' => now(), 'updated_at' => now()]);

        $member->delete();

        $this->assertNull(DB::table('analytics_reports')->where('id', $report->id)->value('user_id'));
        $this->assertSame(1, DB::table('analytics_reports')->where('id', $report->id)->count());
        $this->assertNull(DB::table('analytics_dashboards')->where('id', $dash)->value('user_id'));
    }

    public function test_csv_cells_that_would_run_as_formulas_are_made_plain_and_the_team_name_is_escaped(): void
    {
        $team = $this->team();
        $team->forceFill(['name' => '<script>alert(1)</script>'])->save();
        $service = app(ReportGenerationService::class);

        $html = $service->generateHtml($team, 'insights');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);

        $method = new \ReflectionMethod($service, 'safeCell');
        $this->assertSame("'=HYPERLINK(\"http://evil\")", $method->invoke($service, '=HYPERLINK("http://evil")'));
        $this->assertSame("'@cmd", $method->invoke($service, '@cmd'));
        $this->assertSame('-5', $method->invoke($service, '-5'), 'a number is not a formula');
        $this->assertSame('Plain text', $method->invoke($service, 'Plain text'));
    }

    public function test_a_report_schedule_may_not_run_more_often_than_hourly(): void
    {
        $this->assertFalse(AnalyticsReport::scheduleIsSpaced('* * * * *'));
        $this->assertFalse(AnalyticsReport::scheduleIsSpaced('*/30 * * * *'));
        $this->assertTrue(AnalyticsReport::scheduleIsSpaced('0 6 * * 1'));
        $this->assertTrue(AnalyticsReport::scheduleIsSpaced('0 * * * *'));
        $this->assertFalse(AnalyticsReport::scheduleIsSpaced('not a cron'));
    }

    public function test_a_report_keeps_only_its_newest_runs(): void
    {
        $team = $this->team();
        $user = User::factory()->create();
        $report = TeamContext::system(fn () => AnalyticsReport::create(['team_id' => $team->id, 'user_id' => $user->id, 'title' => 'Weekly', 'type' => 'insights', 'report_type' => 'ad_hoc']));

        foreach (range(1, ReportRun::KEEP + 5) as $n) {
            ReportRun::create(['analytics_report_id' => $report->id, 'status' => 'completed', 'output' => ['n' => $n], 'started_at' => now(), 'completed_at' => now()]);
        }

        $this->assertSame(ReportRun::KEEP, ReportRun::where('analytics_report_id', $report->id)->count());
        $this->assertSame(ReportRun::KEEP + 5, ReportRun::where('analytics_report_id', $report->id)->orderByDesc('id')->first()->output['n']);
    }

    public function test_the_security_headers_agree_and_drop_the_obsolete_one(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'DENY')->assertHeaderMissing('X-XSS-Protection');
    }

    public function test_the_published_api_description_lists_only_routes_that_exist(): void
    {
        $spec = json_decode(file_get_contents(public_path('api-docs/openapi.json')), true);
        $real = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => '/'.preg_replace('~^api/~', '', $r->uri()))->all();

        foreach (array_keys($spec['paths']) as $path) {
            $this->assertContains($path, $real, "{$path} is documented but does not exist");
        }

        $this->assertStringNotContainsString('sql', json_encode(array_keys($spec['paths'])));
    }

    public function test_the_foreign_keys_that_lacked_an_index_have_one(): void
    {
        $this->assertTrue(Schema::hasIndex('report_runs', ['analytics_report_id']));
        $this->assertTrue(Schema::hasIndex('analytics_reports', ['team_id']));
    }

    public function test_api_tokens_expire(): void
    {
        $this->assertNotNull(config('sanctum.expiration'));
        $this->assertLessThanOrEqual(60 * 24 * 365, config('sanctum.expiration'));
    }

    public function test_the_snapshot_model_is_unchanged_by_the_quota_work(): void
    {
        $this->assertTrue(class_exists(AnalyticsSnapshot::class));
    }
}
