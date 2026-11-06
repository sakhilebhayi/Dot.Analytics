<?php

namespace Tests\Feature\Intelligence;

use App\Mail\LibraryDigest;
use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Item;
use App\Models\Intelligence\Observation;
use App\Models\Intelligence\Signal;
use App\Models\Intelligence\Source;
use App\Models\Library\Alert;
use App\Models\Library\Preference;
use App\Models\Library\Watch;
use App\Models\User;
use App\Services\Intelligence\Classifier;
use App\Services\Intelligence\ReferenceData;
use App\Services\Library\AlertScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/** What a signed-in person keeps, and that it stays theirs. */
class MyLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ReferenceData::class)->install();
        config(['ecosystem.sso_only' => false]);
    }

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function item(string $title, $collectedAt = null): Item
    {
        return Item::create(['uuid' => (string) Str::uuid(), 'source_id' => Source::where('key', 'federal-reserve')->value('id'), 'kind' => 'announcement', 'title' => $title, 'source_summary' => null, 'url' => 'https://www.federalreserve.gov/x/'.Str::random(8), 'url_hash' => hash('sha256', Str::random(20)), 'title_hash' => hash('sha256', $title), 'published_at' => now(), 'collected_at' => $collectedAt ?? now()]);
    }

    private function reading(string $key, string $place = 'ZAF'): Indicator
    {
        $i = Indicator::where('key', $key)->firstOrFail();
        Observation::create(['indicator_id' => $i->id, 'geography_id' => Geography::where('code', $place)->value('id'), 'period' => now()->subMonths(2)->startOfMonth(), 'value' => 3.2, 'first_seen_at' => now(), 'last_seen_at' => now()]);

        return $i;
    }

    public function test_a_visitor_is_sent_to_sign_in_and_the_page_is_never_cached_or_public(): void
    {
        $this->get('/me')->assertRedirect();
        $this->get('/me/watchlist')->assertRedirect();
        $this->get('/me/export')->assertRedirect();
    }

    public function test_watching_needs_a_confirm_step_so_a_public_page_never_needs_a_session(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/me/watch?kind=industry&key=manufacturing')->assertOk()->assertSee('Watch Manufacturing?');
        $this->assertSame(0, $user->libraryWatches()->count(), 'a GET changes nothing');

        $this->actingAs($user)->post('/me/watch', ['kind' => 'industry', 'key' => 'manufacturing'])->assertRedirect('/me');
        $this->assertSame(1, $user->libraryWatches()->count());

        $this->actingAs($user)->post('/me/watch', ['kind' => 'industry', 'key' => 'manufacturing']);
        $this->assertSame(1, $user->libraryWatches()->count(), 'watching twice is one watch');
    }

    public function test_only_things_that_exist_in_the_library_can_be_watched(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/me/watch', ['kind' => 'industry', 'key' => 'not-real'])->assertNotFound();
        $this->actingAs($user)->post('/me/watch', ['kind' => 'indicator', 'key' => 'wb.gdp-growth', 'place' => 'ZAF'])->assertNotFound();
        $this->actingAs($user)->post('/me/watch', ['kind' => 'bogus', 'key' => 'x'])->assertSessionHasErrors('kind');

        $this->reading('wb.gdp-growth');
        $this->actingAs($user)->post('/me/watch', ['kind' => 'indicator', 'key' => 'wb.gdp-growth', 'place' => 'ZAF'])->assertRedirect('/me');
        $this->assertSame(1, $user->libraryWatches()->count());

        Indicator::where('key', 'wb.gdp-growth')->update(['is_published' => false]);
        $this->actingAs($user)->get('/me/watchlist')->assertOk()->assertSee('no longer in the library');
    }

    public function test_the_watchlist_has_a_limit_and_says_so(): void
    {
        config(['intelligence_plans.plans.free.watches' => 2]);
        $user = $this->user();

        foreach (['manufacturing', 'agriculture', 'energy'] as $slug) {
            $this->actingAs($user)->post('/me/watch', ['kind' => 'industry', 'key' => $slug]);
        }

        $this->assertSame(2, $user->libraryWatches()->count());
        $this->actingAs($user)->get('/me/watch?kind=industry&key=healthcare')->assertSee('watchlist is full');
    }

    public function test_one_person_cannot_see_or_remove_another_persons_library(): void
    {
        $a = $this->user();
        $b = $this->user();
        $this->actingAs($a)->post('/me/watch', ['kind' => 'industry', 'key' => 'energy']);
        $watch = $a->libraryWatches()->firstOrFail();
        $a->librarySaved()->create(['kind' => 'search', 'ref' => 'secret question', 'title' => 'Search: secret question']);

        $this->actingAs($b)->delete('/me/watch/'.$watch->id);
        $this->assertSame(1, $a->libraryWatches()->count());
        $this->actingAs($b)->get('/me/watchlist')->assertDontSee('Energy');
        $this->actingAs($b)->get('/me/saved')->assertDontSee('secret question');
        $this->actingAs($b)->get('/me/export')->assertDontSee('secret question');
    }

    public function test_an_alert_is_written_once_and_only_for_what_is_watched(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/me/watch', ['kind' => 'industry', 'key' => 'mining-and-minerals']);

        $mining = $this->item('South Africa gold mining output rises');
        $this->item('A speech on the weather');
        app(Classifier::class)->classifyPending();

        $this->assertSame(1, app(AlertScanner::class)->scan($user));
        $this->assertSame(0, app(AlertScanner::class)->scan($user), 'the same thing is never told twice');

        $alert = Alert::firstOrFail();
        $this->assertSame('development', $alert->kind);
        $this->assertStringContainsString('gold mining', $alert->title);
        $this->assertStringContainsString($mining->uuid, $alert->href);
    }

    public function test_a_movement_in_a_watched_figure_becomes_an_alert_with_its_reason(): void
    {
        $user = $this->user();
        $i = $this->reading('wb.inflation');
        $this->actingAs($user)->post('/me/watch', ['kind' => 'indicator', 'key' => 'wb.inflation', 'place' => 'ZAF']);

        Signal::create(['indicator_id' => $i->id, 'geography_id' => Geography::where('code', 'ZAF')->value('id'), 'period' => now()->startOfMonth(), 'kind' => 'rise', 'value' => 9.1, 'compared_to' => 5.0, 'size' => 3.2, 'detected_at' => now()]);

        $this->assertSame(1, app(AlertScanner::class)->scan($user));
        $this->assertStringContainsString('larger rise than usual', Alert::firstOrFail()->title);
        $this->actingAs($user)->get('/me/alerts')->assertSee('Moved more than usual');
    }

    public function test_alerts_are_kept_to_what_the_plan_allows(): void
    {
        config(['intelligence_plans.plans.free.alerts_kept' => 3]);
        $user = $this->user();
        $this->actingAs($user)->post('/me/watch', ['kind' => 'industry', 'key' => 'mining-and-minerals']);

        foreach (range(1, 6) as $n) {
            $this->item("Gold mining output item {$n}");
        }

        app(Classifier::class)->classifyPending();
        app(AlertScanner::class)->scan($user);

        $this->assertSame(3, Alert::where('user_id', $user->id)->count());
    }

    public function test_the_digest_is_off_until_asked_for_and_never_sent_empty(): void
    {
        Mail::fake();
        $user = $this->user();
        $this->actingAs($user)->post('/me/watch', ['kind' => 'industry', 'key' => 'mining-and-minerals']);

        $this->assertSame('off', $user->libraryPreference->digest);
        $this->artisan('library:digest weekly')->assertSuccessful();
        Mail::assertNothingSent();

        $this->actingAs($user)->put('/me/preferences', ['digest' => 'weekly']);
        $this->artisan('library:digest weekly')->expectsOutputToContain('0 sent, 1 had nothing to say')->assertSuccessful();
        Mail::assertNothingSent();

        $this->item('Gold mining output rises');
        app(Classifier::class)->classifyPending();
        Preference::where('user_id', $user->id)->update(['last_digest_at' => now()->subDay(), 'last_scan_at' => now()->subDay()]);
        app(AlertScanner::class)->scan($user);

        $this->artisan('library:digest weekly')->expectsOutputToContain('1 sent')->assertSuccessful();
        Mail::assertSent(LibraryDigest::class, fn ($m) => $m->hasTo($user->email) && str_contains($m->stopUrl, 'signature='));
    }

    public function test_the_email_link_turns_the_digest_off_without_signing_in_and_a_forged_link_does_nothing(): void
    {
        $user = $this->user();
        Preference::create(['user_id' => $user->id, 'digest' => 'daily']);

        $this->get('/me/digest/stop/'.$user->id)->assertForbidden();
        $this->assertSame('daily', $user->libraryPreference()->first()->digest);

        $url = URL::signedRoute('me.digest.stop', ['user' => $user->id]);
        $this->get($url)->assertOk()->assertSee('The digest is off');
        $this->assertSame('off', $user->libraryPreference()->first()->digest);
    }

    public function test_a_person_can_download_and_erase_everything_the_library_keeps(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/me/watch', ['kind' => 'industry', 'key' => 'energy']);
        $user->libraryAlerts()->create(['kind' => 'development', 'ref' => 'x', 'title' => 'T', 'href' => 'https://example.test/']);

        $this->actingAs($user)->get('/me/export')->assertOk()->assertJsonPath('watching.0.key', 'energy')->assertJsonPath('alerts.0.title', 'T');

        $this->actingAs($user)->delete('/me/data', ['confirm' => 'nope'])->assertSessionHasErrors('confirm');
        $this->assertSame(1, $user->libraryWatches()->count());

        $this->actingAs($user)->delete('/me/data', ['confirm' => 'erase'])->assertRedirect('/me');
        $this->assertSame([0, 0, 0], [$user->libraryWatches()->count(), $user->libraryAlerts()->count(), $user->libraryPreference()->count()]);
    }

    public function test_deleting_an_account_removes_its_library_data(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/me/watch', ['kind' => 'industry', 'key' => 'energy']);
        $user->delete();

        $this->assertSame(0, Watch::count());
        $this->assertSame(0, Preference::count());
    }

    public function test_signed_in_library_pages_carry_the_strict_policy_and_the_plans_page_prices_nothing(): void
    {
        $response = $this->actingAs($this->user())->get('/me');
        $response->assertOk();
        $this->assertStringNotContainsString("'unsafe-inline'", $response->headers->get('Content-Security-Policy'));

        $plans = $this->get('/plans')->assertOk()->assertSee('Reading is free')->assertSee('not on sale');
        $this->assertDoesNotMatchRegularExpression('/R\s?\d|\$\s?\d|per month/i', $plans->getContent());
    }

    public function test_no_plan_can_gate_what_is_published(): void
    {
        foreach (config('intelligence_plans.plans') as $plan) {
            $extra = array_diff(array_keys($plan), ['name', 'for_sale', 'watches', 'saved', 'alerts_kept', 'digest']);
            $this->assertSame([], array_values($extra), 'a plan limits what a person keeps, nothing else');
        }
    }

    public function test_saving_and_every_library_page_render(): void
    {
        $user = $this->user();
        $item = $this->item('Saved headline');

        $this->actingAs($user)->get('/me/save?kind=item&ref='.$item->uuid)->assertOk()->assertSee('Saved headline');
        $this->actingAs($user)->post('/me/save', ['kind' => 'item', 'ref' => $item->uuid])->assertRedirect('/me/saved');
        $this->actingAs($user)->post('/me/save', ['kind' => 'search', 'ref' => 'repo rate'])->assertRedirect('/me/saved');
        $this->actingAs($user)->get('/me/saved')->assertOk()->assertSee('Saved headline')->assertSee('Search: repo rate');
        $this->actingAs($user)->get('/me/save?kind=item&ref=nope')->assertNotFound();

        foreach (['/me', '/me/watchlist', '/me/alerts', '/me/preferences'] as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }

        $this->actingAs($user)->post('/me/alerts/read')->assertRedirect('/me/alerts');
        $this->actingAs($user)->delete('/me/saved/'.$user->librarySaved()->firstOrFail()->id)->assertRedirect('/me/saved');
    }
}
