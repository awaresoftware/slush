<?php

use App\Models\Album;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\TrackPlay;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Home')] class extends Component {
    /**
     * The most recently added albums, for the top shelf.
     *
     * @return Collection<int, array{id: int, title: string, artist: ?string, cover: ?string, track_ids: list<int>}>
     */
    #[Computed]
    public function recentAlbums(): Collection
    {
        return Album::query()
            ->where('user_id', Auth::id())
            ->with(['tracks' => fn ($q) => $q->where('is_available', true)->orderBy('track_number')])
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (Album $album) => [
                'id' => $album->id,
                'title' => $album->title,
                'artist' => $album->artist,
                'cover' => $album->coverArtUrl(),
                'track_ids' => $album->tracks->pluck('id')->all(),
            ]);
    }

    /**
     * Albums the user listened to most recently.
     *
     * @return Collection<int, array{id: int, title: string, artist: ?string, cover: ?string, track_ids: list<int>}>
     */
    #[Computed]
    public function jumpBackIn(): Collection
    {
        return Album::query()
            ->where('user_id', Auth::id())
            ->whereHas('tracks', fn ($q) => $q->whereNotNull('last_played_at'))
            ->withMax('tracks as latest_play', 'last_played_at')
            ->with(['tracks' => fn ($q) => $q->where('is_available', true)->orderBy('track_number')])
            ->orderByDesc('latest_play')
            ->limit(12)
            ->get()
            ->map(fn (Album $album) => $this->albumCard($album));
    }

    /**
     * The user's most played albums.
     *
     * @return Collection<int, array{id: int, title: string, artist: ?string, cover: ?string, track_ids: list<int>}>
     */
    #[Computed]
    public function heavyRotation(): Collection
    {
        return Album::query()
            ->where('user_id', Auth::id())
            ->withSum('tracks as total_plays', 'play_count')
            ->with(['tracks' => fn ($q) => $q->where('is_available', true)->orderBy('track_number')])
            ->orderByDesc('total_plays')
            ->limit(12)
            ->get()
            ->filter(fn (Album $album) => (int) $album->total_plays > 0)
            ->values()
            ->map(fn (Album $album) => $this->albumCard($album));
    }

    /**
     * The user's most listened artists, each with cover art gathered from their
     * albums so the circular tiles can show a face rather than an initial.
     *
     * @return Collection<int, array{name: string, plays: int, covers: list<string>}>
     */
    #[Computed]
    public function topArtists(): Collection
    {
        $artists = Track::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('artist')
            ->where('artist', '!=', '')
            ->select('artist')
            ->selectRaw('SUM(play_count) as total_plays')
            ->groupBy('artist')
            ->orderByDesc('total_plays')
            ->orderBy('artist')
            ->limit(12)
            ->get();

        return $artists
            ->filter(fn ($artist) => (int) $artist->total_plays > 0)
            ->values()
            ->map(fn ($artist) => [
                'name' => $artist->artist,
                'plays' => (int) $artist->total_plays,
                'covers' => Album::query()
                    ->where('user_id', Auth::id())
                    ->where('artist', $artist->artist)
                    ->whereNotNull('cover_art_path')
                    ->orderByDesc('updated_at')
                    ->limit(4)
                    ->get()
                    ->map(fn (Album $album) => $album->coverArtUrl())
                    ->filter()
                    ->values()
                    ->all(),
            ]);
    }

    /**
     * Shared card shape for the album shelves.
     *
     * @return array{id: int, title: string, artist: ?string, cover: ?string, track_ids: list<int>}
     */
    private function albumCard(Album $album): array
    {
        return [
            'id' => $album->id,
            'title' => $album->title,
            'artist' => $album->artist,
            'cover' => $album->coverArtUrl(),
            'track_ids' => $album->tracks->pluck('id')->all(),
        ];
    }

    /**
     * The most recently played tracks. Distinct from Jump Back In, which shelves
     * whole albums: this is the per-track history, and the closest a library can
     * get to a resume shelf without persisting a playback position.
     *
     * @return Collection<int, array{id: int, title: string, artist: ?string, cover: ?string, album_id: ?int}>
     */
    #[Computed]
    public function recentlyPlayed(): Collection
    {
        return Track::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('last_played_at')
            ->where('is_available', true)
            ->with('album')
            ->orderByDesc('last_played_at')
            ->limit(12)
            ->get()
            ->map(fn (Track $track) => [
                'id' => $track->id,
                'title' => $track->title,
                'artist' => $track->artist ?? __('Unknown Artist'),
                'cover' => $track->album?->coverArtUrl(),
                'album_id' => $track->album_id,
            ]);
    }

    /**
     * The user's playlists with a small cover mosaic each.
     *
     * @return Collection<int, array{id: int, name: string, count: int, covers: list<string>}>
     */
    #[Computed]
    public function playlists(): Collection
    {
        return Playlist::query()
            ->where('user_id', Auth::id())
            ->withCount('tracks')
            ->with(['tracks' => fn ($q) => $q->with('album')->limit(4)])
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (Playlist $playlist) => [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'count' => $playlist->tracks_count,
                'covers' => $playlist->tracks
                    ->map(fn (Track $t) => $t->album?->coverArtUrl())
                    ->filter()->unique()->take(4)->values()->all(),
            ]);
    }

    /**
     * Library totals for the summary line.
     *
     * @return array{tracks: int, albums: int, artists: int, seconds: int}
     */
    #[Computed]
    public function stats(): array
    {
        $tracks = Track::query()->where('user_id', Auth::id());

        return [
            'tracks' => (clone $tracks)->count(),
            'albums' => Album::where('user_id', Auth::id())->count(),
            'artists' => (clone $tracks)->whereNotNull('artist')->where('artist', '!=', '')->distinct()->count('artist'),
            'seconds' => (int) (clone $tracks)->sum('duration'),
            'plays' => TrackPlay::where('user_id', Auth::id())->count(),
        ];
    }

    public function greeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 12 => __('Good morning'),
            $hour < 18 => __('Good afternoon'),
            default => __('Good evening'),
        };
    }

    #[Computed]
    public function isEmpty(): bool
    {
        return $this->stats['tracks'] === 0 && $this->stats['albums'] === 0;
    }
}; ?>

