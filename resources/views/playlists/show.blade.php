<x-layouts::app :title="$playlist->name">
    @php
        $trackIds = $tracks->pluck('id')->all();
        $seconds = (int) $tracks->sum('duration');

        $meta = array_filter([
            $tracks->count() . ' ' . Str::plural('track', $tracks->count()),
            $seconds > 0 ? intdiv($seconds, 60) . ' ' . Str::plural('minute', intdiv($seconds, 60)) : null,
        ]);

        $covers = $tracks->map(fn ($t) => $t->album?->coverArtUrl())->filter()->unique()->take(4)->values();
    @endphp

    <div x-data class="min-h-screen pb-20">
        <x-ui.page-header :title="$playlist->name" :back="route('playlists.index')" :back-label="__('My Playlists')" :hero-title="false">
            <x-slot:actions>
                <x-mary-button label="{{ __('Edit') }}" class="btn-sm btn-capsule bg-base-300 text-base-content" link="{{ $playlist->isSmart() ? route('playlists.smart.edit', $playlist) : route('playlists.edit', $playlist) }}" wire:navigate />
            </x-slot:actions>
        </x-ui.page-header>

        <div class="px-4 pb-8 sm:px-6">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-end">
                <x-ui.artwork-mosaic :covers="$covers" class="size-48 shrink-0 sm:size-[232px]" />

                <div class="min-w-0 flex-1">
                    <h1 class="flex items-center gap-2 text-title-1">
                        {{ $playlist->name }}
                        @if ($playlist->isSmart())
                            <span class="badge badge-sm gap-1 border-0 bg-primary/12 text-primary">
                                <x-mary-icon name="o-sparkles" class="size-3" />
                                {{ __('Smart') }}
                            </span>
                        @endif
                    </h1>

                    @if ($playlist->description)
                        <p class="mt-1 max-w-prose text-body text-base-content/65">{{ $playlist->description }}</p>
                    @endif

                    <p class="mt-2 text-footnote text-base-content/65">{{ implode(' · ', $meta) }}</p>

                    @if ($tracks->isNotEmpty())
                        <div class="mt-5 flex gap-2">
                            <button
                                type="button"
                                x-on:click="$dispatch('play-track', { trackIds: @js($trackIds), index: 0 })"
                                class="btn btn-capsule btn-sm btn-primary"
                            >
                                <x-mary-icon name="s-play" class="size-4" />
                                {{ __('Play') }}
                            </button>

                            <button
                                type="button"
                                x-on:click="$dispatch('play-track', { trackIds: @js(collect($trackIds)->shuffle()->all()), index: 0 })"
                                class="btn btn-capsule btn-sm bg-base-300 text-base-content"
                            >
                                <x-mary-icon name="o-arrows-right-left" class="size-4" />
                                {{ __('Shuffle') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            @if (session('success'))
                <div x-data x-init="$dispatch('mary-toast', { toast: { title: @js(session('success')), css: 'alert-success', timeout: 3000, noProgress: false } })"></div>
            @endif

            <div class="mt-8">
                @if ($tracks->isEmpty())
                    <x-empty-state
                        :title="$playlist->isSmart() ? __('Nothing matches yet') : __('No tracks yet')"
                        :description="$playlist->isSmart() ? __('Loosen the rules or listen to more music.') : __('Add tracks from your library to this playlist')"
                    >
                        <x-slot:action>
                            <x-mary-button label="{{ __('Go to Library') }}" class="btn-primary btn-capsule" link="{{ route('library.index') }}" wire:navigate />
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-ui.track-table
                        :tracks="$tracks"
                        :track-ids="$trackIds"
                        :mode="$playlist->isSmart() ? 'smart' : 'playlist'"
                        :playlist="$playlist"
                    />
                @endif
            </div>
        </div>
    </div>
</x-layouts::app>
