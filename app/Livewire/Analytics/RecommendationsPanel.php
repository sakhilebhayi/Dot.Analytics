<?php

namespace App\Livewire\Analytics;

use App\Models\Recommendation;
use App\Services\AiModelRouter;
use App\Services\IntelligenceEngineService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RecommendationsPanel extends Component
{
    public bool $generating = false;

    #[Computed]
    public function recommendations(): Collection
    {
        return Recommendation::where('status', 'pending')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->get();
    }

    public function generate(): void
    {
        $this->generating = true;

        $team    = Auth::user()->currentTeam;
        $router  = app(AiModelRouter::class);
        $engine  = app(IntelligenceEngineService::class);
        $context = $engine->buildEcosystemContext($team);
        $engines = implode(', ', array_keys(IntelligenceEngineService::ENGINES));

        $prompt = <<<PROMPT
You are Dot.Analytics — the Enterprise Intelligence Platform and central nervous system of the Dot ecosystem for {$team->name}.

Ecosystem context:
{$context}

Available intelligence engines: {$engines}

Generate 3 actionable cross-platform intelligence recommendations. Each must reference at least 2 platforms.
Return JSON array only, no markdown:
[
  {
    "title": "...",
    "rationale": "Explain the cross-platform causal chain.",
    "priority": "critical|high|medium|low",
    "engine": "one of the engine keys above"
  }
]
PROMPT;

        $raw   = $router->complete($prompt, 'recommendation', $team->id);
        preg_match('/\[.*\]/s', $raw, $matches);
        $items = json_decode($matches[0] ?? '[]', true) ?? [];

        foreach ($items as $rec) {
            Recommendation::create([
                'team_id'   => $team->id,
                'engine'    => $rec['engine'] ?? 'decision',
                'title'     => $rec['title'],
                'rationale' => $rec['rationale'],
                'priority'  => $rec['priority'] ?? 'medium',
                'status'    => 'pending',
            ]);
        }

        unset($this->recommendations);
        $this->generating = false;
    }

    public function action(int $id): void
    {
        Recommendation::findOrFail($id)->update(['status' => 'actioned']);
        unset($this->recommendations);
    }

    public function dismiss(int $id): void
    {
        Recommendation::findOrFail($id)->update(['status' => 'dismissed']);
        unset($this->recommendations);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.analytics.recommendations-panel');
    }
}
