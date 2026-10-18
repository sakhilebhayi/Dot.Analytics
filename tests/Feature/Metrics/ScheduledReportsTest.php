<?php

namespace Tests\Feature\Metrics;

use App\Livewire\Analytics\SavedReportsPanel;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsReport;
use App\Models\ReportRun;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

class ScheduledReportsTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private User $owner;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->ownerWithTeam();
        $this->team = $this->owner->currentTeam;
    }

    private function report(?string $cron, string $type = 'insights', ?Team $team = null, array $extra = []): AnalyticsReport
    {
        $team ??= $this->team;

        return AnalyticsReport::create(array_merge([
            'team_id' => $team->id,
            'user_id' => $team->user_id,
            'title' => 'Report '.$type.' '.($cron ?? 'adhoc'),
            'type' => $cron ? 'scheduled' : 'ad_hoc',
            'cron_expression' => $cron,
            'config' => ['report_type' => $type],
        ], $extra));
    }

    private function runDispatcher(): void
    {
        $this->artisan('analytics:run-scheduled-reports')->assertSuccessful();
    }

    private function at(string $utc): void
    {
        $this->travelTo(Carbon::parse($utc, 'UTC'));
    }

    public function test_a_report_whose_cron_is_due_is_run_and_recorded(): void
    {
        $this->at('2026-10-19 06:00:20'); // a Monday
        AnalyticsAlert::factory()->create(['team_id' => $this->team->id, 'title' => 'Real alert title']);
        $report = $this->report('0 6 * * 1', 'alerts');

        $this->runDispatcher();

        $run = ReportRun::where('analytics_report_id', $report->id)->sole();
        $this->assertSame('completed', $run->status);
        $this->assertSame('alerts', $run->output['report_type']);
        $this->assertSame(1, $run->output['row_count']);
        $this->assertSame('Real alert title', $run->output['rows'][0][0]);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->completed_at);
    }

    public function test_a_report_that_is_not_due_is_left_alone(): void
    {
        $this->at('2026-10-19 07:00:20');
        $this->report('0 6 * * 1');

        $this->runDispatcher();

        $this->assertSame(0, ReportRun::count());
    }

    public function test_a_report_with_no_schedule_is_never_run_by_the_scheduler(): void
    {
        $this->at('2026-10-19 06:00:20');
        $this->report(null);

        $this->runDispatcher();

        $this->assertSame(0, ReportRun::count());
    }

    public function test_the_same_minute_runs_a_report_once_however_often_the_dispatcher_fires(): void
    {
        $this->at('2026-10-19 06:00:05');
        $report = $this->report('* * * * *');

        $this->runDispatcher();
        $this->travelTo(now()->addSeconds(40));
        $this->runDispatcher();
        $this->assertSame(1, ReportRun::where('analytics_report_id', $report->id)->count());

        $this->travelTo(now()->addSeconds(30)); // next minute
        $this->runDispatcher();
        $this->assertSame(2, ReportRun::where('analytics_report_id', $report->id)->count());
    }

    public function test_the_cron_is_read_in_the_teams_timezone(): void
    {
        $this->team->forceFill(['timezone' => 'Africa/Johannesburg'])->save(); // UTC+2
        $report = $this->report('0 6 * * *');

        $this->at('2026-10-19 06:00:10'); // 08:00 in Johannesburg
        $this->runDispatcher();
        $this->assertSame(0, ReportRun::count(), 'it is 08:00 there, not 06:00');

        $this->at('2026-10-19 04:00:10'); // 06:00 in Johannesburg
        $this->runDispatcher();
        $this->assertSame(1, ReportRun::where('analytics_report_id', $report->id)->count());
    }

    public function test_an_invalid_stored_expression_is_skipped_and_does_not_stop_the_others(): void
    {
        $this->at('2026-10-19 06:00:20');
        $bad = $this->report('not a cron');
        $good = $this->report('* * * * *');

        $this->runDispatcher();

        $this->assertSame(0, ReportRun::where('analytics_report_id', $bad->id)->count());
        $this->assertSame(1, ReportRun::where('analytics_report_id', $good->id)->count());
    }

    public function test_an_unknown_report_type_records_a_failed_run_with_the_reason(): void
    {
        $this->at('2026-10-19 06:00:20');
        $report = $this->report('* * * * *', 'astrology');

        $this->runDispatcher();

        $run = ReportRun::where('analytics_report_id', $report->id)->sole();
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('astrology', $run->output['error']);
    }

    public function test_a_report_created_through_the_api_with_only_a_type_still_runs(): void
    {
        $this->at('2026-10-19 06:00:20');
        $report = $this->report('* * * * *', 'insights', null, ['type' => 'alerts', 'config' => null]);

        $this->runDispatcher();

        $this->assertSame('alerts', ReportRun::where('analytics_report_id', $report->id)->sole()->output['report_type']);
    }

    public function test_each_team_only_gets_its_own_data(): void
    {
        $this->at('2026-10-19 06:00:20');
        $other = $this->ownerWithTeam()->currentTeam;
        AnalyticsAlert::factory()->create(['team_id' => $other->id, 'title' => 'Other team alert']);
        $mine = $this->report('* * * * *', 'alerts');
        $theirs = $this->report('* * * * *', 'alerts', $other);

        $this->runDispatcher();

        $this->assertSame(0, ReportRun::where('analytics_report_id', $mine->id)->sole()->output['row_count']);
        $this->assertSame('Other team alert', ReportRun::where('analytics_report_id', $theirs->id)->sole()->output['rows'][0][0]);
    }

    public function test_the_dispatcher_is_scheduled_every_minute_without_overlap(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'analytics:run-scheduled-reports'));

        $this->assertNotNull($event, 'analytics:run-scheduled-reports is not scheduled');
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    // ─── entering a schedule ─────────────────────────────────────────────────

    public function test_the_api_rejects_an_invalid_cron_expression_and_accepts_a_valid_one(): void
    {
        $this->asApi($this->owner)->postJson('/api/v1/saved-reports', ['title' => 'Bad', 'type' => 'insights', 'cron_expression' => 'whenever'])
            ->assertUnprocessable()->assertJsonValidationErrors('cron_expression');

        $this->asApi($this->owner)->postJson('/api/v1/saved-reports', ['title' => 'Good', 'type' => 'insights', 'cron_expression' => '0 6 * * 1'])
            ->assertCreated();

        $this->assertSame('0 6 * * 1', AnalyticsReport::where('title', 'Good')->value('cron_expression'));
    }

    public function test_the_panel_saves_a_valid_schedule_shows_it_and_rejects_a_bad_one(): void
    {
        Livewire::actingAs($this->owner)->test(SavedReportsPanel::class)
            ->set('title', 'Monday risk')->set('reportType', 'alerts')->set('schedule', 'nonsense')
            ->call('create')->assertHasErrors(['schedule']);
        $this->assertSame(0, AnalyticsReport::count());

        Livewire::actingAs($this->owner)->test(SavedReportsPanel::class)
            ->set('title', 'Monday risk')->set('reportType', 'alerts')->set('schedule', '0 6 * * 1')
            ->call('create')->assertHasNoErrors()
            ->assertSee('Runs on schedule')
            ->assertSee('0 6 * * 1');

        $report = AnalyticsReport::sole();
        $this->assertSame('0 6 * * 1', $report->cron_expression);
        $this->assertSame('scheduled', $report->type);
    }

    public function test_a_report_without_a_schedule_is_saved_as_before(): void
    {
        Livewire::actingAs($this->owner)->test(SavedReportsPanel::class)
            ->set('title', 'Ad hoc')->set('reportType', 'insights')
            ->call('create')->assertHasNoErrors()
            ->assertDontSee('Runs on schedule');

        $report = AnalyticsReport::sole();
        $this->assertNull($report->cron_expression);
        $this->assertSame('ad_hoc', $report->type);
    }

    public function test_a_report_saved_from_the_panel_can_also_be_run_through_the_api(): void
    {
        AnalyticsAlert::factory()->create(['team_id' => $this->team->id, 'title' => 'Alert for api run']);
        $report = $this->report(null, 'alerts');

        $response = $this->asApi($this->owner)->postJson("/api/v1/saved-reports/{$report->id}/run")->assertOk();

        $this->assertSame('alerts', $response->json('data.output.report_type'));
        $this->assertSame(1, $response->json('data.output.row_count'));
    }
}
