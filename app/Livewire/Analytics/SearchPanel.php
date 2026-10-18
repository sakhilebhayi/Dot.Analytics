<?php

namespace App\Livewire\Analytics;

use App\Services\Search\GlobalSearch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Search Panel
 *
 * Finds real content across the six entity types this app has: knowledge graph
 * entities, cross-platform insights, alerts, recommendations, saved reports, and
 * dashboards, via GlobalSearch (Scout's database engine; see config/scout.php).
 *
 * Read-only, so every team role may use it, viewers included. GlobalSearch holds every
 * query to the signed-in user's current team explicitly; this component never builds a
 * search of its own.
 */
class SearchPanel extends Component
{
    public string $query = '';

    public function mount(string $query = ''): void
    {
        $this->query = $query;
    }

    #[Computed]
    public function nodes(): Collection
    {
        return $this->resultsFor('nodes');
    }

    #[Computed]
    public function insights(): Collection
    {
        return $this->resultsFor('insights');
    }

    #[Computed]
    public function alerts(): Collection
    {
        return $this->resultsFor('alerts');
    }

    #[Computed]
    public function recommendations(): Collection
    {
        return $this->resultsFor('recommendations');
    }

    #[Computed]
    public function reports(): Collection
    {
        return $this->resultsFor('reports');
    }

    #[Computed]
    public function dashboards(): Collection
    {
        return $this->resultsFor('dashboards');
    }

    #[Computed]
    public function totalCount(): int
    {
        return $this->nodes->count() + $this->insights->count() + $this->alerts->count()
            + $this->recommendations->count() + $this->reports->count() + $this->dashboards->count();
    }

    public function hasQuery(): bool
    {
        return app(GlobalSearch::class)->normalise($this->query) !== null;
    }

    public function render(): View
    {
        return view('livewire.analytics.search-panel');
    }

    private function resultsFor(string $type): Collection
    {
        $user = Auth::user();

        return $user ? app(GlobalSearch::class)->in($type, $user, $this->query) : collect();
    }
}
