<?php

namespace Tests\Unit\Services;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeatureFlagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FeatureFlagService();
    }

    private function user(): User
    {
        return User::factory()->withPersonalTeam()->create();
    }

    public function test_is_enabled_returns_false_for_nonexistent_flag(): void
    {
        $this->assertFalse($this->service->isEnabled('nonexistent'));
    }

    public function test_is_enabled_returns_true_for_globally_enabled_flag(): void
    {
        FeatureFlag::create(['key' => 'global', 'name' => 'Global', 'enabled_globally' => true]);

        $this->assertTrue($this->service->isEnabled('global'));
    }

    public function test_is_enabled_returns_false_for_globally_disabled_flag(): void
    {
        FeatureFlag::create(['key' => 'off', 'name' => 'Off', 'enabled_globally' => false]);

        $this->assertFalse($this->service->isEnabled('off'));
    }

    public function test_is_enabled_checks_per_user_list(): void
    {
        $user = $this->user();
        FeatureFlag::create([
            'key'               => 'user-flag',
            'name'              => 'User Flag',
            'enabled_for_users' => [$user->id],
        ]);

        $this->assertTrue($this->service->isEnabled('user-flag', $user));
    }

    public function test_is_enabled_returns_false_for_user_not_in_list(): void
    {
        $userA = $this->user();
        $userB = $this->user();
        FeatureFlag::create([
            'key'               => 'user-flag',
            'name'              => 'User Flag',
            'enabled_for_users' => [$userA->id],
        ]);

        $this->assertFalse($this->service->isEnabled('user-flag', $userB));
    }

    public function test_is_enabled_checks_per_team_list(): void
    {
        $user = $this->user();
        FeatureFlag::create([
            'key'               => 'team-flag',
            'name'              => 'Team Flag',
            'enabled_for_teams' => [$user->currentTeam->id],
        ]);

        $this->assertTrue($this->service->isEnabled('team-flag', $user));
    }

    public function test_is_enabled_respects_rollout_percentage(): void
    {
        // User with ID that maps to bucket > 50 should be excluded with 50% rollout
        $user = User::factory()->withPersonalTeam()->create();
        $bucket = ($user->id % 100) + 1;

        FeatureFlag::create([
            'key'                => 'rollout',
            'name'               => 'Rollout',
            'rollout_percentage' => $bucket <= 50 ? 100 : 0, // fully on or off based on bucket
        ]);

        $expected = $bucket <= 50;
        $this->assertEquals($expected, $this->service->isEnabled('rollout', $user));
    }

    public function test_enable_sets_flag_globally_true(): void
    {
        FeatureFlag::create(['key' => 'toggle', 'name' => 'Toggle']);

        $this->service->enable('toggle');

        $this->assertDatabaseHas('feature_flags', ['key' => 'toggle', 'enabled_globally' => true]);
    }

    public function test_disable_sets_flag_globally_false(): void
    {
        FeatureFlag::create(['key' => 'on', 'name' => 'On', 'enabled_globally' => true]);

        $this->service->disable('on');

        $this->assertDatabaseHas('feature_flags', ['key' => 'on', 'enabled_globally' => false]);
    }

    public function test_set_rollout_updates_percentage(): void
    {
        FeatureFlag::create(['key' => 'grad', 'name' => 'Gradual']);

        $this->service->setRollout('grad', 75.0);

        $this->assertDatabaseHas('feature_flags', ['key' => 'grad', 'rollout_percentage' => 75.0]);
    }

    public function test_set_rollout_clamps_to_100(): void
    {
        FeatureFlag::create(['key' => 'clamp', 'name' => 'Clamp']);
        $this->service->setRollout('clamp', 150.0);

        $this->assertDatabaseHas('feature_flags', ['key' => 'clamp', 'rollout_percentage' => 100.0]);
    }

    public function test_all_returns_all_flags(): void
    {
        FeatureFlag::create(['key' => 'a', 'name' => 'A']);
        FeatureFlag::create(['key' => 'b', 'name' => 'B']);

        $this->assertCount(2, $this->service->all());
    }

    public function test_environment_scoping_blocks_wrong_env(): void
    {
        FeatureFlag::create([
            'key'             => 'prod-only',
            'name'            => 'Prod Only',
            'enabled_globally' => true,
            'environment'     => 'production',
        ]);

        // In testing env, production-only flag should be disabled
        $this->assertFalse($this->service->isEnabled('prod-only'));
    }
}
