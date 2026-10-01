<?php

namespace App\Livewire;

use App\Models\Album;
use App\Models\Playlist;
use App\Models\Track;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CommandPalette extends Component
{
    public string $query = '';

    /**
     * Grouped results for the current query, every entry shaped the same so
     * the view can render one flat, keyboard-navigable list:
     * ['type', 'label', 'sub', 'icon', 'cover', 'url'|null, 'trackId'|null].
     *
     * @return array<string, list<array<string, mixed>>>
     */
    #[Computed]
    public function results(): array
    {
        // SQL LIKE wildcards are dropped rather than escaped: SQLite has no
        // default ESCAPE character, and nobody names a search after one.
        $term = trim(str_replace(['%', '_'], '', $this->query));

        if ($term === '') {
            return [];
        }

        $userId = Auth::id();
        $like = '%'.$term.'%';

        $tracks = Track::query()
            ->where('user_id', $userId)
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('artist', 'like', $like))
            ->with('album')
            ->orderByDesc('play_count')
            ->orderBy('title')
            ->limit(6)
            ->get()
            ->map(fn (Track $track) => [
                'type' => 'track',
                'label' => $track->title,
                'sub' => $track->artist ?? __('Unknown Artist'),
                'icon' => 'o-musical-note',
                'cover' => $track->album?->coverArtUrl(),
                'url' => null,
                'trackId' => $track->is_available ? $track->id : null,
            ])
            ->all();

        $albums = Album::query()
            ->where('user_id', $userId)
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('artist', 'like', $like))
            ->orderBy('title')
            ->limit(4)
            ->get()
            ->map(fn (Album $album) => [
                'type' => 'album',
                'label' => $album->title,
                'sub' => $album->artist ?? __('Unknown Artist'),
                'icon' => 'o-square-2-stack',
                'cover' => $album->coverArtUrl(),
                'url' => route('library.album.show', $album),
                'trackId' => null,
            ])
            ->all();

        $artists = Track::query()
            ->where('user_id', $userId)
            ->whereNotNull('artist')
            ->where('artist', 'like', $like)
            ->select('artist')
            ->groupBy('artist')
            ->orderBy('artist')
            ->limit(4)
            ->pluck('artist')
            ->map(fn (string $name) => [
                'type' => 'artist',
                'label' => $name,
                'sub' => __('Artist'),
                'icon' => 'o-user',
                'cover' => null,
                'url' => route('library.artist.show', ['artist' => $name]),
                'trackId' => null,
            ])
            ->all();

        $playlists = Playlist::query()
            ->where('user_id', $userId)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (Playlist $playlist) => [
                'type' => 'playlist',
                'label' => $playlist->name,
                'sub' => __('Playlist'),
                'icon' => 'o-queue-list',
                'cover' => null,
                'url' => route('playlists.show', $playlist),
                'trackId' => null,
            ])
            ->all();

        return array_filter([
            __('Tracks') => $tracks,
            __('Albums') => $albums,
            __('Artists') => $artists,
            __('Playlists') => $playlists,
        ]);
    }

    public function render(): View
    {
        return view('livewire.command-palette');
    }
}
