<div>
    <x-mary-modal wire:model="showModal" title="{{ __('Add to Playlist') }}" box-class="max-w-sm">
        @if ($this->selectedTrack)
            <p class="-mt-2 mb-4 text-subhead text-base-content/65">
                {{ __('Adding') }} <span class="font-medium text-base-content">{{ $this->selectedTrack->title }}</span>
            </p>
        @endif

        @if ($this->userPlaylists->isEmpty())
            <div class="py-6 text-center">
                <p class="mb-4 text-subhead text-base-content/65">{{ __('You have no playlists yet.') }}</p>
                <x-mary-button label="{{ __('Create a Playlist') }}" class="btn-primary btn-capsule btn-sm" link="{{ route('playlists.create') }}" wire:navigate />
            </div>
        @else
            <div x-data="{ filter: '' }">
                @if ($this->userPlaylists->count() > 6)
                    <label class="input mb-2 flex items-center gap-2 rounded-full">
                        <x-mary-icon name="o-magnifying-glass" class="size-4 shrink-0 text-base-content/45" />
                        <input x-model="filter" type="search" placeholder="{{ __('Find a playlist') }}" class="grow bg-transparent outline-none" aria-label="{{ __('Find a playlist') }}">
                    </label>
                @endif

                <div class="max-h-72 space-y-0.5 overflow-y-auto">
                    @foreach ($this->userPlaylists as $playlist)
                        <button
                            wire:key="{{ $playlist->id }}"
                            wire:click="addToPlaylist({{ $playlist->id }})"
                            type="button"
                            x-show="!filter || {{ Js::from(Str::lower($playlist->name)) }}.includes(filter.toLowerCase())"
                            class="flex w-full items-center gap-3 rounded-field px-2 py-2 text-left transition-colors duration-150 ease-out-ios hover:bg-fill"
                        >
                            <span class="artwork flex size-9 shrink-0 items-center justify-center rounded-art bg-base-300">
                                <x-mary-icon name="o-queue-list" class="size-4 text-base-content/45" />
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-subhead font-medium">{{ $playlist->name }}</span>
                                <span class="block text-footnote text-base-content/65">
                                    {{ $playlist->tracks_count }} {{ Str::plural('track', $playlist->tracks_count) }}
                                </span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 border-t border-separator pt-4">
                <x-mary-button label="{{ __('Create New Playlist') }}" icon="o-plus" class="btn-capsule btn-sm w-full bg-base-300 text-base-content" link="{{ route('playlists.create') }}" wire:navigate />
            </div>
        @endif
    </x-mary-modal>
</div>
