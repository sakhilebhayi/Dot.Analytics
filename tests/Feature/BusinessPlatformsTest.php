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

    /**
     * InfoDot's answer, in the shape its own tests pin (InfoDot tests/Feature/Platforms/CompanyPlatformsEndpointTest):
     * the caller, the company, and one row per discoverable platform. `access_mode` comes only on the caller's own row.
     *
     * @return array{platform: string, company: array{uuid: string, personal: bool}, platforms: list<array<string, mixed>>}
     */
    private function answer(?array $platforms = null, bool $personal = false): array
    {
        return [
            'platform' => 'analytics',
            'company' => ['uuid' => $this->uuid, 'personal' => $personal],
            'platforms' => $platforms ?? [
                ['key' => 'infodot', 'name' => 'InfoDot', 'status' => 'included', 'entitled' => true, 'is_core' => true],
                ['key' => 'finance', 'name' => 'Dot.Finance', 'status' => 'included', 'entitled' => true, 'is_core' => true],
                ['key' => 'analytics', 'name' => 'Dot.Analytics', 'status' => 'active', 'entitled' => true, 'is_core' => false, 'access_mode' => 'everyone'],
                ['key' => 'design', 'name' => 'Dot.Design', 'status' => 'trial', 'entitled' => true, 'is_core' => false],
                ['key' => 'projects', 'name' => 'Dot.Projects', 'status' => 'free_to_activate', 'entitled' => false, 'is_core' => false],
                ['key' => 'hr', 'name' => 'Dot.HR', 'status' => 'free_while_pricing_finalised', 'entitled' => false, 'is_core' => false],
                ['key' => 'agents', 'name' => 'Dot.Agents', 'status' => 'trial_available', 'entitled' => false, 'is_core' => false],
                ['key' => 'engage', 'name' => 'Dot.Engage', 'status' => 'available_for_purchase', 'entitled' => false, 'is_core' => false],
                ['key' => 'mines', 'name' => 'Dot.Mines', 'status' => 'managed_in_platform', 'entitled' => false, 'is_core' => false],
                ['key' => 'forms', 'name' => 'Dot.Forms', 'status' => 'activation_pending', 'entitled' => false, 'is_core' => false],
                ['key' => 'sheet', 'name' => 'Dot.Sheet', 'status' => 'payment_required', 'entitled' => false, 'is_core' => false],
                ['key' => 'docs', 'name' => 'Dot.docs', 'status' => 'subscription_expired', 'entitled' => false, 'is_core' => false],
                ['key' => 'pulse', 'name' => 'Dot.Pulse', 'status' => 'suspended', 'entitled' => false, 'is_core' => false],
                ['key' => 'press', 'name' => 'Dot.Press', 'status' => 'temporarily_unavailable', 'entitled' => true, 'is_core' => false],
                ['key' => 'tutor', 'name' => 'Dot.Tutor', 'status' => 'temporarily_unavailable', 'entitled' => false, 'is_core' => false],
                ['key' => 'ehail', 'name' => 'Dot.Ehail', 'status' => 'not_launched', 'entitled' => false, 'is_core' => false],
                // Contradictory or unknown answers must never read as "in use".
                ['key' => 'emall', 'name' => 'Dot.Emall', 'status' => 'active', 'entitled' => false, 'is_core' => false],
                ['key' => 'auction', 'name' => 'Dot.Auction', 'status' => 'something_new', 'entitled' => true, 'is_core' => false],
            ],
        ];
    }

    private function panel()
    {
        return Livewire::withoutLazyLoading()->actingAs($this->owner)->test(BusinessPlatformsPanel::class);
    }

    public function test_the_client_asks_infodot_for_one_company_with_this_platforms_secret(): void
    {
        Http::fake([self::COMPANIES.'/*' => Http::response($this->answer())]);

        $read = app(IdentityClient::class)->companyPlatforms($this->uuid);

        $this->assertFalse($read['personal']);
        $this->assertCount(18, $read['platforms']);
        $this->assertSame(['key' => 'finance', 'name' => 'Dot.Finance', 'status' => 'included', 'entitled' => true, 'is_core' => true], $read['platforms'][1]);
        $this->assertSame(['key', 'name', 'status', 'entitled', 'is_core'], array_keys($read['platforms'][2]), 'only what the panel needs is kept');
        // InfoDot refuses the call unless the caller names itself as well as proving its secret.
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === self::COMPANIES.'/'.$this->uuid.'/platforms?platform=analytics'
            && $request->hasHeader('X-Dot-Platform', 'analytics')
            && $request->hasHeader('Authorization', 'Bearer '.$this->secret)
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_the_address_can_be_set_outright(): void
    {
        config(['ecosystem.infodot.companies_url' => 'https://id.infodot.test/v2/companies/']);
        Http::fake(['https://id.infodot.test/v2/companies/*' => Http::response($this->answer())]);

        $this->assertCount(18, app(IdentityClient::class)->companyPlatforms($this->uuid)['platforms']);
    }

    /** @return array<string, array{0: \Closure}> */
    public static function failures(): array
    {
        $envelope = fn (mixed $platforms, mixed $company = ['uuid' => 'x', 'personal' => false]) => Http::response(['platform' => 'analytics', 'company' => $company, 'platforms' => $platforms], 200);

        return [
            'company not known (404)' => [fn () => Http::response(['error' => 'not_found'], 404)],
            'secret or caller name refused (401)' => [fn () => Http::response(['error' => 'invalid_request'], 401)],
            'this platform is switched off in InfoDot (403)' => [fn () => Http::response(['error' => 'invalid_request'], 403)],
            'infodot erroring (500)' => [fn () => Http::response('Server Error', 500)],
            'rate limited (429)' => [fn () => Http::response([], 429)],
            'timeout' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out')],
            'body is not json' => [fn () => Http::response('<html>maintenance</html>', 200)],
            'body is a string' => [fn () => Http::response('"ok"', 200)],
            'a bare list without the envelope' => [fn () => Http::response([['key' => 'files', 'name' => 'Dot.Files', 'status' => 'included', 'entitled' => true, 'is_core' => true]], 200)],
            'no company in the envelope' => [fn () => $envelope([['key' => 'files', 'name' => 'Dot.Files', 'status' => 'included', 'entitled' => true, 'is_core' => true]], null)],
            'platforms is an object, not a list' => [fn () => $envelope(['files' => ['status' => 'active']])],
            'platforms is null' => [fn () => $envelope(null)],
            'an entry has no status' => [fn () => $envelope([['key' => 'files', 'name' => 'Dot.Files', 'entitled' => true]])],
            'entitled is not a boolean' => [fn () => $envelope([['key' => 'files', 'name' => 'Dot.Files', 'status' => 'active', 'entitled' => 'yes']])],
            'a key that is not a key' => [fn () => $envelope([['key' => '../admin', 'name' => 'X', 'status' => 'active', 'entitled' => true]])],
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
            ->assertDontSee('Dot.Finance');

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
        Http::fake([self::COMPANIES.'/*' => Http::response($this->answer())]);

        $html = $this->panel()->assertOk()->assertSee('Your business platforms')->assertSee('Source: InfoDot')->html();

        $groups = [];
        foreach (['in_use', 'available', 'attention'] as $group) {
            $this->assertSame(1, preg_match('/<div class="bp-group" data-group="'.$group.'">(.*?)<\/ul>/s', $html, $m), $group);
            preg_match_all('/data-platform="([a-z]+)"/', $m[1], $keys);
            $groups[$group] = $keys[1];
        }

        $this->assertSame(['finance', 'analytics', 'design'], $groups['in_use']);
        $this->assertSame(['projects', 'hr', 'agents', 'engage', 'mines'], $groups['available']);
        $this->assertSame(['forms', 'sheet', 'docs', 'pulse', 'press'], $groups['attention']);

        foreach (['infodot', 'ehail', 'emall', 'auction', 'tutor'] as $hidden) {
            $this->assertStringNotContainsString('data-platform="'.$hidden.'"', $html, $hidden.' is not launched, contradictory or unknown: it is not listed and never counted as in use');
        }

        $this->assertStringContainsString('href="https://infodot.test/platforms/hr"', $html);
        foreach (['Included with every company', 'Active', 'On trial', 'Free to activate', 'Free while pricing is being finalised', 'Trial available', 'Available to buy',
            'Managed in the platform', 'Activation pending', 'Payment required', 'Subscription expired', 'Suspended', 'Temporarily unavailable'] as $label) {
            $this->assertStringContainsString('<span class="bp-state">'.$label.'</span>', $html);
        }
    }

    public function test_every_state_infodot_can_send_has_a_place_or_is_left_out_on_purpose(): void
    {
        // The states of InfoDot's PlatformAccess::businessState, as merged on its main branch.
        $real = ['included', 'active', 'trial', 'free_to_activate', 'free_while_pricing_finalised', 'trial_available', 'available_for_purchase',
            'activation_pending', 'payment_required', 'subscription_expired', 'suspended', 'temporarily_unavailable', 'not_launched', 'managed_in_platform', 'company_required'];

        $placed = array_merge(array_keys(BusinessPlatformsPanel::IN_USE), array_keys(BusinessPlatformsPanel::AVAILABLE), array_keys(BusinessPlatformsPanel::ATTENTION));

        $this->assertSame(count($placed), count(array_unique($placed)), 'a state belongs to one group');
        $this->assertEqualsCanonicalizing(['not_launched', BusinessPlatformsPanel::COMPANY_REQUIRED], array_values(array_diff($real, $placed)));
        $this->assertSame([], array_values(array_diff($placed, $real)), 'no group holds a state InfoDot never sends');
    }

    public function test_a_personal_workspace_gets_a_sentence_not_a_list(): void
    {
        // InfoDot's answer for a personal workspace: the core is included and everything else needs a company.
        Http::fake([self::COMPANIES.'/*' => Http::response($this->answer([
            ['key' => 'infodot', 'name' => 'InfoDot', 'status' => 'included', 'entitled' => true, 'is_core' => true],
            ['key' => 'files', 'name' => 'Dot.Files', 'status' => 'included', 'entitled' => true, 'is_core' => true],
            ['key' => 'forms', 'name' => 'Dot.Forms', 'status' => 'company_required', 'entitled' => false, 'is_core' => false],
            ['key' => 'analytics', 'name' => 'Dot.Analytics', 'status' => 'company_required', 'entitled' => false, 'is_core' => false, 'access_mode' => 'everyone'],
        ], personal: true))]);

        $this->panel()->assertOk()
            ->assertSee('This is a personal workspace')
            ->assertSeeHtml('href="https://infodot.test/platforms"')
            ->assertDontSeeHtml('class="bp-group"')
            ->assertDontSee('In use')->assertDontSee('Dot.Forms')->assertDontSee('could not be loaded');
    }

    public function test_an_answer_is_kept_for_a_while_and_a_failure_only_briefly(): void
    {
        Http::fake([self::COMPANIES.'/*' => Http::sequence()
            ->push('Server Error', 500)
            ->push($this->answer())
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
            self::COMPANIES.'/'.$this->uuid.'/platforms*' => Http::response($this->answer()),
            self::COMPANIES.'/'.$otherUuid.'/platforms*' => Http::response(['platform' => 'analytics', 'company' => ['uuid' => $otherUuid, 'personal' => false], 'platforms' => [['key' => 'tasks', 'name' => 'Dot.Tasks', 'status' => 'included', 'entitled' => true, 'is_core' => true]]]),
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
