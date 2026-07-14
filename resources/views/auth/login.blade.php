<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Welcome back</h2>
            <p class="text-sm text-gray-500 mt-1">Sign in to your intelligence dashboard</p>
        </div>

        <x-validation-errors class="mb-4" />

        @session('status')
            <div class="mb-4 font-medium text-sm text-green-600">
                {{ $value }}
            </div>
        @endsession

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="email" value="{{ __('Email address') }}" class="text-sm font-medium text-gray-700" />
                <x-input id="email" class="block mt-1 w-full rounded-lg border-gray-300 text-sm" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <x-label for="password" value="{{ __('Password') }}" class="text-sm font-medium text-gray-700" />
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-xs text-indigo-600 hover:text-indigo-800">Forgot password?</a>
                    @endif
                </div>
                <x-input id="password" class="block w-full rounded-lg border-gray-300 text-sm" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="flex items-center gap-2">
                <x-checkbox id="remember_me" name="remember" />
                <label for="remember_me" class="text-sm text-gray-600">Keep me signed in</label>
            </div>

            <x-button class="w-full justify-center bg-indigo-600 hover:bg-indigo-700 py-2.5 rounded-lg text-sm font-semibold">
                Sign in to Dot.Analytics
            </x-button>

            @if (Route::has('register'))
                <p class="text-center text-sm text-gray-500">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="text-indigo-600 font-medium hover:text-indigo-800">Get started free</a>
                </p>
            @endif
        </form>
    </x-authentication-card>
</x-guest-layout>
