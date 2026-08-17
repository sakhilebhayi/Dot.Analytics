<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dot.Analytics</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    {{-- Same brand tokens/type family as resources/views/welcome.blade.php --
         the dashboard previously used its own unrelated navy/Manrope/Inter
         system (#0b1326, sky-blue accent) that matched no other page on
         this platform. This layout now extends the same identity instead
         of running a second one. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { corePlugins: { preflight: false } }</script>
    <style>
        :root {
            --ink: #0c1615;
            --ink-soft: #11201e;
            --panel: #142523;
            --teal: #2bb6b7;
            --teal-soft: #7cd8d5;
            --gold: #f1c62e;
            --gold-soft: #f6da70;
            --paper: #eef2f0;
            --mist: #93aba7;
            --line: rgba(238, 242, 240, 0.12);
            /* Neither welcome.blade.php nor the rest of the brand system
               defines a danger hue -- the panels below need one real
               semantic color for critical/risk states that teal and gold
               can't honestly stand in for. Kept to a single pair so the
               palette stays teal+gold-led, not a rainbow. */
            --danger: #f08a6c;
            --danger-soft: #f6b39c;
            --font-display: 'Space Grotesk', system-ui, sans-serif;
            --font-body: 'IBM Plex Sans', system-ui, sans-serif;
            --font-mono: 'IBM Plex Mono', ui-monospace, monospace;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; background: var(--ink); color: var(--paper); font-family: var(--font-body); }
        .font-display { font-family: var(--font-display); }
        .font-mono { font-family: var(--font-mono); }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; line-height: 1; }
        [x-cloak] { display: none !important; }

        .press { transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1); }
        .press:active { transform: scale(0.97); }

        .sl { display:flex;align-items:center;gap:0.7rem;padding:0.6rem 0.75rem;border-radius:0.4rem;font-family:var(--font-mono);font-size:0.72rem;font-weight:500;letter-spacing:0.06em;text-transform:uppercase;text-decoration:none;color:var(--mist);transition:all 0.15s; }
        .sl:hover { background:rgba(238,242,240,0.04);color:var(--paper); }
        .sl.active { background:rgba(241,198,46,0.08);color:var(--gold); }
        .sl.active .material-symbols-outlined { color:var(--gold); }

        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.35} }
    </style>
    @livewireStyles
    {{-- No separate Alpine script here on purpose: @livewireScripts below already
         bundles and starts Alpine for Livewire 3, exactly like guest.blade.php.
         A second unpkg-loaded Alpine here caused a real bug found while
         verifying this page in browser -- "Detected multiple instances of
         Alpine running" in the console, and wire:click actions that updated
         the server-side component state (confirmed via Livewire's own JS
         API) but never morphed the DOM to show it, e.g. clicking "+ Connect"
         set connectingPlatform without ever rendering the connect form. --}}
    <script defer src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
</head>
<body class="antialiased">
    <x-banner />

    <aside style="position:fixed;left:0;top:0;height:100vh;width:264px;background:var(--ink-soft);border-right:1px solid var(--line);z-index:50;overflow-y:auto;padding:1.75rem 1.25rem;display:flex;flex-direction:column;">
        <a href="{{ route('dashboard') }}" class="press" style="display:flex;align-items:center;gap:0.7rem;text-decoration:none;margin-bottom:2.25rem;">
            <div style="width:34px;height:34px;border-radius:8px;overflow:hidden;background:var(--paper);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <img src="{{ asset('images/mark.png') }}" alt="Dot.Analytics" style="width:100%;height:100%;object-fit:contain;">
            </div>
            <div>
                <div class="font-display" style="font-size:0.95rem;font-weight:700;color:var(--paper);letter-spacing:-0.01em;">Dot.Analytics</div>
                <div class="font-mono" style="font-size:0.55rem;font-weight:500;color:var(--mist);letter-spacing:0.16em;text-transform:uppercase;">Intelligence Platform</div>
            </div>
        </a>

        <nav style="flex:1;display:flex;flex-direction:column;gap:0.15rem;">
            <a href="{{ route('dashboard') }}" class="sl {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="material-symbols-outlined" style="font-size:18px;">dashboard</span>
                <span>Intelligence Overview</span>
            </a>
        </nav>

        @auth
        <div style="margin-top:auto;padding-top:1.25rem;border-top:1px solid var(--line);">
            <div style="display:flex;align-items:center;gap:0.7rem;">
                <div style="width:32px;height:32px;border-radius:9999px;background:var(--gold);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:0.75rem;font-weight:700;color:var(--ink);flex-shrink:0;">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div style="min-width:0;">
                    <div style="font-size:0.75rem;font-weight:600;color:var(--paper);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ Auth::user()->name }}</div>
                    <div class="font-mono" style="font-size:0.58rem;color:var(--mist);text-transform:uppercase;letter-spacing:0.1em;">Member</div>
                </div>
            </div>
        </div>
        @endauth
    </aside>

    @livewire('navigation-menu')

    <div style="margin-left:264px;padding-top:64px;min-height:100vh;">
        @if(isset($header))
        <div style="padding:2rem 2.5rem 0;">{{ $header }}</div>
        @endif
        <main>{{ $slot }}</main>
    </div>

    <div style="position:fixed;bottom:1.5rem;right:1.5rem;display:flex;align-items:center;gap:0.5rem;padding:0.4rem 0.85rem;background:rgba(20,37,35,0.85);backdrop-filter:blur(16px);border-radius:9999px;border:1px solid var(--line);z-index:40;">
        <div style="width:6px;height:6px;border-radius:9999px;background:var(--gold);animation:pulse 2s infinite;"></div>
        <span class="font-mono" style="font-size:0.58rem;font-weight:600;color:var(--mist);text-transform:uppercase;letter-spacing:0.16em;">Dot.Analytics Online</span>
    </div>

    @stack('modals')
    @livewireScripts
</body>
</html>
