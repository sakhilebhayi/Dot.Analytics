{{-- R-50: the company does not hold Dot.Analytics. Rendered by App\Support\EntitlementRefusal with HTTP 403 inside the
     public layout, like the other plain pages (errors/*). It shows nothing from the workspace. One primary action. --}}
<x-public-layout :title="$title" :noindex="true">
    <div class="wrap notfound">
        <h1>{{ $title }}</h1>
        <p class="lede">{{ $body }}</p>
        <p class="btn-row">
            <a class="btn" href="{{ $platformsUrl }}" data-primary-action>Open Platforms in InfoDot</a>
        </p>

        @auth
            @if ($otherCompanies->isNotEmpty())
                <section aria-labelledby="gate-others">
                    <h2 id="gate-others" class="group">Your other {{ $otherCompanies->count() === 1 ? 'company' : 'companies' }} here</h2>
                    <ul class="chips">
                        @foreach ($otherCompanies as $other)
                            <li>
                                <form method="POST" action="{{ route('current-team.update') }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="team_id" value="{{ $other->id }}">
                                    <button type="submit" class="btn quiet" aria-label="Switch to {{ $other->name }}">{{ $other->name }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn quiet">Sign out</button>
            </form>
        @endauth
    </div>
</x-public-layout>
