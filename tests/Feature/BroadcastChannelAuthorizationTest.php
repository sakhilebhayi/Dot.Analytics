<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * routes/channels.php authorizes team.{teamId}.intelligence and
 * team.{teamId}.alerts against the requesting user's current team. This
 * exercises the real /broadcasting/auth endpoint, matching the pattern
 * established in Dot.Mines (see docs/DOT_REALTIME_STANDARD.md there).
 */
class BroadcastChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml forces BROADCAST_CONNECTION=null in the test env so
        // other tests never attempt a real network call -- but the null
        // driver's auth() is a no-op that authorizes everything
        // unconditionally. Switch to a real Pusher-protocol driver for
        // this test class, and re-require routes/channels.php afterward:
        // Broadcast::channel() registers against whichever driver instance
        // is current at call time, and that file already ran once against
        // the null driver during app bootstrap, before this setUp() runs.
        //
        // .env.testing has no REVERB_APP_KEY/SECRET/APP_ID (Laravel loads
        // .env.testing instead of .env entirely in the testing environment,
        // not merged on top of it), so the reverb connection needs explicit
        // credentials here or Pusher\Pusher's constructor rejects the null key.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app-id',
        ]);
        require base_path('routes/channels.php');
    }

    private function authRequest(User $user, string $channelName): TestResponse
    {
        return $this->actingAs($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channelName,
        ]);
    }

    public function test_team_member_can_authorize_intelligence_channel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->authRequest($user, "private-team.{$team->id}.intelligence")
            ->assertOk();
    }

    public function test_non_member_cannot_authorize_intelligence_channel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $outsider = User::factory()->withPersonalTeam()->create();

        $this->authRequest($outsider, "private-team.{$team->id}.intelligence")
            ->assertForbidden();
    }

    public function test_team_member_can_authorize_alerts_channel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->authRequest($user, "private-team.{$team->id}.alerts")
            ->assertOk();
    }

    public function test_non_member_cannot_authorize_alerts_channel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $outsider = User::factory()->withPersonalTeam()->create();

        $this->authRequest($outsider, "private-team.{$team->id}.alerts")
            ->assertForbidden();
    }

    public function test_non_numeric_identifier_fails_closed_rather_than_erroring(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->authRequest($user, 'private-team.not-a-number.intelligence')
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_authorize_any_private_channel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-team.{$team->id}.intelligence",
        ])->assertForbidden();
    }
}
