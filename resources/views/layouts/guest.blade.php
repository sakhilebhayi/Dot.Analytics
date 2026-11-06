<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <meta name="description" content="Sign in to Dot.Analytics. The public library needs no account.">
    <title>{{ $attributes->get('title', 'Sign in') }} · Dot.Analytics</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preload" href="/fonts/ibm-plex-sans-var.woff2" as="font" type="font/woff2" crossorigin>

    @vite(['resources/css/app.css', 'resources/css/tokens.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        html, body { background: var(--ink); color: var(--paper); font-family: var(--font-body); }
        .font-display { font-family: var(--font-display); }
        .font-mono { font-family: var(--font-mono); }
        .press { transition: transform 160ms cubic-bezier(0.16, 1, 0.3, 1); }
        .press:active { transform: scale(0.97); }
    </style>
</head>
<body class="antialiased font-sans">
    <main class="min-h-screen flex flex-col items-center justify-center px-5 py-12 sm:py-16">
        <a href="{{ route('intel.home') }}" class="press mb-8 flex items-center gap-3 shrink-0 no-underline" aria-label="Dot.Analytics home">
            <img src="{{ asset('images/mark.png') }}" alt="" width="32" height="32">
            <span class="font-display text-2xl font-bold" style="color:var(--paper)">Dot.Analytics</span>
        </a>

        <div class="w-full flex justify-center">
            {{ $slot }}
        </div>
    </main>

    @livewireScripts
</body>
</html>
