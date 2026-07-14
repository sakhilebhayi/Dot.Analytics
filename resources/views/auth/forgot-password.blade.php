<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Reset your password</h2>
            <p class="text-sm text-gray-500 mt-1">Enter your email and we'll send you a reset link.</p>
        </div>

        @session('status')
            <div class="mb-4 font-medium text-sm text-green-600">
                {{ $value }}
            </div>
        @endsession

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="email" value="{{ __('Email address') }}" class="text-sm font-medium text-gray-700" />
                <x-input id="email" class="block mt-1 w-full rounded-lg border-gray-300 text-sm" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <x-button class="w-full justify-center bg-indigo-600 hover:bg-indigo-700 py-2.5 rounded-lg text-sm font-semibold">
                Send reset link
            </x-button>

            <p class="text-center text-sm text-gray-500">
                <a href="{{ route('login') }}" class="text-indigo-600 font-medium hover:text-indigo-800">Back to sign in</a>
            </p>
        </form>
    </x-authentication-card>
</x-guest-layout>
