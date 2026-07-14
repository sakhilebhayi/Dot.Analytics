<?php

namespace Tests\Unit\Services;

use App\Services\KnowledgeGraphService;
use App\Models\IntelligenceEdge;
use App\Models\IntelligenceNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeGraphServiceExtendedTest extends TestCase
{
    use RefreshDatabase;

    private KnowledgeGraphService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KnowledgeGraphService();
    }

    private function team()
    {
        return User::factory()->withPersonalTeam()->create()->currentTeam;
    }

    // ─── findPath ────────────────────────────────────────────────────────────

    public function test_find_path_returns_empty_for_disconnected_nodes(): void
    {
        $team  = $this->team();
        $nodeA = $this->service->upsertNode($team, 'customer', 'C-001', 'Customer', 'dot.crm');
        $nodeB = $this->service->upsertNode($team, 'invoice',  'I-001', 'Invoice',  'dot.payments');
        // No edge between them

        $path = $this->service->findPath($team, 'customer', 'C-001', 'invoice', 'I-001');
        $this->assertEmpty($path);
    }

    public function test_find_path_returns_direct_connection(): void
    {
        $team  = $this->team();
        $nodeA = $this->service->upsertNode($team, 'customer', 'C-001', 'Customer', 'dot.crm');
        $nodeB = $this->service->upsertNode($team, 'invoice',  'I-001', 'Invoice',  'dot.payments');
        $this->service->connect($nodeA, $nodeB, 'has');

        $path = $this->service->findPath($team, 'customer', 'C-001', 'invoice', 'I-001');

        $this->assertCount(2, $path);
        $this->assertEquals('customer', $path[0]->entity_type);
        $this->assertEquals('invoice', $path[1]->entity_type);
    }

    public function test_find_path_returns_empty_for_unknown_start_entity(): void
    {
        $team = $this->team();
        $path = $this->service->findPath($team, 'customer', 'NONEXISTENT', 'invoice', 'I-001');
        $this->assertEmpty($path);
    }

    public function test_find_path_returns_empty_for_unknown_end_entity(): void
    {
        $team = $this->team();
        $this->service->upsertNode($team, 'customer', 'C-001', 'Customer', 'dot.crm');

        $path = $this->service->findPath($team, 'customer', 'C-001', 'invoice', 'NONEXISTENT');
        $this->assertEmpty($path);
    }

    // ─── explainEntity ───────────────────────────────────────────────────────

    public function test_explain_entity_returns_empty_for_unknown_entity(): void
    {
        $team        = $this->team();
        $explanation = $this->service->explainEntity($team, 'customer', 'NONEXISTENT');

        $this->assertEmpty($explanation);
    }

    public function test_explain_entity_returns_empty_for_node_with_no_edges(): void
    {
        $team = $this->team();
        $this->service->upsertNode($team, 'customer', 'C-001', 'Customer', 'dot.crm');

        $explanation = $this->service->explainEntity($team, 'customer', 'C-001');
        $this->assertEmpty($explanation); // No incoming edges
    }

    // ─── Graph isolation ─────────────────────────────────────────────────────

    public function test_nodes_are_isolated_by_team(): void
    {
        $teamA = $this->team();
        $teamB = $this->team();

        $this->service->upsertNode($teamA, 'customer', 'C-001', 'Acme', 'dot.crm');

        $statsA = $this->service->getStats($teamA);
        $statsB = $this->service->getStats($teamB);

        $this->assertEquals(1, $statsA['node_count']);
        $this->assertEquals(0, $statsB['node_count']);
    }

    // ─── Cyclic graph handling ───────────────────────────────────────────────

    public function test_traverse_handles_cyclic_graph_without_infinite_loop(): void
    {
        $team  = $this->team();
        $nodeA = $this->service->upsertNode($team, 'customer', 'C-001', 'A', 'dot.crm');
        $nodeB = $this->service->upsertNode($team, 'invoice',  'I-001', 'B', 'dot.payments');
        $nodeC = $this->service->upsertNode($team, 'ticket',   'T-001', 'C', 'dot.support');

        $this->service->connect($nodeA, $nodeB, 'has');
        $this->service->connect($nodeB, $nodeC, 'raised');
        $this->service->connect($nodeC, $nodeA, 'back'); // Cycle!

        // Should not throw or infinite loop
        $result = $this->service->traverse($team, 'customer', 'C-001', 3);

        $this->assertNotNull($result);
        $this->assertCount(2, $result); // B and C, not A (start node excluded)
    }

    // ─── Edge weights ────────────────────────────────────────────────────────

    public function test_connect_stores_custom_weight(): void
    {
        $team  = $this->team();
        $nodeA = $this->service->upsertNode($team, 'customer', 'C-001', 'A', 'dot.crm');
        $nodeB = $this->service->upsertNode($team, 'invoice',  'I-001', 'B', 'dot.payments');

        $edge = $this->service->connect($nodeA, $nodeB, 'has', 0.85);

        $this->assertEquals(0.85, $edge->weight);
    }

    public function test_connect_stores_metadata(): void
    {
        $team  = $this->team();
        $nodeA = $this->service->upsertNode($team, 'customer', 'C-001', 'A', 'dot.crm');
        $nodeB = $this->service->upsertNode($team, 'invoice',  'I-001', 'B', 'dot.payments');

        $edge = $this->service->connect($nodeA, $nodeB, 'has', 1.0, ['source' => 'pipeline']);

        $this->assertEquals('pipeline', $edge->metadata['source']);
    }

    // ─── Stats ───────────────────────────────────────────────────────────────

    public function test_stats_density_is_zero_for_single_node(): void
    {
        $team = $this->team();
        $this->service->upsertNode($team, 'customer', 'C-001', 'Solo', 'dot.crm');

        $stats = $this->service->getStats($team);
        $this->assertEquals(0, $stats['density']);
    }

    public function test_stats_top_relationships_are_ordered_by_count(): void
    {
        $team  = $this->team();
        $nodeA = $this->service->upsertNode($team, 'customer', 'C-001', 'A', 'dot.crm');
        $nodeB = $this->service->upsertNode($team, 'invoice',  'I-001', 'B', 'dot.payments');
        $nodeC = $this->service->upsertNode($team, 'ticket',   'T-001', 'C', 'dot.support');

        $this->service->connect($nodeA, $nodeB, 'owns');
        $this->service->connect($nodeB, $nodeC, 'owns');
        $this->service->connect($nodeA, $nodeC, 'has');

        $stats = $this->service->getStats($team);
        $this->assertEquals(2, $stats['top_relationships']['owns']);
        $this->assertEquals(1, $stats['top_relationships']['has']);
    }
}
