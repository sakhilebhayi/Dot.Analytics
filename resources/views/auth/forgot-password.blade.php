<x-guest-layout>
    <x-authentication-card>
        <div class="mb-8">
            <h2 class="font-display font-semibold text-2xl text-[var(--paper)]">Reset your password</h2>
            <p class="text-sm text-[var(--mist)] mt-1">Enter your email and we'll send you a reset link.</p>
        </div>

        @session('status')
            <div class="mb-4 font-medium text-sm text-teal-300">
                {{ $value }}
            </div>
        @endsession

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="email" value="{{ __('Email address') }}" />
                <x-input id="email" class="block mt-1 text-sm" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <x-button class="w-full justify-center py-2.5">
                Send reset link
            </x-button>

            <p class="text-center text-sm text-[var(--mist)]">
                <a href="{{ route('login') }}" class="text-[var(--teal-soft)] font-medium hover:text-[var(--teal)]">Back to sign in</a>
            </p>
        </form>
    </x-authentication-card>
</x-guest-layout>
