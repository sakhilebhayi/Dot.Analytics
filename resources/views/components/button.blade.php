<button {{ $attributes->merge(['type' => 'submit', 'class' => 'press inline-flex items-center px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] border border-transparent rounded-lg font-display font-semibold text-sm text-[#0c1615] focus:outline-none focus:ring-2 focus:ring-[var(--teal)] focus:ring-offset-2 focus:ring-offset-[var(--ink-soft)] disabled:opacity-50 transition-colors duration-150']) }}>
    {{ $slot }}
</button>
