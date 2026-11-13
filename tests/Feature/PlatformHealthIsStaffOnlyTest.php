<?php

namespace Tests\Feature;

use App\Models\Intelligence\PlatformCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

/**
 * Whether each Dot platform answers is infrastructure health. It belongs to the people who run the platform
 * (the operate-platform gate), and no role inside a company, the owner included, can see it anywhere.
 */
class PlatformHealthIsStaffOnlyTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private const DOWN_URL = 'https://hr.infodot.co.za';

    protected function setUp(): void
    {
        parent::setUp();
        config(['ecosystem.sso_only' => false]);

        PlatformCheck::create(['key' => 'infodot', 'url' => 'https://infodot.co.za', 'status' => 200, 'checked_at' => now(), 'last_ok_at' => now()]);
        PlatformCheck::create(['key' => 'hr', 'url' => self::DOWN_URL, 'status' => 503, 'checked_at' => now(), 'last_ok_at' => now()->subDays(9)]);
    }

    /** @return array<string, array{0: string}> */
    public static function companyRoles(): array
    {
        return ['owner' => ['owner'], 'admin' => ['admin'], 'editor' => ['editor'], 'viewer' => ['viewer']];
    }

    #[DataProvider('companyRoles')]
    public function test_no_company_role_can_open_the_operate_area_or_the_detailed_health_check(string $role): void
    {
        $owner = $this->ownerWithTeam();
        $person = $role === 'owner' ? $owner : $this->memberOf($owner, $role);

        $this->assertFalse($person->can('operate-platform'));
        $this->actingAs($person)->get('/me/operate')->assertForbidden();
        $this->asApi($person)->getJson('/api/v1/health/detailed')->assertForbidden();
    }

    #[DataProvider('companyRoles')]
    public function test_no_workspace_page_shows_a_company_role_which_platforms_answer(string $role): void
    {
        $owner = $this->ownerWithTeam();
        $person = $role === 'owner' ? $owner : $this->memberOf($owner, $role);

        foreach (['/dashboard', '/workspace/figures', '/workspace/sources', '/workspace/insights', '/workspace/briefing', '/workspace/ask', '/workspace/reports', '/me'] as $path) {
            $html = $this->actingAs($person)->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString('hr 503', $html, $path);
            $this->assertStringNotContainsString('platform checks', strtolower($html), $path);
            $this->assertStringNotContainsString(route('me.operate'), $html, $path.' links to the operate area');
        }
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/me/operate')->assertRedirect();
        $this->getJson('/api/v1/health/detailed')->assertUnauthorized();
    }

    public function test_an_operator_sees_the_checks_and_being_an_operator_is_not_a_company_role(): void
    {
        $owner = $this->ownerWithTeam();
        $this->actingAs($owner)->get('/me/operate')->assertForbidden();

        $operator = User::factory()->create(['email_verified_at' => now()]);
        $operator->forceFill(['is_platform_operator' => true])->save();

        $this->actingAs($operator)->get('/me/operate')->assertOk()->assertSee('Open issues and platform checks')->assertSee('hr 503');
    }
}
