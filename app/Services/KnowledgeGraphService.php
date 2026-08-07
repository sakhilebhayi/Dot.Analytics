<?php

namespace App\Services;

use App\Models\IntelligenceEdge;
use App\Models\IntelligenceNode;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Knowledge Graph Service — maintains and traverses the Universal Intelligence Graph.
 *
 * The graph connects every entity across the Dot ecosystem:
 * Customers ↔ Orders ↔ Invoices ↔ Payments ↔ Support Tickets ↔
 * Equipment ↔ Operators ↔ Maintenance ↔ Fuel ↔ Community Posts ↔
 * AI Conversations ↔ Contracts ↔ Projects ↔ Inventory ↔ Suppliers
 *
 * This powers:
 * - Impact analysis ("what does this entity affect?")
 * - Root cause tracing ("why is this entity in this state?")
 * - Dependency mapping
 * - Explainable AI ("here is the evidence chain")
 */
class KnowledgeGraphService
{
    /**
     * Upsert an entity node in the graph.
     */
    public function upsertNode(
        Team $team,
        string $entityType,
        string $entityId,
        string $label,
        string $sourcePlatform,
        array $attributes = [],
    ): IntelligenceNode {
        return IntelligenceNode::updateOrCreate(
            [
                'team_id' => $team->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
            [
                'label' => $label,
                'source_platform' => $sourcePlatform,
                'attributes' => $attributes,
            ],
        );
    }

    /**
     * Create or update a directed relationship between two nodes.
     */
    public function connect(
        IntelligenceNode $from,
        IntelligenceNode $to,
        string $relationship,
        float $weight = 1.0,
        array $metadata = [],
    ): IntelligenceEdge {
        return IntelligenceEdge::updateOrCreate(
            [
                'team_id' => $from->team_id,
                'from_node_id' => $from->id,
                'to_node_id' => $to->id,
                'relationship' => $relationship,
            ],
            [
                'weight' => $weight,
                'metadata' => $metadata,
            ],
        );
    }

    /**
     * Find all nodes reachable from a starting entity within N hops.
     * Used for impact analysis: "which entities are affected by X?"
     *
     * @return Collection<IntelligenceNode>
     */
    public function traverse(Team $team, string $entityType, string $entityId, int $maxDepth = 3): Collection
    {
        $startNode = IntelligenceNode::where('team_id', $team->id)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->first();

        if (! $startNode) {
            return collect();
        }

        return $this->bfs($startNode, $maxDepth);
    }

    /**
     * Trace the shortest path between two entities.
     * Used for root cause analysis.
     */
    public function findPath(
        Team $team,
        string $fromType, string $fromId,
        string $toType, string $toId,
    ): array {
        $start = IntelligenceNode::where('team_id', $team->id)
            ->where('entity_type', $fromType)
            ->where('entity_id', $fromId)
            ->first();

        $end = IntelligenceNode::where('team_id', $team->id)
            ->where('entity_type', $toType)
            ->where('entity_id', $toId)
            ->first();

        if (! $start || ! $end) {
            return [];
        }

        return $this->bfsPath($start, $end);
    }

    /**
     * Get a summary of graph statistics for a team.
     */
    public function getStats(Team $team): array
    {
        $nodeCount = IntelligenceNode::where('team_id', $team->id)->count();
        $edgeCount = IntelligenceEdge::where('team_id', $team->id)->count();

        $entityTypes = IntelligenceNode::where('team_id', $team->id)
            ->selectRaw('entity_type, count(*) as count')
            ->groupBy('entity_type')
            ->pluck('count', 'entity_type')
            ->toArray();

        $topRelationships = IntelligenceEdge::where('team_id', $team->id)
            ->selectRaw('relationship, count(*) as count')
            ->groupBy('relationship')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'relationship')
            ->toArray();

        return [
            'node_count' => $nodeCount,
            'edge_count' => $edgeCount,
            'entity_types' => $entityTypes,
            'top_relationships' => $topRelationships,
            'density' => $nodeCount > 1 ? round($edgeCount / ($nodeCount * ($nodeCount - 1)), 4) : 0,
        ];
    }

    /**
     * Explain why an entity is in a given state by tracing inbound relationships.
     * Returns a human-readable explanation chain.
     */
    public function explainEntity(Team $team, string $entityType, string $entityId): array
    {
        $node = IntelligenceNode::where('team_id', $team->id)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->with('incomingEdges.fromNode')
            ->first();

        if (! $node) {
            return [];
        }

        $chain = [];
        foreach ($node->incomingEdges as $edge) {
            if ($edge->fromNode) {
                $chain[] = [
                    'from_entity' => $edge->fromNode->entity_type,
                    'from_label' => $edge->fromNode->label,
                    'relationship' => $edge->relationship,
                    'weight' => $edge->weight,
                ];
            }
        }

        return $chain;
    }

    // ─── Private graph algorithms ──────────────────────────────────────────────

    /**
     * Breadth-first search to find all reachable nodes.
     *
     * @return Collection<IntelligenceNode>
     */
    private function bfs(IntelligenceNode $start, int $maxDepth): Collection
    {
        $visited = collect([$start->id => $start]);
        $queue = [[$start, 0]];

        while (! empty($queue)) {
            [$node, $depth] = array_shift($queue);

            if ($depth >= $maxDepth) {
                continue;
            }

            $neighbors = IntelligenceNode::whereIn(
                'id',
                IntelligenceEdge::where('from_node_id', $node->id)->pluck('to_node_id')
            )->get();

            foreach ($neighbors as $neighbor) {
                if (! $visited->has($neighbor->id)) {
                    $visited->put($neighbor->id, $neighbor);
                    $queue[] = [$neighbor, $depth + 1];
                }
            }
        }

        return $visited->forget($start->id)->values();
    }

    /**
     * BFS to find shortest path between two nodes.
     */
    private function bfsPath(IntelligenceNode $start, IntelligenceNode $end): array
    {
        $visited = [$start->id => null];
        $queue = [$start];

        while (! empty($queue)) {
            $node = array_shift($queue);

            if ($node->id === $end->id) {
                return $this->reconstructPath($visited, $start->id, $end->id);
            }

            $edges = IntelligenceEdge::where('from_node_id', $node->id)
                ->with('toNode')
                ->get();

            foreach ($edges as $edge) {
                $neighbor = $edge->toNode;
                if ($neighbor && ! array_key_exists($neighbor->id, $visited)) {
                    $visited[$neighbor->id] = $node->id;
                    $queue[] = $neighbor;
                }
            }
        }

        return [];
    }

    private function reconstructPath(array $parents, int $startId, int $endId): array
    {
        $path = [];
        $current = $endId;

        while ($current !== null) {
            $node = IntelligenceNode::find($current);
            if ($node) {
                array_unshift($path, $node);
            }
            $current = $parents[$current] ?? null;
        }

        return $path;
    }
}
