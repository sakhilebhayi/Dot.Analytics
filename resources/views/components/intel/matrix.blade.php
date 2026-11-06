@props(['matrix', 'caption'])
<div class="matrix-wrap" tabindex="0" role="region" aria-label="{{ $caption }}">
    <table class="matrix">
        <caption class="sr">{{ $caption }}</caption>
        <thead>
            <tr>
                <th scope="col">Country</th>
                @foreach($matrix['indicators'] as $i)<th scope="col">{{ $i->name }}<br><span class="num">{{ \App\Services\Intelligence\Reading\Format::note($i->unit) }}</span></th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($matrix['rows'] as $row)
                <tr>
                    <th scope="row"><a href="{{ route('intel.country', $row['geography']) }}">{{ $row['geography']->name }}</a></th>
                    @foreach($matrix['indicators'] as $i)
                        @php $f = $row['cells'][$i->key] ?? null; @endphp
                        @if($f)
                            <td data-label="{{ $i->name }}" class="{{ $f->freshness() === \App\Services\Intelligence\Reading\Freshness::Stale ? 'stale' : '' }}">
                                <a href="{{ route('intel.indicator', [$i, 'place' => $row['geography']->code]) }}" class="num">{{ $f->value() }}</a>
                                <small>{{ $f->period() }}@if($f->freshness() === \App\Services\Intelligence\Reading\Freshness::Stale), out of date @endif</small>
                            </td>
                        @else
                            <td data-label="{{ $i->name }}" class="missing">Not available</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
