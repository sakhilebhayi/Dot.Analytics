<?php

namespace Tests\Feature\Foundations;

use App\Models\AiModelUsage;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsReport;
use App\Models\AnalyticsSnapshot;
use App\Models\AuditLog;
use App\Models\BusinessDnaProfile;
use App\Models\ComputedMetric;
use App\Models\Concerns\HasTeamScope;
use App\Models\CrossPlatformInsight;
use App\Models\DataSource;
use App\Models\ExecutiveBriefing;
use App\Models\IntelligenceEngineRun;
use App\Models\Recommendation;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/** Public pages will sit beside private business data. Reaching it must be impossible, not merely unlikely. */
class PublicCannotReachPrivateTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string}> */
    public static function privateModels(): array
    {
        $models = [AiModelUsage::class, AnalyticsAlert::class, AnalyticsDashboard::class, AnalyticsReport::class, AnalyticsSnapshot::class, AuditLog::class, BusinessDnaProfile::class, ComputedMetric::class, CrossPlatformInsight::class, DataSource::class, ExecutiveBriefing::class, IntelligenceEngineRun::class, Recommendation::class];

        return array_combine(array_map('class_basename', $models), array_map(fn ($m) => [$m], $models));
    }

    public function test_the_list_above_is_every_model_with_the_scope(): void
    {
        $found = [];

        foreach ((new Finder)->files()->in(app_path('Models'))->depth(0)->name('*.php') as $file) {
            $class = 'App\\Models\\'.$file->getBasename('.php');

            if (in_array(HasTeamScope::class, class_uses_recursive($class), true)) {
                $found[] = $class;
            }
        }

        sort($found);
        $listed = array_column(self::privateModels(), 0);
        sort($listed);

        $this->assertSame($listed, $found);
    }

    #[DataProvider('privateModels')]
    public function test_a_visitor_reads_nothing_through_any_private_model(string $model): void
    {
        Route::get('/_probe', fn () => response()->json(['sql' => $model::query()->toSql()]));

        $this->getJson('/_probe')->assertOk()->assertJsonPath('sql', fn ($sql) => str_contains($sql, '1 = 0'));
    }

    #[DataProvider('privateModels')]
    public function test_someone_signed_in_with_no_company_reads_nothing(string $model): void
    {
        Route::get('/_probe', fn () => response()->json(['sql' => $model::query()->toSql()]));
        $user = User::factory()->create(['current_team_id' => null]);

        $this->actingAs($user)->getJson('/_probe')->assertOk()->assertJsonPath('sql', fn ($sql) => str_contains($sql, '1 = 0'));
    }

    public function test_a_company_someone_was_removed_from_reads_nothing_even_if_it_is_still_their_current_one(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $stranger = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam->id;
        $stranger->forceFill(['current_team_id' => $team])->save();
        AnalyticsAlert::factory()->create(['team_id' => $team]);
        Route::get('/_probe', fn () => response()->json(['count' => AnalyticsAlert::count(), 'team' => TeamContext::id()]));

        $this->actingAs($stranger)->getJson('/_probe')->assertJsonPath('count', 0)->assertJsonPath('team', fn ($t) => $t !== $team);
    }

    public function test_a_suspended_company_reads_nothing(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        AnalyticsAlert::factory()->create(['team_id' => $owner->currentTeam->id]);
        Team::whereKey($owner->currentTeam->id)->update(['suspended_at' => now()]);
        Route::get('/_probe', fn () => response()->json(['count' => AnalyticsAlert::count()]));

        $this->actingAs($owner->fresh())->getJson('/_probe')->assertJsonPath('count', 0);
    }

    public function test_the_metrics_api_refuses_a_token_with_no_company(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $this->withToken($user->createToken('x')->plainTextToken)->getJson('/api/v1/metrics')->assertForbidden();
    }

    public function test_scheduled_work_still_sees_every_company(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        AnalyticsAlert::factory()->create(['team_id' => $owner->currentTeam->id]);

        $this->assertSame(1, AnalyticsAlert::count());
    }
}
