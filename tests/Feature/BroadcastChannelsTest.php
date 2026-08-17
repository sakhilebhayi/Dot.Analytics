<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for a real production bug: routes/channels.php never
 * registered the 'App.Models.User.{id}' private channel that Laravel's
 * notification broadcasting uses by default
 * (Notifiable::receivesBroadcastNotificationsOn()), and that
 * layouts/app.blade.php subscribes every logged-in user to. Without an
 * authorization callback for it, POST /broadcasting/auth 403'd for every
 * subscription attempt -- the notification still broadcast fine
 * server-side, the browser's Echo client just could never actually
 * receive it. Found by dispatching a real notification while watching
 * the notification bell in a browser: the database row was created, but
 * the bell's unread badge never updated without a manual page reload.
 */
class BroadcastChannelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml forces BROADCAST_CONNECTION=null so the rest of the
        // suite never needs a live Reverb server -- but the null
        // broadcaster's auth() implementation doesn't actually invoke the
        // routes/channels.php callbacks at all, it just succeeds
        // unconditionally. That makes it useless for testing channel
        // authorization itself, so this test switches to the real
        // 'reverb' driver (Reverb speaks the Pusher protocol, so
        // computing the auth signature only needs key/secret/app_id
        // strings -- no live socket connection required). .env.testing
        // doesn't define REVERB_APP_KEY/SECRET/APP_ID (this app has no
        // published config/broadcasting.php for them to flow through
        // either), so they're supplied directly here -- their values
        // don't need to match anything real, the Pusher SDK just needs
        // non-null strings to compute the HMAC signature locally.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app-id',
        ]);

        // routes/channels.php's Broadcast::channel() calls already ran once
        // at boot, registering every channel pattern against whatever
        // driver was active then (the 'null' one, per phpunit.xml). Once
        // this default is switched above, BroadcastManager lazily builds a
        // brand-new, separate 'reverb' driver instance with an empty
        // channel registry -- the boot-time registrations never applied to
        // it. Re-requiring the file re-runs those same Broadcast::channel()
        // calls against the now-active driver. Without this, every private
        // channel appears unregistered and /broadcasting/auth 403s
        // regardless of what the authorization callback itself says.
        require base_path('routes/channels.php');
    }

    public function test_a_user_can_authorize_their_own_notification_channel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->post('/broadcasting/auth', [
            'channel_name' => "private-App.Models.User.{$user->id}",
            'socket_id' => '1234.5678',
        ]);

        $response->assertOk();
    }

    public function test_a_user_cannot_authorize_another_users_notification_channel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->post('/broadcasting/auth', [
            'channel_name' => "private-App.Models.User.{$otherUser->id}",
            'socket_id' => '1234.5678',
        ]);

        $response->assertForbidden();
    }
}
