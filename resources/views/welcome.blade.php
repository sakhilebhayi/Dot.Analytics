<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dot.Analytics — The intelligence layer across the Dot Ecosystem</title>
        <meta name="description" content="Dot.Analytics ingests the snapshots every connected Dot platform pushes, runs them through 17 intelligence engines, and surfaces the cross-platform insights, alerts, and briefings no single platform can see on its own.">

        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

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

            @media (prefers-reduced-motion: no-preference) {
                .reveal {
                    opacity: 0;
                    transform: translateY(14px);
                    transition: opacity 600ms var(--ease-out), transform 600ms var(--ease-out);
                }
                .reveal.is-visible { opacity: 1; transform: translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .reveal { opacity: 1; transform: none; }
            }

            @media (hover: hover) and (pointer: fine) {
                .row-hover:hover { background: rgba(238, 242, 240, 0.03); }
                .link-underline { background-size: 0% 1px; }
                .link-underline:hover { background-size: 100% 1px; }
            }
            .link-underline {
                background-image: linear-gradient(currentColor, currentColor);
                background-position: 0 100%;
                background-repeat: no-repeat;
                transition: background-size 220ms var(--ease-out);
            }
        </style>
    </head>
    <body class="antialiased">

        <!-- Nav -->
        <header
            id="site-header"
            class="fixed top-0 left-0 right-0 z-50 border-b border-transparent transition-colors duration-300"
        >
            <nav class="max-w-[1400px] mx-auto px-5 sm:px-8 py-3 flex items-center justify-between">
                <a href="/" class="flex items-center press">
                    <img src="{{ asset('images/logo.png') }}" alt="Dot.Analytics" class="h-16 sm:h-20 w-auto">
                </a>

                <div class="hidden md:flex items-center gap-8 font-mono text-[13px] tracking-wide uppercase text-[var(--mist)]">
                    <a href="#what-it-does" class="link-underline hover:text-[var(--paper)] pb-0.5">What it does</a>
                    <a href="#engines" class="link-underline hover:text-[var(--paper)] pb-0.5">Engines</a>
                    <a href="#capabilities" class="link-underline hover:text-[var(--paper)] pb-0.5">Platform</a>
                </div>

                @if (Route::has('login'))
                    <div class="flex items-center gap-3">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="press flex items-center gap-2 px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#0c1615] text-sm font-display font-semibold rounded-lg transition-colors">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="hidden sm:block text-sm font-medium text-[var(--mist)] hover:text-[var(--paper)] transition-colors">
                                Sign in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="press px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#0c1615] text-sm font-display font-semibold rounded-lg transition-colors">
                                    Get started
                                </a>
                            @endif
                        @endauth

                        <button id="mobile-menu-btn" class="md:hidden press p-2 -mr-2 text-[var(--paper)]" aria-label="Toggle menu" aria-expanded="false">
                            <svg id="icon-open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 7h16M4 12h16M4 17h16"></path>
                            </svg>
                            <svg id="icon-close" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                @endif
            </nav>

            <div id="mobile-menu" class="md:hidden hidden border-t border-[var(--line)] bg-[#0c1615]">
                <div class="flex flex-col px-5 py-4 gap-1 font-mono text-sm uppercase tracking-wide">
                    <a href="#what-it-does" class="px-3 py-2.5 text-[var(--mist)] hover:text-[var(--paper)]">What it does</a>
                    <a href="#engines" class="px-3 py-2.5 text-[var(--mist)] hover:text-[var(--paper)]">Engines</a>
                    <a href="#capabilities" class="px-3 py-2.5 text-[var(--mist)] hover:text-[var(--paper)]">Platform</a>
                    @guest
                        <a href="{{ route('login') }}" class="px-3 py-2.5 text-[var(--mist)] hover:text-[var(--paper)]">Sign in</a>
                    @endguest
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative min-h-[100dvh] flex items-center overflow-hidden">
            <!-- Photo: laptop displaying an analytics dashboard, by Stephen Dawson, unsplash.com/photos/turned-on-monitoring-screen-qwtCeJ5cLYs. Served locally (not hotlinked) because this platform's CSP img-src is 'self' data: blob: only, with no external https allowance. -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ asset('images/hero-dashboard.jpg') }}');"></div>
            <div class="absolute inset-0" style="background: linear-gradient(100deg, var(--ink) 0%, var(--ink) 28%, rgba(12,22,21,0.5) 50%, rgba(12,22,21,0.2) 74%, rgba(12,22,21,0.05) 100%);"></div>

            <!-- Signature: line-art nod to the real arrow mark in the Dot.Analytics logo, crossed with the knowledge graph (IntelligenceNode / IntelligenceEdge) this platform actually builds -->
            <svg class="hidden lg:block absolute right-[2%] top-1/2 -translate-y-1/2 h-[85%] w-auto pointer-events-none" viewBox="0 0 420 520" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M130 40 L340 260 L130 480" stroke="var(--paper)" stroke-width="13" stroke-linecap="round" stroke-linejoin="round" opacity="0.09"/>
                <g stroke="var(--teal-soft)" stroke-width="1" opacity="0.28">
                    <line x1="40" y1="410" x2="95" y2="345"/>
                    <line x1="95" y1="345" x2="150" y2="365"/>
                    <line x1="150" y1="365" x2="205" y2="255"/>
                    <line x1="205" y1="255" x2="255" y2="280"/>
                    <line x1="255" y1="280" x2="300" y2="150"/>
                    <line x1="150" y1="365" x2="70" y2="230"/>
                    <line x1="205" y1="255" x2="260" y2="120"/>
                </g>
                <g fill="var(--gold)" opacity="0.55">
                    <circle cx="40" cy="410" r="4"/>
                    <circle cx="95" cy="345" r="3"/>
                    <circle cx="150" cy="365" r="4.5"/>
                    <circle cx="205" cy="255" r="3.5"/>
                    <circle cx="255" cy="280" r="3"/>
                    <circle cx="300" cy="150" r="5"/>
                    <circle cx="70" cy="230" r="3"/>
                    <circle cx="260" cy="120" r="3.5"/>
                </g>
            </svg>

            <div class="relative z-10 max-w-[1400px] mx-auto px-5 sm:px-8 pt-28 pb-16 w-full">
                <div class="max-w-2xl reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--teal-soft)] mb-6">
                        Multi-tenant intelligence platform
                    </p>

                    <h1 class="font-display font-bold text-4xl sm:text-5xl lg:text-6xl leading-[1.05] tracking-tight text-[var(--paper)] mb-6">
                        The pattern no single platform shows you.
                    </h1>

                    <p class="text-lg text-[var(--mist)] leading-relaxed max-w-xl mb-10">
                        Dot.Analytics ingests the snapshots every connected Dot platform pushes, runs them through its intelligence engines, and writes the results — insights, alerts, a knowledge graph, recommendations — to one place. It doesn't visualise data you prepare; it reads across platforms for signals no individual tool can produce alone.
                    </p>

                    @guest
                        <div class="flex flex-wrap items-center gap-4">
                            <a href="{{ route('register') }}" class="press px-7 py-3.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#0c1615] font-display font-semibold rounded-lg transition-colors">
                                Get started
                            </a>
                            <a href="#what-it-does" class="press flex items-center gap-2 px-7 py-3.5 text-[var(--paper)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--mist)] transition-colors">
                                See what it does
                            </a>
                        </div>
                    @else
                        <a href="{{ url('/dashboard') }}" class="press inline-flex px-7 py-3.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#0c1615] font-display font-semibold rounded-lg transition-colors">
                            Open your dashboard
                        </a>
                    @endguest
                </div>
            </div>

            <!-- Capability strip — real modules (§2/§3 of the platform wiki), not invented metrics -->
            <div class="relative z-10 w-full border-t border-[var(--line)] bg-[#0c1615]/60 backdrop-blur-sm">
                <div class="max-w-[1400px] mx-auto px-5 sm:px-8 py-4 flex flex-wrap gap-x-8 gap-y-2 font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--mist)]">
                    <span>Snapshot ingestion</span>
                    <span class="text-[var(--gold)]">·</span>
                    <span>17 intelligence engines</span>
                    <span class="text-[var(--gold)]">·</span>
                    <span>Cross-platform insight discovery</span>
                    <span class="text-[var(--gold)]">·</span>
                    <span>Knowledge graph</span>
                    <span class="text-[var(--gold)]">·</span>
                    <span>Executive briefings</span>
                </div>
            </div>
        </section>

        <!-- What it does -->
        <section id="what-it-does" class="py-24 sm:py-28 px-5 sm:px-8">
            <div class="max-w-[1400px] mx-auto">
                <div class="max-w-xl mb-16 reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--teal-soft)] mb-4">What it does</p>
                    <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight">
                        Six systems, running underneath one intelligence layer
                    </h2>
                </div>

                <div class="grid md:grid-cols-2 border-t border-[var(--line)]">
                    @php
                        $features = [
                            ['tag' => 'Ingestion', 'title' => 'Snapshot ingestion', 'body' => 'Every connected platform pushes its data as a signed snapshot over a versioned API. No manual export, no CSV upload — the pipeline runs whether or not anyone is watching.'],
                            ['tag' => 'Engines', 'title' => 'Intelligence engines', 'body' => 'Seventeen engines — financial, operational, people, risk, predictive, and more — run against every connected team\'s data on a six-hourly schedule, each producing its own read on what the data means.'],
                            ['tag' => 'Insights', 'title' => 'Cross-platform insights', 'body' => 'Signals that only exist in the relationship between platforms get written up as a discrete, dated insight the moment the engines find them, with the highest-severity ones raised as alerts.'],
                            ['tag' => 'Graph', 'title' => 'Knowledge graph', 'body' => 'Every entity and relationship the engines discover is written into a graph of nodes and edges — queryable and traversable through the API, not locked inside a single report.'],
                            ['tag' => 'DNA', 'title' => 'Business DNA', 'body' => 'A profile of how your organisation actually operates — seasonal rhythm, decision patterns, growth signals — recomputed nightly with a confidence score that grows as more platforms connect.'],
                            ['tag' => 'Briefings', 'title' => 'Executive briefings', 'body' => 'Daily, weekly, and monthly summaries generated automatically from the engines\' output, ready to read before the first meeting of the day.'],
                        ];
                    @endphp
                    @foreach ($features as $i => $f)
                        <div class="row-hover border-b border-[var(--line)] {{ $i % 2 === 0 ? 'md:border-r' : '' }} px-1 py-8 sm:py-10 transition-colors reveal" data-reveal>
                            <p class="font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--teal-soft)] mb-3">{{ $f['tag'] }}</p>
                            <h3 class="font-display font-semibold text-xl text-[var(--paper)] mb-2.5">{{ $f['title'] }}</h3>
                            <p class="text-[var(--mist)] leading-relaxed max-w-md">{{ $f['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Worked examples -->
        <section class="py-24 sm:py-28 px-5 sm:px-8 bg-[var(--ink-soft)] border-y border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto">
                <div class="max-w-xl mb-14 reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--teal-soft)] mb-4">Read together, not apart</p>
                    <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight">
                        What separates this from a BI tool
                    </h2>
                    <p class="text-[var(--mist)] leading-relaxed mt-4">
                        A dashboard visualises the data you give it. Dot.Analytics reads across the platforms that push data to it, and only some signals are visible from there.
                    </p>
                </div>

                <div class="grid lg:grid-cols-2 gap-px bg-[var(--line)]">
                    <div class="bg-[var(--ink-soft)] p-8 sm:p-10 reveal" data-reveal>
                        <div class="flex flex-wrap gap-2 mb-5 font-mono text-[10px] tracking-[0.1em] uppercase text-[var(--gold)]">
                            <span>HR</span><span class="text-[var(--mist)]">/</span>
                            <span>Fleet</span><span class="text-[var(--mist)]">/</span>
                            <span>Finance</span><span class="text-[var(--mist)]">/</span>
                            <span>Assets</span>
                        </div>
                        <h3 class="font-display font-semibold text-xl text-[var(--paper)] mb-3">A site's productivity drops, and no single platform shows why.</h3>
                        <p class="text-[var(--mist)] leading-relaxed">Certified operators are on leave. Equipment idle time is elevated. Overtime costs are up. Maintenance is behind schedule. Four platforms, four unconnected facts — until the engines read them as one signal.</p>
                    </div>
                    <div class="bg-[var(--ink-soft)] p-8 sm:p-10 reveal" data-reveal>
                        <div class="flex flex-wrap gap-2 mb-5 font-mono text-[10px] tracking-[0.1em] uppercase text-[var(--gold)]">
                            <span>Sentiment</span><span class="text-[var(--mist)]">/</span>
                            <span>Sales</span><span class="text-[var(--mist)]">/</span>
                            <span>Support</span><span class="text-[var(--mist)]">/</span>
                            <span>Billing</span><span class="text-[var(--mist)]">/</span>
                            <span>Contracts</span>
                        </div>
                        <h3 class="font-display font-semibold text-xl text-[var(--paper)] mb-3">A customer looks fine in every dashboard but one.</h3>
                        <p class="text-[var(--mist)] leading-relaxed">Sentiment is declining. Sales have slowed. Support tickets are up. An invoice is overdue. The contract renews in a month. Read separately, none of it is urgent. Read together, it's a churn risk with a deadline.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Engines -->
        <section id="engines" class="py-24 sm:py-28 px-5 sm:px-8">
            <div class="max-w-[1400px] mx-auto">
                <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-12 lg:gap-20">
                    <div class="reveal" data-reveal>
                        <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--teal-soft)] mb-4">Engines</p>
                        <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight mb-5">
                            Seventeen engines, one schedule
                        </h2>
                        <p class="text-[var(--mist)] leading-relaxed max-w-sm">
                            Every connected team's active engines run automatically every six hours — which ones run depends on which platforms you've connected.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2.5 content-start reveal" data-reveal>
                        @foreach (['Data', 'Business', 'Operational', 'Financial', 'People', 'Customer', 'Document', 'Community', 'AI', 'Predictive', 'Decision', 'Risk', 'Compliance', 'Asset', 'Mining', 'Agriculture', 'Construction'] as $engine)
                            <span class="font-mono text-xs tracking-wide uppercase text-[var(--mist)] border border-[var(--line)] rounded-md px-3 py-1.5">{{ $engine }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <!-- Capabilities -->
        <section id="capabilities" class="py-24 sm:py-28 px-5 sm:px-8 bg-[var(--ink-soft)] border-y border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto">
                <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-12 lg:gap-20">
                    <div class="reveal" data-reveal>
                        <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--teal-soft)] mb-4">Built for the ecosystem</p>
                        <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight mb-5">
                            Runs alongside the rest of the Dot Ecosystem
                        </h2>
                        <p class="text-[var(--mist)] leading-relaxed max-w-sm">
                            Not a standalone reporting tool — it's built to sit underneath every platform you already run.
                        </p>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-x-10">
                        @php
                            $capabilities = [
                                ['title' => 'Team-scoped multi-tenancy', 'body' => 'Every table carries a team_id and every query is scoped to it, down to the intelligence-engine internals, not just the surface pages.'],
                                ['title' => 'Ecosystem single sign-on', 'body' => 'Sign in once through the ecosystem\'s own auth handoff and land in your team\'s dashboard, no separate Dot.Analytics password to manage.'],
                                ['title' => 'Versioned JSON API', 'body' => 'The same intelligence — insights, metrics, the knowledge graph, reports — is available under /api/v1 for anything you want to build on top of it.'],
                                ['title' => 'Report export', 'body' => 'Every report and saved report runs to JSON, CSV, or HTML, on demand or on a schedule you set.'],
                                ['title' => 'Feature flags', 'body' => 'Roll a new capability out to one team before every team, with per-flag targeting and rollout percentage.'],
                                ['title' => 'Audit log', 'body' => 'Every material action is written to a per-team audit trail — nothing happens silently.'],
                            ];
                        @endphp
                        @foreach ($capabilities as $c)
                            <div class="py-6 border-t border-[var(--line)] reveal" data-reveal>
                                <h3 class="font-display font-medium text-base text-[var(--paper)] mb-1.5">{{ $c['title'] }}</h3>
                                <p class="text-sm text-[var(--mist)] leading-relaxed">{{ $c['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="relative py-28 sm:py-36 px-5 sm:px-8 overflow-hidden">
            <div class="absolute inset-0" style="background: radial-gradient(ellipse 70% 60% at 50% 0%, rgba(241,198,46,0.08) 0%, transparent 60%), var(--ink);"></div>

            <div class="relative z-10 max-w-2xl mx-auto text-center reveal" data-reveal>
                <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight mb-5">
                    Connect a platform and see what surfaces
                </h2>
                <p class="text-[var(--mist)] leading-relaxed mb-10 max-w-lg mx-auto">
                    No exports, no dashboards to build first. Connect one Dot platform and the intelligence layer starts working on the next scheduled run.
                </p>

                @guest
                    <div class="flex flex-wrap justify-center gap-4">
                        <a href="{{ route('register') }}" class="press px-8 py-3.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[#0c1615] font-display font-semibold rounded-lg transition-colors">
                            Get started
                        </a>
                        <a href="{{ route('login') }}" class="press px-8 py-3.5 text-[var(--paper)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--mist)] transition-colors">
                            Sign in
                        </a>
                    </div>
                @endguest
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-14 px-5 sm:px-8 border-t border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-6">
                <a href="/" class="flex items-center">
                    <img src="{{ asset('images/logo.png') }}" alt="Dot.Analytics" class="h-11 w-auto opacity-90">
                </a>
                <p class="font-mono text-xs tracking-wide text-[var(--mist)] text-center sm:text-right">
                    &copy; {{ date('Y') }} SK Digital / BluPin Incorporated. Part of the Dot Ecosystem.
                </p>
            </div>
        </footer>

        <script>
            // Nav background on scroll
            (function () {
                const header = document.getElementById('site-header');
                const onScroll = () => {
                    if (window.pageYOffset > 24) {
                        header.classList.add('bg-[#0c1615]/95', 'backdrop-blur-md', 'border-b', 'border-[var(--line)]');
                    } else {
                        header.classList.remove('bg-[#0c1615]/95', 'backdrop-blur-md', 'border-b', 'border-[var(--line)]');
                    }
                };
                window.addEventListener('scroll', onScroll, { passive: true });
                onScroll();
            })();

            // Mobile menu toggle
            (function () {
                const btn = document.getElementById('mobile-menu-btn');
                const menu = document.getElementById('mobile-menu');
                const iconOpen = document.getElementById('icon-open');
                const iconClose = document.getElementById('icon-close');
                if (!btn || !menu) return;
                btn.addEventListener('click', () => {
                    const isHidden = menu.classList.contains('hidden');
                    menu.classList.toggle('hidden', !isHidden);
                    iconOpen.classList.toggle('hidden', isHidden);
                    iconClose.classList.toggle('hidden', !isHidden);
                    btn.setAttribute('aria-expanded', String(isHidden));
                });
            })();

            // Scroll reveal
            if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches && 'IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            io.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
                document.querySelectorAll('[data-reveal]').forEach((el) => io.observe(el));
            } else {
                document.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-visible'));
            }
        </script>
    </body>
</html>
