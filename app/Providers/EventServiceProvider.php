<?php

namespace App\Providers;

use App\Events\Analytics\CriticalInsightDiscovered;
use App\Events\Analytics\PlatformConnected;
use App\Listeners\Analytics\DispatchEnginesOnPlatformConnection;
use App\Listeners\Analytics\NotifyOnCriticalInsight;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PlatformConnected::class => [
            DispatchEnginesOnPlatformConnection::class,
        ],
        CriticalInsightDiscovered::class => [
            NotifyOnCriticalInsight::class,
        ],
    ];

    public function boot(): void {}

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
