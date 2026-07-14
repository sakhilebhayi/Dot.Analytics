<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Create your account</h2>
            <p class="text-sm text-gray-500 mt-1">Start connecting your Dot ecosystem platforms</p>
        </div>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="name" value="{{ __('Full name') }}" class="text-sm font-medium text-gray-700" />
                <x-input id="name" class="block mt-1 w-full rounded-lg border-gray-300 text-sm" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            </div>

            <div>
                <x-label for="email" value="{{ __('Email address') }}" class="text-sm font-medium text-gray-700" />
                <x-input id="email" class="block mt-1 w-full rounded-lg border-gray-300 text-sm" type="email" name="email" :value="old('email')" required autocomplete="username" />
            </div>

            <div>
                <x-label for="password" value="{{ __('Password') }}" class="text-sm font-medium text-gray-700" />
                <x-input id="password" class="block mt-1 w-full rounded-lg border-gray-300 text-sm" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div>
                <x-label for="password_confirmation" value="{{ __('Confirm password') }}" class="text-sm font-medium text-gray-700" />
                <x-input id="password_confirmation" class="block mt-1 w-full rounded-lg border-gray-300 text-sm" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <div>
                    <x-label for="terms">
                        <div class="flex items-start gap-2">
                            <x-checkbox name="terms" id="terms" required class="mt-0.5" />
                            <div class="text-sm text-gray-600">
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                    'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="text-indigo-600 hover:underline">'.__('Terms of Service').'</a>',
                                    'privacy_policy'   => '<a target="_blank" href="'.route('policy.show').'" class="text-indigo-600 hover:underline">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </div>
                        </div>
                    </x-label>
                </div>
            @endif

            <x-button class="w-full justify-center bg-indigo-600 hover:bg-indigo-700 py-2.5 rounded-lg text-sm font-semibold">
                Create account
            </x-button>

            <p class="text-center text-sm text-gray-500">
                Already have an account?
                <a href="{{ route('login') }}" class="text-indigo-600 font-medium hover:text-indigo-800">Sign in</a>
            </p>
        </form>
    </x-authentication-card>
</x-guest-layout>
