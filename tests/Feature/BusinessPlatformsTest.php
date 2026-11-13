<?php

namespace Tests\Feature;

use App\Livewire\Analytics\BusinessPlatformsPanel;
use App\Models\User;
use App\Services\InfoDot\IdentityClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

/**
 * "Your business platforms": which Dot platforms a company has, read from InfoDot. Prepared and switched off. Whatever
 * InfoDot does (answers, refuses, hangs, sends nonsense), the workspace still loads, and nothing is ever shown as in use
 * unless InfoDot said so.
 */
class BusinessPlatformsTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private const COMPANIES = 'https://infodot.test/api/ecosystem/companies';

    private User $owner;

    private string $uuid;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secret = Str::random(40);
        config([
            'ecosystem.sso_only' => false,
            'ecosystem.platforms.infodot.url' => 'https://infodot.test',
            'ecosystem.infodot' => ['redeem_url' => 'https://infodot.test/api/ecosystem/redeem', 'platform' => 'analytics', 'secret' => $this->secret, 'timeout' => 2, 'provision' => true],
            'analytics.business_platforms.enabled' => true,
        ]);

        $this->owner = $this->ownerWithTeam();
        $this->uuid = $this->linkCompany($this->owner->currentTeam);
    }

    /** @return list<array<string, mixed>> */
    private function answer(): array
    {
        return [
            ['key' => 'files', 'name' => 'Dot.Files', 'status' => 'included', 'entitled' => true, 'is_core' => true],
            ['key' => 'hr', 'name' => 'Dot.HR', 'status' => 'active', 'entitled' => true, 'is_core' => false],
            ['key' => 'design', 'name' => 'Dot.Design', 'status' => 'trial', 'entitled' => true, 'is_core' => false],
            ['key' => 'forms', 'name' => 'Dot.Forms', 'status' => 'free_to_activate', 'entitled' => false, 'is_core' => false],
            ['key' => 'mines', 'name' => 'Dot.Mines', 'status' => 'managed_in_platform', 'entitled' => false, 'is_core' => false],
            ['key' => 'agents', 'name' => 'Dot.Agents', 'status' => 'payment_required', 'entitled' => false, 'is_core' => false],
            ['key' => 'pulse', 'name' => 'Dot.Pulse', 'status' => 'suspended', 'entitled' => true, 'is_core' => false],
            ['key' => 'press', 'name' => 'Dot.Press', 'status' => 'temporarily_unavailable', 'entitled' => true, 'is_core' => false],
            ['key' => 'tutor', 'name' => 'Dot.Tutor', 'status' => 'temporarily_unavailable', 'entitled' => false, 'is_core' => false],
            ['key' => 'ehail', 'name' => 'Dot.Ehail', 'status' => 'not_launched', 'entitled' => false, 'is_core' => false],
            // Contradictory or unknown answers must never read as "in use".
            ['key' => 'emall', 'name' => 'Dot.Emall', 'status' => 'active', 'entitled' => false, 'is_core' => false],
            ['key' => 'auction', 'name' => 'Dot.Auction', 'status' => 'something_new', 'entitled' => true, 'is_core' => false],
        ];
    }

    private function panel()
    {
        return Livewire::withoutLazyLoading()->actingAs($this->owner)->test(BusinessPlatformsPanel::class);
    }

    public function test_the_client_asks_infodot_for_one_company_with_this_platforms_secret(): void
    {
        Http::fake([self::COMPANIES.'/*' => Http::response(['platforms' => $this->answer()])]);

        $platforms = app(IdentityClient::class)->companyPlatforms($this->uuid);

        $this->assertCount(12, $platforms);
        $this->assertSame(['key' => 'files', 'name' => 'Dot.Files', 'status' => 'included', 'entitled' => true, 'is_core' => true], $platforms[0]);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === self::COMPANIES.'/'.$this->uuid.'/platforms'
            && $request->hasHeader('Authorization', 'Bearer '.$this->secret)
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_the_client_also_accepts_a_bare_list_and_an_explicit_address(): void
    {
        config(['ecosystem.infodot.companies_url' => 'https://id.infodot.test/v2/companies/']);
        Http::fake(['https://id.infodot.test/v2/companies/*' => Http::response($this->answer())]);

        $this->assertCount(12, app(IdentityClient::class)->companyPlatforms($this->uuid));
    }

    /** @return array<string, array{0: \Closure}> */
    public static function failures(): array
    {
        return [
            'company not known (404)' => [fn () => Http::response(['message' => 'Not found.'], 404)],
            'secret refused (401)' => [fn () => Http::response(['message' => 'Unauthenticated.'], 401)],
            'not entitled to ask (403)' => [fn () => Http::response([], 403)],
            'infodot erroring (500)' => [fn () => Http::response('Server Error', 500)],
            'rate limited (429)' => [fn () => Http::response([], 429)],
            'timeout' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out')],
            'body is not json' => [fn () => Http::response('<html>maintenance</html>', 200)],
            'body is a string' => [fn () => Http::response('"ok"', 200)],
            'body is an object, not a list' => [fn () => Http::response(['platforms' => ['files' => ['status' => 'active']]], 200)],
            'platforms is null' => [fn () => Http::response(['platforms' => null], 200)],
            'an entry has no status' => [fn () => Http::response(['platforms' => [['key' => 'files', 'name' => 'Dot.Files', 'entitled' => true]]], 200)],
            'entitled is not a boolean' => [fn () => Http::response(['platforms' => [['key' => 'files', 'name' => 'Dot.Files', 'status' => 'active', 'entitled' => 'yes']]], 200)],
            'a key that is not a key' => [fn () => Http::response(['platforms' => [['key' => '../admin', 'name' => 'X', 'status' => 'active', 'entitled' => true]]], 200)],
        ];
    }

    #[DataProvider('failures')]
    public function test_every_failure_is_a_null_never_an_exception_and_the_panel_says_it_could_not_load(\Closure $respond): void
    {
        Http::fake([self::COMPANIES.'/*' => $respond]);

        $this->assertNull(app(IdentityClient::class)->companyPlatforms($this->uuid));

        Cache::flush();
        $this->panel()
            ->assertOk()
            ->assertSee('Your platforms could not be loaded just now')
            ->assertSeeHtml('href="https://infodot.test/platforms"')
            ->assertDontSee('In use')->assertDontSee('Active')->assertDontSee('Included')
            ->assertDontSee('Dot.Files');

        $this->actingAs($this->owner)->get('/dashboard')->assertOk();
    }

    public function test_the_client_does_not_call_out_without_a_secret_or_for_something_that_is_not_a_company_id(): void
    {
        Http::fake();

        $this->assertNull(app(IdentityClient::class)->companyPlatforms('not-a-uuid'));
        $this->assertNull(app(IdentityClient::class)->companyPlatforms($this->uuid.'/../../admin'));

        config(['ecosystem.infodot.secret' => null]);
        $this->assertNull(app(IdentityClient::class)->companyPlatforms($this->uuid));

        Http::assertNothingSent();
    }

    public function test_the_panel_groups_platforms_and_links_each_to_its_page_in_infodot(): void
    {
        Http::fake([self::COMPANIES.'/*' => Http::response(['platforms' => $this->answer()])]);

        $html = $this->panel()->assertOk()->assertSee('Your business platforms')->assertSee('Source: InfoDot')->html();

        $groups = [];
        foreach (['in_use', 'available', 'attention'] as $group) {
            $this->assertSame(1, preg_match('/<div class="bp-group" data-group="'.$group.'">(.*?)<\/ul>/s', $html, $m), $group);
            preg_match_all('/data-platform="([a-z]+)"/', $m[1], $keys);
            $groups[$group] = $keys[1];
        }

        $this->assertSame(['files', 'hr', 'design'], $groups['in_use']);
        $this->assertSame(['forms', 'mines'], $groups['available']);
        $this->assertSame(['agents', 'pulse', 'press'], $groups['attention']);

        foreach (['ehail', 'emall', 'auction', 'tutor'] as $hidden) {
            $this->assertStringNotContainsString('data-platform="'.$hidden.'"', $html, $hidden.' is not launched, contradictory or unknown: it is not listed and never counted as in use');
        }

        $this->assertStringContainsString('href="https://infodot.test/platforms/hr"', $html);
        $this->assertStringContainsString('Included with every company', $html);
        $this->assertStringContainsString('Payment required', $html);
        $this->assertStringContainsString('Free to activate', $html);
    }

    public function test_an_answer_is_kept_for_a_while_and_a_failure_only_briefly(): void
    {
        Http::fake([self::COMPANIES.'/*' => Http::sequence()
            ->push('Server Error', 500)
            ->push(['platforms' => $this->answer()])
            ->push('Server Error', 500)]);

        $this->panel()->assertSee('could not be loaded just now');
        $this->panel()->assertSee('could not be loaded just now');
        Http::assertSentCount(1);

        $this->travel(61)->seconds();
        $this->panel()->assertSee('Dot.HR');
        $this->panel()->assertSee('Dot.HR');
        Http::assertSentCount(2);

        $this->travel(11)->minutes();
        $this->panel()->assertSee('could not be loaded just now')->assertDontSee('Dot.HR');
    }

    public function test_one_companys_answer_is_never_shown_to_another(): void
    {
        $other = $this->ownerWithTeam();
        $otherUuid = $this->linkCompany($other->currentTeam);
        Http::fake([
            self::COMPANIES.'/'.$this->uuid.'/platforms' => Http::response(['platforms' => $this->answer()]),
            self::COMPANIES.'/'.$otherUuid.'/platforms' => Http::response(['platforms' => [['key' => 'tasks', 'name' => 'Dot.Tasks', 'status' => 'included', 'entitled' => true, 'is_core' => true]]]),
        ]);

        $this->panel()->assertSee('Dot.HR');
        Livewire::withoutLazyLoading()->actingAs($other)->test(BusinessPlatformsPanel::class)->assertSee('Dot.Tasks')->assertDontSee('Dot.HR');
    }

    public function test_a_workspace_not_linked_to_infodot_asks_nothing_and_says_so(): void
    {
        Http::fake();
        $unlinked = $this->ownerWithTeam();

        Livewire::withoutLazyLoading()->actingAs($unlinked)->test(BusinessPlatformsPanel::class)->assertSee('not linked to a company in InfoDot');
        Http::assertNothingSent();
    }
}
