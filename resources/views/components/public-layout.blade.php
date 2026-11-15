@props(['title', 'description' => null, 'canonical' => null, 'section' => null, 'jsonld' => null, 'noindex' => false, 'tools' => null])
@php
    $site = 'Dot.Analytics';
    $fullTitle = $title === $site ? $site.'. Business intelligence you can check' : $title.' · '.$site;
    $fullTitle = \Illuminate\Support\Str::limit($fullTitle, 68);
    $description ??= 'Business developments, industry and country figures, each with its source, period, licence and last update. Free to read.';
    $canonical ??= url()->current();
    $navItems = [];
    foreach ([['intel.industries', 'Industries'], ['intel.countries', 'Countries'], ['intel.newsroom', 'Newsroom'], ['intel.briefings', 'Briefings'], ['intel.sources', 'Sources']] as [$navRoute, $navLabel]) {
        $navItems[] = ['label' => $navLabel, 'href' => route($navRoute), 'current' => $section === $navRoute];
    }
@endphp
<!DOCTYPE html>
<html lang="en" class="dot-roboto">
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
    {{-- Roboto and the shared Dot UI 0.6.1 (one bundle: type, header, search pill, switcher), served from this site: the strict policy needs nothing more. --}}
    {{-- Kept (dot-ui 0.6.1 allows it when the largest paint is text and the swap is visible): without it the home page's hero text reflows when Roboto arrives, CLS 0.20 at 320px. --}}
    <link rel="preload" href="/dot-ui/fonts/roboto/roboto-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/dot-ui/css/dot-ui.min.css">
    <script src="/dot-ui/js/dot-ui-core.min.js" defer></script>
    @vite(['resources/css/public.css', 'resources/js/public.js'])
    @if($jsonld)<script type="application/ld+json">{!! json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>@endif
</head>
<body>
<x-dot.header id="dh" brand-href="{{ route('intel.home') }}" brand-label="Dot.Analytics home" nav-label="Main" :nav="$navItems" skip="#main"
              :guest="['login' => ['label' => 'My library', 'href' => route('me.home')]]">
    <x-slot:brand>
        <img src="{{ asset('images/mark.png') }}" alt="" width="26" height="26">
        <span class="dh-brand__name">Dot.Analytics</span>
    </x-slot:brand>
    <x-slot:search>
        <x-dot.search name="q" id="q-top" :action="route('intel.find')" placeholder="Search or ask" label="Search the library" maxlength="160" :form-attributes="['aria-label' => 'Search the library']" />
    </x-slot:search>
    <x-slot:tools>@if($tools){{ $tools }}@endif</x-slot:tools>
</x-dot.header>

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
