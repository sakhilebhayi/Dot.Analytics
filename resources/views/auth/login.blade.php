<x-guest-layout>
    <x-authentication-card>
        <div class="mb-8">
            <h2 class="font-display font-semibold text-2xl text-[var(--paper)]">Welcome back</h2>
            <p class="text-sm text-[var(--mist)] mt-1">Sign in to your intelligence dashboard</p>
        </div>

        <x-validation-errors class="mb-4" />

        @session('status')
            <div class="mb-4 font-medium text-sm text-teal-300">
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

            @if (Route::has('register'))
                <p class="text-center text-sm text-[var(--mist)]">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="text-[var(--teal-soft)] font-medium hover:text-[var(--teal)]">Get started free</a>
                </p>
            @endif
        </form>
    </x-authentication-card>
</x-guest-layout>
