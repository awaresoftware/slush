<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
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
