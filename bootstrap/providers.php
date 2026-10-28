<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HealthCheckServiceProvider;
use App\Providers\JetstreamServiceProvider;

return [
    AppServiceProvider::class,
    EventServiceProvider::class,
    FortifyServiceProvider::class,
    HealthCheckServiceProvider::class,
    JetstreamServiceProvider::class,
];
