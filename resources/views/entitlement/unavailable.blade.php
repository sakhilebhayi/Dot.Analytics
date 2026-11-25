{{-- R-61: InfoDot has Dot.Analytics switched off (maintenance or unavailable). Rendered by App\Support\EntitlementRefusal
     with HTTP 503 inside the public layout, like the refusal page. It shows nothing from the workspace and is not a purchase
     or permission problem. The message is InfoDot's wording and is escaped. --}}
<x-public-layout title="Dot.Analytics is not available right now" :noindex="true">
    <div class="wrap notfound">
        <h1>Dot.Analytics is not available right now</h1>
        <p class="lede">{{ $message }} Your reports and connected sources are kept as they are.</p>
        <p class="btn-row">
            <a class="btn" href="{{ $infodotUrl }}" data-primary-action>Open InfoDot</a>
        </p>
        <p class="prov">Source: InfoDot Platforms. Reason given: temporarily unavailable. Nothing in your workspace has been changed.</p>

        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn quiet">Sign out</button>
            </form>
        @endauth
    </div>
</x-public-layout>
