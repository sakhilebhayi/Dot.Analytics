<x-guest-layout>
    <x-authentication-card>
        <div class="mb-8">
            <h2 class="font-display font-semibold text-2xl text-[var(--paper)]">Create your account</h2>
            <p class="text-sm text-[var(--mist)] mt-1">Start connecting your Dot ecosystem platforms</p>
        </div>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="name" value="{{ __('Full name') }}" />
                <x-input id="name" class="block mt-1 text-sm" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            </div>

            <div>
                <x-label for="email" value="{{ __('Email address') }}" />
                <x-input id="email" class="block mt-1 text-sm" type="email" name="email" :value="old('email')" required autocomplete="username" />
            </div>

            <div>
                <x-label for="password" value="{{ __('Password') }}" />
                <x-input id="password" class="block mt-1 text-sm" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div>
                <x-label for="password_confirmation" value="{{ __('Confirm password') }}" />
                <x-input id="password_confirmation" class="block mt-1 text-sm" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <div>
                    <x-label for="terms">
                        <div class="flex items-start gap-2">
                            <x-checkbox name="terms" id="terms" required class="mt-0.5" />
                            <div class="text-sm text-[var(--mist)]">
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                    'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="text-[var(--teal-soft)] hover:underline">'.__('Terms of Service').'</a>',
                                    'privacy_policy'   => '<a target="_blank" href="'.route('policy.show').'" class="text-[var(--teal-soft)] hover:underline">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </div>
                        </div>
                    </x-label>
                </div>
            @endif

            <x-button class="w-full justify-center py-2.5">
                Create account
            </x-button>

            <p class="text-center text-sm text-[var(--mist)]">
                Already have an account?
                <a href="{{ route('login') }}" class="text-[var(--teal-soft)] font-medium hover:text-[var(--teal)]">Sign in</a>
            </p>
        </form>
    </x-authentication-card>
</x-guest-layout>
