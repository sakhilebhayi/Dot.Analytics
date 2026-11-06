@props(['kind' => 'source'])
@php
    $words = ['source' => 'Source fact', 'calculated' => 'Calculated by us', 'ai' => 'AI summary', 'editorial' => 'Editorial view', 'forecast' => 'Forecast'];
@endphp
<span class="label"><i class="glyph {{ $kind }}" aria-hidden="true"></i>{{ $words[$kind] ?? $kind }}</span>
