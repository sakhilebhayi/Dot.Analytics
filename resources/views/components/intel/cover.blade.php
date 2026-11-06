@props(['cells', 'key' => false])
@php
    use App\Services\Intelligence\Reading\Freshness;
    $count = fn (Freshness $f) => count(array_filter($cells, fn ($c) => $c['freshness'] === $f));
    $summary = $count(Freshness::Current).' of '.count($cells).' series current, '.$count(Freshness::Stale).' out of date, '.$count(Freshness::Missing).' not available';
@endphp
@if($cells !== [])
<span class="cover" role="img" aria-label="{{ $summary }}">
    @foreach(collect($cells)->sortBy(fn ($c) => ['current' => 0, 'stale' => 1, 'missing' => 2][$c['freshness']->value])->values() as $cell)<i class="{{ $cell['freshness']->value }}"></i>@endforeach
</span>
@endif
@if($key)
    <p class="cover-key" aria-hidden="true">
        <span><i class="sw current"></i> Current</span>
        <span><i class="sw stale"></i> Out of date</span>
        <span><i class="sw missing"></i> Not available</span>
    </p>
@endif
