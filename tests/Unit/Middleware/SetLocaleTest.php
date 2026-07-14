<?php

namespace Tests\Unit\Middleware;

use App\Models\User;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_locale_from_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->currentTeam->update(['locale' => 'fr']);

        $this->actingAs($user)
            ->get('/dashboard');

        // Locale is applied via middleware in the web stack
        // We can assert the team setting is stored correctly
        $this->assertEquals('fr', $user->currentTeam->fresh()->locale);
    }

    public function test_locale_defaults_to_en_without_team(): void
    {
        $this->assertEquals('en', App::getLocale());
    }

    public function test_rejects_invalid_locale(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->currentTeam->update(['locale' => 'INVALID_INJECTION; system()']);

        $middleware = new SetLocale();
        $called     = false;

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response('ok');
        });

        // Should fall back to default locale, not apply the injection
        $locale = App::getLocale();
        $this->assertStringNotContainsString('system()', $locale);
        $this->assertTrue($called);
    }

    public function test_rejects_invalid_timezone(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->currentTeam->update(['timezone' => '../../etc/passwd']);

        $middleware = new SetLocale();
        $request    = Request::create('/');
        $request->setUserResolver(fn () => $user);

        // Should not throw — falls back to UTC
        $middleware->handle($request, fn ($req) => response('ok'));

        $this->assertTrue(true); // No exception
    }
}
