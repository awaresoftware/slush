<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    public bool $showDeleteModal = false;

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }

    /**
     * Close the delete modal and reset state.
     */
    public function closeModal(): void
    {
        $this->reset('password', 'showDeleteModal');
        $this->resetErrorBag();
    }
}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <h3 class="text-lg font-semibold">{{ __('Delete account') }}</h3>
        <p class="text-sm text-base-content/60">{{ __('Delete your account and all of its resources') }}</p>
    </div>

    <x-mary-button class="btn-error btn-capsule" wire:click="$set('showDeleteModal', true)" data-test="delete-user-button">
        {{ __('Delete account') }}
    </x-mary-button>

    <x-mary-modal wire:model="showDeleteModal" box-class="max-w-lg">
        <form wire:submit="deleteUser" class="space-y-6">
            <div>
                <h3 class="text-lg font-semibold">{{ __('Are you sure you want to delete your account?') }}</h3>

                <p class="text-sm text-base-content/60 mt-2">
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                </p>
            </div>

            <x-mary-input wire:model="password" :label="__('Password')" type="password" />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <x-mary-button class="btn-capsule bg-base-300 text-base-content" wire:click="closeModal" type="button">{{ __('Cancel') }}</x-mary-button>

                <x-mary-button class="btn-error btn-capsule" type="submit" data-test="confirm-delete-user-button">
                    {{ __('Delete account') }}
                </x-mary-button>
            </div>
        </form>
    </x-mary-modal>
</section>
