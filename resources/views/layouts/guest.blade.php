<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Dot.Analytics') }}</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        :root {
            --ink: #0c1615;
            --ink-soft: #11201e;
            --teal: #2bb6b7;
            --teal-soft: #7cd8d5;
            --gold: #f1c62e;
            --gold-soft: #f6da70;
            --paper: #eef2f0;
            --mist: #93aba7;
            --line: rgba(238, 242, 240, 0.12);
            --font-display: 'Space Grotesk', system-ui, sans-serif;
            --font-body: 'IBM Plex Sans', system-ui, sans-serif;
            --font-mono: 'IBM Plex Mono', ui-monospace, monospace;
            --ease-out: cubic-bezier(0.23, 1, 0.32, 1);
        }
        html { background: var(--ink); }
        body { font-family: var(--font-body); background: var(--ink); color: var(--paper); }
        .font-display { font-family: var(--font-display); }
        .font-mono { font-family: var(--font-mono); }

        .press { transition: transform 160ms var(--ease-out); }
        .press:active { transform: scale(0.97); }
    </style>
</head>
<body class="antialiased font-sans">
    <div class="relative min-h-screen flex flex-col items-center justify-center px-5 py-12 sm:py-16 overflow-hidden">
        {{-- Same hero photo as welcome.blade.php (analytics dashboard, Stephen Dawson), served
        locally rather than hotlinked — this platform's CSP img-src is 'self' data: blob: only. --}}
        <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ asset('images/hero-dashboard.jpg') }}');"></div>
        <div class="absolute inset-0" style="background: radial-gradient(ellipse 68% 62% at 50% 40%, rgba(12,22,21,0.9) 0%, rgba(12,22,21,0.68) 45%, rgba(12,22,21,0.35) 74%, rgba(12,22,21,0.12) 100%);"></div>
        <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(12,22,21,0.6) 0%, transparent 18%, transparent 74%, rgba(12,22,21,0.5) 100%);"></div>

        <a href="/" class="press relative z-10 mb-8 flex items-center shrink-0">
            <img src="{{ asset('images/logo-light.png') }}" alt="Dot.Analytics" class="h-16 sm:h-20 w-auto">
        </a>

        <div class="relative z-10 w-full flex justify-center">
            {{ $slot }}
        </div>
    </div>

    @livewireScripts
</body>
</html>
