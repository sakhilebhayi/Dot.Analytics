<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HealthCheckServiceProvider;
use App\Providers\JetstreamServiceProvider;
use Dot\SsoClient\DotSsoServiceProvider;

return [
    AppServiceProvider::class,
    EventServiceProvider::class,
    FortifyServiceProvider::class,
    HealthCheckServiceProvider::class,
    JetstreamServiceProvider::class,
    DotSsoServiceProvider::class,
];
