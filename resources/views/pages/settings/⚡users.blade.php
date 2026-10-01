<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        if (! auth()->user()->is_admin) {
            abort(403);
        }
    }

    /**
     * Create a new user directly with a temporary password.
     */
    public function createUser(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'is_admin' => false,
            'force_password_change' => true,
        ]);

        session()->flash('user_created', 'User created: '.$this->email.'. They must change their password on first login.');

        $this->reset('name', 'email', 'password', 'password_confirmation');
    }

    /**
     * Delete a user by ID (cannot delete yourself).
     */
    public function deleteUser(int $userId): void
    {
        if ($userId === auth()->id()) {
            return;
        }

        $user = User::findOrFail($userId);
        $user->delete();
    }

    /**
     * Send a password reset email for the given user.
     */
    public function resetPassword(int $userId): void
    {
        $user = User::findOrFail($userId);

        Password::sendResetLink(['email' => $user->email]);

        session()->flash('reset_sent', 'Password reset email sent to '.$user->email.'.');
    }

    #[Computed]
    public function users(): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('id', '!=', auth()->id())->orderBy('name')->get();
    }
}; ?>

<section class="w-full px-4 pt-3 sm:px-6">
    @include('partials.settings-heading')

    <h2 class="sr-only">{{ __('User Management') }}</h2>

    <x-pages::settings.layout :heading="__('Users')" :subheading="__('Manage users')">
        {{-- Create user form --}}
        <form wire:submit="createUser" class="mt-6 space-y-4">
            <x-mary-input
                wire:model="name"
                :label="__('Name')"
                placeholder="Jane Smith"
                autocomplete="off"
                required
            />

            @error('name')
                <p class="text-sm text-error">{{ $message }}</p>
            @enderror

            <x-mary-input
                wire:model="email"
                :label="__('Email')"
                type="email"
                placeholder="user@example.com"
                autocomplete="off"
                required
            />

            @error('email')
                <p class="text-sm text-error">{{ $message }}</p>
            @enderror

            <x-mary-input
                wire:model="password"
                :label="__('Password')"
                type="password"
                autocomplete="new-password"
                required
            />

            @error('password')
                <p class="text-sm text-error">{{ $message }}</p>
            @enderror

            <x-mary-input
                wire:model="password_confirmation"
                :label="__('Confirm Password')"
                type="password"
                autocomplete="new-password"
                required
            />

            @if (session('user_created'))
                <p class="text-sm text-success">{{ session('user_created') }}</p>
            @endif

            <x-mary-button class="btn-primary" type="submit">
                {{ __('Create User') }}
            </x-mary-button>
        </form>

        {{-- User list --}}
        @if ($this->users->isNotEmpty())
            <div class="mt-8 space-y-3">
                <h4 class="text-sm font-semibold">{{ __('Existing Users') }}</h4>

                @if (session('reset_sent'))
                    <p class="text-sm text-success">{{ session('reset_sent') }}</p>
                @endif

                <div class="divide-y divide-base-content/10">
                    @foreach ($this->users as $user)
                        <div class="flex items-center justify-between py-3" wire:key="user-{{ $user->id }}">
                            <div>
                                <p class="text-sm font-medium">
                                    {{ $user->name }}
                                    @if ($user->force_password_change)
                                        <span class="text-xs text-warning">(must change password)</span>
                                    @endif
                                </p>
                                <p class="text-sm text-base-content/60">{{ $user->email }}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <x-mary-button
                                    class="btn-ghost btn-sm"
                                    wire:click="resetPassword({{ $user->id }})"
                                    wire:confirm="Send password reset email to {{ $user->email }}?"
                                >
                                    {{ __('Reset Password') }}
                                </x-mary-button>

                                <x-mary-button
                                    class="btn-error btn-sm"
                                    wire:click="deleteUser({{ $user->id }})"
                                    wire:confirm="Delete {{ $user->name }}? This cannot be undone."
                                >
                                    {{ __('Delete') }}
                                </x-mary-button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-pages::settings.layout>
</section>
