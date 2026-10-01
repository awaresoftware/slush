@php
    $limit = 10;
    $playlists = auth()->user()->playlists()->latest()->limit($limit + 1)->get();
    $hasMore = $playlists->count() > $limit;
    $playlists = $playlists->take($limit);

    $current = request()->route('playlist');
    $currentId = $current instanceof \App\Models\Playlist ? $current->id : null;
@endphp

@if ($playlists->isNotEmpty())
    <div class="mt-6">
        <h2 class="px-2.5 pb-1 text-caption text-base-content/45">{{ __('Your Playlists') }}</h2>

        @foreach ($playlists as $playlist)
            <x-ui.nav-item
                :href="route('playlists.show', $playlist)"
                :active="$currentId === $playlist->id"
            >
                @if ($playlist->isSmart())<x-mary-icon name="o-sparkles" class="-mt-0.5 mr-0.5 inline size-3 text-primary" />@endif{{ $playlist->name }}
            </x-ui.nav-item>
        @endforeach

        @if ($hasMore)
            <a
                href="{{ route('playlists.index') }}"
                wire:navigate
                class="block rounded-field px-2.5 py-2 text-subhead text-base-content/65 transition-colors duration-150 ease-out-ios hover:bg-fill hover:text-base-content"
            >
                {{ __('See all') }}
            </a>
        @endif
    </div>
@endif
