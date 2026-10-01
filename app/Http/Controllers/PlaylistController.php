<?php

namespace App\Http\Controllers;

use App\Actions\ResolveSmartPlaylist;
use App\Http\Requests\StorePlaylistRequest;
use App\Http\Requests\UpdatePlaylistRequest;
use App\Models\Playlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PlaylistController extends Controller
{
    /**
     * Display a listing of the user's playlists.
     */
    public function index(ResolveSmartPlaylist $resolver): View
    {
        $playlists = Playlist::query()
            ->where('user_id', Auth::id())
            ->withCount('tracks')
            ->with(['tracks' => fn ($query) => $query->with('album')->limit(4)])
            ->orderBy('name')
            ->get();

        // Smart playlists have no pivot rows; resolve their real count and a
        // few covers for the mosaic.
        $playlists->filter->isSmart()->each(function (Playlist $playlist) use ($resolver) {
            $query = $resolver($playlist);

            $playlist->tracks_count = (clone $query)->toBase()->getCountForPagination();
            $playlist->setRelation('tracks', $query->limit(4)->get());
        });

        return view('playlists.index', compact('playlists'));
    }

    /**
     * Show the form for creating a new playlist.
     */
    public function create(): View
    {
        return view('playlists.create');
    }

    /**
     * Store a newly created playlist in storage.
     */
    public function store(StorePlaylistRequest $request): RedirectResponse
    {
        $playlist = Playlist::create([
            'user_id' => Auth::id(),
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
        ]);

        return redirect()
            ->route('playlists.show', $playlist)
            ->with('success', 'Playlist created successfully.');
    }

    /**
     * Display the specified playlist.
     */
    public function show(Playlist $playlist, ResolveSmartPlaylist $resolver): View
    {
        $this->authorizePlaylist($playlist);

        if ($playlist->isSmart()) {
            $tracks = $resolver($playlist)->get();
        } else {
            $playlist->load(['tracks.album']);
            $tracks = $playlist->tracks;
        }

        return view('playlists.show', compact('playlist', 'tracks'));
    }

    /**
     * Show the form for editing the specified playlist.
     */
    public function edit(Playlist $playlist): View|RedirectResponse
    {
        $this->authorizePlaylist($playlist);

        if ($playlist->isSmart()) {
            return redirect()->route('playlists.smart.edit', $playlist);
        }

        return view('playlists.edit', compact('playlist'));
    }

    /**
     * Update the specified playlist in storage.
     */
    public function update(UpdatePlaylistRequest $request, Playlist $playlist): RedirectResponse
    {
        $this->authorizePlaylist($playlist);

        $playlist->update($request->validated());

        return redirect()
            ->route('playlists.show', $playlist)
            ->with('success', 'Playlist updated successfully.');
    }

    /**
     * Remove the specified playlist from storage.
     */
    public function destroy(Playlist $playlist): RedirectResponse
    {
        $this->authorizePlaylist($playlist);

        $playlist->delete();

        return redirect()
            ->route('playlists.index')
            ->with('success', 'Playlist deleted.');
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
