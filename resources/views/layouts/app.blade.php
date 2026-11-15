<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dot-roboto">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $attributes->get('title', 'Company intelligence') }} · Dot.Analytics</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    {{-- Roboto and the shared Dot UI 0.6.1 (one bundle: type, header, search pill, platform switcher; the results controller loads lazily), all served from this site's own public/dot-ui: no
         third-party font or icon request (R-52). The bell and the other icons are inline SVG. --}}
    {{-- The dashboard's largest paint is text and the swap to Roboto is visible, so this page keeps the latin preload (dot-ui 0.6.1 PERFORMANCE.md): measured +0.2 s LCP without it. --}}
    <link rel="preload" href="/dot-ui/fonts/roboto/roboto-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/dot-ui/css/dot-ui.min.css">
    <script src="/dot-ui/js/dot-ui-core.min.js" defer></script>
    @vite(['resources/css/workspace.css'])
    @livewireStyles
    {{-- No separate Alpine script here on purpose: @livewireScripts below already bundles and starts Alpine for
         Livewire 3. A second copy made wire:click update server state without morphing the DOM. --}}
    {{-- Real-time bell updates. Optional: rendered only for signed-in users and only when config/echo.php says Reverb is
         on. Off (e.g. the shared cPanel host, which cannot run a WebSocket server) the bell polls instead and nothing is
         loaded from the CDN. Only the public app key is emitted, never the Reverb app secret. --}}
    @auth
    @if (config('echo.enabled'))
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.5.0/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@2.4.0/dist/echo.iife.js"></script>
    <script>
        {{-- laravel-echo@2.4.0's IIFE build assigns the global `Echo` to the whole ES-module namespace object, not the
             Echo class itself -- the class is at Echo.default. --}}
        try {
            window.Echo = new Echo.default({
                broadcaster: 'reverb',
                key: @js(config('echo.key')),
                wsHost: @js(config('echo.host')),
                wsPort: @js((int) config('echo.port')),
                wssPort: @js((int) config('echo.port')),
                forceTLS: @js(config('echo.scheme') === 'https'),
                enabledTransports: ['ws', 'wss'],
            });

            document.addEventListener('livewire:init', () => {
                window.Echo.private(@js('App.Models.User.'.Auth::id()))
                    .notification(() => {
                        Livewire.dispatch('notification-received');
                    });
            });
        } catch (e) {
            // Real-time is a nicety: the bell's polling keeps working without it.
        }
    </script>
    @endif
    @endauth
</head>
<body>
    <x-banner />

    @include('partials.workspace-header')

    <div class="ws-page">
        <main id="main">
            @if(isset($header))
            <div class="ws-head">{{ $header }}</div>
            @endif
            {{ $slot }}
        </main>
    </div>

    @stack('modals')
    @livewireScripts
</body>
</html>
