<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    // Listeners are mapped explicitly in EventServiceProvider. Leaving framework
    // auto-discovery on registered every listener TWICE (duplicate alerts per
    // critical insight, double engine dispatch on every platform connect).
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        // CORS — allow requests from trusted Dot ecosystem origins
        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        // Security headers on all responses
        $middleware->append(SecurityHeaders::class);

        // Locale + timezone from team settings (authenticated routes)
        $middleware->web(append: [
            SetLocale::class,
        ]);
        $middleware->api(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
