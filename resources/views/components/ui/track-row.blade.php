@props([
    'track',
    'index',
    'queueIndex' => null,
    'ids',
    'mode' => 'library',
    'showArtist' => true,
    'showAlbum' => true,
    'playlist' => null,
    'playAction' => null,
])

@php
    // Accepts either a Track model or the plain row arrays the library page
    // accumulates for infinite scroll.
    $row = is_array($track)
        ? $track
        : [
            'id' => $track->id,
            'title' => $track->title,
            'artist' => $track->artist,
            'album_id' => $track->album_id,
            'album_title' => $track->album?->title,
            'album_cover' => $track->album?->coverArtUrl(),
            'duration' => $track->duration,
            'source_type' => $track->source_type instanceof \App\Enums\SourceType
                ? $track->source_type->value
                : $track->source_type,
            'is_available' => (bool) $track->is_available,
            'track_number' => $track->track_number,
            'favorited' => $track->isFavorited(),
        ];

    $available = $row['is_available'];
    $title = $row['title'];
    $number = $row['track_number'] ?? $index + 1;
    $queueIndex = $queueIndex ?? $index;
    $duration = $row['duration'] === null
        ? null
        : intdiv($row['duration'], 60) . ':' . str_pad($row['duration'] % 60, 2, '0', STR_PAD_LEFT);
    // A paginated list names a component action instead, so the server can queue
    // every matching track rather than only the rows on this page.
    $playDispatch = $playAction
        ? "\$wire.{$playAction}({$row['id']})"
        : "\$dispatch('play-track', { trackIds: {$ids}, index: {$queueIndex} })";
@endphp

<tr
    wire:key="track-{{ $row['id'] }}"
    @if ($available)
        x-on:click="if ($event.target.closest('button, a, form')) return; {{ $playDispatch }}"
    @endif
    @class([
        'group border-b border-separator transition-colors duration-150',
        'cursor-pointer hover:bg-fill' => $available,
        'opacity-50' => ! $available,
    ])
