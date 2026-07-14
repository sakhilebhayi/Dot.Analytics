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
        $team = Auth::user()->currentTeam;

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
        $team   = Auth::user()->currentTeam;
        $report = AnalyticsReport::where('team_id', $team->id)->findOrFail($id);

        return app(ReportGenerationService::class)->streamCsv(
            $team,
            $report->config['report_type'] ?? 'insights',
        );
    })->name('reports.download');
});
