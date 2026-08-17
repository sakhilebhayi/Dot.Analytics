<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Analytics\SearchPanel;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsDashboard;
use App\Models\CrossPlatformInsight;
use App\Models\IntelligenceNode;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SearchPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_one_character_query_returns_nothing_from_every_type(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        IntelligenceNode::factory()->create(['team_id' => $user->currentTeam->id, 'label' => 'A']);

        $component = Livewire::actingAs($user)->test(SearchPanel::class)->set('query', 'A');

        $this->assertTrue($component->get('nodes')->isEmpty());
    }

    public function test_a_real_query_finds_matches_across_multiple_types_simultaneously(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        IntelligenceNode::factory()->create(['team_id' => $user->currentTeam->id, 'label' => 'Meridian Freight']);
        AnalyticsAlert::factory()->create(['team_id' => $user->currentTeam->id, 'title' => 'Meridian route delayed']);
        CrossPlatformInsight::factory()->create(['team_id' => $user->currentTeam->id, 'title' => 'Unrelated insight']);

        $component = Livewire::actingAs($user)->test(SearchPanel::class)->set('query', 'Meridian');

        $this->assertCount(1, $component->get('nodes'));
        $this->assertCount(1, $component->get('alerts'));
        $this->assertCount(0, $component->get('insights'));
    }

    public function test_results_are_capped_at_ten_per_type(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Recommendation::factory()->count(15)->create(['team_id' => $user->currentTeam->id, 'title' => 'Reduce idle time on fleet']);

        $component = Livewire::actingAs($user)->test(SearchPanel::class)->set('query', 'idle time');

        $this->assertCount(10, $component->get('recommendations'));
    }

    public function test_dashboard_results_exclude_another_members_private_dashboard(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'title' => 'Ops Private View',
            'visibility' => 'private',
        ]);
        AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'title' => 'Ops Shared View',
            'visibility' => 'team',
        ]);

        $component = Livewire::actingAs($member)->test(SearchPanel::class)->set('query', 'Ops');

        $titles = $component->get('dashboards')->pluck('title');
        $this->assertFalse($titles->contains('Ops Private View'));
        $this->assertTrue($titles->contains('Ops Shared View'));
    }

    public function test_mount_accepts_an_initial_query_from_the_route(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        IntelligenceNode::factory()->create(['team_id' => $user->currentTeam->id, 'label' => 'Preloaded Match']);

        $component = Livewire::actingAs($user)->test(SearchPanel::class, ['query' => 'Preloaded']);

        $this->assertCount(1, $component->get('nodes'));
    }
}
