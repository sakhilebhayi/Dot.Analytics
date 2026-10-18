<?php

namespace Tests\Feature\Search;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsReport;
use App\Models\CrossPlatformInsight;
use App\Models\DataConnector;
use App\Models\DataSource;
use App\Models\IntelligenceNode;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Scout\Searchable;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * What can be searched is a short, explicit list, and nothing on it can carry a secret.
 */
class SearchIndexSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const SEARCHABLE = [
        AnalyticsAlert::class,
        AnalyticsDashboard::class,
        AnalyticsReport::class,
        CrossPlatformInsight::class,
        IntelligenceNode::class,
        Recommendation::class,
    ];

    /** @return list<class-string<Model>> */
    private function searchableModels(): array
    {
        $found = [];
        foreach ((new Finder)->files()->in(app_path('Models'))->name('*.php') as $file) {
            $class = 'App\\Models\\'.$file->getBasename('.php');
            if (class_exists($class) && in_array(Searchable::class, class_uses_recursive($class), true)) {
                $found[] = $class;
            }
        }
        sort($found);

        return $found;
    }

    public function test_exactly_the_six_content_models_are_searchable(): void
    {
        $expected = self::SEARCHABLE;
        sort($expected);

        $this->assertSame($expected, $this->searchableModels());
    }

    public function test_connection_models_that_hold_secrets_are_not_searchable(): void
    {
        foreach ([DataSource::class, DataConnector::class, User::class] as $class) {
            $this->assertNotContains(Searchable::class, class_uses_recursive($class), $class);
        }
    }

    public function test_no_searchable_column_is_a_secret_or_a_raw_payload(): void
    {
        $forbidden = '/(secret|token|password|key|credential|authorization|config|context|payload|provenance|attributes|supporting|action_url|layout)/i';

        foreach (self::SEARCHABLE as $class) {
            $columns = array_keys((new $class)->toSearchableArray());

            $this->assertNotSame([], $columns, $class);
            foreach ($columns as $column) {
                $this->assertDoesNotMatchRegularExpression($forbidden, $column, "{$class}::{$column}");
            }
        }
    }

    public function test_the_search_columns_are_declared_not_inherited_from_toarray(): void
    {
        foreach (self::SEARCHABLE as $class) {
            $reflection = new \ReflectionMethod($class, 'toSearchableArray');

            $this->assertSame($class, $reflection->getDeclaringClass()->getName(), "{$class} must declare its own search columns");
        }
    }

    public function test_the_database_engine_is_the_default_so_a_missing_env_cannot_pick_another(): void
    {
        $this->assertStringContainsString("env('SCOUT_DRIVER', 'database')", file_get_contents(config_path('scout.php')));
    }

    public function test_nothing_outside_the_search_service_calls_the_raw_scout_search(): void
    {
        $offenders = [];
        foreach ((new Finder)->files()->in(app_path())->name('*.php') as $file) {
            if (str_contains($file->getPathname(), '/Services/Search/') || str_contains($file->getPathname(), '/Models/Concerns/')) {
                continue;
            }
            if (preg_match('/::search\(/', $file->getContents())) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Go through App\Services\Search\GlobalSearch so the team constraint cannot be forgotten.');
    }
}
