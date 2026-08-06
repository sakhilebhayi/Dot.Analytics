@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full rounded-lg bg-[var(--ink)] border border-[var(--line)] text-[var(--paper)] placeholder-[var(--mist)]/60 shadow-sm focus:border-[var(--teal)] focus:ring-[var(--teal)] focus:ring-1 focus:outline-none']) !!}>
