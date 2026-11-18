{{-- R-54: the claim page, in the reading-room language (DESIGN.md). Shown when an account here already uses the email
     address of the InfoDot sign-in. Nothing on the account is replaced; the code proves the address. --}}
<x-guest-layout title="Confirm it is you">
    <x-authentication-card>
        <div class="mb-6">
            <h1 class="font-display font-bold text-3xl text-[var(--paper)]">Confirm it is you</h1>
            <p class="text-sm text-[var(--mist)] mt-2" style="overflow-wrap:anywhere">An account here already uses <strong class="text-[var(--paper)]">{{ $maskedEmail }}</strong>. We sent a {{ $codeLength }}-digit code to that address. Enter it to connect the two. Your library, watches and workspace stay as they are.</p>
        </div>

        @if (session('dotsso_status'))
            <p class="mb-4 text-sm font-medium text-[var(--paper)]" role="status">{{ session('dotsso_status') }}</p>
        @endif

        <form method="POST" action="{{ $verifyUrl }}" class="space-y-4" novalidate>
            @csrf
            <div>
                <x-label for="code" value="Code from the email" />
                <x-input id="code" class="block mt-1 text-center text-2xl font-semibold tracking-[0.3em] tabular-nums" style="min-height:48px" type="text" name="code" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="{{ $codeLength }}" required autofocus aria-describedby="code-help code-error" aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}" />
                <p id="code-help" class="mt-1 text-xs text-[var(--mist)]">It works for {{ $minutes }} minutes. You have {{ $maxAttempts }} tries.</p>
                <p id="code-error" class="mt-1 text-sm text-[var(--danger)] empty:hidden" role="alert">{{ $errors->first('code') }}</p>
            </div>
            <x-button class="w-full justify-center py-2.5" style="min-height:44px">Confirm and continue</x-button>
        </form>

        <div class="mt-4 flex flex-wrap gap-2">
            <form method="POST" action="{{ $resendUrl }}">@csrf<button type="submit" class="press inline-flex items-center px-4 text-sm text-[var(--mist)] underline underline-offset-4 hover:text-[var(--paper)]" style="min-height:44px">Send a new code</button></form>
            <form method="POST" action="{{ $cancelUrl }}">@csrf<button type="submit" class="press inline-flex items-center px-4 text-sm text-[var(--mist)] underline underline-offset-4 hover:text-[var(--paper)]" style="min-height:44px">Cancel</button></form>
        </div>
    </x-authentication-card>
</x-guest-layout>
