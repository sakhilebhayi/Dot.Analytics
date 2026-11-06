<x-public-layout title="Page not found" :noindex="true">
    <div class="wrap notfound">
        <h1>That page is not in the library.</h1>
        <p class="lede">The address may have changed, or we may never have held it. Search for what you wanted, or start from the industries.</p>
        <form class="search-big" action="{{ route('intel.find') }}" method="get" role="search">
            <label class="sr" for="q-404">Search the library</label>
            <input id="q-404" type="search" name="q" autocomplete="off" maxlength="160" placeholder="Search, or ask a question">
            <button type="submit">Search</button>
        </form>
        <p class="btn-row"><a class="btn quiet" href="{{ route('intel.home') }}">Go to the front page</a><a class="btn quiet" href="{{ route('intel.industries') }}">Industries</a></p>
    </div>
</x-public-layout>
