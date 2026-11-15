<?php

namespace App\Livewire\Analytics;

use App\Models\DataSource;
use App\Services\Metrics\MetricReader;
use App\Services\Search\GlobalSearch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The live results under the search pill in the top bar (the shared dot-ui pill, typed into).
 *
 * Presentation over the data this workspace already searches: GlobalSearch (insights, alerts, recommendations, saved
 * reports, dashboards; held to the signed-in user's current team there) plus two lists read the same way for the same
 * person: the figures the company's CONNECTED platforms can report (the catalogue MetricReader offers for the picker),
 * and the company's own data sources. Every query starts from the current team the user really belongs to; a person
 * without one, or a term under two characters, gets nothing. Read-only: every team role may use it. Enter with nothing
 * highlighted submits the form to /search, the full page, unchanged.
 */
class HeaderSearch extends Component
{
    /** Per group in the dropdown; the full page lists up to GlobalSearch::LIMIT. */
    private const PER_GROUP = 4;

    public string $query = '';

    public function mount(string $query = ''): void
    {
        $this->query = mb_substr($query, 0, GlobalSearch::MAX_LENGTH);
    }

    public function ready(): bool
    {
        return app(GlobalSearch::class)->normalise($this->query) !== null;
    }

    /**
     * @return list<array{head: string, rows: list<array{label: string, href: string, tag: string|null}>}>
     */
    #[Computed]
    public function groups(): array
    {
        $user = Auth::user();
        $term = app(GlobalSearch::class)->normalise($this->query);
        $team = $user?->currentTeam;

        if ($user === null || $term === null || $team === null || ! $user->belongsToTeam($team)) {
            return [];
        }

        $search = app(GlobalSearch::class);
        $groups = [];

        $figures = [];
        foreach (app(MetricReader::class)->pickable($team) as $platform => $metrics) {
            foreach ($metrics as $key => $label) {
                if (Str::contains(Str::lower($label.' '.$platform), Str::lower($term))) {
                    $figures[] = ['label' => $label, 'href' => route('dashboard', ['figure' => $key]), 'tag' => $platform];
                }
            }
        }
        $groups[] = ['head' => 'Figures', 'rows' => array_slice($figures, 0, self::PER_GROUP)];

        $like = '%'.addcslashes($term, '\\%_').'%';
        $groups[] = ['head' => 'Sources', 'rows' => DataSource::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where(fn ($q) => $q->where('display_name', 'like', $like)->orWhere('platform', 'like', $like))
            ->orderBy('display_name')->limit(self::PER_GROUP)->get(['id', 'platform', 'display_name', 'status'])
            ->map(fn (DataSource $s) => ['label' => $s->display_name ?: Str::headline($s->platform), 'href' => route('workspace.sources'), 'tag' => $s->status])
            ->all()];

        $groups[] = ['head' => 'Insights', 'rows' => $search->in('insights', $user, $term)->take(self::PER_GROUP)
            ->map(fn ($m) => ['label' => (string) $m->title, 'href' => route('workspace.insights'), 'tag' => null])->values()->all()];
        $groups[] = ['head' => 'Alerts', 'rows' => $search->in('alerts', $user, $term)->take(self::PER_GROUP)
            ->map(fn ($m) => ['label' => (string) $m->title, 'href' => route('workspace.figures').'#decisions', 'tag' => $m->status ?? null])->values()->all()];
        $groups[] = ['head' => 'Recommendations', 'rows' => $search->in('recommendations', $user, $term)->take(self::PER_GROUP)
            ->map(fn ($m) => ['label' => (string) $m->title, 'href' => route('workspace.figures').'#decisions', 'tag' => null])->values()->all()];
        $groups[] = ['head' => 'Reports', 'rows' => $search->in('reports', $user, $term)->take(self::PER_GROUP)
            ->map(fn ($m) => ['label' => (string) $m->title, 'href' => route('workspace.reports'), 'tag' => null])->values()->all()];
        $groups[] = ['head' => 'Dashboards', 'rows' => $search->in('dashboards', $user, $term)->take(self::PER_GROUP)
            ->map(fn ($m) => ['label' => (string) ($m->title ?? $m->name ?? 'Dashboard'), 'href' => route('workspace.figures'), 'tag' => null])->values()->all()];

        return array_values(array_filter($groups, fn (array $g) => $g['rows'] !== []));
    }

    public function render(): View
    {
        return view('livewire.analytics.header-search');
    }
}
