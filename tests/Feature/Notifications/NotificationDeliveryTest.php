<?php

namespace Tests\Feature\Notifications;

use App\Events\Analytics\CriticalInsightDiscovered;
use App\Events\Analytics\PlatformConnected;
use App\Jobs\Analytics\GenerateExecutiveBriefingJob;
use App\Jobs\Analytics\RunIntelligenceEngineJob;
use App\Listeners\Analytics\NotifyOnCriticalInsight;
use App\Models\AnalyticsAlert;
use App\Models\CrossPlatformInsight;
use App\Models\DataSource;
use App\Models\ExecutiveBriefing;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Channels\BestEffortBroadcastChannel;
use App\Notifications\CriticalAlertTriggered;
use App\Notifications\ExecutiveBriefingReady;
use App\Services\AiModelRouter;
use App\Services\CrossPlatformIntelligenceService;
use App\Services\IntelligenceEngineService;
use App\Services\Metrics\ComputeMetrics;
use App\Services\Metrics\MetricAnomalyDetector;
use App\Services\Metrics\SnapshotIngestService;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\BuildsTeams;
use Tests\Support\FakeAiRouter;
use Tests\TestCase;

/**
 * Notifications fire only for real, provenance-carrying alerts and briefings, reach
 * every member of exactly that team (viewers included: reading is what they do),
 * and can never fail or slow the work that produced them.
 */
class NotificationDeliveryTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private User $owner;

    private Team $team;

    private User $editor;

    private User $viewer;

    private string $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Connecting a platform queues engine runs; keep fixtures inert.
        Event::fake([PlatformConnected::class]);

        $this->owner = $this->ownerWithTeam();
        $this->team = $this->owner->currentTeam;
        $this->editor = $this->memberOf($this->owner, 'editor');
        $this->viewer = $this->memberOf($this->owner, 'viewer');
        $this->company = $this->linkCompany($this->team);
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(9, 0));
    }

    /** @return list<User> */
    private function members(): array
    {
        return [$this->owner, $this->editor, $this->viewer];
    }

    private function useRouter(FakeAiRouter $router): FakeAiRouter
    {
        $this->app->instance(AiModelRouter::class, $router);
        $this->app->forgetInstance(CrossPlatformIntelligenceService::class);

        return $router;
    }

    private function runEngine(Team $team, string $engine = 'community'): void
    {
        app()->call([new RunIntelligenceEngineJob($team->id, $engine), 'handle']);
    }

    private function insightJson(string $severity): string
    {
        return json_encode([[
            'title' => 'Reviews are collapsing',
            'narrative' => 'Pulse logged 3 reviews against 40 a week ago.',
            'platforms_involved' => ['pulse'],
            'insight_type' => 'risk',
            'confidence' => 0.9,
            'severity' => $severity,
        ]]);
    }

    private function realPulseData(?Team $team = null): void
    {
        $team ??= $this->team;
        $this->snapshot($this->connect($team, 'pulse'), ['reviews' => 3]);
    }

    private function makeBriefing(Team $team, FakeAiRouter $router): void
    {
        (new GenerateExecutiveBriefingJob($team->id, 'weekly', now()->toDateString()))
            ->handle($router, app(IntelligenceEngineService::class));
    }

    /** One 'tasks.overdue' snapshot per value, the last on 2026-10-17. */
    private function overdueSeries(array $values, string $type = 'webhook'): void
    {
        $source = DataSource::factory()->create(['team_id' => $this->team->id, 'platform' => 'tasks', 'status' => 'connected']);
        $count = count($values);
        foreach ($values as $i => $value) {
            $day = now()->utc()->startOfDay()->subDays($count - $i)->format('Y-m-d');
            app(SnapshotIngestService::class)->ingest(
                $source,
                $this->snapshotBody($this->company, 'tasks', ['overdue' => $value], "{$day}T06:00:00Z"),
                $type,
            );
        }
        app(ComputeMetrics::class)->forTeam($this->team);
    }

    // ─── Critical insights from real engine runs ────────────────────────────

    public function test_a_critical_insight_from_real_data_notifies_every_member_of_that_team_only(): void
    {
        $this->realPulseData();
        $outsider = $this->ownerWithTeam();
        $this->useRouter(new FakeAiRouter(response: $this->insightJson('critical')));
        Notification::fake();

        $this->runEngine($this->team);

        $this->assertSame(1, AnalyticsAlert::withoutGlobalScopes()->count());
        foreach ($this->members() as $member) {
            Notification::assertSentTo($member, CriticalAlertTriggered::class, fn ($n) => $n->alert->team_id === $this->team->id);
        }
        Notification::assertNotSentTo($outsider, CriticalAlertTriggered::class);
        Notification::assertSentTimes(CriticalAlertTriggered::class, 3);
    }

    public function test_the_stored_notification_belongs_to_the_team_and_a_viewer_can_read_it(): void
    {
        $this->realPulseData();
        $this->useRouter(new FakeAiRouter(response: $this->insightJson('critical')));

        $this->runEngine($this->team);

        $stored = $this->viewer->fresh()->notifications()->sole();
        $this->assertSame('critical_alert', $stored->data['type']);
        $this->assertSame('Reviews are collapsing', $stored->data['title']);
        $this->assertSame($this->team->id, $stored->data['team_id']);
    }

    public function test_warning_and_info_insights_do_not_notify(): void
    {
        $this->realPulseData();
        $this->useRouter(new FakeAiRouter(response: $this->insightJson('warning')));
        Notification::fake();

        $this->runEngine($this->team);

        $this->assertSame(1, CrossPlatformInsight::count());
        Notification::assertNothingSent();
    }

    public function test_runs_that_found_no_data_or_had_no_provider_notify_nobody(): void
    {
        Notification::fake();

        // Connected but nothing ingested: a no_data run.
        $pulse = $this->connect($this->team, 'pulse');
        $this->useRouter(new FakeAiRouter(response: $this->insightJson('critical')));
        $this->runEngine($this->team);
        $this->assertSame('no_data', $this->team->intelligenceEngineRuns()->latest('id')->first()->status);

        // Data but no AI provider: a skipped run.
        $this->snapshot($pulse, ['reviews' => 3]);
        $this->useRouter(new FakeAiRouter(configured: false));
        $this->runEngine($this->team);
        $this->assertSame('skipped', $this->team->intelligenceEngineRuns()->latest('id')->first()->status);

        $this->assertSame(0, AnalyticsAlert::withoutGlobalScopes()->count());
        Notification::assertNothingSent();
    }

    public function test_a_retried_listener_neither_duplicates_the_alert_nor_the_notifications(): void
    {
        $insight = CrossPlatformInsight::factory()->critical()->create([
            'team_id' => $this->team->id,
            'provenance' => ['snapshot_ids' => [1], 'engine' => 'community'],
        ]);
        Notification::fake();

        $listener = new NotifyOnCriticalInsight;
        $listener->handle(new CriticalInsightDiscovered($insight));
        $listener->handle(new CriticalInsightDiscovered($insight));

        $this->assertSame(1, AnalyticsAlert::withoutGlobalScopes()->count());
        Notification::assertSentTimes(CriticalAlertTriggered::class, 3);
    }

    public function test_an_insight_without_provenance_still_gets_its_alert_but_notifies_nobody(): void
    {
        $insight = CrossPlatformInsight::factory()->critical()->create(['team_id' => $this->team->id, 'provenance' => null]);
        Notification::fake();

        (new NotifyOnCriticalInsight)->handle(new CriticalInsightDiscovered($insight));

        $this->assertSame(1, AnalyticsAlert::withoutGlobalScopes()->count());
        Notification::assertNothingSent();
    }

    // ─── Metric anomaly alerts ──────────────────────────────────────────────

    public function test_a_critical_metric_anomaly_notifies_the_team_once_per_day(): void
    {
        Notification::fake();
        $this->overdueSeries([10, 11, 9, 10, 12, 10, 8, 10, 40]);

        app(MetricAnomalyDetector::class)->detectForTeam($this->team);
        app(MetricAnomalyDetector::class)->detectForTeam($this->team); // the hourly run again

        $alert = AnalyticsAlert::withoutGlobalScopes()->sole();
        $this->assertSame('critical', $alert->severity);
        foreach ($this->members() as $member) {
            Notification::assertSentToTimes($member, CriticalAlertTriggered::class, 1);
        }
    }

    public function test_a_warning_anomaly_does_not_notify(): void
    {
        Notification::fake();
        // 18 against median 10 / MAD 2 is 4 MADs: above the 3.5 threshold, below twice it.
        $this->overdueSeries([10, 12, 8, 14, 6, 10, 12, 8, 18]);

        app(MetricAnomalyDetector::class)->detectForTeam($this->team);

        $this->assertSame('warning', AnalyticsAlert::withoutGlobalScopes()->sole()->severity);
        Notification::assertNothingSent();
    }

    public function test_an_anomaly_built_from_demo_data_does_not_notify(): void
    {
        Notification::fake();
        $this->overdueSeries([10, 11, 9, 10, 12, 10, 8, 10, 40], SnapshotIngestService::TYPE_DEMO);

        app(MetricAnomalyDetector::class)->detectForTeam($this->team);

        $alert = AnalyticsAlert::withoutGlobalScopes()->sole();
        $this->assertTrue($alert->context['is_demo']);
        Notification::assertNothingSent();
    }

    // ─── Executive briefings ────────────────────────────────────────────────

    public function test_a_ready_briefing_notifies_every_member_of_that_team_only(): void
    {
        $outsider = $this->ownerWithTeam();
        CrossPlatformInsight::factory()->create(['team_id' => $this->team->id]);
        Notification::fake();

        $this->makeBriefing($this->team, new FakeAiRouter(response: json_encode(['summary' => 'Reviews are trending down.'])));

        $this->assertSame('ready', ExecutiveBriefing::withoutGlobalScopes()->sole()->status);
        foreach ($this->members() as $member) {
            Notification::assertSentTo($member, ExecutiveBriefingReady::class, fn ($n) => $n->briefing->team_id === $this->team->id);
        }
        Notification::assertNotSentTo($outsider, ExecutiveBriefingReady::class);
        Notification::assertSentTimes(ExecutiveBriefingReady::class, 3);
    }

    public function test_an_unavailable_briefing_notifies_nobody(): void
    {
        Notification::fake();

        // Nothing to summarise.
        $this->makeBriefing($this->team, new FakeAiRouter(response: json_encode(['summary' => 'made up'])));
        // Insights but no provider.
        CrossPlatformInsight::factory()->create(['team_id' => $this->team->id]);
        (new GenerateExecutiveBriefingJob($this->team->id, 'daily', now()->toDateString()))
            ->handle(new FakeAiRouter(configured: false), app(IntelligenceEngineService::class));
        // Unparseable output.
        (new GenerateExecutiveBriefingJob($this->team->id, 'monthly', now()->toDateString()))
            ->handle(new FakeAiRouter(response: 'not json'), app(IntelligenceEngineService::class));

        $this->assertSame(3, ExecutiveBriefing::withoutGlobalScopes()->where('status', 'unavailable')->count());
        Notification::assertNothingSent();
    }

    public function test_re_running_a_finished_briefing_does_not_notify_again(): void
    {
        CrossPlatformInsight::factory()->create(['team_id' => $this->team->id]);
        $router = new FakeAiRouter(response: json_encode(['summary' => 'Once.']));
        Notification::fake();

        $this->makeBriefing($this->team, $router);
        $this->makeBriefing($this->team, $router);

        Notification::assertSentTimes(ExecutiveBriefingReady::class, 3);
    }

    // ─── Delivery guarantees ────────────────────────────────────────────────

    public function test_each_channel_is_queued_as_its_own_job_so_a_dead_mail_transport_cannot_hold_back_the_bell(): void
    {
        Queue::fake();
        $alert = AnalyticsAlert::factory()->create(['team_id' => $this->team->id, 'severity' => 'critical']);

        $this->owner->notify(new CriticalAlertTriggered($alert));

        $channels = Queue::pushed(SendQueuedNotifications::class)
            ->map(fn (SendQueuedNotifications $job) => $job->channels)
            ->all();
        $this->assertSame([['database'], [BestEffortBroadcastChannel::class], ['mail']], $channels);
    }

    public function test_it_survives_a_real_database_queue_round_trip_and_a_worker_delivers_every_channel(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'array']);
        $alert = AnalyticsAlert::factory()->create(['team_id' => $this->team->id, 'severity' => 'critical', 'title' => 'Queued for a worker']);

        $this->owner->notify(new CriticalAlertTriggered($alert));

        $this->assertSame(3, DB::table('jobs')->count(), 'database, real-time and mail are separate jobs');
        $this->assertSame(0, $this->owner->notifications()->count(), 'nothing is sent inside the request');

        $this->artisan('queue:work', ['--stop-when-empty' => true, '--queue' => 'default'])->assertSuccessful();

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame('Queued for a worker', $this->owner->notifications()->sole()->data['title']);
    }

    public function test_the_mail_is_sent_through_the_configured_mailer_without_context_or_secrets(): void
    {
        config(['mail.default' => 'array']);
        $this->realPulseData();
        $this->useRouter(new FakeAiRouter(response: $this->insightJson('critical')));

        $this->runEngine($this->team);

        // MAIL_MAILER=array: one message per member, in the array transport.
        $sent = app('mailer')->getSymfonyTransport();
        $this->assertCount(3, $sent->messages());
        $body = $sent->messages()->map(fn ($m) => $m->getOriginalMessage()->toString())->implode("\n");
        $this->assertStringContainsString('Reviews are collapsing', $body);
        $this->assertStringNotContainsString('snapshot_ids', $body);
        $this->assertStringNotContainsString('"payload"', $body);
    }

    public function test_a_missing_mailer_still_delivers_the_in_app_copy_without_an_exception(): void
    {
        config(['mail.default' => null]);
        $this->realPulseData();
        $this->useRouter(new FakeAiRouter(response: $this->insightJson('critical')));

        $this->runEngine($this->team);

        foreach ($this->members() as $member) {
            $this->assertSame(1, $member->fresh()->notifications()->count());
        }
    }

    public function test_a_failing_mail_transport_never_fails_the_job_that_raised_the_alert(): void
    {
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('smtp down'));
        $this->realPulseData();
        $this->useRouter(new FakeAiRouter(response: $this->insightJson('critical')));

        $this->runEngine($this->team);

        $this->assertSame(1, AnalyticsAlert::withoutGlobalScopes()->count());
        $this->assertSame('completed', $this->team->intelligenceEngineRuns()->latest('id')->first()->status);
        foreach ($this->members() as $member) {
            $this->assertSame(1, $member->fresh()->notifications()->count(), 'every member still gets the in-app copy');
        }
    }

    public function test_realtime_delivery_degrades_silently_when_the_websocket_server_is_down(): void
    {
        Broadcast::extend('unreachable', fn () => new class extends Broadcaster
        {
            public function auth($request) {}

            public function validAuthenticationResponse($request, $result) {}

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                throw new BroadcastException('cURL error 7: Failed to connect to localhost port 8080');
            }
        });
        config([
            'broadcasting.default' => 'down',
            'broadcasting.connections.down' => ['driver' => 'unreachable'],
        ]);
        $this->realPulseData();
        $this->useRouter(new FakeAiRouter(response: $this->insightJson('critical')));

        $this->runEngine($this->team);

        $this->assertSame('completed', $this->team->intelligenceEngineRuns()->latest('id')->first()->status);
        foreach ($this->members() as $member) {
            $this->assertSame(1, $member->fresh()->notifications()->count());
        }
    }

    public function test_a_dead_broadcaster_is_logged_by_class_only_never_with_its_message_or_url(): void
    {
        Log::spy();
        Broadcast::extend('unreachable', fn () => new class extends Broadcaster
        {
            public function auth($request) {}

            public function validAuthenticationResponse($request, $result) {}

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                throw new BroadcastException('cURL error 7 for http://localhost:8080/apps/1/events?auth_key=PUBKEY&auth_signature=SIG');
            }
        });
        config([
            'broadcasting.default' => 'down',
            'broadcasting.connections.down' => ['driver' => 'unreachable'],
        ]);
        $alert = AnalyticsAlert::factory()->create(['team_id' => $this->team->id, 'severity' => 'critical']);

        $this->owner->notify(new CriticalAlertTriggered($alert));

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context = []) {
            $logged = $message.json_encode($context);

            return str_contains($logged, 'BroadcastException')
                && ! str_contains($logged, 'auth_key')
                && ! str_contains($logged, 'SIG');
        })->once();
        $this->assertSame(1, $this->owner->fresh()->notifications()->count());
    }
}
