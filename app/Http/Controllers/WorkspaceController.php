<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsAlert;
use App\Models\DataSource;
use App\Models\Recommendation;
use App\Services\IntelligenceEngineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** The signed-in company workspace: the overview, and one page for each of the things the old single page piled together. */
class WorkspaceController extends Controller
{
    public function overview(): View|RedirectResponse
    {
        // No team-context middleware runs ahead of this route, so a user who belongs to no team (e.g. removed from their
        // last team) reaches here with currentTeam genuinely null. Send them to team creation instead of crashing.
        $team = Auth::user()->currentTeam;

        if (! $team) {
            return redirect()->route('teams.create');
        }

        $connectedPlatforms = DataSource::where('team_id', $team->id)
            ->where('status', 'connected')
            ->whereIn('platform', array_keys(IntelligenceEngineService::PLATFORMS))
            ->pluck('platform')
            ->toArray();

        return view('dashboard', [
            'connectedCount' => count($connectedPlatforms),
            'platformTotal' => count(IntelligenceEngineService::PLATFORMS),
            'activeEngineCount' => count(app(IntelligenceEngineService::class)->getActiveEngines($connectedPlatforms)),
            // Engines with no real platform able to feed them are dormant and not counted.
            'engineTotal' => count(array_filter(IntelligenceEngineService::ENGINES, fn ($e) => $e['sources'] !== [])),
            'openAlertCount' => AnalyticsAlert::where('team_id', $team->id)->where('status', 'open')->count(),
            'pendingRecommendationCount' => Recommendation::where('team_id', $team->id)->where('status', 'pending')->count(),
        ]);
    }

    public function page(string $section): View|RedirectResponse
    {
        abort_unless(in_array($section, ['insights', 'briefing', 'ask', 'reports'], true), 404);

        return Auth::user()->currentTeam ? view('workspace.'.$section) : redirect()->route('teams.create');
    }
}
