<?php

namespace App\Livewire\Analytics;

use App\Services\KnowledgeGraphService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Knowledge Graph Panel
 *
 * Provides visual access to the Universal Intelligence Graph.
 * Users can traverse entity relationships, perform impact analysis,
 * and trace causal chains across the entire Dot ecosystem.
 */
class KnowledgeGraphPanel extends Component
{
    public string $entityType = 'customer';
    public string $entityId   = '';
    public int    $maxDepth   = 2;
    public array  $results    = [];
    public bool   $searching  = false;
    public string $error      = '';

    public function traverse(): void
    {
        $this->validate([
            'entityType' => 'required|string|max:50',
            'entityId'   => 'required|string|max:100',
            'maxDepth'   => 'integer|min:1|max:5',
        ]);

        $this->searching = true;
        $this->error     = '';

        $service = app(KnowledgeGraphService::class);
        $team    = Auth::user()->currentTeam;

        $nodes = $service->traverse($team, $this->entityType, $this->entityId, $this->maxDepth);

        if ($nodes->isEmpty()) {
            $this->error = "No entity found: {$this->entityType} '{$this->entityId}'. Entities are created as platforms contribute data to the intelligence layer.";
        }

        $this->results  = $nodes->values()->toArray();
        $this->searching = false;
    }

    #[Computed]
    public function graphStats(): array
    {
        return app(KnowledgeGraphService::class)
            ->getStats(Auth::user()->currentTeam);
    }

    public function clear(): void
    {
        $this->entityId = '';
        $this->results  = [];
        $this->error    = '';
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.analytics.knowledge-graph-panel');
    }
}
