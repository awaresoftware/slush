@props([
    'tracks',
    'trackIds' => [],
    'mode' => 'library',
    'showArtist' => true,
    'showAlbum' => true,
    'playlist' => null,
])

@php
    // Inlined per row so the dispatched queue always matches the page of rows
    // currently on screen, rather than an Alpine array frozen at first render.
    $ids = \Illuminate\Support\Js::from($trackIds)->toHtml();
@endphp

<div class="overflow-x-auto">
    <table class="w-full">
        <thead>
            <tr class="border-b border-separator text-left">
                <th class="{{ in_array($mode, ['library', 'smart'], true) ? 'w-14' : 'w-10' }} pb-2"></th>
                <th class="pb-2 pr-4 text-caption text-base-content/45">{{ __('Title') }}</th>

                @if ($showArtist)
                    <th class="hidden pb-2 pr-4 text-caption text-base-content/45 sm:table-cell">{{ __('Artist') }}</th>
                @endif

                @if ($showAlbum)
                    <th class="hidden pb-2 pr-4 text-caption text-base-content/45 lg:table-cell">{{ __('Album') }}</th>
                @endif

                <th class="w-9 pb-2"></th>
                <th class="pb-2 pr-2 text-right text-caption text-base-content/45">{{ __('Duration') }}</th>
                <th class="w-20 pb-2"></th>
            </tr>
        </thead>

        <tbody>
            @php
                // The player's loadQueue() drops unavailable tracks before it
                // indexes the queue, so rows must dispatch their position
                // within the available-only subsequence — not the raw loop
                // index, which drifts as soon as one track is missing.
                $queueIndex = -1;
            @endphp

            @foreach ($tracks as $index => $track)
                @php
                    $isAvailable = is_array($track)
                        ? (bool) ($track['is_available'] ?? true)
                        : (bool) $track->is_available;

                    if ($isAvailable) {
                        $queueIndex++;
                    }
                @endphp

                <x-ui.track-row
                    :track="$track"
                    :index="$index"
                    :queue-index="$queueIndex"
                    :ids="$ids"
                    :mode="$mode"
                    :show-artist="$showArtist"
                    :show-album="$showAlbum"
                    :playlist="$playlist"
                />
            @endforeach
        </tbody>
    </table>

    {{ $slot }}
</div>
