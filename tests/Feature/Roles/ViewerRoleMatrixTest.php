<?php

namespace Tests\Feature\Roles;

use App\Livewire\Analytics\AlertsPanel;
use App\Livewire\Analytics\BusinessDnaPanel;
use App\Livewire\Analytics\CrossPlatformInsightPanel;
use App\Livewire\Analytics\DashboardBuilderPanel;
use App\Livewire\Analytics\EcosystemMapPanel;
use App\Livewire\Analytics\ExecutiveBriefingPanel;
use App\Livewire\Analytics\FeatureFlagsPanel;
use App\Livewire\Analytics\IntelligenceDashboard;
use App\Livewire\Analytics\RecommendationsPanel;
use App\Livewire\Analytics\SavedReportsPanel;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsReport;
use App\Models\CrossPlatformInsight;
use App\Models\DashboardWidget;
use App\Models\DataConnector;
use App\Models\DataSource;
use App\Models\FeatureFlag;
use App\Models\Recommendation;
use App\Models\ReportRun;
use App\Models\User;
use App\Services\AiModelRouter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Jetstream\Jetstream;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\BuildsTeams;
use Tests\Support\FakeAiRouter;
use Tests\TestCase;

/**
 * Role by action, with the read-only "viewer" role InfoDot's viewers now get.
 *
 *   owner, admin  manage the intelligence layer (platforms, engines, briefings,
 *                 SQL, recommendations, alerts, reports)
 *   editor        works inside it: runs saved reports, edits dashboards, asks the
 *                 Intelligence box; never mutates what the whole team relies on
 *   viewer        reads. Nothing that writes, and nothing that spends AI budget
 *   outsider      not on the team (their own team is theirs, never this one)
 *
 * Nobody below owner/admin ever sees a webhook secret, and no team role grants
 * the platform-operator feature-flag powers.
 */
class ViewerRoleMatrixTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private const ROLES = ['owner', 'admin', 'editor', 'viewer', 'outsider'];

    private const SECRET_NOTE = 'wh-secret-value';

    private User $owner;

    /** @var array<string, User> */
    private array $users = [];

    private FakeAiRouter $ai;

    private string $secret = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->ownerWithTeam();
        $this->users = [
            'owner' => $this->owner,
            'admin' => $this->memberOf($this->owner, 'admin'),
            'editor' => $this->memberOf($this->owner, 'editor'),
            'viewer' => $this->memberOf($this->owner, 'viewer'),
            'outsider' => $this->ownerWithTeam(),
        ];

        $this->ai = new FakeAiRouter(true, '[]');
        $this->app->instance(AiModelRouter::class, $this->ai);
    }

    private function team()
    {
        return $this->owner->currentTeam;
    }

    /** @return array<string, mixed> */
    private function fresh(): array
    {
        foreach ([DashboardWidget::class, AnalyticsDashboard::class, ReportRun::class, AnalyticsReport::class, Recommendation::class, AnalyticsAlert::class, CrossPlatformInsight::class, DataConnector::class, DataSource::class, FeatureFlag::class] as $model) {
            $model::withoutGlobalScopes()->delete();
        }

        $source = $this->connect($this->team(), 'tasks');
        $source->forceFill(['display_name' => 'Matrix Fleet Display'])->save();
        $this->secret = $source->rotateWebhookSecret();

        $report = AnalyticsReport::create([
            'team_id' => $this->team()->id, 'user_id' => $this->owner->id, 'title' => 'Matrix Report',
            'type' => 'ad_hoc', 'config' => ['report_type' => 'insights'],
        ]);
        $dashboard = AnalyticsDashboard::create(['team_id' => $this->team()->id, 'user_id' => $this->owner->id, 'title' => 'Board Matrix', 'is_default' => true]);
        $widget = DashboardWidget::create(['analytics_dashboard_id' => $dashboard->id, 'widget_type' => 'chart', 'title' => 'W', 'col' => 0, 'row' => 0, 'width' => 4, 'height' => 2, 'config' => []]);

        return [
            'source' => $source,
            'report' => $report,
            'dashboard' => $dashboard,
            'widget' => $widget,
            'alert' => AnalyticsAlert::factory()->create(['team_id' => $this->team()->id, 'status' => 'open', 'title' => 'Matrix Alert Title']),
            'rec' => Recommendation::factory()->create(['team_id' => $this->team()->id, 'status' => 'pending', 'title' => 'Matrix Recommendation Title']),
            'insight' => CrossPlatformInsight::factory()->create(['team_id' => $this->team()->id, 'status' => 'new']),
        ];
    }

    public function test_jetstream_has_a_read_only_viewer_role(): void
    {
        $viewer = Jetstream::findRole('viewer');

        $this->assertNotNull($viewer);
        $this->assertSame(['read'], $viewer->permissions);
        $this->assertSame(['admin', 'editor', 'viewer'], array_keys(Jetstream::$roles));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function gates(): array
    {
        $managers = ['owner', 'admin'];
        $contributors = ['owner', 'admin', 'editor'];
        $members = ['owner', 'admin', 'editor', 'viewer'];

        return [
            'manage-platforms' => ['manage-platforms', $managers],
            'run-intelligence-engines' => ['run-intelligence-engines', $managers],
            'generate-briefing' => ['generate-briefing', $managers],
            'manage-connectors' => ['manage-connectors', $managers],
            'execute-sql-query' => ['execute-sql-query', $managers],
            'view-audit-logs' => ['view-audit-logs', $managers],
            'manage-recommendations' => ['manage-recommendations', $managers],
            'manage-alerts' => ['manage-alerts', $managers],
            'manage-reports' => ['manage-reports', $managers],
            'manage-feature-flags' => ['manage-feature-flags', []],
            'view-intelligence' => ['view-intelligence', $members],
            'ask-intelligence' => ['ask-intelligence', $contributors],
            'edit-dashboards' => ['edit-dashboards', $contributors],
            'run-reports' => ['run-reports', $contributors],
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    #[DataProvider('gates')]
    public function test_gate_matrix(string $gate, array $allowed): void
    {
        foreach (self::ROLES as $role) {
            $user = $this->users[$role];
            if ($role === 'outsider') {
                // Even with this team forced as their active team, a non-member gets nothing.
                $user->forceFill(['current_team_id' => $this->team()->id])->save();
                $user = $user->fresh();
            }

            $this->assertSame(in_array($role, $allowed, true), Gate::forUser($user)->allows($gate), "{$gate} by {$role}");
        }
    }

    // ─── API ─────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{string}>
     */
    public static function apiWrites(): array
    {
        return array_map(fn ($k) => [$k], [
            'run engines', 'connect platform', 'disconnect platform', 'rotate webhook secret',
            'sql generate', 'sql query', 'create saved report', 'delete saved report', 'run saved report',
            'create feature flag', 'enable feature flag',
        ]);
    }

    /** @return array<string, list<string>> */
    private static function apiAllowed(): array
    {
        $managers = ['owner', 'admin'];

        return [
            'run engines' => $managers, 'connect platform' => $managers, 'disconnect platform' => $managers,
            'rotate webhook secret' => $managers, 'sql generate' => $managers, 'sql query' => $managers,
            'create saved report' => $managers, 'delete saved report' => $managers,
            'run saved report' => ['owner', 'admin', 'editor'],
            'create feature flag' => [], 'enable feature flag' => [],
        ];
    }

    #[DataProvider('apiWrites')]
    public function test_api_write_matrix(string $action): void
    {
        $allowed = self::apiAllowed()[$action];

        foreach (self::ROLES as $role) {
            $r = $this->fresh();
            Queue::fake();
            $this->ai->prompts = [];
            $connector = DataConnector::create([
                'team_id' => $this->team()->id, 'name' => 'DB', 'type' => 'database', 'driver' => 'sqlite',
                'config' => ['driver' => 'sqlite', 'database' => ':memory:'], 'status' => 'active',
            ]);
            $sql = ['question' => 'Show me every user', 'connector_id' => $connector->id, 'schema' => ['tables' => [['name' => 'users']]]];
            $flag = FeatureFlag::create(['key' => 'matrix.flag', 'name' => 'Matrix', 'is_enabled' => false, 'rollout_percentage' => 0]);

            $api = $this->asApi($this->users[$role]);
            $response = match ($action) {
                'run engines' => $api->postJson('/api/v1/intelligence/run'),
                'connect platform' => $api->postJson('/api/v1/platforms/emall/connect'),
                'disconnect platform' => $api->deleteJson('/api/v1/platforms/tasks'),
                'rotate webhook secret' => $api->postJson('/api/v1/platforms/tasks/webhook-secret'),
                'sql generate' => $api->postJson('/api/v1/sql/generate', $sql),
                'sql query' => $api->postJson('/api/v1/sql/query', $sql),
                'create saved report' => $api->postJson('/api/v1/saved-reports', ['title' => 'Sneaky', 'type' => 'insights']),
                'delete saved report' => $api->deleteJson("/api/v1/saved-reports/{$r['report']->id}"),
                'run saved report' => $api->postJson("/api/v1/saved-reports/{$r['report']->id}/run"),
                'create feature flag' => $api->postJson('/api/v1/feature-flags', ['key' => 'sneaky.flag', 'name' => 'Sneaky']),
                'enable feature flag' => $api->patchJson('/api/v1/feature-flags/matrix.flag/enable'),
            };

            $effect = match ($action) {
                'run engines' => count(Queue::pushedJobs()) > 0,
                'connect platform' => DataSource::withoutGlobalScopes()->where('team_id', $this->team()->id)->where('platform', 'emall')->exists(),
                'disconnect platform' => ! DataSource::withoutGlobalScopes()->where('team_id', $this->team()->id)->where('platform', 'tasks')->exists(),
                'rotate webhook secret' => $r['source']->fresh()->webhook_secret !== $this->secret,
                'sql generate', 'sql query' => $this->ai->prompts !== [],
                'create saved report' => AnalyticsReport::withoutGlobalScopes()->where('title', 'Sneaky')->exists(),
                'delete saved report' => ! AnalyticsReport::withoutGlobalScopes()->whereKey($r['report']->id)->exists(),
                'run saved report' => ReportRun::where('analytics_report_id', $r['report']->id)->exists(),
                'create feature flag' => FeatureFlag::where('key', 'sneaky.flag')->exists(),
                'enable feature flag' => FeatureFlag::where('key', 'matrix.flag')->value('is_enabled') == true,
            };

            if ($role === 'outsider') {
                // They act inside their own team; nothing of THIS team may move.
                $this->assertFalse($this->targetMoved($action, $r), "outsider {$action} touched the target team");

                continue;
            }

            if (in_array($role, $allowed, true)) {
                $this->assertNotContains($response->getStatusCode(), [401, 403], "{$role} was refused {$action}");
            } else {
                $response->assertForbidden();
                $this->assertFalse($effect, "{$role} {$action} must have no effect");
            }
        }
    }

    /** @param array<string, mixed> $r */
    private function targetMoved(string $action, array $r): bool
    {
        $team = $this->team()->id;

        return match ($action) {
            'connect platform' => DataSource::withoutGlobalScopes()->where('team_id', $team)->where('platform', 'emall')->exists(),
            'disconnect platform' => ! DataSource::withoutGlobalScopes()->where('team_id', $team)->where('platform', 'tasks')->exists(),
            'rotate webhook secret' => $r['source']->fresh()->webhook_secret !== $this->secret,
            'create saved report' => AnalyticsReport::withoutGlobalScopes()->where('team_id', $team)->where('title', 'Sneaky')->exists(),
            'delete saved report' => ! AnalyticsReport::withoutGlobalScopes()->whereKey($r['report']->id)->exists(),
            'run saved report' => ReportRun::where('analytics_report_id', $r['report']->id)->exists(),
            'create feature flag' => FeatureFlag::where('key', 'sneaky.flag')->exists(),
            'enable feature flag' => FeatureFlag::where('key', 'matrix.flag')->value('is_enabled') == true,
            default => false,
        };
    }

    /** @return array<string, array{string}> */
    public static function apiReads(): array
    {
        return array_map(fn ($k) => [$k], [
            '/api/v1/intelligence/engines', '/api/v1/intelligence/insights', '/api/v1/intelligence/graph',
            '/api/v1/platforms', '/api/v1/platforms/connected', '/api/v1/platforms/tasks',
            '/api/v1/metrics/definitions', '/api/v1/metrics/ai-usage', '/api/v1/saved-reports',
            '/api/v1/saved-reports/{report}/runs', '/api/v1/reports/insights', '/api/v1/reports/insights/csv',
            '/api/v1/reports/insights/html', '/api/v1/feature-flags',
        ]);
    }

    #[DataProvider('apiReads')]
    public function test_every_member_reads_the_same_api_and_nothing_secret_leaves(string $path): void
    {
        $r = $this->fresh();
        $path = str_replace('{report}', (string) $r['report']->id, $path);

        $bodies = [];
        foreach (['owner', 'admin', 'editor', 'viewer'] as $role) {
            $response = $this->asApi($this->users[$role])->get($path, ['Accept' => 'application/json']);
            $response->assertOk();
            $body = $response instanceof TestResponse && $response->baseResponse instanceof StreamedResponse
                ? $response->streamedContent()
                : $response->getContent();

            $this->assertStringNotContainsString($this->secret, $body, "{$role} {$path} leaks the webhook secret");
            $this->assertStringNotContainsString('webhook_secret', $body, "{$role} {$path} mentions webhook_secret");
            $bodies[$role] = $body;
        }

        // A viewer sees no more than an editor, byte for byte.
        $this->assertSame($bodies['editor'], $bodies['viewer'], "{$path}: viewer and editor must see the same");

        $outsider = $this->asApi($this->users['outsider'])->get($path, ['Accept' => 'application/json']);
        $content = $outsider->baseResponse instanceof StreamedResponse ? $outsider->streamedContent() : $outsider->getContent();
        foreach (['Matrix Fleet Display', 'Matrix Report', $this->secret] as $teamData) {
            $this->assertStringNotContainsString($teamData, $content, "{$path}: an outsider must not see the team's data");
        }
    }

    // ─── Livewire ────────────────────────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function livewireActions(): array
    {
        return array_map(fn ($k) => [$k], [
            'run engines', 'review insight', 'dismiss insight', 'generate briefing', 'start connect', 'confirm connect',
            'disconnect', 'acknowledge alert', 'resolve alert', 'generate recommendations', 'action recommendation',
            'dismiss recommendation', 'compute dna', 'create report', 'delete report', 'run report',
            'create dashboard', 'set default dashboard', 'delete dashboard', 'add widget', 'remove widget', 'ask intelligence',
            'create flag', 'toggle flag',
        ]);
    }

    /** @return array<string, list<string>> */
    private static function livewireAllowed(): array
    {
        $managers = ['owner', 'admin'];
        $contributors = ['owner', 'admin', 'editor'];

        return [
            'run engines' => $managers, 'review insight' => $managers, 'dismiss insight' => $managers,
            'generate briefing' => $managers, 'start connect' => $managers, 'confirm connect' => $managers,
            'disconnect' => $managers, 'acknowledge alert' => $managers, 'resolve alert' => $managers,
            'generate recommendations' => $managers, 'action recommendation' => $managers,
            'dismiss recommendation' => $managers, 'compute dna' => $managers, 'create report' => $managers,
            'delete report' => $managers, 'run report' => $contributors,
            'create dashboard' => $contributors, 'set default dashboard' => $contributors,
            'delete dashboard' => $contributors, 'add widget' => $contributors, 'remove widget' => $contributors,
            'ask intelligence' => $contributors,
            'create flag' => [], 'toggle flag' => [],
        ];
    }

    #[DataProvider('livewireActions')]
    public function test_livewire_matrix(string $action): void
    {
        $allowed = self::livewireAllowed()[$action];

        foreach (self::ROLES as $role) {
            $r = $this->fresh();
            Queue::fake();
            $this->ai->prompts = [];
            // So that asking has real data to answer from, and spending would be visible.
            $this->snapshot($r['source'], ['orders' => 12, 'note' => 'Matrix snapshot payload']);
            $flag = FeatureFlag::create(['key' => 'matrix.flag', 'name' => 'Matrix', 'is_enabled' => false, 'rollout_percentage' => 0]);

            $user = $this->users[$role];

            try {
                $component = match ($action) {
                    'run engines' => Livewire::actingAs($user)->test(CrossPlatformInsightPanel::class)->call('runEngines'),
                    'review insight' => Livewire::actingAs($user)->test(CrossPlatformInsightPanel::class)->call('review', $r['insight']->id),
                    'dismiss insight' => Livewire::actingAs($user)->test(CrossPlatformInsightPanel::class)->call('dismiss', $r['insight']->id),
                    'generate briefing' => Livewire::actingAs($user)->test(ExecutiveBriefingPanel::class)->call('generate'),
                    'start connect' => Livewire::actingAs($user)->test(EcosystemMapPanel::class)->call('startConnect', 'emall'),
                    'confirm connect' => Livewire::actingAs($user)->test(EcosystemMapPanel::class)->set('connectingPlatform', 'emall')->call('confirmConnect'),
                    'disconnect' => Livewire::actingAs($user)->test(EcosystemMapPanel::class)->call('disconnect', 'tasks'),
                    'acknowledge alert' => Livewire::actingAs($user)->test(AlertsPanel::class)->call('acknowledge', $r['alert']->id),
                    'resolve alert' => Livewire::actingAs($user)->test(AlertsPanel::class)->call('resolve', $r['alert']->id),
                    'generate recommendations' => Livewire::actingAs($user)->test(RecommendationsPanel::class)->call('generate'),
                    'action recommendation' => Livewire::actingAs($user)->test(RecommendationsPanel::class)->call('action', $r['rec']->id),
                    'dismiss recommendation' => Livewire::actingAs($user)->test(RecommendationsPanel::class)->call('dismiss', $r['rec']->id),
                    'compute dna' => Livewire::actingAs($user)->test(BusinessDnaPanel::class)->call('compute'),
                    'create report' => Livewire::actingAs($user)->test(SavedReportsPanel::class)->set('title', 'Sneaky Report')->set('reportType', 'insights')->call('create'),
                    'delete report' => Livewire::actingAs($user)->test(SavedReportsPanel::class)->call('delete', $r['report']->id),
                    'run report' => Livewire::actingAs($user)->test(SavedReportsPanel::class)->call('run', $r['report']->id),
                    'create dashboard' => Livewire::actingAs($user)->test(DashboardBuilderPanel::class)->set('newDashboardTitle', 'Sneaky Board')->call('createDashboard'),
                    'set default dashboard' => Livewire::actingAs($user)->test(DashboardBuilderPanel::class)->call('setDefault', $r['dashboard']->id),
                    'delete dashboard' => Livewire::actingAs($user)->test(DashboardBuilderPanel::class)->call('deleteDashboard', $r['dashboard']->id),
                    'add widget' => Livewire::actingAs($user)->test(DashboardBuilderPanel::class)->set('activeDashboardId', $r['dashboard']->id)->set('widgetType', 'kpi')->set('widgetMetric', 'tasks.open')->call('addWidget'),
                    'remove widget' => Livewire::actingAs($user)->test(DashboardBuilderPanel::class)->call('removeWidget', $r['widget']->id),
                    'ask intelligence' => Livewire::actingAs($user)->test(IntelligenceDashboard::class)->set('intelligenceQuery', 'Why are orders down this month?')->call('askIntelligence'),
                    'create flag' => Livewire::actingAs($user)->test(FeatureFlagsPanel::class)->set('newKey', 'sneaky.flag')->set('newName', 'Sneaky')->call('create'),
                    'toggle flag' => Livewire::actingAs($user)->test(FeatureFlagsPanel::class)->call('toggle', $flag->id),
                };
            } catch (ModelNotFoundException|NotFoundHttpException) {
                // An outsider's active team is their own: the target's ids simply do not exist for them.
                $this->assertSame('outsider', $role, "{$role} got a 404 for {$action}");

                continue;
            }

            $team = $this->team()->id;
            $moved = match ($action) {
                'review insight' => $r['insight']->fresh()->status !== 'new',
                'dismiss insight' => $r['insight']->fresh()->status === 'dismissed',
                'confirm connect' => DataSource::withoutGlobalScopes()->where('team_id', $team)->where('platform', 'emall')->exists(),
                'disconnect' => ! DataSource::withoutGlobalScopes()->where('team_id', $team)->where('platform', 'tasks')->exists(),
                'acknowledge alert' => $r['alert']->fresh()->status !== 'open',
                'resolve alert' => $r['alert']->fresh()->status === 'resolved',
                'action recommendation' => $r['rec']->fresh()->status === 'actioned',
                'dismiss recommendation' => $r['rec']->fresh()->status === 'dismissed',
                'create report' => AnalyticsReport::withoutGlobalScopes()->where('team_id', $team)->where('title', 'Sneaky Report')->exists(),
                'delete report' => ! AnalyticsReport::withoutGlobalScopes()->whereKey($r['report']->id)->exists(),
                'run report' => ReportRun::where('analytics_report_id', $r['report']->id)->exists(),
                'create dashboard' => AnalyticsDashboard::withoutGlobalScopes()->where('team_id', $team)->where('title', 'Sneaky Board')->exists(),
                'delete dashboard' => ! AnalyticsDashboard::withoutGlobalScopes()->whereKey($r['dashboard']->id)->exists(),
                'add widget' => DashboardWidget::where('analytics_dashboard_id', $r['dashboard']->id)->count() > 1,
                'remove widget' => ! DashboardWidget::whereKey($r['widget']->id)->exists(),
                'set default dashboard' => false,
                'ask intelligence' => $this->ai->prompts !== [],
                'create flag' => FeatureFlag::where('key', 'sneaky.flag')->exists(),
                'toggle flag' => $flag->fresh()->is_enabled == true,
                default => false, // spends AI/queue budget; checked by status below
            };

            if ($role === 'outsider') {
                // Anything an outsider can do, they did to their own team: the target must not move.
                if (! in_array($action, ['ask intelligence'], true)) {
                    $this->assertFalse($moved, "outsider {$action} touched the target team");
                }

                continue;
            }

            if (in_array($role, $allowed, true)) {
                $component->assertOk();
            } else {
                $component->assertForbidden();
                $this->assertFalse($moved, "{$role} {$action} must change nothing");
                $this->assertSame([], $this->ai->prompts, "{$role} {$action} must not spend AI");
                $this->assertSame([], Queue::pushedJobs(), "{$role} {$action} must queue nothing");
            }
        }
    }

    public function test_a_viewer_who_asks_the_intelligence_box_costs_nothing_but_an_editor_may(): void
    {
        $r = $this->fresh();
        $this->snapshot($r['source'], ['orders' => 12, 'note' => 'Matrix snapshot payload']);

        Livewire::actingAs($this->users['viewer'])->test(IntelligenceDashboard::class)
            ->set('intelligenceQuery', 'Why are orders down this month?')->call('askIntelligence')->assertForbidden();
        $this->assertSame([], $this->ai->prompts);

        Livewire::actingAs($this->users['editor'])->test(IntelligenceDashboard::class)
            ->set('intelligenceQuery', 'Why are orders down this month?')->call('askIntelligence')->assertOk();
        $this->assertNotSame([], $this->ai->prompts);
    }

    // ─── What a viewer sees ──────────────────────────────────────────────────

    public function test_no_page_or_panel_a_viewer_loads_leaks_the_webhook_secret(): void
    {
        $this->fresh();

        foreach (['viewer', 'editor'] as $role) {
            $page = $this->actingAs($this->users[$role])->get(route('dashboard'))->assertOk()->getContent();
            $this->assertStringNotContainsString($this->secret, $page, "{$role} dashboard");

            foreach ([EcosystemMapPanel::class, AlertsPanel::class, RecommendationsPanel::class, CrossPlatformInsightPanel::class, SavedReportsPanel::class, DashboardBuilderPanel::class, IntelligenceDashboard::class, ExecutiveBriefingPanel::class, BusinessDnaPanel::class] as $class) {
                $component = Livewire::actingAs($this->users[$role])->test($class);
                $payload = json_encode($component->snapshot).$component->html();
                $this->assertStringNotContainsString($this->secret, $payload, "{$role} {$class}");
            }
        }

        // And the models never serialise it.
        $source = DataSource::withoutGlobalScopes()->first();
        $this->assertArrayNotHasKey('webhook_secret', $source->toArray());
        $this->assertStringNotContainsString($this->secret, $source->toJson());
    }

    public function test_a_viewer_reads_what_an_editor_reads(): void
    {
        $this->fresh();

        foreach (['viewer', 'editor'] as $role) {
            Livewire::actingAs($this->users[$role])->test(AlertsPanel::class)->assertSee('Matrix Alert Title');
            Livewire::actingAs($this->users[$role])->test(RecommendationsPanel::class)->assertSee('Matrix Recommendation Title');
            Livewire::actingAs($this->users[$role])->test(SavedReportsPanel::class)->assertSee('Matrix Report');
        }
    }

    public function test_a_viewer_is_shown_no_control_that_would_answer_403(): void
    {
        $this->fresh();
        $viewer = $this->users['viewer'];

        Livewire::actingAs($viewer)->test(AlertsPanel::class)
            ->assertDontSeeHtml('wire:click="acknowledge(')->assertDontSeeHtml('wire:click="resolve(');
        Livewire::actingAs($viewer)->test(RecommendationsPanel::class)
            ->assertDontSeeHtml('wire:click="action(')->assertDontSeeHtml('wire:click="dismiss(')->assertDontSeeHtml('wire:click="generate"');
        Livewire::actingAs($viewer)->test(CrossPlatformInsightPanel::class)
            ->assertDontSeeHtml('wire:click="review(')->assertDontSeeHtml('wire:click="runEngines"');
        Livewire::actingAs($viewer)->test(EcosystemMapPanel::class)
            ->assertDontSeeHtml('wire:click="startConnect(')->assertDontSeeHtml('wire:click="disconnect(');
        Livewire::actingAs($viewer)->test(SavedReportsPanel::class)
            ->assertSee('Matrix Report')
            ->assertDontSeeHtml('wire:click="run(')->assertDontSeeHtml('wire:click="delete(')->assertDontSeeHtml('wire:click="$toggle(\'showCreate\')"');
        Livewire::actingAs($viewer)->test(DashboardBuilderPanel::class)
            ->assertSee('Board Matrix')
            ->assertDontSeeHtml('wire:click="$toggle(\'showNewDashboard\')"')->assertDontSeeHtml('wire:click="$toggle(\'showAddWidget\')"')
            ->assertDontSeeHtml('wire:click="deleteDashboard(')->assertDontSeeHtml('wire:click="removeWidget(');
        Livewire::actingAs($viewer)->test(IntelligenceDashboard::class)
            ->assertDontSeeHtml('wire:submit="askIntelligence"');
        Livewire::actingAs($viewer)->test(ExecutiveBriefingPanel::class)->assertDontSeeHtml('wire:click="generate"');
        Livewire::actingAs($viewer)->test(BusinessDnaPanel::class)->assertDontSeeHtml('wire:click="compute"');
        Livewire::actingAs($viewer)->test(FeatureFlagsPanel::class)->assertDontSeeHtml('wire:submit="create"');
    }

    public function test_the_same_panels_do_show_the_controls_to_an_admin_and_an_editor_gets_only_its_own(): void
    {
        $this->fresh();

        Livewire::actingAs($this->users['admin'])->test(AlertsPanel::class)->assertSeeHtml('wire:click="resolve(');
        Livewire::actingAs($this->users['admin'])->test(RecommendationsPanel::class)->assertSeeHtml('wire:click="action(');
        Livewire::actingAs($this->users['admin'])->test(SavedReportsPanel::class)->assertSeeHtml('wire:click="run(')->assertSeeHtml('wire:click="delete(');
        Livewire::actingAs($this->users['admin'])->test(DashboardBuilderPanel::class)->assertSeeHtml('wire:click="$toggle(\'showNewDashboard\')"');
        Livewire::actingAs($this->users['admin'])->test(IntelligenceDashboard::class)->assertSeeHtml('wire:submit="askIntelligence"');

        // An editor works (runs reports, edits dashboards, asks) but cannot manage.
        Livewire::actingAs($this->users['editor'])->test(SavedReportsPanel::class)
            ->assertSeeHtml('wire:click="run(')->assertDontSeeHtml('wire:click="delete(');
        Livewire::actingAs($this->users['editor'])->test(DashboardBuilderPanel::class)->assertSeeHtml('wire:click="$toggle(\'showNewDashboard\')"');
        Livewire::actingAs($this->users['editor'])->test(IntelligenceDashboard::class)->assertSeeHtml('wire:submit="askIntelligence"');
        Livewire::actingAs($this->users['editor'])->test(AlertsPanel::class)->assertDontSeeHtml('wire:click="resolve(');
        Livewire::actingAs($this->users['editor'])->test(RecommendationsPanel::class)->assertDontSeeHtml('wire:click="action(');
    }
}
