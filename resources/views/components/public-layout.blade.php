@props(['title', 'description' => null, 'canonical' => null, 'section' => null, 'jsonld' => null, 'noindex' => false, 'tools' => null])
@php
    $site = 'Dot.Analytics';
    $fullTitle = $title === $site ? $site.'. Business intelligence you can check' : $title.' · '.$site;
    $fullTitle = \Illuminate\Support\Str::limit($fullTitle, 68);
    $description ??= 'Business developments, industry and country figures, each with its source, period, licence and last update. Free to read.';
    $canonical ??= url()->current();
    $nav = [['intel.industries', 'Industries'], ['intel.countries', 'Countries'], ['intel.newsroom', 'Newsroom'], ['intel.briefings', 'Briefings'], ['intel.sources', 'Sources']];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    @if($noindex)<meta name="robots" content="noindex">@endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $site }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ url('/og-image.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="preload" href="/fonts/ibm-plex-sans-var.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/ibm-plex-sans-condensed-700-normal.woff2" as="font" type="font/woff2" crossorigin>
    {{-- The shared Dot UI (search pill, platform switcher), served from this site: the strict policy needs nothing more. --}}
    <link rel="stylesheet" href="/dot-ui/css/dot-fonts.css">
    <link rel="stylesheet" href="/dot-ui/css/dot-tokens.css">
    <link rel="stylesheet" href="/dot-ui/css/dot-search.css">
    @if($tools)
    <link rel="stylesheet" href="/dot-ui/css/dot-switcher.css">
    <script src="/dot-ui/js/dot-switcher.js" defer></script>
    @endif
    @vite(['resources/css/public.css', 'resources/js/public.js'])
    @if($jsonld)<script type="application/ld+json">{!! json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>@endif
</head>
<body>
<a class="skip" href="#main">Skip to the content</a>

<header class="top">
    <div class="wrap top-in">
        <a class="mark" href="{{ route('intel.home') }}" aria-label="Dot.Analytics home"><img src="{{ asset('images/mark.png') }}" alt="" width="26" height="26"><span>Dot.</span>Analytics</a>
        <nav class="nav" aria-label="Main">
            @foreach($nav as [$route, $label])
                <a href="{{ route($route) }}" @if($section === $route) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="top-end">
            <div class="top-search">
                <x-dot.search name="q" id="q-top" :action="route('intel.find')" placeholder="Search or ask" label="Search the library" maxlength="160" :form-attributes="['aria-label' => 'Search the library']" />
            </div>
            @if($tools){{ $tools }}@endif
            <a class="signin" href="{{ route('me.home') }}">My library</a>
        </div>
        <details class="menu">
            <summary aria-label="Menu">Menu</summary>
            <div class="menu-panel">
                @foreach($nav as [$route, $label])<a href="{{ route($route) }}">{{ $label }}</a>@endforeach
                <a href="{{ route('me.home') }}">My library</a>
                <div class="menu-search">
                    <x-dot.search name="q" id="q-menu" :action="route('intel.find')" placeholder="Search or ask" label="Search the library" maxlength="160" :form-attributes="['aria-label' => 'Search from the menu']" />
                </div>
            </div>
        </details>
    </div>
</header>

<main id="main">{{ $slot }}</main>

<footer class="foot">
    <div class="wrap foot-grid">
        <div>
            <p class="big">Figures you can check.</p>
            <p>Every figure here comes with its source, its period and its licence. When we do not have something, we say so.</p>
        </div>
        <nav aria-label="Library">
            <h2>Library</h2>
            <a href="{{ route('intel.industries') }}">Industries</a>
            <a href="{{ route('intel.countries') }}">Countries</a>
            <a href="{{ route('intel.indicators') }}">All figures</a>
            <a href="{{ route('intel.newsroom') }}">Newsroom</a>
            <a href="{{ route('intel.briefings') }}">Briefings</a>
        </nav>
        <nav aria-label="Trust">
            <h2>Trust</h2>
            <a href="{{ route('intel.method') }}">How figures are labelled</a>
            <a href="{{ route('intel.sources') }}">Sources and their licences</a>
            <a href="{{ route('intel.find') }}">Ask a question</a>
        </nav>
        <nav aria-label="Elsewhere">
            <h2>Elsewhere</h2>
            <a href="{{ route('intel.ecosystem') }}">The InfoDot ecosystem</a>
            <a href="{{ route('intel.plans') }}">Free and paid</a>
            <a href="{{ route('terms.show') }}">Terms</a>
            <a href="{{ route('policy.show') }}">Privacy</a>
            <a href="{{ route('cookies') }}">Cookies</a>
        </nav>
        <p class="fine">Dot.Analytics is part of the BluPin Inc ecosystem. Figures belong to the sources named beside them and are used under the licences listed on the sources page. Nothing here is investment, legal or tax advice.</p>
    </div>
</footer>
</body>
</html>
