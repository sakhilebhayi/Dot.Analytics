<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Confirm your password</h2>
            <p class="text-sm text-gray-500 mt-1">This is a secure area. Please confirm your password before continuing.</p>
        </div>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="password" value="{{ __('Password') }}" class="text-sm font-medium text-gray-700" />
                <x-input id="password" class="block mt-1 w-full rounded-lg border-gray-300 text-sm" type="password" name="password" required autocomplete="current-password" autofocus />
            </div>

            <x-button class="w-full justify-center bg-indigo-600 hover:bg-indigo-700 py-2.5 rounded-lg text-sm font-semibold">
                Confirm and continue
            </x-button>
        </form>
    </x-authentication-card>
</x-guest-layout>
