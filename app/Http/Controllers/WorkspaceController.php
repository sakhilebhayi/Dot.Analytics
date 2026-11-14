<?php

namespace App\Http\Controllers;

use App\Livewire\Analytics\BusinessPlatformsPanel;
use App\Models\AnalyticsAlert;
use App\Models\DataSource;
use App\Models\Recommendation;
use App\Models\User;
use App\Services\AiModelRouter;
use App\Services\IntelligenceEngineService;
use App\Services\Metrics\MetricReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** The signed-in company workspace: the page a person lands on, and one page for each of the things the old single page piled together. */
class WorkspaceController extends Controller
{
    /** The pages under /workspace. The landing links to every one of them. */
    public const SECTIONS = ['figures', 'insights', 'briefing', 'ask', 'reports', 'sources'];

    /** How many figures the landing's ledger lists. The Figures page holds the rest. */
    public const LEDGER_ROWS = 12;

    /** Days of stored points the selected figure's chart reads. */
    private const CHART_DAYS = 30;

    public function overview(Request $request, MetricReader $reader, AiModelRouter $ai): View|RedirectResponse
    {
        // No team-context middleware runs ahead of this route, so a user who belongs to no team (e.g. removed from their
        // last team) reaches here with currentTeam genuinely null. Send them to team creation instead of crashing.
        $user = Auth::user();
        $team = $user->currentTeam;

        if (! $team) {
            return redirect()->route('teams.create');
        }

        $dashboard = $reader->dashboard($team);
        $connected = collect($dashboard['platforms'])->where('connected', true)->values();
        $reporting = $connected->where('has_data', true)->values();

        // Every figure the landing prints comes from here: stored values a platform reported, newest first, each with
        // the platform that sent it, the day it is for and when it arrived.
        $newest = $connected->flatMap(fn (array $platform) => $platform['tiles'])
            ->filter(fn (array $tile) => $tile['state'] !== 'no_data')
            ->sortByDesc(fn (array $tile) => [$tile['as_of'], (string) $tile['observed_at']])
            ->values();
        // One line from each reporting platform before a second from any, so one busy platform does not fill the ledger.
        $first = $newest->unique('platform');
        $figures = $first->concat($newest->reject(fn (array $tile) => $first->contains('key', $tile['key'])))->take(self::LEDGER_ROWS)->values();

        // The selected figure comes from ?figure= only when it is one of the rows listed (so it is this company's and
        // really reporting). Otherwise: the figure an open anomaly alert is about, else the first row.
        $requested = $request->query('figure');
        $anomalyKeys = $dashboard['summary']['anomaly_keys'];
        $selected = (is_string($requested) ? $figures->firstWhere('key', $requested) : null)
            ?? $figures->first(fn (array $tile) => in_array($tile['key'], $anomalyKeys, true))
            ?? $figures->first();
        $series = $selected ? $reader->series($team, $selected['key'], self::CHART_DAYS) : null;

        $state = match (true) {
            $connected->isEmpty() => 'nothing-connected',
            $reporting->isEmpty() => 'waiting',
            default => 'reporting',
        };

        return view('dashboard', [
            'team' => $team,
            'state' => $state,
            'canConnect' => $user->can('create', DataSource::class),
            'notConnected' => collect($dashboard['platforms'])->where('connected', false)->pluck('label')->values(),
            'primary' => $this->primaryAction($user, $state, $ai->hasProvider()),
            'connected' => $connected,
            'reporting' => $reporting,
            'figures' => $figures,
            'figureCount' => $newest->count(),
            'selected' => $selected,
            'series' => $series,
            'summary' => $dashboard['summary'],
            'newestObservedAt' => $newest->pluck('observed_at')->filter()->max(),
            'hasDemo' => (bool) $dashboard['summary']['has_demo'],
            // Kept under the names the page has always used: data sources this company has connected, of the platforms
            // that are able to report. This is about data arriving here, never about which platforms a company may use.
            'connectedCount' => $connected->count(),
            'platformTotal' => count(IntelligenceEngineService::PLATFORMS),
            'alerts' => AnalyticsAlert::where('team_id', $team->id)->where('status', 'open')->orderByDesc('triggered_at')->limit(3)->get(),
            'openAlertCount' => AnalyticsAlert::where('team_id', $team->id)->where('status', 'open')->count(),
            'recommendations' => Recommendation::where('team_id', $team->id)->where('status', 'pending')->latest()->limit(3)->get(),
            'pendingRecommendationCount' => Recommendation::where('team_id', $team->id)->where('status', 'pending')->count(),
            'showBusinessPlatforms' => BusinessPlatformsPanel::enabled(),
            'infodot' => rtrim((string) config('ecosystem.platforms.infodot.url', 'https://infodot.co.za'), '/'),
        ]);
    }

    public function page(string $section): View|RedirectResponse
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);

        return Auth::user()->currentTeam ? view('workspace.'.$section) : redirect()->route('teams.create');
    }

    /**
     * The one thing the landing asks of this person, chosen from what really works for them right now.
     *
     * With nothing reporting there is nothing to ask or read, so the step is the one that changes that: connecting a
     * platform, for the people allowed to. Asking needs figures, a role that may spend the assistant's budget, and an
     * assistant that is actually configured; otherwise the working step is reading the figures.
     *
     * @return array{label: string, route: string, why: string}
     */
    private function primaryAction(User $user, string $state, bool $assistantAvailable): array
    {
        $managesSources = $user->can('create', DataSource::class);

        if ($state !== 'reporting') {
            if (! $managesSources) {
                return ['label' => 'Read the public library', 'route' => 'intel.home', 'why' => 'An owner or admin connects platforms. Until one reports, the library of public figures is open to you.'];
            }

            return $state === 'waiting'
                ? ['label' => 'Check your sources', 'route' => 'workspace.sources', 'why' => 'A connected platform sends its first figures once it holds the secret it was issued.']
                : ['label' => 'Connect a platform', 'route' => 'workspace.sources', 'why' => 'Choose a platform, and it is issued a secret to sign its daily figures with.'];
        }

        return $assistantAvailable && $user->can('ask-intelligence')
            ? ['label' => 'Ask a question', 'route' => 'workspace.ask', 'why' => 'Answered only from the figures your platforms have sent.']
            : ['label' => 'Read the figures', 'route' => 'workspace.figures', 'why' => 'Every number your platforms have sent, with its trend.'];
    }
}
