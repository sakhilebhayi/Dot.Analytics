<?php

namespace Tests\Feature\Integrity;

use App\Jobs\Analytics\GenerateExecutiveBriefingJob;
use App\Livewire\Analytics\CrossPlatformInsightPanel;
use App\Livewire\Analytics\ExecutiveBriefingPanel;
use App\Models\CrossPlatformInsight;
use App\Models\ExecutiveBriefing;
use App\Services\IntelligenceEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsTeams;
use Tests\Support\FakeAiRouter;
use Tests\TestCase;

class BriefingIntegrityTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private function run_(int $teamId, FakeAiRouter $router): void
    {
        (new GenerateExecutiveBriefingJob($teamId, 'weekly', now()->toDateString()))
            ->handle($router, app(IntelligenceEngineService::class));
    }

    public function test_no_provider_stores_an_unavailable_briefing_not_a_ready_one(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        CrossPlatformInsight::factory()->create(['team_id' => $team->id]);
        $router = new FakeAiRouter(configured: false);

        $this->run_($team->id, $router);

        $briefing = ExecutiveBriefing::where('team_id', $team->id)->firstOrFail();
        $this->assertSame('unavailable', $briefing->status);
        $this->assertNull($briefing->summary);
        $this->assertNotEmpty($briefing->status_reason);
        $this->assertSame([], $router->prompts);
        $this->assertStringNotContainsString('Intelligence briefing generated', (string) $briefing->summary);
    }

    public function test_no_insights_means_unavailable_and_no_model_call(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        $router = new FakeAiRouter(response: json_encode(['summary' => 'made up']));

        $this->run_($team->id, $router);

        $this->assertSame('unavailable', ExecutiveBriefing::firstOrFail()->status);
        $this->assertSame([], $router->prompts);
    }

    public function test_unparseable_model_output_is_not_stored_as_a_briefing(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        CrossPlatformInsight::factory()->create(['team_id' => $team->id]);

        $this->run_($team->id, new FakeAiRouter(response: 'not json at all'));

        $briefing = ExecutiveBriefing::firstOrFail();
        $this->assertSame('unavailable', $briefing->status);
        $this->assertNull($briefing->summary);
    }

    public function test_real_insights_produce_a_ready_briefing_with_provenance(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        $insight = CrossPlatformInsight::factory()->create(['team_id' => $team->id]);
        $router = new FakeAiRouter(response: json_encode([
            'summary' => 'Reviews are trending down.',
            'highlights' => ['Support backlog cleared'],
            'risks' => ['Churn risk'],
            'recommendations' => [['title' => 'Call top accounts', 'rationale' => 'x', 'priority' => 'high']],
        ]));

        $this->run_($team->id, $router);

        $briefing = ExecutiveBriefing::firstOrFail();
        $this->assertSame('ready', $briefing->status);
        $this->assertSame('Reviews are trending down.', $briefing->summary);
        $this->assertStringContainsString($insight->title, $router->prompts[0]);
        $this->assertSame([$insight->id], $briefing->provenance['insight_ids']);
    }

    public function test_unavailable_briefing_can_be_regenerated_once_a_provider_exists(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        CrossPlatformInsight::factory()->create(['team_id' => $team->id]);

        $this->run_($team->id, new FakeAiRouter(configured: false));
        $this->run_($team->id, new FakeAiRouter(response: json_encode(['summary' => 'Real summary.'])));

        $this->assertSame(1, ExecutiveBriefing::count());
        $this->assertSame('ready', ExecutiveBriefing::firstOrFail()->status);
    }

    public function test_panel_displays_unavailable_state_with_reason(): void
    {
        $owner = $this->ownerWithTeam();
        ExecutiveBriefing::create([
            'team_id' => $owner->currentTeam->id,
            'period' => 'weekly',
            'period_date' => now()->toDateString(),
            'status' => 'unavailable',
            'status_reason' => 'No AI provider is configured.',
        ]);

        Livewire::actingAs($owner)->test(ExecutiveBriefingPanel::class)
            ->assertSee('Briefing unavailable')
            ->assertSee('No AI provider is configured.')
            ->assertDontSee('Intelligence briefing generated');
    }

    public function test_empty_states_say_no_data_connected_yet(): void
    {
        $owner = $this->ownerWithTeam();

        Livewire::actingAs($owner)->test(CrossPlatformInsightPanel::class)
            ->assertSee('No data connected yet');
        Livewire::actingAs($owner)->test(ExecutiveBriefingPanel::class)
            ->assertSee('No data connected yet');
    }
}
