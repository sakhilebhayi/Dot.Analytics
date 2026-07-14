<?php

namespace Tests\Feature\Api;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): array
    {
        $user  = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;
        return [$user, $token];
    }

    public function test_index_returns_all_flags(): void
    {
        [$user, $token] = $this->admin();
        FeatureFlag::create(['key' => 'test-flag', 'name' => 'Test Flag']);

        $this->withToken($token)->getJson('/api/v1/feature-flags')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_check_returns_enabled_false_when_not_configured(): void
    {
        [$user, $token] = $this->admin();

        $this->withToken($token)->getJson('/api/v1/feature-flags/check/nonexistent')
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
    }

    public function test_check_returns_enabled_true_for_global_flag(): void
    {
        [$user, $token] = $this->admin();
        FeatureFlag::create(['key' => 'active', 'name' => 'Active Flag', 'enabled_globally' => true]);

        $this->withToken($token)->getJson('/api/v1/feature-flags/check/active')
            ->assertOk()
            ->assertJsonPath('data.enabled', true);
    }

    public function test_store_creates_flag(): void
    {
        [$user, $token] = $this->admin();

        $this->withToken($token)->postJson('/api/v1/feature-flags', [
            'key'  => 'new-feature',
            'name' => 'New Feature',
        ])->assertCreated();

        $this->assertDatabaseHas('feature_flags', ['key' => 'new-feature']);
    }

    public function test_store_rejects_duplicate_key(): void
    {
        [$user, $token] = $this->admin();
        FeatureFlag::create(['key' => 'existing', 'name' => 'Existing']);

        $this->withToken($token)->postJson('/api/v1/feature-flags', [
            'key'  => 'existing',
            'name' => 'Duplicate',
        ])->assertUnprocessable();
    }

    public function test_enable_sets_flag_globally(): void
    {
        [$user, $token] = $this->admin();
        FeatureFlag::create(['key' => 'my-flag', 'name' => 'My Flag']);

        $this->withToken($token)->patchJson('/api/v1/feature-flags/my-flag/enable')
            ->assertOk()->assertJsonPath('data.enabled', true);

        $this->assertDatabaseHas('feature_flags', ['key' => 'my-flag', 'enabled_globally' => true]);
    }

    public function test_disable_clears_flag(): void
    {
        [$user, $token] = $this->admin();
        FeatureFlag::create(['key' => 'on-flag', 'name' => 'On', 'enabled_globally' => true]);

        $this->withToken($token)->patchJson('/api/v1/feature-flags/on-flag/disable')
            ->assertOk()->assertJsonPath('data.enabled', false);

        $this->assertDatabaseHas('feature_flags', ['key' => 'on-flag', 'enabled_globally' => false]);
    }

    public function test_rollout_sets_percentage(): void
    {
        [$user, $token] = $this->admin();
        FeatureFlag::create(['key' => 'rollout-flag', 'name' => 'Rollout']);

        $this->withToken($token)->patchJson('/api/v1/feature-flags/rollout-flag/rollout', ['percentage' => 50])
            ->assertOk();

        $this->assertDatabaseHas('feature_flags', ['key' => 'rollout-flag', 'rollout_percentage' => 50]);
    }

    public function test_feature_flags_require_authentication(): void
    {
        $this->getJson('/api/v1/feature-flags')->assertUnauthorized();
    }
}
