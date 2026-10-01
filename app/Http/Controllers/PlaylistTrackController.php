<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\Track;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class PlaylistTrackController extends Controller
{
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
}
