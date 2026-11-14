<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

/** The signed-in company workspace: one page for each thing the old single page piled together, in the library's design. */
class WorkspacePagesTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ecosystem.sso_only' => false]);
    }

    public function test_each_part_of_the_workspace_has_its_own_page_and_a_guest_is_sent_to_sign_in(): void
    {
        $owner = $this->ownerWithTeam();

        foreach (['/dashboard' => 'Overview', '/workspace/figures' => 'Figures', '/workspace/sources' => 'Sources', '/workspace/insights' => 'Insights', '/workspace/briefing' => 'Briefing', '/workspace/ask' => 'Ask', '/workspace/reports' => 'Reports', '/search' => 'Search'] as $path => $title) {
            $this->actingAs($owner)->get($path)->assertOk()->assertSee($title.' · Dot.Analytics', false);
        }

    }

    public function test_a_guest_is_sent_to_sign_in_from_every_workspace_page(): void
    {
        foreach (['/dashboard', '/workspace/figures', '/workspace/sources', '/workspace/insights', '/workspace/briefing', '/workspace/ask', '/workspace/reports'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_a_person_with_no_team_is_sent_to_create_one_from_every_workspace_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'current_team_id' => null]);

        foreach (['/dashboard', '/workspace/figures', '/workspace/sources', '/workspace/insights', '/workspace/briefing', '/workspace/ask', '/workspace/reports', '/search'] as $path) {
            $this->actingAs($user)->get($path)->assertRedirect(route('teams.create'));
        }
    }

    public function test_the_workspace_loads_nothing_from_a_third_party_script_and_uses_the_library_fonts(): void
    {
        $html = $this->actingAs($this->ownerWithTeam())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('Space+Grotesk', $html);
        $this->assertStringContainsString('ibm-plex-sans-var.woff2', $html);
        $this->assertStringContainsString('Skip to the content', $html);
        $this->assertStringContainsString(route('me.home'), $html, 'the personal library is one click from the workspace');
    }

    public function test_the_overview_states_what_it_knows_and_nothing_it_does_not(): void
    {
        $this->actingAs($this->ownerWithTeam())->get('/dashboard')->assertOk()
            ->assertSee('No figures yet')->assertSee('No platform reports to this workspace')->assertDontSee('Enterprise Intelligence');
        $this->actingAs($this->ownerWithTeam())->get('/workspace/figures')->assertOk()
            ->assertSee('platforms connected as data sources')->assertSee('Nothing is reporting yet');
    }

    public function test_the_sign_in_page_no_longer_offers_a_sign_up_that_does_not_exist_or_a_stock_photo(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Get started free')->assertDontSee('hero-dashboard')->assertSee('Accounts are made in InfoDot');
    }

    public function test_the_error_pages_use_the_library_design_and_still_name_the_ecosystem(): void
    {
        $this->get('/nothing-here')->assertNotFound()->assertSee('not in the library');
    }
}
