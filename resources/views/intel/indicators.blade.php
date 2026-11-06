<x-public-layout title="All figures" section="intel.indicators" description="Every published series in the library, by topic, with its source and how often it updates.">
    <div class="wrap page-head">
        <h1>Every series we publish.</h1>
        <p class="lede">A series appears here only when we hold a reading and have checked that its licence allows us to publish it.</p>
    </div>
    <div class="wrap section">
        @forelse($byTopic as $topic => $list)
            <h2 class="group">{{ \Illuminate\Support\Str::headline($topic) }}</h2>
            <table class="table-plain">
                <caption class="sr">Series on {{ $topic }}</caption>
                <thead><tr><th scope="col">Series</th><th scope="col">Source</th><th scope="col" class="n">Updates</th></tr></thead>
                <tbody>
                    @foreach($list as $i)
                        <tr><th scope="row"><a href="{{ route('intel.indicator', $i) }}">{{ $i->name }}</a></th><td>{{ $i->source->name }}</td><td class="n">{{ ['A' => 'Yearly', 'Q' => 'Quarterly', 'M' => 'Monthly', 'D' => 'Daily'][$i->frequency] ?? $i->frequency }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @empty
            <p class="empty-note"><b>No series yet.</b> The first collection has not finished.</p>
        @endforelse
    </div>
</x-public-layout>
