<?php

use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsReport;
use App\Models\DataSource;
use App\Models\Recommendation;
use App\Services\IntelligenceEngineService;
use App\Services\ReportGenerationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
    ->name('ecosystem.auth');

Route::get('/', fn () => view('welcome'));

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        // No team-context middleware runs ahead of this route, so a user who
        // belongs to no team (e.g. removed from their last team) reaches
        // here with currentTeam genuinely null. Send them to team creation
        // instead of crashing on the null dereference below.
        $team = Auth::user()->currentTeam;
        if (! $team) {
            return redirect()->route('teams.create');
        }

        $connectedPlatforms = DataSource::where('team_id', $team->id)
            ->where('status', 'connected')
            ->pluck('platform')
            ->toArray();

        $activeEngineCount = count(
            app(IntelligenceEngineService::class)->getActiveEngines($connectedPlatforms)
        );

        return view('dashboard', [
            'connectedCount'             => count($connectedPlatforms),
            'activeEngineCount'          => $activeEngineCount,
            'openAlertCount'             => AnalyticsAlert::where('team_id', $team->id)->where('status', 'open')->count(),
            'pendingRecommendationCount' => Recommendation::where('team_id', $team->id)->where('status', 'pending')->count(),
        ]);
    })->name('dashboard');

    // Report CSV download
    Route::get('/reports/{id}/download', function (int $id) {
        // Same reachable-null case as /dashboard above; this route has no
        // report to scope to without a team, so abort rather than guess.
        $team = Auth::user()->currentTeam;
        if (! $team) {
            abort(403, 'No active team selected.');
        }
        $report = AnalyticsReport::where('team_id', $team->id)->findOrFail($id);

        return app(ReportGenerationService::class)->streamCsv(
            $team,
            $report->config['report_type'] ?? 'insights',
        );
    })->name('reports.download');
});
