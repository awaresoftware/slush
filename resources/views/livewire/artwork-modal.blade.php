<div>
    <x-mary-modal wire:model="showModal" title="{{ __('Change artwork') }}" box-class="max-w-2xl">
        {{-- One combined field, pre-filled and editable. Nothing is searched
             until the button is pressed, so opening the modal is silent. --}}
        <div class="flex gap-2">
            <label class="input flex flex-1 items-center gap-2">
                <x-mary-icon name="o-magnifying-glass" class="size-4 shrink-0 text-base-content/45" />
                <input
                    type="search"
                    wire:model="query"
                    wire:keydown.enter="search"
                    class="grow bg-transparent outline-none"
                    placeholder="{{ __('Album or artist') }}"
                    aria-label="{{ __('Search for artwork') }}"
                >
            </label>

            <x-mary-button label="{{ __('Search') }}" icon="o-magnifying-glass" class="btn-primary" wire:click="search" />
        </div>

        @error('error')
            <p class="mt-2 text-footnote text-error" role="alert">{{ $message }}</p>
        @enderror

        {{-- The selected provider lives on the component, so a tab switch
             round-trips and the grid on screen is the one the server rendered. --}}
        <div class="mt-4 flex items-center justify-between gap-3">
            <x-ui.segmented-control
                :options="['itunes' => 'iTunes', 'deezer' => 'Deezer']"
                model="provider"
                :current="$provider"
                :label="__('Artwork provider')"
            />

            <span class="text-footnote text-base-content/65">
                {{ count($results) }} {{ Str::plural('result', count($results)) }}
            </span>
        </div>

        <div class="mt-3 max-h-72 overflow-y-auto">
            @forelse ($results as $result)
                <button
                    wire:key="result-{{ $result['provider'] }}-{{ $result['id'] }}"
                    wire:click="applyRemote('{{ $result['provider'] }}', @js($result['id']))"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-field p-2 text-left transition-colors duration-150 ease-out-ios hover:bg-fill"
                >
                    <span class="artwork relative flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-art bg-base-300">
                        <x-mary-icon name="o-musical-note" class="size-5 text-base-content/25" />
                        <img
                            src="{{ $result['image_url'] }}"
                            alt=""
                            loading="lazy"
                            referrerpolicy="no-referrer"
                            onerror="this.remove()"
                            class="absolute inset-0 size-full object-cover"
                        >
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-body-emphasized">{{ $result['title'] }}</span>
                        <span class="block truncate text-subhead text-base-content/65">{{ $result['artist'] }}</span>
                        <span class="block truncate text-footnote text-base-content/45">
                            {{ collect([$result['year'], $result['meta']])->filter()->join(' · ') }}
                        </span>
                    </span>
                </button>
            @empty
                <p class="py-8 text-center text-subhead text-base-content/65">
                    {{ __('Press Search to look for artwork.') }}
                </p>
            @endforelse
        </div>

        {{-- Same dashed drop well as the upload modal, so the affordance and
             its states are the ones already learned. --}}
        <div
            x-data="{ dragging: false }"
            class="mt-4"
        >
            <label
                x-on:dragover.prevent="dragging = true"
                x-on:dragleave.prevent="dragging = false"
                x-on:drop="dragging = false"
                class="flex cursor-pointer flex-col items-center justify-center rounded-box border border-dashed px-6 py-6 text-center transition-colors duration-150 ease-out-ios"
                :class="dragging ? 'border-primary bg-primary/12' : 'border-separator bg-base-200 hover:bg-base-300'"
            >
                <x-mary-icon name="o-photo" class="size-6 text-base-content/45" />
                <span class="mt-2 text-subhead text-base-content">{{ __('Or choose a local image') }}</span>

                <input type="file" wire:model="coverFile" accept="image/*" class="sr-only">
            </label>

            @error('coverFile')
                <p class="mt-2 text-footnote text-error">{{ $message }}</p>
            @enderror

            @if ($coverFile)
                <div class="mt-3 flex justify-end">
                    <x-mary-button label="{{ __('Use this image') }}" class="btn-primary" wire:click="upload" />
                </div>
            @endif
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-separator pt-4">
            <button type="button" wire:click="resetToAuto" class="text-subhead text-base-content/65 hover:text-base-content hover:underline">
                {{ __('Use auto-detected again') }}
            </button>

            <div class="flex gap-2">
                <x-mary-button
                    label="{{ __('Remove') }}"
                    class="btn-capsule btn-sm bg-base-300 text-base-content"
                    wire:click="remove"
                />
                <x-mary-button label="{{ __('Done') }}" class="btn-primary" wire:click="closeModal" />
            </div>
        </div>
    </x-mary-modal>
</div>
