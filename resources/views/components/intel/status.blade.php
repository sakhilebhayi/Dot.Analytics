@props(['freshness'])
<span class="status {{ $freshness->value }}">{{ $freshness->label() }}</span>
