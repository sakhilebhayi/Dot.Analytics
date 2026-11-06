<x-guest-layout>
    <x-authentication-card>
        <div class="mb-8">
            <h1 class="font-display font-bold text-3xl text-[var(--paper)]">Sign in</h1>
            <p class="text-sm text-[var(--mist)] mt-2">To your company's intelligence. Reading the public library needs no account.</p>
        </div>

        <x-validation-errors class="mb-4" />

        @session('status')
            <div class="mb-4 font-medium text-sm text-[var(--teal-soft)]">
                {{ $value }}
            </div>
        @endsession

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="email" value="{{ __('Email address') }}" />
                <x-input id="email" class="block mt-1 text-sm" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <x-label for="password" value="{{ __('Password') }}" />
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-xs text-[var(--teal-soft)] hover:text-[var(--teal)]">Forgot password?</a>
                    @endif
                </div>
                <x-input id="password" class="block text-sm" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="flex items-center gap-2">
                <x-checkbox id="remember_me" name="remember" />
                <label for="remember_me" class="text-sm text-[var(--mist)]">Keep me signed in</label>
            </div>

            <x-button class="w-full justify-center py-2.5">
                Sign in to Dot.Analytics
            </x-button>

            <p class="text-center text-sm text-[var(--mist)]">
                Accounts are made in InfoDot.
                <a href="{{ config('ecosystem.platforms.infodot.url') }}" class="text-[var(--teal-soft)] font-medium underline" rel="noopener">Go to InfoDot</a>
            </p>
        </form>
    </x-authentication-card>
</x-guest-layout>
