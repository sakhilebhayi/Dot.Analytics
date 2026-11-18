{{-- R-54: every sign-in failure ends here, in words, with one next step and no automatic redirect. --}}
<x-guest-layout title="Sign-in problem">
    <x-authentication-card>
        <div role="alert">
            <h1 class="font-display font-bold text-3xl text-[var(--paper)]">{{ $headline }}</h1>
            <p class="text-sm text-[var(--mist)] mt-2">{{ $body }}</p>
        </div>
        <div class="mt-6 grid gap-2">
            @if ($retry)<a class="press inline-flex items-center justify-center px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] rounded font-display font-semibold text-sm text-[var(--on-accent)] no-underline" style="min-height:44px" href="{{ $retryUrl }}">Start again</a>@endif
            <a class="press inline-flex items-center justify-center px-5 py-2.5 border border-[var(--line)] rounded font-display font-semibold text-sm text-[var(--paper)] no-underline" style="min-height:44px" href="{{ $homeUrl }}">Back to Dot.Analytics</a>
        </div>
        <p class="mt-4 text-xs text-[var(--mist)]">Reference: {{ $code }}</p>
    </x-authentication-card>
</x-guest-layout>