>
    {{-- Leading: artwork in library and smart lists, track number on album/playlist --}}
    @if (in_array($mode, ['library', 'smart'], true))
        <td class="w-14 py-2 pr-3 pl-1">
            <span class="artwork relative flex size-10 items-center justify-center overflow-hidden rounded-art bg-base-300">
                {{-- Glyph always rendered, image overlaid and self-removing on a
                     failed load, so a bad file falls back to the placeholder. --}}
                <x-mary-icon name="o-musical-note" class="size-4 text-base-content/35" />

                @if ($row['album_cover'] ?? null)
                    <img src="{{ $row['album_cover'] }}" alt="" loading="lazy" onerror="this.remove()" class="absolute inset-0 size-full object-cover">
                @endif

                @if ($available)
                    <button
                        type="button"
                        x-on:click="{{ $playDispatch }}"
                        class="absolute inset-0 flex items-center justify-center bg-overlay text-on-overlay opacity-0 transition-opacity duration-150 group-hover:opacity-100 focus-visible:opacity-100"
                        aria-label="Play {{ $title }}"
                    >
                        <x-mary-icon name="s-play" class="size-4" />
                    </button>
                @endif
            </span>
        </td>
    @else
        <td class="w-10 py-2 pr-2 text-center">
            <span @class(['text-footnote text-base-content/45 tabular-nums', 'group-hover:hidden' => $available])>
                {{ $number }}
            </span>

            @if ($available)
                <button
                    type="button"
                    x-on:click="{{ $playDispatch }}"
                    class="hidden size-7 items-center justify-center rounded-full text-base-content group-hover:inline-flex focus-visible:inline-flex"
                    aria-label="Play {{ $title }}"
                >
                    <x-mary-icon name="s-play" class="size-4" />
                </button>
            @endif
        </td>
    @endif

    {{-- Title. max-w-0 lets the cell absorb the spare width and truncate,
         instead of pushing the row wider than a phone screen. --}}
    <td class="py-2 pr-4 max-sm:w-full max-sm:max-w-0">
        <span class="flex items-center gap-2">
            <span class="min-w-0 truncate text-body-emphasized">{{ $title }}</span>

            @if ($row['source_type'] === 'local')
                {{-- data-test is the hook the source-badge tests assert on, so a
                     restyle of the badge colours cannot fail them. --}}
                <x-mary-badge value="Local" data-test="local-badge" class="badge-primary badge-sm shrink-0 px-2" />
            @endif

            @if (! $available)
                <x-mary-badge value="Unavailable" class="badge-error badge-sm shrink-0 px-2" />
            @endif
        </span>

        {{-- Phones drop the artist, album and duration columns, so the row
             carries them as a second line instead. --}}
        <span class="block truncate text-footnote text-base-content/65 sm:hidden" data-test="track-row-subline">
            {{ implode(' · ', array_filter([
                $showArtist ? ($row['artist'] ?? __('Unknown Artist')) : null,
                $showAlbum ? $row['album_title'] : null,
                $duration,
            ])) }}
        </span>
    </td>

    @if ($showArtist)
        <td class="hidden py-2 pr-4 text-body text-base-content/65 sm:table-cell">
            @if ($row['artist'])
                <a
                    href="{{ route('library.artist.show', ['artist' => $row['artist']]) }}"
                    wire:navigate
                    class="block truncate transition-colors duration-150 hover:text-base-content hover:underline"
                >{{ $row['artist'] }}</a>
            @else
                <span class="block truncate">—</span>
            @endif
        </td>
    @endif

    @if ($showAlbum)
        <td class="hidden py-2 pr-4 text-body text-base-content/65 lg:table-cell">
            @if (! empty($row['album_id']) && $row['album_title'])
                <a
                    href="{{ route('library.album.show', $row['album_id']) }}"
                    wire:navigate
                    class="block truncate transition-colors duration-150 hover:text-base-content hover:underline"
                >{{ $row['album_title'] }}</a>
            @else
                <span class="block truncate">{{ $row['album_title'] ?? '—' }}</span>
            @endif
        </td>
    @endif

    {{-- Heart: revealed on hover, always visible once favorited (Apple Music). --}}
    <td class="w-9 py-2">
        <span
            x-data="{ fav: @js((bool) ($row['favorited'] ?? false)) }"
            x-on:favorite-changed.window="if ($event.detail.trackId === {{ $row['id'] }}) fav = $event.detail.favorited"
        >
            <button
                type="button"
                x-on:click.stop="$dispatch('toggle-favorite', { trackId: {{ $row['id'] }} })"
                :aria-pressed="fav"
                aria-label="Favourite {{ $title }}"
                class="inline-flex size-8 items-center justify-center rounded-full transition-opacity duration-150 ease-out-ios hover:bg-fill focus-visible:opacity-100"
                :class="fav ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 max-sm:opacity-100'"
                data-test="favorite-btn"
            >
                <template x-if="fav">
                    <x-mary-icon name="s-heart" class="size-4 text-primary" />
                </template>
                <template x-if="!fav">
                    <x-mary-icon name="o-heart" class="size-4 text-base-content/45" />
                </template>
            </button>
        </span>
    </td>

    <td class="hidden py-2 pr-2 text-right sm:table-cell">
        <time class="text-footnote text-base-content/65 tabular-nums">{{ $duration ?? '—' }}</time>
    </td>

    {{-- Trailing actions --}}
    <td class="w-20 py-2 max-sm:w-auto">
        <div class="flex items-center justify-end gap-0.5">
            @if (! $available)
                <x-ui.icon-button
                    icon="link"
                    size="sm"
                    :label="'Re-link ' . $title"
                    x-on:click="$dispatch('open-relink-modal', { trackId: {{ $row['id'] }} })"
                    class="opacity-0 transition-opacity duration-150 group-hover:opacity-100 focus-visible:opacity-100 max-sm:opacity-100"
                    data-test="relink-btn"
                />
            @elseif ($mode === 'playlist' && $playlist)
                <form method="POST" action="{{ route('playlist-tracks.destroy', [$playlist, $row['id']]) }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.icon-button
                        tag="button"
                        type="submit"
                        icon="x-mark"
                        size="sm"
                        :label="'Remove ' . $title . ' from playlist'"
                        class="opacity-0 transition-opacity duration-150 group-hover:opacity-100 focus-visible:opacity-100 max-sm:opacity-100"
                    />
                </form>
            @else
                <x-ui.icon-button
                    icon="plus"
                    size="sm"
                    :label="'Add ' . $title . ' to playlist'"
                    x-on:click="$dispatch('open-add-to-playlist', { trackId: {{ $row['id'] }} })"
                    class="opacity-0 transition-opacity duration-150 group-hover:opacity-100 focus-visible:opacity-100 max-sm:hidden"
                    data-test="add-to-playlist-btn"
                />
            @endif

            @if ($available)
                {{-- Mary closes the dropdown on any menu click, so items need no
                     manual teardown. The favourite item mirrors the row's own
                     Alpine flag, kept in step by the favorite-changed broadcast. --}}
                <x-mary-dropdown right>
                    <x-slot:trigger>
                        <x-ui.icon-button
                            icon="ellipsis-horizontal"
                            size="sm"
                            :label="'Actions for ' . $title"
                            class="opacity-0 transition-opacity duration-150 group-hover:opacity-100 focus-visible:opacity-100 max-sm:opacity-100"
                            data-test="track-menu-btn"
                        />
                    </x-slot:trigger>

                    <li>
                        <button
                            type="button"
                            x-data="{ fav: @js((bool) ($row['favorited'] ?? false)) }"
                            x-on:favorite-changed.window="if ($event.detail.trackId === {{ $row['id'] }}) fav = $event.detail.favorited"
                            x-on:click="$dispatch('toggle-favorite', { trackId: {{ $row['id'] }} })"
                            data-test="track-menu-favorite"
                        >
                            <x-mary-icon name="o-heart" class="size-4" x-show="! fav" />
                            <x-mary-icon name="s-heart" class="size-4 text-primary" x-show="fav" x-cloak />
                            <span x-text="fav ? @js(__('Remove from Favourites')) : @js(__('Add to Favourites'))">{{ __('Add to Favourites') }}</span>
                        </button>
                    </li>

                    <li>
                        <button
                            type="button"
                            x-on:click="$dispatch('add-to-queue', { trackId: {{ $row['id'] }} })"
                            data-test="track-menu-add-to-queue"
                        >
                            <x-mary-icon name="o-queue-list" class="size-4" />
                            {{ __('Add to Queue') }}
                        </button>
                    </li>

                    <li>
                        <button
                            type="button"
                            x-on:click="$dispatch('open-add-to-playlist', { trackId: {{ $row['id'] }} })"
                            data-test="track-menu-add-to-playlist"
                        >
                            <x-mary-icon name="o-plus-circle" class="size-4" />
                            {{ __('Add to Playlist') }}
                        </button>
                    </li>

                    @if (! empty($row['album_id']))
                        <li>
                            <a href="{{ route('library.album.show', $row['album_id']) }}" wire:navigate>
                                <x-mary-icon name="o-square-3-stack-3d" class="size-4" />
                                {{ __('Go to Album') }}
                            </a>
                        </li>
                    @endif

                    @if (! empty($row['artist']))
                        <li>
                            <a href="{{ route('library.artist.show', ['artist' => $row['artist']]) }}" wire:navigate>
                                <x-mary-icon name="o-musical-note" class="size-4" />
                                {{ __('Go to Artist') }}
                            </a>
                        </li>
                    @endif
                </x-mary-dropdown>
            @endif
        </div>
    </td>
</tr>
