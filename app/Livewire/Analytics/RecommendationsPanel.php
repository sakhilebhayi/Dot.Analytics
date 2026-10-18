<?php

namespace App\Livewire\Analytics;

use App\Models\Recommendation;
use App\Services\AiModelRouter;
use App\Services\IntelligenceEngineService;
use App\Services\SnapshotContextBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RecommendationsPanel extends Component
{
    public bool $generating = false;

    public ?string $notice = null;

    #[Computed]
    public function recommendations(): Collection
    {
        return Recommendation::where('status', 'pending')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->get();
    }

    public function generate(): void
    {
        // Generating spends AI budget: owner/admin only.
        Gate::authorize('run-intelligence-engines');

        $this->generating = true;
        $this->notice = null;

        $team = Auth::user()->currentTeam;
        $router = app(AiModelRouter::class);

        $connected = $team->dataSources()->where('status', 'connected')->pluck('platform')->all();
        $context = app(SnapshotContextBuilder::class)->build($team, $connected);

        if (! $context['has_data']) {
            $this->notice = 'No data connected yet: no platform has pushed a snapshot in the last '.$context['window_days'].' days, so there is nothing to base recommendations on.';
        } elseif (! $router->hasProvider()) {
            $this->notice = 'No AI provider is configured, so nothing was generated.';
        } else {
            $this->notice = $this->createFromModel($team, $router, $context);
        }

        unset($this->recommendations);
        $this->generating = false;
    }

    private function createFromModel($team, AiModelRouter $router, array $context): string
    {
        $engines = implode(', ', array_keys(IntelligenceEngineService::ENGINES));
        $allowed = implode(', ', $context['platforms_with_data']);

        $prompt = <<<PROMPT
You are Dot.Analytics for {$team->name}.

The ONLY data you may use is the following real platform snapshots (window {$context['window_start']} to {$context['window_end']}):

{$context['block']}

Available intelligence engines: {$engines}

Generate up to 3 actionable recommendations that the data above directly supports. Do NOT invent figures, people or events.
Only reference platforms from: {$allowed}. If the data supports none, return [].
Return JSON array only, no markdown:
[
  {
    "title": "...",
    "rationale": "Explain which figures above support this.",
    "priority": "critical|high|medium|low",
    "engine": "one of the engine keys above"
  }
]
PROMPT;

        $raw = $router->completeStrict($prompt, 'recommendation', $team->id);
        if ($raw === null || ! preg_match('/\[.*\]/s', $raw, $matches)) {
            return 'The AI provider did not return usable recommendations. Nothing was stored.';
        }

        $items = json_decode($matches[0], true);
        if (! is_array($items)) {
            return 'The AI response could not be parsed. Nothing was stored.';
        }

        $created = 0;
        foreach ($items as $rec) {
            if (! is_array($rec) || trim((string) ($rec['title'] ?? '')) === '' || trim((string) ($rec['rationale'] ?? '')) === '') {
                continue;
            }

            Recommendation::create([
                'team_id' => $team->id,
                'engine' => array_key_exists($rec['engine'] ?? null, IntelligenceEngineService::ENGINES) ? $rec['engine'] : 'decision',
                'title' => mb_substr(trim($rec['title']), 0, 255),
                'rationale' => trim($rec['rationale']),
                'priority' => in_array($rec['priority'] ?? null, ['critical', 'high', 'medium', 'low'], true) ? $rec['priority'] : 'medium',
                'status' => 'pending',
            ]);
            $created++;
        }

        return $created === 0
            ? 'The model found nothing in the available data worth recommending.'
            : "{$created} recommendation(s) generated from your ingested data.";
    }

    public function action(int $id): void
    {
        Gate::authorize('manage-recommendations');

        Recommendation::findOrFail($id)->update(['status' => 'actioned']);
        unset($this->recommendations);
    }

    public function dismiss(int $id): void
    {
        Gate::authorize('manage-recommendations');

        Recommendation::findOrFail($id)->update(['status' => 'dismissed']);
        unset($this->recommendations);
    }

    public function render(): View
    {
        return view('livewire.analytics.recommendations-panel');
    }
}
