<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The browser-side Echo client is only emitted when real-time delivery is configured, only for
 * signed-in users, and never carries the Reverb app secret.
 */
class RealtimeClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_echo_client_is_rendered_when_realtime_is_off(): void
    {
        config(['echo.enabled' => false]);
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('laravel-echo', false)
            ->assertDontSee('cdn.jsdelivr.net', false)
            ->assertDontSee('window.Echo', false);
    }

    public function test_the_echo_client_subscribes_to_the_signed_in_users_own_channel_when_realtime_is_on(): void
    {
        config([
            'echo.enabled' => true,
            'echo.key' => 'public-app-key',
            'echo.host' => 'ws.example.test',
            'echo.port' => 8081,
            'echo.scheme' => 'http',
            'broadcasting.connections.reverb.secret' => 'reverb-app-secret-value',
        ]);
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();

        $response->assertSee('laravel-echo@', false)
            ->assertSee('pusher-js@', false)
            ->assertSee('public-app-key', false)
            ->assertSee('ws.example.test', false)
            ->assertSee('App.Models.User.'.$user->id, false)
            ->assertDontSee('reverb-app-secret-value', false);
    }

    public function test_guests_never_get_the_echo_client(): void
    {
        config(['echo.enabled' => true, 'echo.key' => 'public-app-key']);

        $this->get('/')->assertOk()->assertDontSee('laravel-echo', false);
    }
}
