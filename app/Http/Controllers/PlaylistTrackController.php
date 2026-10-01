<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\Track;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlaylistTrackController extends Controller
{
    /**
     * Add a track to a playlist.
     */
    public function store(Request $request, Playlist $playlist): RedirectResponse
    {
        $this->authorizePlaylist($playlist);

        abort_if($playlist->isSmart(), 422, 'Smart playlists choose their own tracks.');

        $validated = $request->validate([
            'track_id' => ['required', 'integer', 'exists:tracks,id'],
        ]);

        $track = Track::where('id', $validated['track_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Only add if not already in the playlist
        if (! $playlist->tracks()->where('track_id', $track->id)->exists()) {
            $maxPosition = $playlist->playlistTracks()->max('position') ?? -1;

            $playlist->tracks()->attach($track->id, ['position' => $maxPosition + 1]);
        }

        return back()->with('success', "\"$track->title\" added to {$playlist->name}.");
    }

    /**
     * Remove a track from a playlist.
     */
    public function destroy(Playlist $playlist, Track $track): RedirectResponse
    {
        $this->authorizePlaylist($playlist);

        abort_if($playlist->isSmart(), 422, 'Smart playlists choose their own tracks.');

        // Ensure the track belongs to the authenticated user
        if ($track->user_id !== Auth::id()) {
            abort(403);
        }

        $playlist->tracks()->detach($track->id);

        return back()->with('success', "\"$track->title\" removed from {$playlist->name}.");
    }

    /**
     * Abort with 403 if the authenticated user does not own the playlist.
     */
    protected function authorizePlaylist(Playlist $playlist): void
    {
        if ($playlist->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
