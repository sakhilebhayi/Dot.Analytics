<?php

namespace App\Livewire\Analytics;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsReport;
use App\Models\CrossPlatformInsight;
use App\Models\IntelligenceNode;
use App\Models\Recommendation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Search Panel
 *
 * Finds real content across the six entity types this app has: knowledge
 * graph entities, cross-platform insights, alerts, recommendations, saved
 * reports, and custom dashboards. Uses Scout's database engine (plain LIKE
 * against each model's own table) -- see config/scout.php.
 *
 * Team isolation is automatic for five of the six types via each model's
 * own HasTeamScope global scope. AnalyticsDashboard additionally needs the
 * private/team visibility rule from AnalyticsDashboardPolicy applied here,
 * in PHP, after the Scout query returns -- Scout's own fluent query builder
 * has no closure-based OR condition to express "mine, or shared with the
 * team" directly.
 */
class SearchPanel extends Component
{
    public string $query = '';

    public function mount(string $query = ''): void
    {
        $this->query = $query;
    }

    private function hasQuery(): bool
    {
        return mb_strlen(trim($this->query)) >= 2;
    }

    #[Computed]
    public function nodes(): Collection
    {
        return $this->hasQuery() ? IntelligenceNode::search($this->query)->take(10)->get() : collect();
    }

    #[Computed]
    public function insights(): Collection
    {
        return $this->hasQuery() ? CrossPlatformInsight::search($this->query)->take(10)->get() : collect();
    }

    #[Computed]
    public function alerts(): Collection
    {
        return $this->hasQuery() ? AnalyticsAlert::search($this->query)->take(10)->get() : collect();
    }

    #[Computed]
    public function recommendations(): Collection
    {
        return $this->hasQuery() ? Recommendation::search($this->query)->take(10)->get() : collect();
    }

    #[Computed]
    public function reports(): Collection
    {
        return $this->hasQuery() ? AnalyticsReport::search($this->query)->take(10)->get() : collect();
    }

    // Deliberately no ->take(10) on the Scout query itself here, unlike the
    // five methods above: filtering happens after fetch, so capping the
    // raw query first could drop visible dashboards if several of the top
    // raw matches turn out to be other members' private ones. Row counts
    // per team are small enough that fetching all matches first is fine.
    #[Computed]
    public function dashboards(): Collection
    {
        if (! $this->hasQuery()) {
            return collect();
        }

        return AnalyticsDashboard::search($this->query)->get()
            ->filter(fn (AnalyticsDashboard $d) => $d->user_id === Auth::id() || $d->visibility === 'team')
            ->take(10);
    }

    #[Computed]
    public function totalCount(): int
    {
        return $this->nodes->count() + $this->insights->count() + $this->alerts->count()
            + $this->recommendations->count() + $this->reports->count() + $this->dashboards->count();
    }

    public function render(): View
    {
        return view('livewire.analytics.search-panel');
    }
}
