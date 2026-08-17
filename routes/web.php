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
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;

Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
    ->name('ecosystem.auth');

Route::get('/', fn () => view('welcome'));

// Cookie Policy — Jetstream's termsAndPrivacyPolicy feature covers terms.show/policy.show
// natively (registered at /terms-of-service and /privacy-policy, reading resources/markdown/
// terms.md and policy.md). There's no Jetstream equivalent for a Cookie Policy, so this one is
// wired by hand, following the exact same Markdown-source convention.
Route::get('/cookies', function () {
    return view('cookies', [
        'cookies' => Str::markdown(file_get_contents(Jetstream::localizedMarkdownPath('cookies.md'))),
    ]);
})->name('cookies');

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
            'connectedCount' => count($connectedPlatforms),
            'activeEngineCount' => $activeEngineCount,
            'openAlertCount' => AnalyticsAlert::where('team_id', $team->id)->where('status', 'open')->count(),
            'pendingRecommendationCount' => Recommendation::where('team_id', $team->id)->where('status', 'pending')->count(),
        ]);
    })->name('dashboard');

    Route::get('/dashboards', function () {
        // Same reachable-null case as /dashboard above.
        if (! Auth::user()->currentTeam) {
            return redirect()->route('teams.create');
        }

        return view('dashboards');
    })->name('dashboards.index');

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
