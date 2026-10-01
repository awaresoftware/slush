<?php

use App\Models\Album;
use App\Models\Track;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Album')] class extends Component {
    use WithPagination;

    public Album $album;

    public function mount(Album $album): void
    {
        abort_unless($album->user_id === Auth::id(), 403);
        $this->album = $album;
    }

    /**
     * Get the album's tracks, ordered by track number then title, paginated.
     */
    #[Computed]
    public function tracks(): Paginator
    {
        return Track::query()
            ->where('album_id', $this->album->id)
            ->orderByRaw('CASE WHEN track_number IS NULL THEN 1 ELSE 0 END')
            ->orderBy('track_number')
            ->orderBy('title')
            ->simplePaginate(50);
    }

    /**
     * Year, genre, track count and total running time for the metadata line.
     *
     * @return array{count: int, seconds: int, year: ?int, genre: ?string}
     */
    #[Computed]
    public function summary(): array
    {
        $tracks = Track::query()->where('album_id', $this->album->id);

        return [
            'count' => (clone $tracks)->count(),
            'seconds' => (int) (clone $tracks)->sum('duration'),
            'year' => (clone $tracks)->value('year'),
            'genre' => (clone $tracks)->value('genre'),
        ];
    }
}; ?>

<div>
    {{-- Scoped to this page: D2 rules out a hover menu on album cards and a
         global artwork manager. --}}
    <livewire:artwork-modal />

    @php
        $summary = $this->summary;
        $albumTrackIds = $this->tracks->pluck('id')->all();

        $meta = array_filter([
            $summary['year'],
            $summary['genre'],
            $summary['count'] . ' ' . Str::plural('track', $summary['count']),
            $summary['seconds'] > 0
                ? intdiv($summary['seconds'], 60) . ' ' . Str::plural('minute', intdiv($summary['seconds'], 60))
                : null,
        ]);
    @endphp

    <x-ui.page-header :title="$album->title" :back="route('library.index')" :back-label="__('Back to Library')" :hero-title="false" />

    <div class="px-4 pb-8 sm:px-6">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-end">
            <div class="artwork relative size-48 shrink-0 overflow-hidden rounded-art-lg bg-base-300 shadow-2 sm:size-[232px]">
                {{-- Glyph always rendered, image overlaid and self-removing on a
                     failed load, so a bad file falls back to the placeholder. --}}
                <span class="flex size-full items-center justify-center">
                    <x-mary-icon name="o-musical-note" class="size-16 text-base-content/25" />
                </span>

                @if ($album->coverArtUrl())
                    <img src="{{ $album->coverArtUrl() }}" alt="{{ $album->title }}" onerror="this.remove()" class="absolute inset-0 size-full object-cover">
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <h1 class="text-title-1">{{ $album->title }}</h1>
                @if ($album->artist)
                    <a
                        href="{{ route('library.artist.show', ['artist' => $album->artist]) }}"
                        wire:navigate
                        class="mt-1 inline-block text-title-3 text-primary hover:underline"
                    >{{ $album->artist }}</a>
                @else
                    <p class="mt-1 text-title-3 text-primary">{{ __('Unknown Artist') }}</p>
                @endif

                @if (! empty($meta))
                    <p class="mt-2 text-footnote text-base-content/65">{{ implode(' · ', $meta) }}</p>
                @endif

                <div class="mt-5 flex flex-wrap gap-2">
                    @if (! empty($albumTrackIds))
                        <button
                            type="button"
                            x-on:click="$dispatch('play-track', { trackIds: @js($albumTrackIds), index: 0 })"
                            class="btn btn-capsule btn-sm btn-primary"
                        >
                            <x-mary-icon name="s-play" class="size-4" />
                            {{ __('Play') }}
                        </button>

                        <button
                            type="button"
                            x-on:click="$dispatch('play-track', { trackIds: @js(collect($albumTrackIds)->shuffle()->all()), index: 0 })"
                            class="btn btn-capsule btn-sm bg-base-300 text-base-content"
                        >
                            <x-mary-icon name="o-arrows-right-left" class="size-4" />
                            {{ __('Shuffle') }}
                        </button>
                    @endif

                    <button
                        type="button"
                        x-on:click="$dispatch('open-artwork-modal', { albumId: {{ $album->id }} })"
                        class="btn btn-ghost btn-sm"
                    >
                        <x-mary-icon name="o-photo" class="size-4" />
                        {{ __('Change artwork') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-8">
            @if ($this->tracks->isEmpty())
                <x-empty-state :title="__('No tracks in this album yet')" :description="__('Upload tracks and assign them to this album')" />
            @else
                <x-ui.track-table
                    :tracks="$this->tracks"
                    :track-ids="$albumTrackIds"
                    mode="album"
                    :show-artist="false"
                    :show-album="false"
                />

                <div class="mt-4">{{ $this->tracks->links() }}</div>
            @endif
        </div>
    </div>
</div>
