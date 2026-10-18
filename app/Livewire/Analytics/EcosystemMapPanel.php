<?php

namespace App\Livewire\Analytics;

use App\Actions\Analytics\ConnectPlatformAction;
use App\Actions\Analytics\DisconnectPlatformAction;
use App\Models\DataSource;
use App\Services\IntelligenceEngineService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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

    /** Webhook secret issued by the last connect. Shown once, never stored on the component after dismissal. */
    public ?string $issuedWebhookSecret = null;

    public ?string $issuedForPlatform = null;

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
        Gate::authorize('create', DataSource::class);

        $this->connectingPlatform = $platform;
        $this->connectUrl = '';
    }

    public function confirmConnect(): void
    {
        Gate::authorize('create', DataSource::class);

        $this->validate(['connectUrl' => 'nullable|url|max:255']);

        if (! $this->connectingPlatform) {
            return;
        }

        [, $secret] = app(ConnectPlatformAction::class)->handleWithSecret(
            Auth::user()->currentTeam,
            $this->connectingPlatform,
            $this->connectUrl ?: null,
        );

        $this->issuedWebhookSecret = $secret;
        $this->issuedForPlatform = $secret ? $this->connectingPlatform : null;

        $this->connectingPlatform = null;
        $this->connectUrl = '';
        unset($this->platformCatalog, $this->activeEngines, $this->connectedCount);
    }

    public function dismissSecret(): void
    {
        $this->issuedWebhookSecret = null;
        $this->issuedForPlatform = null;
    }

    public function disconnect(string $platform): void
    {
        Gate::authorize('manage-platforms');

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
