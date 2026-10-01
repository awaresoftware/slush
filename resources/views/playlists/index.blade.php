<x-layouts::app title="My Playlists">
    <div x-data>
        <x-ui.page-header :title="__('My Playlists')">
            <x-slot:actions>
                <x-mary-button label="{{ __('New Smart Playlist') }}" icon="o-sparkles" class="btn-capsule btn-sm bg-base-300 text-base-content" link="{{ route('playlists.smart.create') }}" wire:navigate />
                <x-mary-button label="{{ __('New Playlist') }}" class="btn-primary btn-capsule btn-sm" link="{{ route('playlists.create') }}" wire:navigate />
            </x-slot:actions>
        </x-ui.page-header>

        <div class="px-4 pb-8 sm:px-6">
            @if (session('success'))
                <div x-data x-init="$dispatch('mary-toast', { toast: { title: @js(session('success')), css: 'alert-success', timeout: 3000, noProgress: false } })"></div>
            @endif

            @if ($playlists->isEmpty())
                <x-empty-state
                    :title="__('No playlists yet')"
                    :description="__('Create your first playlist to organize your music')"
                    icon="o-queue-list"
                >
                    <x-slot:action>
                        <x-mary-button label="{{ __('Create Playlist') }}" class="btn-primary btn-capsule" link="{{ route('playlists.create') }}" wire:navigate />
                    </x-slot:action>
                </x-empty-state>
            @else
                <div class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($playlists as $playlist)
                        @php
                            $covers = $playlist->tracks->map(fn ($t) => $t->album?->coverArtUrl())->filter()->unique()->take(4)->values();
                        @endphp
                        <a href="{{ route('playlists.show', $playlist) }}" wire:navigate wire:key="playlist-{{ $playlist->id }}" class="group">
                            <x-ui.artwork-mosaic :covers="$covers" class="aspect-square w-full" />
                            <span class="mt-2 flex items-center gap-1.5 truncate text-body-emphasized">
                                @if ($playlist->isSmart())
                                    <x-mary-icon name="o-sparkles" class="size-3.5 shrink-0 text-primary" />
                                @endif
                                <span class="truncate">{{ $playlist->name }}</span>
                            </span>
                            <span class="block text-subhead text-base-content/65">
                                {{ $playlist->tracks_count }} {{ Str::plural('track', $playlist->tracks_count) }}{{ $playlist->isSmart() ? ' · '.__('Smart') : '' }}
                            </span>
                        </a>
                    @endforeach

                    <a href="{{ route('playlists.create') }}" wire:navigate
                       class="flex aspect-square items-center justify-center rounded-art-lg border border-dashed border-separator text-base-content/45 transition-colors duration-150 ease-out-ios hover:border-primary hover:text-primary">
                        <span class="flex flex-col items-center gap-1">
                            <x-mary-icon name="o-plus" class="size-7" />
                            <span class="text-subhead">{{ __('New Playlist') }}</span>
                        </span>
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
