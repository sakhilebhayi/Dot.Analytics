<?php

use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\EnsureCompanyEntitled;
use App\Http\Middleware\ReverifyWithInfoDot;
use App\Models\AnalyticsReport;
use App\Services\ReportGenerationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

// Token redemption is brute-forceable if unthrottled: 10 attempts/min per IP.
// The token is read from the POST body only; a GET carrying it is not honoured (see below).
Route::post('/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
    ->withoutMiddleware(['Illuminate\Foundation\Http\Middleware\PreventRequestForgery', 'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken', 'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken'])
    ->middleware('throttle:ecosystem-auth')
    ->name('ecosystem.auth');

// Links minted before the token moved into a POST body carried it in the URL. They are no longer honoured:
// the visitor is sent to the normal sign-in page and the token in the URL is never read.
Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'legacy']);

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
    ReverifyWithInfoDot::class,
    // R-50: the company must still hold Dot.Analytics. The public library and sign-in are outside this group.
    EnsureCompanyEntitled::class,
])->group(function () {
    Route::get('/dashboard', [WorkspaceController::class, 'overview'])->name('dashboard');

    foreach (WorkspaceController::SECTIONS as $section) {
        Route::get('/workspace/'.$section, fn () => app(WorkspaceController::class)->page($section))->name('workspace.'.$section);
    }

    Route::get('/search', function () {
        // Same reachable-null case as /dashboard above.
        if (! Auth::user()->currentTeam) {
            return redirect()->route('teams.create');
        }

        return view('search');
    })->name('search');

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
            $report->reportType() ?? 'insights',
        );
    })->name('reports.download');
});

// Public sign-up is closed (accounts are created by InfoDot at first sign-in). Keep the named route so any link or
// invitation email that points at it lands on the login page instead of failing.
if (! Features::enabled(Features::registration())) {
    Route::redirect('/register', '/login')->name('register');
}
