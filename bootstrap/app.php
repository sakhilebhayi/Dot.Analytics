<?php

use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // The public library: no session, no sign-in, finished pages cached. It never touches a company's data.
            Route::middleware('public')->group(base_path('routes/public.php'));

            // A person's own library: watching, saving, alerts and email. Signed in, never cached, strict policy.
            Route::middleware('web')->group(base_path('routes/me.php'));
        },
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

        // Public pages carry no session and no cookie, so one finished page serves everyone.
        $middleware->group('public', [
            SubstituteBindings::class,
            'throttle:public',
            CachePublicPage::class,
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
