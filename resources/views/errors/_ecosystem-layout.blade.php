@php
    // The other platforms, from the same registry every Dot platform shares, so a new one appears here by being added once.
    $discover = collect(config('ecosystem.platforms', []))
        ->reject(fn ($p) => ($p['name'] ?? null) === 'Dot.Analytics')
        ->reject(fn ($p) => ($p['active'] ?? true) === false)
        ->reject(fn ($p, $k) => ($p['internal'] ?? false) === true || in_array($k, ['memory', 'brain', 'dopemine'], true))
        ->values()
        ->all();
@endphp
<x-public-layout :title="'Error '.$code" :noindex="true">
    <div class="wrap notfound">
        <p class="prov">Error {{ $code }}</p>
        <h1>@yield('title')</h1>
        <p class="lede">@yield('message')</p>
        <p class="btn-row">
            <a class="btn" href="{{ route('intel.home') }}">Go to the front page</a>
            @auth
                <a class="btn quiet" href="{{ route('dashboard') }}">Company intelligence</a>
            @else
                @if (Route::has('login'))<a class="btn quiet" href="{{ route('login') }}">Sign in</a>@endif
            @endauth
        </p>

        @if (! empty($discover))
            <section aria-labelledby="more-h">
                <h2 id="more-h" class="group">While you are here, the rest of the Dot Ecosystem ({{ count($discover) }})</h2>
                <ul class="chips">
                    @foreach ($discover as $platform)<li><a href="{{ $platform['url'] }}" rel="noopener">{{ $platform['name'] }}</a></li>@endforeach
                </ul>
            </section>
        @endif
    </div>
</x-public-layout>
