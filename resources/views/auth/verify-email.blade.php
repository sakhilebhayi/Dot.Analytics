<x-guest-layout>
    <x-authentication-card>
        <div class="mb-4 text-sm text-[var(--mist)]">
            {{ __('Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="mb-4 font-medium text-sm text-teal-300">
                {{ __('A new verification link has been sent to the email address you provided in your profile settings.') }}
            </div>
        @endif

        <div class="mt-4 flex items-center justify-between">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <div>
                    <x-button type="submit">
                        {{ __('Resend Verification Email') }}
                    </x-button>
                </div>
            </form>

            <div>
                <a
                    href="{{ route('profile.show') }}"
                    class="underline text-sm text-[var(--mist)] hover:text-[var(--paper)] rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-[var(--ink-soft)] focus:ring-[var(--teal)]"
                >
                    {{ __('Edit Profile') }}</a>

                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf

                    <button type="submit" class="underline text-sm text-[var(--mist)] hover:text-[var(--paper)] rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-[var(--ink-soft)] focus:ring-[var(--teal)] ms-2">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </x-authentication-card>
</x-guest-layout>
