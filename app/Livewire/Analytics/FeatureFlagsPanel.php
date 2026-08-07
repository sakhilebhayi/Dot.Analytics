<?php

namespace App\Livewire\Analytics;

use App\Models\FeatureFlag;
use App\Services\FeatureFlagService;
use Illuminate\Support\Collection;
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
        return app(FeatureFlagService::class)->all();
    }

    public function create(): void
    {
        Gate::authorize('manage-platforms');
        $this->validate();

        FeatureFlag::create([
            'key' => $this->newKey,
            'name' => $this->newName,
            'description' => $this->newDesc ?: null,
            'environment' => $this->newEnv,
        ]);

        $this->reset(['newKey', 'newName', 'newDesc', 'newEnv', 'showCreate']);
        unset($this->flags);
    }

    public function toggle(int $id): void
    {
        Gate::authorize('manage-platforms');

        $flag = FeatureFlag::findOrFail($id);
        $service = app(FeatureFlagService::class);

        $flag->enabled_globally
            ? $service->disable($flag->key)
            : $service->enable($flag->key);

        unset($this->flags);
    }

    public function setRollout(int $id, float $pct): void
    {
        Gate::authorize('manage-platforms');

        $flag = FeatureFlag::findOrFail($id);
        app(FeatureFlagService::class)->setRollout($flag->key, $pct);
        unset($this->flags);
    }

    public function render(): View
    {
        return view('livewire.analytics.feature-flags-panel');
    }
}
