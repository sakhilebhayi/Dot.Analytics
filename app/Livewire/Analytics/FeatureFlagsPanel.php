<?php

namespace App\Livewire\Analytics;

use App\Models\FeatureFlag;
use App\Services\FeatureFlagService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class FeatureFlagsPanel extends Component
{
    public bool $showCreate = false;

    public string $newKey = '';

    public string $newName = '';

    public string $newDesc = '';

    public string $newEnv = 'all';

    protected array $rules = [
        'newKey' => 'required|string|max:100|alpha_dash',
        'newName' => 'required|string|max:120',
        'newDesc' => 'nullable|string|max:500',
        'newEnv' => 'in:all,production,local',
    ];

    #[Computed]
    public function flags(): Collection
    {
        $flags = app(FeatureFlagService::class)->all();

        if (Gate::allows('manage-feature-flags')) {
            return $flags;
        }

        // Team users see only each flag's effective state for themselves, never the
        // global targeting lists (which name other tenants) or rollout config.
        $service = app(FeatureFlagService::class);
        $user = Auth::user();

        return $flags->map(fn (FeatureFlag $f) => (object) [
            'id' => $f->id,
            'key' => $f->key,
            'name' => $f->name,
            'description' => $f->description,
            'environment' => 'all',
            'rollout_percentage' => 0,
            'enabled_globally' => $service->isEnabled($f->key, $user),
        ]);
    }

    public function create(): void
    {
        Gate::authorize('manage-feature-flags');
        $this->validate();

        app(FeatureFlagService::class)->create([
            'key' => $this->newKey,
            'name' => $this->newName,
            'description' => $this->newDesc ?: null,
            'environment' => $this->newEnv,
        ], Auth::user());

        $this->reset(['newKey', 'newName', 'newDesc', 'newEnv', 'showCreate']);
        unset($this->flags);
    }

    public function toggle(int $id): void
    {
        Gate::authorize('manage-feature-flags');

        $flag = FeatureFlag::findOrFail($id);
        $service = app(FeatureFlagService::class);

        $flag->enabled_globally
            ? $service->disable($flag->key, Auth::user())
            : $service->enable($flag->key, Auth::user());

        unset($this->flags);
    }

    public function setRollout(int $id, float $pct): void
    {
        Gate::authorize('manage-feature-flags');

        $flag = FeatureFlag::findOrFail($id);
        app(FeatureFlagService::class)->setRollout($flag->key, $pct, Auth::user());
        unset($this->flags);
    }

    public function render(): View
    {
        return view('livewire.analytics.feature-flags-panel');
    }
}
