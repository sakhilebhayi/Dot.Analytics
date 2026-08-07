<?php

namespace Tests\Unit\Services;

use App\Models\IntelligenceEdge;
use App\Models\IntelligenceNode;
use App\Models\User;
use App\Services\KnowledgeGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeGraphServiceTest extends TestCase
{
    use RefreshDatabase;

    private KnowledgeGraphService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KnowledgeGraphService;
    }

    private function team()
    {
        return User::factory()->withPersonalTeam()->create()->currentTeam;
    }

    public function test_upsert_node_creates_new_node(): void
    {
        $team = $this->team();

        $node = $this->service->upsertNode($team, 'customer', 'C-001', 'Acme Corp', 'dot.crm');

        $this->assertInstanceOf(IntelligenceNode::class, $node);
        $this->assertDatabaseHas('intelligence_nodes', [
            'team_id' => $team->id,
            'entity_type' => 'customer',
            'entity_id' => 'C-001',
            'label' => 'Acme Corp',
        ]);
    }

    public function test_upsert_node_updates_existing_node(): void
    {
        $team = $this->team();

        $this->service->upsertNode($team, 'customer', 'C-001', 'Old Label', 'dot.crm');
        $updated = $this->service->upsertNode($team, 'customer', 'C-001', 'New Label', 'dot.crm');

        $this->assertEquals('New Label', $updated->label);
        $this->assertDatabaseCount('intelligence_nodes', 1);
    }

    public function test_connect_creates_edge_between_nodes(): void
    {
        $team = $this->team();
        $nodeA = $this->service->upsertNode($team, 'customer', 'C-001', 'Customer', 'dot.crm');
        $nodeB = $this->service->upsertNode($team, 'invoice', 'INV-001', 'Invoice #1', 'dot.payments');

        $edge = $this->service->connect($nodeA, $nodeB, 'owns', 1.0);

        $this->assertInstanceOf(IntelligenceEdge::class, $edge);
        $this->assertEquals('owns', $edge->relationship);
    }

    public function test_traverse_returns_connected_nodes(): void
    {
        $team = $this->team();
        $nodeA = $this->service->upsertNode($team, 'customer', 'C-001', 'Customer', 'dot.crm');
        $nodeB = $this->service->upsertNode($team, 'invoice', 'INV-001', 'Invoice', 'dot.payments');
        $nodeC = $this->service->upsertNode($team, 'ticket', 'TKT-001', 'Ticket', 'dot.support');

        $this->service->connect($nodeA, $nodeB, 'has_invoice');
        $this->service->connect($nodeB, $nodeC, 'raised_ticket');

        $reachable = $this->service->traverse($team, 'customer', 'C-001', 3);

        $this->assertCount(2, $reachable);
        $ids = $reachable->pluck('id')->toArray();
        $this->assertContains($nodeB->id, $ids);
        $this->assertContains($nodeC->id, $ids);
    }

    public function test_traverse_returns_empty_for_unknown_entity(): void
    {
        $team = $this->team();
        $result = $this->service->traverse($team, 'customer', 'NONEXISTENT');

        $this->assertEmpty($result);
    }

    public function test_get_stats_returns_correct_counts(): void
    {
        $team = $this->team();
        $nodeA = $this->service->upsertNode($team, 'customer', 'C-001', 'Customer', 'dot.crm');
        $nodeB = $this->service->upsertNode($team, 'invoice', 'I-001', 'Invoice', 'dot.payments');
        $this->service->connect($nodeA, $nodeB, 'owns');

        $stats = $this->service->getStats($team);

        $this->assertEquals(2, $stats['node_count']);
        $this->assertEquals(1, $stats['edge_count']);
        $this->assertArrayHasKey('entity_types', $stats);
        $this->assertArrayHasKey('top_relationships', $stats);
    }

    public function test_explain_entity_returns_inbound_relationships(): void
    {
        $team = $this->team();
        $nodeA = $this->service->upsertNode($team, 'customer', 'C-001', 'Customer', 'dot.crm');
        $nodeB = $this->service->upsertNode($team, 'invoice', 'I-001', 'Invoice', 'dot.payments');
        $this->service->connect($nodeA, $nodeB, 'has');

        $explanation = $this->service->explainEntity($team, 'invoice', 'I-001');

        $this->assertNotEmpty($explanation);
        $this->assertEquals('customer', $explanation[0]['from_entity']);
        $this->assertEquals('has', $explanation[0]['relationship']);
    }
}
