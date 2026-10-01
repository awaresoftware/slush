<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use ProfileValidationRules;
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $bio = '';

    #[Rule(['nullable', 'image', 'max:2048'])]
    public $avatar = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->bio = Auth::user()->bio ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $this->validateOnly('avatar');

        if ($this->avatar !== null) {
            if ($user->avatar_path !== null) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $manager = new ImageManager(new Driver());
            $image = $manager->read($this->avatar->getRealPath());
            $image->cover(400, 400);
            $path = 'avatars/' . $user->id . '.webp';
            Storage::disk('public')->put($path, $image->toWebp());
            $user->avatar_path = $path;
            $this->avatar = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Remove the user's avatar image.
     */
    public function removeAvatar(): void
    {
        $user = Auth::user();

        if ($user->avatar_path !== null) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        }

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail
            || (Auth::user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full px-4 pt-3 sm:px-6">
    @include('partials.settings-heading')

    <h2 class="sr-only">{{ __('Profile Settings') }}</h2>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name, email, bio and avatar')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <x-mary-input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <x-mary-input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <p class="mt-4 text-sm">
                            {{ __('Your email address is unverified.') }}

                            <a class="text-sm link cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </a>
                        </p>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 text-sm font-medium text-success">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <div>
                <x-mary-textarea
                    wire:model="bio"
                    :label="__('Bio')"
                    rows="3"
                    maxlength="160"
                    :placeholder="__('Tell others a little about yourself')"
                />
                <p class="text-xs mt-1 text-base-content/60">{{ strlen($bio) }}/160</p>
            </div>

            <div>
                <label class="label"><span class="label-text">{{ __('Avatar') }}</span></label>

                <div class="mt-2 flex items-center gap-4">
                    <div class="relative shrink-0">
                        @if (auth()->user()->avatarUrl())
                            <img
                                src="{{ auth()->user()->avatarUrl() }}"
                                alt="{{ __('Current avatar') }}"
                                class="artwork size-24 rounded-full object-cover"
                            >
                        @else
                            <div class="flex size-24 items-center justify-center rounded-full bg-primary/12 text-title-2 font-semibold text-primary">
                                {{ auth()->user()->initials() }}
                            </div>
                        @endif

                        <span class="surface-chrome absolute right-0 bottom-0 flex size-7 items-center justify-center rounded-full text-base-content shadow-1">
                            <x-mary-icon name="o-camera" class="size-4" />
                        </span>
                    </div>

                    <div class="flex flex-col gap-2">
                        <input
                            type="file"
                            wire:model="avatar"
                            accept="image/*"
                            class="block cursor-pointer text-subhead text-base-content/65 file:mr-4 file:rounded-field file:border-0 file:bg-base-300 file:px-4 file:py-2 file:text-subhead file:font-medium file:text-base-content hover:file:bg-fill"
                        >

                        @error('avatar')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror

                        @if (auth()->user()->avatar_path)
                            <x-mary-button
                                type="button"
                                wire:click="removeAvatar"
                                class="btn-ghost btn-sm text-error hover:text-error/80"
                            >
                                {{ __('Remove avatar') }}
                            </x-mary-button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <x-mary-button class="btn-primary btn-capsule w-full" type="submit" data-test="update-profile-button">
                        {{ __('Save') }}
                    </x-mary-button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
