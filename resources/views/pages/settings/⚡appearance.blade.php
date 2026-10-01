<?php

use App\Enums\Accent;
use App\Enums\Theme;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    private const REMEMBER_MINUTES = 60 * 24 * 365 * 5;

    public string $theme = '';

    public string $accent = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->theme = Theme::forUser($user)->value;
        $this->accent = Accent::forUser($user)->value;
    }

    public function updatedTheme(): void
    {
        $this->persist();
    }

    public function updatedAccent(): void
    {
        $this->persist();
    }

    /**
     * Store the appearance preferences, restoring the saved values when the
     * submitted slugs are not ones we recognise.
     *
     * The cookies carry the same slugs so a signed-out visitor still sees their
     * own appearance on the auth pages, which have no user record to read.
     */
    private function persist(): void
    {
        try {
            $validated = $this->validate([
                'theme' => ['required', Rule::in(Theme::values())],
                'accent' => ['required', Rule::in(Accent::values())],
            ]);
        } catch (ValidationException) {
            $user = Auth::user();

            $this->theme = Theme::forUser($user)->value;
            $this->accent = Accent::forUser($user)->value;

            $this->dispatch('appearance-failed');

            return;
        }

        Auth::user()->update($validated);

        Cookie::queue(Theme::COOKIE, $this->theme, self::REMEMBER_MINUTES);
        Cookie::queue(Accent::COOKIE, $this->accent, self::REMEMBER_MINUTES);
    }

    /**
     * @return array{themes: list<Theme>, accents: list<Accent>}
     */
    public function with(): array
    {
        return [
            'themes' => Theme::cases(),
            'accents' => Accent::cases(),
        ];
    }
}; ?>

<section class="w-full px-4 pt-3 sm:px-6">
    @include('partials.settings-heading')

    <h2 class="sr-only">{{ __('Appearance Settings') }}</h2>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Choose how Rekodo looks on this account')">
        <div
            x-data="appearanceSettings()"
            x-on:appearance-failed.window="revert()"
            class="space-y-10"
        >
            {{-- Theme --}}
            <fieldset>
                <legend class="text-title-3">{{ __('Theme') }}</legend>
                <p class="mt-1 text-subhead text-base-content/65">
                    {{ __('Auto follows your device between light and dark.') }}
                </p>

                <div
                    class="mt-4 grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-3"
                    role="radiogroup"
                    aria-label="{{ __('Theme') }}"
                >
                    @foreach ($themes as $option)
                        <x-ui.theme-card
                            :theme="$option"
                            :accent="$accent"
                            :selected="$theme === $option->value"
                            name="theme"
                            wire:model.live="theme"
                            x-on:change="apply()"
                        />
                    @endforeach
                </div>
            </fieldset>

            {{-- Accent --}}
            <fieldset>
                <legend class="text-title-3">{{ __('Accent colour') }}</legend>
                <p class="mt-1 text-subhead text-base-content/65">
                    {{ __('Used for buttons, playback controls and the current selection.') }}
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-3">
                    <div class="flex flex-wrap gap-3" role="radiogroup" aria-label="{{ __('Accent colour') }}">
                        @foreach ($accents as $option)
                            <x-ui.accent-dot
                                :accent="$option"
                                :selected="$accent === $option->value"
                                name="accent"
                                wire:model.live="accent"
                                x-on:change="apply()"
                            />
                        @endforeach
                    </div>

                    <span class="text-subhead text-base-content/65" data-test="accent-name">
                        {{ Accent::from($accent)->label() }}
                    </span>
                </div>
            </fieldset>
        </div>
    </x-pages::settings.layout>
</section>
