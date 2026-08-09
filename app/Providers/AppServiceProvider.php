<?php

namespace App\Providers;

use App\Models\DataSource;
use App\Models\User;
use App\Observers\DataSourceObserver;
use App\Services\AiModelRouter;
use App\Services\AiSqlService;
use App\Services\AnomalyDetectionService;
use App\Services\BusinessDnaService;
use App\Services\Connectors\ConnectorRegistry;
use App\Services\Connectors\DatabaseConnector;
use App\Services\Connectors\FileConnector;
use App\Services\Connectors\RestApiConnector;
use App\Services\CrossPlatformIntelligenceService;
use App\Services\CurrencyService;
use App\Services\FeatureFlagService;
use App\Services\IntelligenceEngineService;
use App\Services\KnowledgeGraphService;
use App\Services\PipelineExecutionService;
use App\Services\ReportGenerationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Intelligence services as singletons — one instance per request
        $this->app->singleton(IntelligenceEngineService::class);
        $this->app->singleton(AiModelRouter::class);
        $this->app->singleton(KnowledgeGraphService::class);

        $this->app->singleton(CrossPlatformIntelligenceService::class, fn ($app) => new CrossPlatformIntelligenceService(
            $app->make(IntelligenceEngineService::class),
            $app->make(AiModelRouter::class),
        )
        );

        $this->app->singleton(BusinessDnaService::class, fn ($app) => new BusinessDnaService(
            $app->make(IntelligenceEngineService::class),
            $app->make(AiModelRouter::class),
        )
        );

        // Connector registry — register all built-in connectors
        $this->app->singleton(ConnectorRegistry::class, function () {
            $registry = new ConnectorRegistry;
            $registry->register(new RestApiConnector);
            $registry->register(new DatabaseConnector);
            $registry->register(new FileConnector);

            return $registry;
        });

        $this->app->singleton(PipelineExecutionService::class, fn ($app) => new PipelineExecutionService($app->make(ConnectorRegistry::class))
        );

        $this->app->singleton(ReportGenerationService::class);

        $this->app->singleton(FeatureFlagService::class);
        $this->app->singleton(CurrencyService::class);
        $this->app->singleton(AnomalyDetectionService::class);

        $this->app->singleton(AiSqlService::class, fn ($app) => new AiSqlService(
            $app->make(AiModelRouter::class),
            $app->make(ConnectorRegistry::class),
        )
        );
    }

    public function boot(): void
    {
        // Register model observer for audit logging and domain events
        DataSource::observe(DataSourceObserver::class);

        // ─── Gates — action-level authorisation ───────────────────────────────
        // Team owners and admins can manage the intelligence layer.
        // Members get read-only access.

        Gate::define('manage-platforms', fn ($user) => $this->isTeamOwnerOrAdmin($user)
        );

        Gate::define('run-intelligence-engines', fn ($user) => $this->isTeamOwnerOrAdmin($user)
        );

        Gate::define('generate-briefing', fn ($user) => $this->isTeamOwnerOrAdmin($user)
        );

        Gate::define('manage-connectors', fn ($user) => $this->isTeamOwnerOrAdmin($user)
        );

        Gate::define('execute-sql-query', fn ($user) => $this->isTeamOwnerOrAdmin($user)
        );

        Gate::define('view-audit-logs', fn ($user) => $this->isTeamOwnerOrAdmin($user)
        );

        Gate::define('manage-recommendations', fn ($user) => $this->isTeamOwnerOrAdmin($user)
        );

        Gate::define('view-intelligence', fn ($user) =>
            // All authenticated team members can view intelligence
            $user->currentTeam !== null
        );

        // API rate limiters
        RateLimiter::for('analytics-api', function (Request $request) {
            $user = $request->user();

            return $user
                ? Limit::perMinute(120)->by($user->id)
                : Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('analytics-ingest', function (Request $request) {
            $user = $request->user();

            return $user
                ? Limit::perMinute(300)->by($user->id)  // Higher limit for ingest webhooks
                : Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('analytics-ai', function (Request $request) {
            $user = $request->user();

            return $user
                ? Limit::perMinute(10)->by($user->id)   // AI calls are expensive
                : Limit::perMinute(2)->by($request->ip());
        });
    }

    private function isTeamOwnerOrAdmin(User $user): bool
    {
        $team = $user->currentTeam;
        if (! $team) {
            return false;
        }
        if ($team->user_id === $user->id) {
            return true;
        }

        return $team->users()
            ->where('user_id', $user->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
