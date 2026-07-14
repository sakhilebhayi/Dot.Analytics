<div class="min-h-screen flex">

    {{-- Left panel: brand + ecosystem context --}}
    <div class="hidden lg:flex lg:w-1/2 bg-slate-950 flex-col justify-between p-12">
        {{-- Logo --}}
        <a href="/" class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center shrink-0">
                <span class="text-sm font-bold text-white">D</span>
            </div>
            <span class="font-semibold text-white tracking-tight">Dot.Analytics</span>
            <span class="text-xs text-slate-500 font-medium">EIP</span>
        </a>

        {{-- Headline --}}
        <div>
            <p class="text-xs font-semibold text-indigo-400 uppercase tracking-widest mb-3">Enterprise Intelligence Platform</p>
            <h1 class="text-3xl font-bold text-white leading-snug mb-4">
                The central nervous system<br/>of the Dot ecosystem.
            </h1>
            <p class="text-slate-400 text-sm leading-relaxed max-w-xs">
                Connect every Dot platform. Discover cross-platform intelligence that no individual tool can see.
            </p>

            {{-- Platform dots --}}
            <div class="mt-8 grid grid-cols-5 gap-2">
                @foreach([
                    ['Fleet',     'bg-blue-600'],
                    ['CRM',       'bg-green-600'],
                    ['HR',        'bg-purple-600'],
                    ['Hear',      'bg-pink-600'],
                    ['Documents', 'bg-yellow-600'],
                    ['Support',   'bg-orange-600'],
                    ['Inventory', 'bg-teal-600'],
                    ['Payments',  'bg-emerald-600'],
                    ['Security',  'bg-red-600'],
                    ['Agents',    'bg-violet-600'],
                ] as [$name, $color])
                    <div class="flex flex-col items-center gap-1">
                        <div class="w-7 h-7 rounded-lg {{ $color }} flex items-center justify-center">
                            <span class="text-white text-xs font-bold">{{ $name[0] }}</span>
                        </div>
                        <span class="text-slate-600 text-xs">{{ $name }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Footer --}}
        <p class="text-xs text-slate-700">Part of the Dot Ecosystem &mdash; &copy; {{ date('Y') }} SK Digital</p>
    </div>

    {{-- Right panel: form --}}
    <div class="flex-1 flex flex-col items-center justify-center bg-white px-6 py-12">
        {{-- Mobile logo --}}
        <div class="flex lg:hidden items-center gap-2 mb-8">
            {{ $logo }}
        </div>

        <div class="w-full max-w-sm">
            {{ $slot }}
        </div>
    </div>

</div>
