<?php

namespace Tests\Feature\Sso;

use App\Mail\SsoClaimCodeMail;
use App\Models\Team;
use App\Models\User;
use App\Sso\AnalyticsProvisioner;
use Dot\SsoClient\Tests\Support\FakeIdp;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** R-54 rollout: Dot.Analytics behind DOTSSO_ENABLED. Off, nothing changes; on, every entry goes to InfoDot. */
class CentralSignInRolloutTest extends TestCase
{
    use RefreshDatabase;

    private FakeIdp $idp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idp = (new FakeIdp)->install();
        config([
            'dotsso.provisioner' => AnalyticsProvisioner::class,
            'dotsso.enabled' => false,
            'ecosystem.sso_only' => false,
        ]);
        Mail::fake();
    }

    private function on(): void
    {
        config(['dotsso.enabled' => true]);
    }

    private function signInVia(array $claims = [], array $session = [])
    {
        $response = $this->withSession($session)->get('/sso/login')->assertRedirect();
        $authorize = FakeIdp::authorizeParams($response->headers->get('Location'));
        $code = $this->idp->approve($authorize, $claims);

        return $this->get('/sso/callback?'.http_build_query(['code' => $code, 'state' => $authorize['state']]));
    }

    public function test_the_users_table_has_a_nullable_unique_infodot_user_uuid(): void
    {
        $a = User::factory()->create();
        $this->assertNull($a->infodot_user_uuid);
        User::factory()->create();

        $a->forceFill(['infodot_user_uuid' => 'x'])->save();
        $this->expectException(QueryException::class);
        User::factory()->create()->forceFill(['infodot_user_uuid' => 'x'])->save();
    }

    public function test_with_the_flag_off_nothing_changes(): void
    {
        $this->get('/login')->assertOk()->assertSee('Keep me signed in');
        $this->get('/sso/login')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('/sso/login', false);
    }

    public function test_with_the_flag_on_the_local_pages_go_to_infodot_sign_in(): void
    {
        $this->on();

        $this->get('/login')->assertRedirect(route('dotsso.login'));
        $this->get('/register')->assertRedirect(route('dotsso.login', ['screen' => 'register']));
        $this->get('/forgot-password')->assertRedirect(route('dotsso.login'));
        $this->post('/login', ['email' => 'a@b.test', 'password' => 'x'])->assertRedirect(route('dotsso.login'));
    }

    public function test_the_signed_out_pages_and_mail_link_straight_to_the_central_sign_in(): void
    {
        $this->on();

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString(route('dotsso.login', ['screen' => 'register']), $html, 'the public header offers Create account');
        $this->get('/login')->assertRedirect(route('dotsso.login'));
    }

    public function test_the_break_glass_switch_brings_the_local_login_back(): void
    {
        $this->on();
        config(['dotsso.allow_local_login' => true]);

        $this->get('/login')->assertOk()->assertSee('Keep me signed in');
    }

    public function test_a_signed_out_deep_link_survives_the_round_trip(): void
    {
        $this->on();
        $this->get('/me/watchlist')->assertRedirect('/login');
        $this->get('/login')->assertRedirect(route('dotsso.login'));

        $this->signInVia()->assertRedirect('/me/watchlist');
        $this->assertAuthenticated();
    }

    public function test_a_first_sign_in_creates_the_account_with_the_infodot_id_and_a_personal_team(): void
    {
        $this->on();

        $this->signInVia(['sub' => 'infodot-uuid-9', 'email' => 'new@example.test', 'name' => 'Nia New'])->assertRedirect('/dashboard');

        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertSame('infodot-uuid-9', $user->infodot_user_uuid);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Team::where('user_id', $user->id)->where('personal_team', true)->exists());
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_central_sign_in_stamps_the_twelve_hour_recheck_so_it_does_not_loop(): void
    {
        $this->on();
        config(['ecosystem.sso_only' => true]);

        $this->signInVia(['sub' => 'infodot-uuid-9', 'email' => 'new@example.test'])->assertRedirect('/dashboard');

        $this->assertEqualsWithDelta(now()->getTimestamp(), session('infodot_verified_at'), 5);
        // Signed in and not sent round again.
        $this->get('/dashboard')->assertOk();
    }

    public function test_provisioning_can_be_switched_off_like_the_old_receiver(): void
    {
        $this->on();
        config(['dotsso.auto_provision' => false]);

        $this->signInVia(['sub' => 'infodot-uuid-9', 'email' => 'nobody@example.test'])->assertForbidden();
        $this->assertGuest();
        $this->assertNull(User::where('email', 'nobody@example.test')->first());
    }

    public function test_an_unverified_local_user_claims_with_a_code_and_keeps_data_and_password(): void
    {
        $this->on();
        $user = User::factory()->withPersonalTeam()->create(['email' => 'ada@example.test', 'email_verified_at' => null, 'password' => Hash::make('old-password-1')]);
        $team = $user->ownedTeams()->first();
        $hash = $user->password;

        $this->signInVia(['sub' => 'infodot-uuid-5'])->assertRedirect(route('dotsso.claim'));
        $this->assertGuest();

        $sent = null;
        Mail::assertSent(SsoClaimCodeMail::class, function ($m) use (&$sent) {
            $sent = $m->code;

            return $m->hasTo('ada@example.test');
        });

        $this->get('/sso/claim')->assertOk()->assertSee('Confirm it is you')->assertDontSee($sent);
        $this->post('/sso/claim', ['code' => $sent])->assertRedirect('/dashboard');

        $user->refresh();
        $this->assertSame('infodot-uuid-5', $user->infodot_user_uuid);
        $this->assertSame($hash, $user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($team->fresh()->is($user->ownedTeams()->first()), 'team untouched');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_claim_code_signs_nobody_in(): void
    {
        $this->on();
        User::factory()->create(['email' => 'ada@example.test', 'email_verified_at' => null]);
        $this->signInVia(['sub' => 'infodot-uuid-5'])->assertRedirect(route('dotsso.claim'));

        $this->from('/sso/claim')->post('/sso/claim', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_the_claim_page_is_one_form_with_a_labelled_code_field_and_real_buttons(): void
    {
        $this->on();
        User::factory()->create(['email' => 'ada@example.test', 'email_verified_at' => null]);
        $this->signInVia()->assertRedirect(route('dotsso.claim'));

        $html = $this->get('/sso/claim')->assertOk()->getContent();

        $this->assertStringContainsString('autocomplete="one-time-code"', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertMatchesRegularExpression('/<label[^>]+for="code"/', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    /** The gap R-54 closes: a passkey sign-in authenticates a session like a password, and was missing from the lock. */

    /** The gap R-54 closes: under the old lock the reset POSTs still ran (mail sent, a local hash changed). */
    public function test_the_password_reset_posts_are_refused_under_the_old_lock(): void
    {
        config(['ecosystem.sso_only' => true]);
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertNotFound();
        $this->post('/reset-password', ['token' => 'x', 'email' => $user->email, 'password' => 'N3w-passw0rd-9!', 'password_confirmation' => 'N3w-passw0rd-9!'])->assertNotFound();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_the_password_reset_posts_are_refused_when_the_central_sign_in_is_on(): void
    {
        $this->on();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect(route('dotsso.login'));
        $this->post('/reset-password', ['token' => 'x', 'email' => $user->email, 'password' => 'N3w-passw0rd-9!', 'password_confirmation' => 'N3w-passw0rd-9!'])->assertRedirect(route('dotsso.login'));
    }

    public function test_the_reset_posts_are_untouched_while_both_locks_are_off(): void
    {
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_the_public_library_stays_public_while_sso_is_on(): void
    {
        $this->on();

        $this->get('/')->assertOk();
        $this->get('/cookies')->assertOk();
    }

    public function test_a_bearer_token_api_call_works_while_sso_is_on(): void
    {
        $this->on();
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->getJson('/api/user')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/user')->assertOk();
    }

    public function test_the_ingest_door_the_signed_unsubscribe_link_and_the_health_check_are_unaffected(): void
    {
        $this->on();
        $user = User::factory()->create();

        // Unsigned ingest: refused by its own signature check, never sent to a sign-in page.
        $r = $this->postJson('/api/ingest/snapshots', []);
        $this->assertContains($r->getStatusCode(), [401, 403, 422]);
        $this->assertStringNotContainsString('sso', (string) $r->headers->get('Location'));

        $this->get(URL::signedRoute('me.digest.stop', ['user' => $user->id]))->assertSuccessful();
        $this->get('/up')->assertOk();
        $this->get('/auth/ecosystem')->assertRedirect('/login');
    }
}
