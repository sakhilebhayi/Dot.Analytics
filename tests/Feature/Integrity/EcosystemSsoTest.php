<?php

namespace Tests\Feature\Integrity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

class EcosystemSsoTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    /** InfoDot's launch page, as the browser reports it. */
    private function fromInfodot(): array
    {
        $infodot = parse_url((string) config('ecosystem.infodot.redeem_url', ''), PHP_URL_HOST);

        if (is_string($infodot) && $infodot !== '') {
            return ['Origin' => 'https://'.$infodot];
        }

        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        return ['Origin' => 'https://'.(str_contains($host, '.') ? substr($host, strpos($host, '.') + 1) : $host)];
    }

    private function ssoToken(User $user, array $abilities = ['ecosystem:read']): string
    {
        return $user->createToken('sso', $abilities)->plainTextToken;
    }

    public function test_route_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('ecosystem.auth'), ['token' => 'bogus'], $this->fromInfodot())->assertStatus(303)->assertRedirect('/login');
        }

        $this->post(route('ecosystem.auth'), ['token' => 'bogus'], $this->fromInfodot())->assertStatus(429);
    }

    public function test_session_id_is_regenerated_on_login(): void
    {
        $user = $this->ownerWithTeam();
        $this->startSession();
        $before = session()->getId();

        $this->post(route('ecosystem.auth'), ['token' => $this->ssoToken($user)], $this->fromInfodot())->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
    }

    public function test_current_team_is_set_when_user_has_none(): void
    {
        $user = $this->ownerWithTeam();
        $user->forceFill(['current_team_id' => null])->save();
        $this->assertNull($user->fresh()->current_team_id);

        $this->post(route('ecosystem.auth'), ['token' => $this->ssoToken($user)], $this->fromInfodot())->assertRedirect(route('dashboard'));

        $this->assertSame($user->personalTeam()->id, $user->fresh()->current_team_id);
    }

    public function test_existing_valid_current_team_is_kept(): void
    {
        $owner = $this->ownerWithTeam();
        $user = $this->ownerWithTeam();
        $owner->currentTeam->users()->attach($user, ['role' => 'editor']);
        $user->switchTeam($owner->currentTeam);

        $this->post(route('ecosystem.auth'), ['token' => $this->ssoToken($user)], $this->fromInfodot())->assertRedirect(route('dashboard'));

        $this->assertSame($owner->currentTeam->id, $user->fresh()->current_team_id);
    }

    public function test_token_is_single_use_and_requires_the_ability(): void
    {
        $user = $this->ownerWithTeam();
        $token = $this->ssoToken($user);

        $this->post(route('ecosystem.auth'), ['token' => $token], $this->fromInfodot())->assertRedirect();
        $this->app['auth']->guard('web')->logout();
        $this->post(route('ecosystem.auth'), ['token' => $token], $this->fromInfodot())->assertStatus(303)->assertRedirect('/login');
        $this->post(route('ecosystem.auth'), ['token' => $this->ssoToken($user, ['other'])], $this->fromInfodot())->assertStatus(303)->assertRedirect('/login');
        $this->post(route('ecosystem.auth'), ['token' => ['x']], $this->fromInfodot())->assertStatus(303)->assertRedirect('/login');
    }
}