<div class="min-h-screen pb-20">
    @if ($this->isEmpty)
        <div class="flex min-h-[70vh] flex-col items-center justify-center px-6 text-center">
            <x-ui.wordmark class="mb-6 scale-125" />
            <h1 class="text-title-1">{{ __('Your library starts here') }}</h1>
            <p class="mt-2 max-w-md text-body text-base-content/65">
                {{ __('Upload your music or link a folder on this machine, and it all becomes one collection you can play from anywhere.') }}
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <x-mary-button label="{{ __('Upload music') }}" class="btn-primary btn-capsule" x-on:click="$dispatch('open-upload-modal')" />
                <x-mary-button label="{{ __('Link a folder') }}" class="btn-capsule bg-base-300 text-base-content" link="{{ route('settings.linked-folders') }}" wire:navigate />
            </div>
        </div>
    @else
        @php
            $mins = intdiv($this->stats['seconds'], 60);
            $statLine = implode(' · ', array_filter([
                $this->stats['tracks'] . ' ' . Str::plural('track', $this->stats['tracks']),
                $this->stats['albums'] . ' ' . Str::plural('album', $this->stats['albums']),
                $this->stats['artists'] . ' ' . Str::plural('artist', $this->stats['artists']),
                $mins > 0 ? $mins . ' ' . Str::plural('minute', $mins) : null,
                $this->stats['plays'] > 0 ? $this->stats['plays'] . ' ' . Str::plural('play', $this->stats['plays']) : null,
            ]));
        @endphp

        <div class="px-4 pt-6 pb-6 sm:px-6">
            <h1 class="text-large-title">{{ $this->greeting() }}, {{ Str::of(auth()->user()->name)->explode(' ')->first() }}</h1>
            <p class="mt-1 text-subhead text-base-content/65">{{ $statLine }}</p>
        </div>

        <div class="space-y-10 pb-4">
            @if ($this->topArtists->isNotEmpty())
                <x-ui.media-shelf :title="__('Top Artists')" :href="route('library.index', ['activeTab' => 'artists'])">
                    @foreach ($this->topArtists as $artist)
                        <a
                            href="{{ route('library.artist.show', ['artist' => $artist['name']]) }}"
                            wire:navigate
                            wire:key="top-artist-{{ $loop->index }}"
                            class="group w-32 shrink-0 snap-start text-center sm:w-36"
                        >
                            <x-ui.artwork-mosaic :covers="$artist['covers']" class="aspect-square w-full rounded-full" />
                            <span class="mt-2 block truncate text-body-emphasized">{{ $artist['name'] }}</span>
                            <span class="block truncate text-subhead text-base-content/65">
                                {{ $artist['plays'] }} {{ Str::plural('play', $artist['plays']) }}
                            </span>
                        </a>
                    @endforeach
                </x-ui.media-shelf>
            @endif

            @if ($this->recentlyPlayed->isNotEmpty())
                <x-ui.media-shelf :title="__('Recently Played')" :href="route('library.index', ['sort' => 'recently_played'])">
                    @foreach ($this->recentlyPlayed as $track)
                        <x-ui.album-card
                            wire:key="recent-track-{{ $track['id'] }}"
                            size="shelf"
                            :title="$track['title']"
                            :subtitle="$track['artist']"
                            :href="$track['album_id']
                                ? route('library.album.show', $track['album_id'])
                                : route('library.index')"
                            :cover="$track['cover']"
                            :track-ids="[$track['id']]"
                        />
                    @endforeach
                </x-ui.media-shelf>
            @endif

            @if ($this->recentAlbums->isNotEmpty())
                <x-ui.media-shelf :title="__('Recently Added')" :href="route('library.index')">
                    @foreach ($this->recentAlbums as $album)
                        <x-ui.album-card
                            wire:key="recent-{{ $album['id'] }}"
                            size="shelf"
                            :title="$album['title']"
                            :subtitle="$album['artist'] ?? __('Unknown Artist')"
                            :href="route('library.album.show', $album['id'])"
                            :cover="$album['cover']"
                            :track-ids="$album['track_ids']"
                        />
                    @endforeach
                </x-ui.media-shelf>
            @endif

            @if ($this->jumpBackIn->isNotEmpty())
                <x-ui.media-shelf :title="__('Jump Back In')" :href="route('library.index')">
                    @foreach ($this->jumpBackIn as $album)
                        <x-ui.album-card
                            wire:key="jump-{{ $album['id'] }}"
                            size="shelf"
                            :title="$album['title']"
                            :subtitle="$album['artist'] ?? __('Unknown Artist')"
                            :href="route('library.album.show', $album['id'])"
                            :cover="$album['cover']"
                            :track-ids="$album['track_ids']"
                        />
                    @endforeach
                </x-ui.media-shelf>
            @endif

            @if ($this->heavyRotation->isNotEmpty())
                <x-ui.media-shelf :title="__('Heavy Rotation')" :href="route('library.index')">
                    @foreach ($this->heavyRotation as $album)
                        <x-ui.album-card
                            wire:key="heavy-{{ $album['id'] }}"
                            size="shelf"
                            :title="$album['title']"
                            :subtitle="$album['artist'] ?? __('Unknown Artist')"
                            :href="route('library.album.show', $album['id'])"
                            :cover="$album['cover']"
                            :track-ids="$album['track_ids']"
                        />
                    @endforeach
                </x-ui.media-shelf>
            @endif

            @if ($this->playlists->isNotEmpty())
                <x-ui.media-shelf :title="__('Your Playlists')" :href="route('playlists.index')">
                    @foreach ($this->playlists as $playlist)
                        <a
                            href="{{ route('playlists.show', $playlist['id']) }}"
                            wire:navigate
                            wire:key="home-playlist-{{ $playlist['id'] }}"
                            class="group w-40 shrink-0 snap-start sm:w-44"
                        >
                            <x-ui.artwork-mosaic :covers="$playlist['covers']" class="aspect-square w-full" />
                            <span class="mt-2 block truncate text-body-emphasized">{{ $playlist['name'] }}</span>
                            <span class="block text-subhead text-base-content/65">
                                {{ $playlist['count'] }} {{ Str::plural('track', $playlist['count']) }}
                            </span>
                        </a>
                    @endforeach
                </x-ui.media-shelf>
            @endif
        </div>
    @endif
</div>
