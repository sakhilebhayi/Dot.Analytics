<x-public-layout title="Free and paid" section="intel.plans" description="Everything published in the library is free to read. What a paid plan could add, and what it never will.">
    <div class="wrap page-head">
        <h1>Reading is free, for everyone, for good.</h1>
        <p class="lede">Nothing published here is held back from anyone, signed in or not. A plan can add convenience. It can never put a figure behind a payment, and it can never unlock data that a source's licence does not let us sell.</p>
    </div>
    <section class="wrap section" aria-labelledby="free-h">
        <div class="section-head"><h2 id="free-h">Always free</h2></div>
        <ul class="prose">@foreach($always as $line)<li>{{ $line }}</li>@endforeach</ul>
    </section>
    <section class="wrap section" aria-labelledby="plans-h">
        <div class="section-head"><h2 id="plans-h">What you can keep</h2><p>Limits protect storage and your inbox. They never hide a page.</p></div>
        <table class="table-plain">
            <caption class="sr">What each plan lets you keep</caption>
            <thead><tr><th scope="col">Plan</th><th scope="col" class="n">Things you watch</th><th scope="col" class="n">Saved items</th><th scope="col" class="n">Alerts kept</th><th scope="col">Status</th></tr></thead>
            <tbody>
                @foreach($plans as $key => $plan)
                    <tr><th scope="row">{{ $plan['name'] }}</th><td class="n num">{{ $plan['watches'] }}</td><td class="n num">{{ $plan['saved'] }}</td><td class="n num">{{ $plan['alerts_kept'] }}</td><td>{{ ($plan['for_sale'] ?? true) ? 'What everyone has today' : 'Designed, not on sale. No price is set.' }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <p class="prose">There is no billing on this platform yet. When there is, this page will say what a paid plan costs before anyone is asked for anything.</p>
    </section>
</x-public-layout>
