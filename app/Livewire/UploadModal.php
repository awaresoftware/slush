<?php

namespace App\Livewire;

use App\Jobs\ExtractAudioMetadata;
use App\Models\Album;
use App\Models\Track;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

class UploadModal extends Component
{
    use Toast;
    use WithFileUploads;

    public bool $showModal = false;

    public bool $uploadComplete = false;

    /** @var array<int, TemporaryUploadedFile> */
    public array $audioFiles = [];

    public ?int $selectedAlbumId = null;

    /** @var array<int, string> */
    public array $duplicateWarnings = [];

    /**
     * Open the modal and reset all state.
     */
    #[On('open-upload-modal')]
    public function openModal(): void
    {
        $this->reset(['audioFiles', 'selectedAlbumId', 'duplicateWarnings', 'uploadComplete']);
        $this->showModal = true;
    }

    /**
     * Close the modal and reset all state.
     */
    public function closeModal(): void
    {
        $this->reset(['audioFiles', 'selectedAlbumId', 'duplicateWarnings', 'uploadComplete']);
        $this->showModal = false;
    }

    /**
     * Upload the selected audio files, create Track records, and dispatch metadata jobs.
     * Stores results in session and shows success state — does NOT redirect.
     */
    public function save(): void
    {
        $this->validate([
            'audioFiles.*' => 'file|mimes:mp3,wav,flac,m4a,aac,ogg|max:102400',
        ]);

        $user = Auth::user();
        $uploadedTrackIds = [];

        foreach ($this->audioFiles as $file) {
            $hash = hash_file('sha256', $file->getRealPath());

            if (Track::where('file_hash', $hash)->where('user_id', $user->id)->exists()) {
                $this->duplicateWarnings[] = $file->getClientOriginalName();
            }

            $path = $file->store("music/{$user->id}", 'public');

            $track = Track::create([
                'user_id' => $user->id,
                'album_id' => $this->selectedAlbumId,
                'file_path' => $path,
                'file_hash' => $hash,
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            ]);

            ExtractAudioMetadata::dispatch($track);
            $uploadedTrackIds[] = $track->id;
        }

        session([
            'uploaded_track_ids' => $uploadedTrackIds,
            'duplicate_warnings' => $this->duplicateWarnings,
        ]);

        $this->uploadComplete = true;
    }

    /**
     * Get the authenticated user's albums for the album picker dropdown.
     *
     * @return Collection<int, Album>
     */
    #[Computed]
    public function userAlbums(): Collection
    {
        return Album::where('user_id', Auth::id())->orderBy('title')->get();
    }

    public function render(): View
    {
        return view('livewire.upload-modal');
    }
}
