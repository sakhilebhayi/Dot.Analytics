<x-guest-layout>
    <x-authentication-card>
        <div class="mb-8">
            <h2 class="font-display font-semibold text-2xl text-[var(--paper)]">Confirm your password</h2>
            <p class="text-sm text-[var(--mist)] mt-1">This is a secure area. Please confirm your password before continuing.</p>
        </div>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="password" value="{{ __('Password') }}" />
                <x-input id="password" class="block mt-1 text-sm" type="password" name="password" required autocomplete="current-password" autofocus />
            </div>

            <x-button class="w-full justify-center py-2.5">
                Confirm and continue
            </x-button>
        </form>
    </x-authentication-card>
</x-guest-layout>
