<x-public-layout title="The InfoDot ecosystem" section="intel.ecosystem" description="The platforms around Dot.Analytics, listed only while they answer.">
    <div class="wrap page-head">
        <h1>Where this fits, and where to go next.</h1>
        <p class="lede">Dot.Analytics reads the world. InfoDot and the Dot platforms are where a business acts on it. We list a platform here only while it answers a check we run each day.</p>
    </div>
    <div class="wrap section">
        @forelse($platforms as $p)
            <article class="src-card">
                <h3><a href="{{ $p['url'] }}" rel="noopener">{{ $p['name'] }}</a></h3>
                <p>{{ $p['does'] }}</p>
            </article>
        @empty
            <p class="empty-note"><b>No platform answered our last check.</b> We show none rather than link to something that may be down.</p>
        @endforelse
    </div>
</x-public-layout>
