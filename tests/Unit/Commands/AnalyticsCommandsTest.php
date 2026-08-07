<?php

namespace Tests\Unit\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyticsCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_engines_command_outputs_dispatched_count(): void
    {
        Queue::fake();
        User::factory()->withPersonalTeam()->create();

        $this->artisan('analytics:run-engines')
            ->assertExitCode(0)
            ->expectsOutputToContain('Total engines dispatched');
    }

    public function test_run_engines_command_accepts_team_option(): void
    {
        Queue::fake();
        $user = User::factory()->withPersonalTeam()->create();

        $this->artisan('analytics:run-engines', ['--team' => $user->currentTeam->id])
            ->assertExitCode(0);
    }

    public function test_generate_briefings_command_runs_for_weekly(): void
    {
        Queue::fake();
        User::factory()->withPersonalTeam()->create();

        $this->artisan('analytics:briefings', ['period' => 'weekly'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Briefings queued');
    }

    public function test_generate_briefings_command_rejects_invalid_period(): void
    {
        $this->artisan('analytics:briefings', ['period' => 'invalid'])
            ->assertExitCode(1);
    }

    public function test_recompute_dna_command_runs_successfully(): void
    {
        User::factory()->withPersonalTeam()->create();

        $this->artisan('analytics:recompute-dna')
            ->assertExitCode(0)
            ->expectsOutputToContain('DNA recomputed');
    }

    public function test_recompute_dna_accepts_team_option(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->artisan('analytics:recompute-dna', ['--team' => $user->currentTeam->id])
            ->assertExitCode(0);
    }
}
