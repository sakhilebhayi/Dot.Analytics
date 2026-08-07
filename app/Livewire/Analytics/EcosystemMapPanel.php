<?php

namespace App\Livewire\Analytics;

use App\Actions\Analytics\ConnectPlatformAction;
use App\Actions\Analytics\DisconnectPlatformAction;
use App\Models\DataSource;
use App\Services\IntelligenceEngineService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Ecosystem Map Panel
 *
 * Displays the complete Dot platform catalog. Connected platforms glow green.
 * Unconnected platforms can be connected inline. Shows which intelligence
 * engines each platform powers.
 */
class EcosystemMapPanel extends Component
{
    public ?string $connectingPlatform = null;

    public string $connectUrl = '';

    private IntelligenceEngineService $engineService;

    public function boot(IntelligenceEngineService $engineService): void
    {
        $this->engineService = $engineService;
    }

    #[Computed]
    public function platformCatalog(): array
    {
        $sources = Auth::user()->currentTeam
            ? DataSource::where('team_id', Auth::user()->currentTeam->id)->get()->keyBy('platform')
            : collect();

        $catalog = [];
        foreach (IntelligenceEngineService::PLATFORMS as $key => $platform) {
            $source = $sources->get($key);
            $colors = $this->engineService->getPlatformColorClasses($key);
            $catalog[$key] = array_merge($platform, [
                'key' => $key,
                'source' => $source,
                'status' => $source?->status ?? 'not_connected',
                'colors' => $colors,
            ]);
        }

        return $catalog;
    }

    #[Computed]
    public function activeEngines(): array
    {
        if (! Auth::user()->currentTeam) {
            return $this->engineService->getActiveEngines([]);
        }

        $connected = DataSource::where('team_id', Auth::user()->currentTeam->id)
            ->where('status', 'connected')
            ->pluck('platform')
            ->toArray();

        return $this->engineService->getActiveEngines($connected);
    }

    #[Computed]
    public function connectedCount(): int
    {
        if (! Auth::user()->currentTeam) {
            return 0;
        }

        return DataSource::where('team_id', Auth::user()->currentTeam->id)
            ->where('status', 'connected')
            ->count();
    }

    public function startConnect(string $platform): void
    {
        $this->connectingPlatform = $platform;
        $this->connectUrl = '';
    }

    public function confirmConnect(): void
    {
        $this->validate(['connectUrl' => 'nullable|url|max:255']);

        if (! $this->connectingPlatform) {
            return;
        }

        app(ConnectPlatformAction::class)->handle(
            Auth::user()->currentTeam,
            $this->connectingPlatform,
            $this->connectUrl ?: null,
        );

        $this->connectingPlatform = null;
        $this->connectUrl = '';
        unset($this->platformCatalog, $this->activeEngines, $this->connectedCount);
    }

    public function disconnect(string $platform): void
    {
        app(DisconnectPlatformAction::class)->handle(
            Auth::user()->currentTeam,
            $platform,
        );

        unset($this->platformCatalog, $this->activeEngines, $this->connectedCount);
    }

    public function cancelConnect(): void
    {
        $this->connectingPlatform = null;
        $this->connectUrl = '';
    }

    public function render(): View
    {
        return view('livewire.analytics.ecosystem-map-panel');
    }
}
