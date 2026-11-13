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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** The signed-in company workspace: the page a person lands on, and one page for each of the things the old single page piled together. */
class WorkspaceController extends Controller
{
    /** The pages under /workspace. The landing links to every one of them. */
    public const SECTIONS = ['figures', 'insights', 'briefing', 'ask', 'reports', 'sources'];

    /** How many of the latest figures the landing prints on its working tape. */
    private const TAPE_LINES = 3;

    public function overview(MetricReader $reader, AiModelRouter $ai): View|RedirectResponse
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
        // One line from each reporting platform before a second from any, so one busy platform does not fill the tape.
        $first = $newest->unique('platform');
        $figures = $first->concat($newest->reject(fn (array $tile) => $first->contains('key', $tile['key'])))->take(self::TAPE_LINES)->values();

        $state = match (true) {
            $connected->isEmpty() => 'nothing-connected',
            $reporting->isEmpty() => 'waiting',
            default => 'reporting',
        };

        return view('dashboard', [
            'firstName' => Str::of((string) $user->name)->trim()->before(' ')->toString(),
            'team' => $team,
            'state' => $state,
            'primary' => $this->primaryAction($user, $state, $ai->hasProvider()),
            'connected' => $connected,
            'reporting' => $reporting,
            'figures' => $figures,
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
