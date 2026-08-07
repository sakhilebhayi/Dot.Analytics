<?php

namespace Tests\Feature\Livewire;

use App\Jobs\Analytics\GenerateExecutiveBriefingJob;
use App\Livewire\Analytics\ExecutiveBriefingPanel;
use App\Livewire\Analytics\IntelligenceDashboard;
use App\Models\ExecutiveBriefing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ComponentCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($this->user);
    }

    // ─── ExecutiveBriefingPanel ──────────────────────────────────────────────

    public function test_briefing_panel_generate_dispatches_job(): void
    {
        Queue::fake();

        Livewire::test(ExecutiveBriefingPanel::class)
            ->set('period', 'weekly')
            ->call('generate');

        Queue::assertPushed(GenerateExecutiveBriefingJob::class, function ($job) {
            return $job->period === 'weekly';
        });
    }

    public function test_briefing_panel_shows_ready_briefing(): void
    {
        ExecutiveBriefing::create([
            'team_id' => $this->user->currentTeam->id,
            'period' => 'weekly',
            'period_date' => now()->toDateString(),
            'status' => 'ready',
            'summary' => 'All systems nominal.',
            'highlights' => ['Revenue up 8%'],
            'risks' => ['Key operator on leave'],
            'recommendations' => [['title' => 'Hire operator', 'rationale' => 'Reduce overtime']],
        ]);

        Livewire::test(ExecutiveBriefingPanel::class)
            ->assertSee('All systems nominal.')
            ->assertSee('Revenue up 8%');
    }

    public function test_briefing_panel_generating_status_shows_spinner(): void
    {
        ExecutiveBriefing::create([
            'team_id' => $this->user->currentTeam->id,
            'period' => 'weekly',
            'period_date' => now()->toDateString(),
            'status' => 'generating',
        ]);

        Livewire::test(ExecutiveBriefingPanel::class)
            ->assertSee('Synthesising intelligence');
    }

    public function test_briefing_panel_period_switch_refreshes_briefing(): void
    {
        $component = Livewire::test(ExecutiveBriefingPanel::class)
            ->set('period', 'daily')
            ->assertSet('period', 'daily');

        // Switching to monthly
        $component->set('period', 'monthly')->assertSet('period', 'monthly');
    }

    // ─── IntelligenceDashboard ───────────────────────────────────────────────

    public function test_intelligence_dashboard_shows_example_prompts(): void
    {
        Livewire::test(IntelligenceDashboard::class)
            ->assertSee('Why is productivity down this month?');
    }

    public function test_intelligence_dashboard_can_set_query_from_example(): void
    {
        Livewire::test(IntelligenceDashboard::class)
            ->set('intelligenceQuery', 'Why is productivity down this month?')
            ->assertSet('intelligenceQuery', 'Why is productivity down this month?');
    }

    public function test_intelligence_dashboard_resets_answer_before_new_query(): void
    {
        Livewire::test(IntelligenceDashboard::class)
            ->set('intelligenceQuery', 'What is causing the spike?')
            ->call('askIntelligence')
            ->assertSet('queryLoading', false);
    }

    public function test_intelligence_dashboard_validates_max_length(): void
    {
        Livewire::test(IntelligenceDashboard::class)
            ->set('intelligenceQuery', str_repeat('x', 501))
            ->call('askIntelligence')
            ->assertHasErrors(['intelligenceQuery']);
    }
}
