<?php

namespace App\Livewire\Analytics;

use App\Services\InfoDot\IdentityClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Your business platforms: which Dot platforms the company has, as InfoDot records it.
 *
 * This is about access, not data. It is read from InfoDot (never from this app's own platform list), grouped into what
 * is in use, what can be activated and what needs attention, and every row leads to that platform's page in InfoDot,
 * where the company acts on it. When InfoDot cannot answer the panel says so. It never falls back to a list of its own,
 * and a platform is only ever "in use" when InfoDot says the company is entitled to it and its state is a working one.
 *
 * Loaded after the page so a slow InfoDot never holds the workspace up. Switched on by ANALYTICS_BUSINESS_PLATFORMS.
 */
#[Lazy]
class BusinessPlatformsPanel extends Component
{
    /** States in which an entitled company is using the platform. */
    public const IN_USE = ['active' => 'Active', 'included' => 'Included', 'trial' => 'On trial'];

    /** States a company can act on to start using a platform. */
    public const AVAILABLE = [
        'free_to_activate' => 'Free to activate',
        'unpriced_free' => 'Free while pricing is being finalised',
        'trial_available' => 'Trial available',
        'available_for_purchase' => 'Available to buy',
        'managed_in_platform' => 'Managed in the platform',
    ];

    /** States that stop or threaten access until someone acts. */
    public const ATTENTION = [
        'payment_required' => 'Payment required',
        'suspended' => 'Suspended',
        'expired' => 'Subscription expired',
        'activation_pending' => 'Activation pending',
        'temporarily_unavailable' => 'Temporarily unavailable',
    ];

    public static function enabled(): bool
    {
        return (bool) config('analytics.business_platforms.enabled', false);
    }

    /**
     * @return array{state: 'loaded'|'unavailable'|'unlinked', read_at: ?Carbon, groups: array<string, list<array{key: string, name: string, label: string, url: string}>>}
     */
    #[Computed]
    public function platforms(): array
    {
        $team = Auth::user()?->currentTeam;
        $uuid = $team?->infodot_team_uuid;

        if (! is_string($uuid) || $uuid === '') {
            return ['state' => 'unlinked', 'read_at' => null, 'groups' => []];
        }

        $answer = $this->read($uuid);

        if ($answer === null) {
            return ['state' => 'unavailable', 'read_at' => null, 'groups' => []];
        }

        $groups = ['in_use' => [], 'available' => [], 'attention' => []];

        foreach ($answer['platforms'] as $platform) {
            $status = $platform['status'];
            $row = ['key' => $platform['key'], 'name' => $platform['name'], 'url' => $this->infodot().'/platforms/'.$platform['key']];

            if (isset(self::ATTENTION[$status]) && ($status !== 'temporarily_unavailable' || $platform['entitled'])) {
                $groups['attention'][] = $row + ['label' => self::ATTENTION[$status]];
            } elseif ($platform['entitled'] && isset(self::IN_USE[$status])) {
                $groups['in_use'][] = $row + ['label' => $platform['is_core'] ? 'Included with every company' : self::IN_USE[$status]];
            } elseif (isset(self::AVAILABLE[$status])) {
                $groups['available'][] = $row + ['label' => self::AVAILABLE[$status]];
            }
            // Anything else (not launched, or a state this app does not know) is not shown and is never counted as in use.
        }

        return ['state' => 'loaded', 'read_at' => Carbon::parse($answer['read_at']), 'groups' => $groups];
    }

    public function infodot(): string
    {
        return rtrim((string) config('ecosystem.platforms.infodot.url', 'https://infodot.co.za'), '/');
    }

    public function placeholder(): View
    {
        return view('livewire.analytics.business-platforms-panel', ['loading' => true]);
    }

    public function render(): View
    {
        return view('livewire.analytics.business-platforms-panel', ['loading' => false]);
    }

    /**
     * A company's answer is kept for a few minutes; a failure is remembered for a minute so a struggling InfoDot is
     * not asked again on every page.
     *
     * @return array{platforms: list<array{key: string, name: string, status: string, entitled: bool, is_core: bool}>, read_at: string}|null
     */
    private function read(string $uuid): ?array
    {
        $key = 'business-platforms:'.$uuid;
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached['platforms'] === null ? null : $cached;
        }

        $platforms = app(IdentityClient::class)->companyPlatforms($uuid);
        $answer = ['platforms' => $platforms, 'read_at' => now()->toIso8601String()];

        Cache::put($key, $answer, $platforms === null
            ? now()->addSeconds((int) config('analytics.business_platforms.retry_seconds', 60))
            : now()->addMinutes((int) config('analytics.business_platforms.cache_minutes', 10)));

        return $platforms === null ? null : $answer;
    }
}
