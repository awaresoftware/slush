<x-layouts::auth>
    <div class="mt-4 flex flex-col gap-6">
        <p class="text-sm text-center">
            {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
        </p>

        @if (session('status') == 'verification-link-sent')
            <p class="text-sm text-center font-medium text-success">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </p>
        @endif

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-mary-button class="btn-primary btn-capsule w-full" type="submit">
                    {{ __('Resend verification email') }}
                </x-mary-button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-mary-button class="btn-ghost text-sm" type="submit" data-test="logout-button">
                    {{ __('Log out') }}
                </x-mary-button>
            </form>
        </div>
    </div>
</x-layouts::auth>
