<?php

use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Symfony\Component\HttpFoundation\Response;

new class extends Component {
    #[Locked]
    public bool $twoFactorEnabled;

    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showModal = false;

    public bool $showVerificationStep = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        abort_unless(Features::enabled(Features::twoFactorAuthentication()), Response::HTTP_FORBIDDEN);

        if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
            $disableTwoFactorAuthentication(auth()->user());
        }

        $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
    }

    /**
     * Enable two-factor authentication for the user.
     */
    public function enable(EnableTwoFactorAuthentication $enableTwoFactorAuthentication): void
    {
        $enableTwoFactorAuthentication(auth()->user());

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        }

        $this->loadSetupData();

        $this->showModal = true;
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        $user = auth()->user();

        try {
            $this->qrCodeSvg = $user?->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->closeModal();

        $this->twoFactorEnabled = true;
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showModal',
            'showVerificationStep',
        );

        $this->resetErrorBag();

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        }
    }

    /**
     * Get the current modal configuration state.
     */
    public function getModalConfigProperty(): array
    {
        if ($this->twoFactorEnabled) {
            return [
                'title' => __('Two-Factor Authentication Enabled'),
                'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify Authentication Code'),
                'description' => __('Enter the 6-digit code from your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable Two-Factor Authentication'),
            'description' => __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.'),
            'buttonText' => __('Continue'),
        ];
    }
}; ?>

<section class="w-full px-4 pt-3 sm:px-6">
    @include('partials.settings-heading')

    <h2 class="sr-only">{{ __('Two-Factor Authentication Settings') }}</h2>

    <x-pages::settings.layout
        :heading="__('Two Factor Authentication')"
        :subheading="__('Manage your two-factor authentication settings')"
    >
        <div class="flex flex-col w-full mx-auto space-y-6 text-sm" wire:cloak>
            @if ($twoFactorEnabled)
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <x-mary-badge value="{{ __('Enabled') }}" class="badge-success" />
                    </div>

                    <p class="text-sm">
                        {{ __('With two-factor authentication enabled, you will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                    </p>

                    <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />

                    <div class="flex justify-start">
                        <x-mary-button
                            class="btn-error"
                            icon="o-shield-exclamation"
                            wire:click="disable"
                        >
                            {{ __('Disable 2FA') }}
                        </x-mary-button>
                    </div>
                </div>
            @else
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <x-mary-badge value="{{ __('Disabled') }}" class="badge-error" />
                    </div>

                    <p class="text-sm text-base-content/60">
                        {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                    </p>

                    <x-mary-button
                        class="btn-primary"
                        icon="o-shield-check"
                        wire:click="enable"
                    >
                        {{ __('Enable 2FA') }}
                    </x-mary-button>
                </div>
            @endif
        </div>
    </x-pages::settings.layout>

    <x-mary-modal wire:model="showModal" box-class="max-w-md md:min-w-[28rem]" @close="closeModal">
        <div class="space-y-6">
            <div class="flex flex-col items-center space-y-4">
                <div class="p-0.5 w-auto rounded-full border border-separator bg-base-100 shadow-1">
                    <div class="p-2.5 rounded-full relative overflow-hidden border border-separator bg-base-200">
                        <div class="flex items-stretch absolute inset-0 w-full h-full divide-x [&>div]:flex-1 divide-separator justify-around opacity-50">
                            @for ($i = 1; $i <= 5; $i++)
                                <div></div>
                            @endfor
                        </div>

                        <div class="flex flex-col items-stretch absolute w-full h-full divide-y [&>div]:flex-1 inset-0 divide-separator justify-around opacity-50">
                            @for ($i = 1; $i <= 5; $i++)
                                <div></div>
                            @endfor
                        </div>

                        <x-mary-icon name="o-qr-code" class="relative z-20" />
                    </div>
                </div>

                <div class="space-y-2 text-center">
                    <h3 class="text-lg font-semibold">{{ $this->modalConfig['title'] }}</h3>
                    <p class="text-sm text-base-content/60">{{ $this->modalConfig['description'] }}</p>
                </div>
            </div>

            @if ($showVerificationStep)
                <div class="space-y-6">
                    <div class="flex flex-col items-center space-y-3 justify-center">
                        <x-mary-pin :size="6" :numeric="true" wire:model="code" error-field="code" class="mx-auto" />
                    </div>

                    <div class="flex items-center space-x-3">
                        <x-mary-button
                            class="btn-outline flex-1"
                            wire:click="resetVerification"
                        >
                            {{ __('Back') }}
                        </x-mary-button>

                        <x-mary-button
                            class="btn-primary flex-1"
                            wire:click="confirmTwoFactor"
                            x-bind:disabled="$wire.code.length < 6"
                        >
                            {{ __('Confirm') }}
                        </x-mary-button>
                    </div>
                </div>
            @else
                @error('setupData')
                    <div class="alert alert-error">
                        <x-mary-icon name="o-x-circle" class="size-5" />
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                <div class="flex justify-center">
                    <div class="relative w-64 overflow-hidden aspect-square rounded-box border border-separator">
                        @empty($qrCodeSvg)
                            <div class="absolute inset-0 flex items-center justify-center bg-base-200 animate-pulse">
                                <x-mary-loading class="loading-spinner" />
                            </div>
                        @else
                            <div class="flex items-center justify-center h-full p-4">
                                <div class="rounded p-3" style="background-color:#ffffff">{{-- QR must stay black-on-white to scan --}}
                                    {!! $qrCodeSvg !!}
                                </div>
                            </div>
                        @endempty
                    </div>
                </div>

                <div>
                    <x-mary-button
                        class="btn-primary w-full"
                        wire:click="showVerificationIfNecessary"
                        :disabled="$errors->has('setupData')"
                    >
                        {{ $this->modalConfig['buttonText'] }}
                    </x-mary-button>
                </div>

                <div class="space-y-4">
                    <div class="relative flex items-center justify-center w-full">
                        <div class="absolute inset-0 w-full h-px top-1/2 bg-separator"></div>
                        <span class="relative px-2 text-sm bg-base-100 text-base-content/65">
                            {{ __('or, enter the code manually') }}
                        </span>
                    </div>

                    <div
                        class="flex items-center space-x-2"
                        x-data="{
                            copied: false,
                            async copy() {
                                try {
                                    await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 1500);
                                } catch (e) {
                                    console.warn('Could not copy to clipboard');
                                }
                            }
                        }"
                    >
                        <div class="flex items-stretch w-full rounded-box border border-separator">
                            @empty($manualSetupKey)
                                <div class="flex items-center justify-center w-full p-3 bg-base-200">
                                    <x-mary-loading class="loading-spinner loading-sm" />
                                </div>
                            @else
                                <input
                                    type="text"
                                    readonly
                                    value="{{ $manualSetupKey }}"
                                    class="w-full p-3 bg-transparent text-base-content outline-none"
                                />

                                <button
                                    @click="copy()"
                                    class="px-3 transition-colors cursor-pointer border-l border-separator"
                                >
                                    <x-mary-icon name="o-document-duplicate" x-show="!copied" />
                                    <x-mary-icon name="s-check" class="text-success" x-show="copied" />
                                </button>
                            @endempty
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-mary-modal>
</section>
