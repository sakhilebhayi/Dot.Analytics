<?php

namespace App\Livewire\Analytics;

use App\Models\AnalyticsAlert;
use App\Models\DataSource;
use App\Models\Recommendation;
use App\Services\AiModelRouter;
use App\Services\SnapshotContextBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class IntelligenceDashboard extends Component
{
    public string $intelligenceQuery = '';

    public string $queryAnswer = '';

    public bool $queryLoading = false;

    #[Computed]
    public function connectedSources(): Collection
    {
        if (! Auth::user()->currentTeam) {
            return collect();
        }

        return DataSource::where('team_id', Auth::user()->currentTeam->id)
            ->where('status', 'connected')
            ->get();
    }

    #[Computed]
    public function openAlerts(): Collection
    {
        if (! Auth::user()->currentTeam) {
            return collect();
        }

        return AnalyticsAlert::where('team_id', Auth::user()->currentTeam->id)
            ->where('status', 'open')
            ->orderBy('severity')
            ->orderByDesc('triggered_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function pendingRecommendations(): Collection
    {
        if (! Auth::user()->currentTeam) {
            return collect();
        }

        return Recommendation::where('team_id', Auth::user()->currentTeam->id)
            ->where('status', 'pending')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->limit(5)
            ->get();
    }

    public function askIntelligence(): void
    {
        $this->validate(['intelligenceQuery' => 'required|string|min:5|max:500']);

        if (! Auth::user()->currentTeam) {
            $this->addError('intelligenceQuery', 'You need a team before asking Intelligence questions.');

            return;
        }

        $this->queryLoading = true;

        $team = Auth::user()->currentTeam;
        $router = app(AiModelRouter::class);

        $connected = $team->dataSources()->where('status', 'connected')->pluck('platform')->all();
        $context = app(SnapshotContextBuilder::class)->build($team, $connected);

        // Answers come from the team's ingested snapshots or not at all.
        if (! $context['has_data']) {
            $this->queryAnswer = 'No data connected yet. Connect a platform and let it push data before asking questions.';
            $this->queryLoading = false;

            return;
        }

        $prompt = <<<PROMPT
You are Dot.Analytics — the Enterprise Intelligence Platform for {$team->name}.
Answer ONLY from the real platform snapshots below (window {$context['window_start']} to {$context['window_end']}).
If they do not contain the answer, say so plainly. Never invent figures.

{$context['block']}

Question: {$this->intelligenceQuery}
PROMPT;

        $this->queryAnswer = $router->complete($prompt, 'query', $team->id);
        $this->queryLoading = false;
    }

    public function render(): View
    {
        return view('livewire.analytics.intelligence-dashboard');
    }
}
