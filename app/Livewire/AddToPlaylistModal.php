<?php

namespace App\Livewire;

use App\Models\Playlist;
use App\Models\Track;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class AddToPlaylistModal extends Component
{
    use Toast;

    public bool $showModal = false;

    public ?int $selectedTrackId = null;

    /**
     * Open the modal for the given track ID.
     */
    #[On('open-add-to-playlist')]
    public function openForTrack(int $trackId): void
    {
        $this->selectedTrackId = $trackId;
        $this->showModal = true;
    }

    /**
     * Close the modal and reset all state.
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->selectedTrackId = null;
    }

    /**
     * Add the selected track to the given playlist, then close the modal.
     */
    public function addToPlaylist(int $playlistId): void
    {
        $track = Track::where('id', $this->selectedTrackId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $playlist = Playlist::where('id', $playlistId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (! $playlist->tracks()->where('track_id', $track->id)->exists()) {
            $maxPosition = $playlist->playlistTracks()->max('position') ?? -1;
            $playlist->tracks()->attach($track->id, ['position' => $maxPosition + 1]);
        }

        $this->closeModal();
        $this->success("Added to {$playlist->name}");
    }

    /**
     * Get the currently selected track model.
     */
    #[Computed]
    public function selectedTrack(): ?Track
    {
        if ($this->selectedTrackId === null) {
            return null;
        }

        return Track::find($this->selectedTrackId);
    }

    /**
     * Get the authenticated user's playlists ordered by name.
     *
     * @return Collection<int, Playlist>
     */
    #[Computed]
    public function userPlaylists(): Collection
    {
        return Playlist::where('user_id', Auth::id())
            ->whereNull('rules')
            ->withCount('tracks')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.add-to-playlist-modal');
    }
}
