<?php

namespace Tests\Feature\Integrity;

use App\Livewire\Analytics\FeatureFlagsPanel;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\FeatureFlagService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

/**
 * FeatureFlag is a global table: only platform operators may mutate it.
 * Owning a team (even being admin of one) must not grant that power.
 */
class FeatureFlagOperatorTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private function operator(): User
    {
        $user = $this->ownerWithTeam();
        $user->forceFill(['is_platform_operator' => true])->save();

        return $user->fresh();
    }

    public function test_is_platform_operator_defaults_false_and_is_not_mass_assignable(): void
    {
        $user = User::create([
            'name' => 'Probe', 'email' => 'probe@example.com', 'password' => 'secret-pass-123',
            'is_platform_operator' => true,
        ]);

        $this->assertFalse($user->fresh()->is_platform_operator);
        $this->assertFalse($this->ownerWithTeam()->fresh()->is_platform_operator);
    }

    public function test_gate_is_true_only_for_operators(): void
    {
        $this->assertTrue($this->operator()->can('manage-feature-flags'));
        $this->assertFalse($this->ownerWithTeam()->can('manage-feature-flags'));
    }

    public function test_team_owner_cannot_mutate_flags_over_the_api(): void
    {
        $owner = $this->ownerWithTeam();
        $token = $owner->createToken('t')->plainTextToken;
        FeatureFlag::create(['key' => 'global-flag', 'name' => 'Global']);

        $this->withToken($token)->postJson('/api/v1/feature-flags', ['key' => 'mine', 'name' => 'Mine'])->assertForbidden();
        $this->withToken($token)->patchJson('/api/v1/feature-flags/global-flag/enable')->assertForbidden();
        $this->withToken($token)->patchJson('/api/v1/feature-flags/global-flag/disable')->assertForbidden();
        $this->withToken($token)->patchJson('/api/v1/feature-flags/global-flag/rollout', ['percentage' => 100])->assertForbidden();

        $this->assertDatabaseMissing('feature_flags', ['key' => 'mine']);
        $this->assertDatabaseHas('feature_flags', ['key' => 'global-flag', 'enabled_globally' => false, 'rollout_percentage' => 0]);
    }

    public function test_operator_can_mutate_flags_over_the_api(): void
    {
        $operator = $this->operator();
        $token = $operator->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/feature-flags', ['key' => 'new-one', 'name' => 'New'])->assertCreated();
        $this->withToken($token)->patchJson('/api/v1/feature-flags/new-one/enable')->assertOk();
        $this->withToken($token)->patchJson('/api/v1/feature-flags/new-one/rollout', ['percentage' => 40])->assertOk();
        $this->withToken($token)->patchJson('/api/v1/feature-flags/new-one/disable')->assertOk();
    }

    public function test_team_users_only_read_their_effective_flags(): void
    {
        $owner = $this->ownerWithTeam();
        $other = $this->ownerWithTeam();
        $token = $owner->createToken('t')->plainTextToken;
        FeatureFlag::create([
            'key' => 'beta', 'name' => 'Beta', 'enabled_for_teams' => [$owner->currentTeam->id, $other->currentTeam->id],
            'enabled_for_users' => [$other->id], 'rollout_percentage' => 10,
        ]);

        $row = $this->withToken($token)->getJson('/api/v1/feature-flags')->assertOk()->json('data.0');

        $this->assertTrue($row['enabled']);
        $this->assertArrayNotHasKey('enabled_for_teams', $row);
        $this->assertArrayNotHasKey('enabled_for_users', $row);
        $this->assertArrayNotHasKey('rollout_percentage', $row);
    }

    public function test_operator_sees_full_flag_configuration(): void
    {
        $operator = $this->operator();
        $token = $operator->createToken('t')->plainTextToken;
        FeatureFlag::create(['key' => 'beta', 'name' => 'Beta', 'enabled_for_teams' => [1, 2]]);

        $row = $this->withToken($token)->getJson('/api/v1/feature-flags')->assertOk()->json('data.0');

        $this->assertSame([1, 2], $row['enabled_for_teams']);
    }

    public function test_panel_blocks_team_owner_and_allows_operator(): void
    {
        $owner = $this->ownerWithTeam();
        $flag = FeatureFlag::create(['key' => 'f', 'name' => 'F']);

        Livewire::actingAs($owner)->test(FeatureFlagsPanel::class)
            ->set('newKey', 'x')->set('newName', 'X')->call('create')->assertForbidden();
        Livewire::actingAs($owner)->test(FeatureFlagsPanel::class)->call('toggle', $flag->id)->assertForbidden();
        Livewire::actingAs($owner)->test(FeatureFlagsPanel::class)->call('setRollout', $flag->id, 100)->assertForbidden();
        $this->assertDatabaseMissing('feature_flags', ['key' => 'x']);
        $this->assertFalse($flag->fresh()->enabled_globally);

        Livewire::actingAs($this->operator())->test(FeatureFlagsPanel::class)->call('toggle', $flag->id)->assertOk();
        $this->assertTrue($flag->fresh()->enabled_globally);
    }

    public function test_panel_hides_targeting_lists_and_controls_from_team_users(): void
    {
        $owner = $this->ownerWithTeam();
        FeatureFlag::create(['key' => 'f', 'name' => 'Visible flag', 'enabled_for_teams' => [999]]);

        $component = Livewire::actingAs($owner)->test(FeatureFlagsPanel::class);

        $component->assertSee('Visible flag')->assertDontSee('New Flag');
        foreach ($component->get('flags') as $flag) {
            $this->assertFalse(isset($flag->enabled_for_teams));
        }
    }

    public function test_service_refuses_non_operator_actor(): void
    {
        $flag = FeatureFlag::create(['key' => 'svc', 'name' => 'Svc']);
        $service = app(FeatureFlagService::class);

        foreach ([
            fn () => $service->enable('svc', $this->ownerWithTeam()),
            fn () => $service->disable('svc', $this->ownerWithTeam()),
            fn () => $service->setRollout('svc', 50, $this->ownerWithTeam()),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Expected AuthorizationException');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertFalse($flag->fresh()->enabled_globally);

        $service->enable('svc', $this->operator());
        $this->assertTrue($flag->fresh()->enabled_globally);
    }

    public function test_grant_command_toggles_operator_flag(): void
    {
        $user = $this->ownerWithTeam();

        $this->artisan('analytics:operator', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_platform_operator);

        $this->artisan('analytics:operator', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_platform_operator);
    }
}
