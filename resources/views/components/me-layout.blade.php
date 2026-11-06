@props(['title'])
@php
    $unread = auth()->user()?->libraryAlerts()->whereNull('read_at')->count() ?? 0;
    $tabs = [['me.home', 'Library'], ['me.watchlist', 'Watchlist'], ['me.saved', 'Saved'], ['me.alerts', 'Alerts'], ['me.preferences', 'Preferences']];
@endphp
<x-public-layout :title="$title" :noindex="true" section="me">
    <div class="wrap me-bar">
        <nav class="me-nav" aria-label="My library">
            @foreach($tabs as [$route, $label])
                <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}@if($route === 'me.alerts' && $unread > 0) <span class="count" aria-label="{{ $unread }} unread">{{ $unread }}</span>@endif</a>
            @endforeach
        </nav>
        <div class="me-end">
            <a href="{{ route('dashboard') }}">Company intelligence</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
        </div>
    </div>
    @if(session('status'))<div class="wrap"><p class="flash" role="status">{{ session('status') }}</p></div>@endif
    @if(session('error'))<div class="wrap"><p class="flash bad" role="alert">{{ session('error') }}</p></div>@endif
    {{ $slot }}
</x-public-layout>
