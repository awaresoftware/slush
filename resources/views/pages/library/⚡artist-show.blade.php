<?php

use App\Models\Album;
use App\Models\Track;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public string $artist;

    public function mount(string $artist): void
    {
        $this->artist = $artist;

        abort_unless(
            Track::where('user_id', Auth::id())->where('artist', $this->artist)->exists(),
            404
        );
    }

    /**
     * Base query for this artist's tracks, scoped to the owner.
     */
    private function trackQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Track::query()
            ->where('user_id', Auth::id())
            ->where('artist', $this->artist);
    }

    /**
     * Albums by this artist.
     *
     * @return Collection<int, Album>
     */
    #[Computed]
    public function albums(): Collection
    {
        return Album::query()
            ->where('user_id', Auth::id())
            ->where('artist', $this->artist)
            ->with(['tracks' => fn ($q) => $q->where('is_available', true)->orderBy('track_number')])
            ->orderBy('title')
            ->get();
    }

    /**
     * The artist's most-listened tracks; alphabetical until plays exist.
     *
     * @return Collection<int, Track>
     */
    #[Computed]
    public function topTracks(): Collection
    {
        return $this->trackQuery()
            ->with('album')
            ->orderByDesc('play_count')
            ->orderBy('title')
            ->limit(5)
            ->get();
    }

    /**
     * Every playable track id in album order, for Play and Shuffle.
     *
     * @return list<int>
     */
    #[Computed]
    public function allTrackIds(): array
    {
        return $this->trackQuery()
            ->where('is_available', true)
            ->orderBy('album_id')
            ->orderByRaw('CASE WHEN track_number IS NULL THEN 1 ELSE 0 END')
            ->orderBy('track_number')
            ->orderBy('title')
            ->pluck('id')
            ->all();
    }

    /**
     * Counts for the metadata line.
     *
     * @return array{tracks: int, seconds: int, plays: int}
     */
    #[Computed]
    public function summary(): array
    {
        return [
            'tracks' => (clone $this->trackQuery())->count(),
            'seconds' => (int) (clone $this->trackQuery())->sum('duration'),
            'plays' => (int) (clone $this->trackQuery())->sum('play_count'),
        ];
    }
}; ?>

<div class="min-h-screen pb-20">
    @php
        $summary = $this->summary;
        $mins = intdiv($summary['seconds'], 60);
        $covers = $this->albums->map(fn ($a) => $a->coverArtUrl())->filter()->take(4)->values();

        $meta = array_filter([
            $this->albums->count() . ' ' . Str::plural('album', $this->albums->count()),
            $summary['tracks'] . ' ' . Str::plural('track', $summary['tracks']),
            $mins > 0 ? $mins . ' ' . Str::plural('minute', $mins) : null,
            $summary['plays'] > 0 ? $summary['plays'] . ' ' . Str::plural('play', $summary['plays']) : null,
        ]);
    @endphp

    <x-ui.page-header :title="$artist" :back="route('library.index')" :back-label="__('Back to Library')" :hero-title="false" />

    <div class="px-4 pb-8 sm:px-6">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-end">
            @if ($covers->isNotEmpty())
                <x-ui.artwork-mosaic :covers="$covers" class="size-48 shrink-0 rounded-full sm:size-[200px]" />
            @else
                <span class="flex size-48 shrink-0 items-center justify-center rounded-full bg-primary/12 text-large-title font-semibold text-primary sm:size-[200px]">
                    {{ Str::of($artist)->substr(0, 1)->upper() }}
                </span>
            @endif

            <div class="min-w-0 flex-1">
                <h1 class="text-title-1">{{ $artist }}</h1>
                <p class="mt-2 text-footnote text-base-content/65">{{ implode(' · ', $meta) }}</p>

                @if (! empty($this->allTrackIds))
                    <div class="mt-5 flex gap-2">
                        <button
                            type="button"
                            x-data
                            x-on:click="$dispatch('play-track', { trackIds: @js($this->allTrackIds), index: 0 })"
                            class="btn btn-capsule btn-sm btn-primary"
                        >
                            <x-mary-icon name="s-play" class="size-4" />
                            {{ __('Play') }}
                        </button>

                        <button
                            type="button"
                            x-data
                            x-on:click="$dispatch('play-track', { trackIds: @js(collect($this->allTrackIds)->shuffle()->all()), index: 0 })"
                            class="btn btn-capsule btn-sm bg-base-300 text-base-content"
                        >
                            <x-mary-icon name="o-arrows-right-left" class="size-4" />
                            {{ __('Shuffle') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <section class="mt-10">
            <h2 class="mb-3 text-title-2">{{ __('Top Tracks') }}</h2>
            <x-ui.track-table
                :tracks="$this->topTracks"
                :track-ids="$this->topTracks->pluck('id')->all()"
                :show-artist="false"
            />
        </section>

        @if ($this->albums->isNotEmpty())
            <section class="mt-10">
                <h2 class="mb-3 text-title-2">{{ __('Albums') }}</h2>
                <div class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($this->albums as $album)
                        <x-ui.album-card
                            wire:key="artist-album-{{ $album->id }}"
                            :title="$album->title"
                            :subtitle="$album->tracks->count() . ' ' . Str::plural('track', $album->tracks->count())"
                            :href="route('library.album.show', $album)"
                            :cover="$album->coverArtUrl()"
                            :track-ids="$album->tracks->pluck('id')->all()"
                        />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
