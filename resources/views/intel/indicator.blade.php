@php $s = $indicator->source; @endphp
<x-public-layout :title="$indicator->name.', '.$place->name" section="intel.indicators" :description="$indicator->name.' for '.$place->name.': '.$figure->value().' '.$figure->note().' ('.$figure->period().'), from '.$s->name.'.'"
    :jsonld="['@context' => 'https://schema.org', '@type' => 'Dataset', 'name' => $indicator->name.', '.$place->name, 'description' => $indicator->definition, 'creator' => ['@type' => 'Organization', 'name' => $s->name, 'url' => $s->homepage_url], 'license' => $s->terms_url ?: $s->homepage_url, 'isAccessibleForFree' => true]">
    <div class="wrap page-head">
        <ol class="crumbs"><li><a href="{{ route('intel.indicators') }}">All figures</a></li><li aria-current="page">{{ $indicator->name }}</li></ol>
        <h1>{{ $indicator->name }}, {{ $place->name }}</h1>
        <p class="lede">{{ $indicator->definition }}</p>
        <p class="btn-row"><a class="btn quiet" href="{{ route('me.watch.confirm', ['kind' => 'indicator', 'key' => $indicator->key, 'place' => $place->code]) }}">Watch this figure</a></p>
    </div>

    <div class="wrap big-figure"><x-intel.figure :figure="$figure" :chart="true" :place="false"/></div>

    <section class="wrap section split" aria-labelledby="cmp-h">
        <div>
            <div class="section-head"><h2 id="cmp-h">Against other places</h2><p>Each place's newest reading. Years differ.</p></div>
            <table class="table-plain">
                <caption class="sr">Newest {{ $indicator->name }} by place</caption>
                <thead><tr><th scope="col">Place</th><th scope="col">Period</th><th scope="col" class="n">{{ \App\Services\Intelligence\Reading\Format::note($indicator->unit) }}</th></tr></thead>
                <tbody>
                    @foreach($compare as $row)
                        <tr @if($row['geography']->id === $place->id) aria-current="true" @endif>
                            <th scope="row"><a href="{{ route('intel.indicator', [$indicator, 'place' => $row['geography']->code]) }}">{{ $row['geography']->name }}</a></th>
                            <td>{{ $row['period'] }}@if($row['stale']), out of date @endif</td>
                            <td class="n num">{{ $row['value'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <aside class="panel" aria-labelledby="use-h">
            <h3 id="use-h">Use this figure</h3>
            <p>Download every value we hold with the source line attached, or read the source's own terms.</p>
            <p class="btn-row"><a class="btn quiet" href="{{ route('intel.indicator.csv', [$indicator, 'place' => $place->code]) }}">Download CSV</a></p>
            @if($indicator->caveat)<p><b>Limits.</b> {{ $indicator->caveat }}</p>@endif
        </aside>
    </section>
</x-public-layout>
