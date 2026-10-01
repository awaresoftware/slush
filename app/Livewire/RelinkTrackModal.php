<?php

namespace App\Livewire;

use App\Models\LinkedFolder;
use App\Models\Track;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class RelinkTrackModal extends Component
{
    use Toast;

    public bool $showModal = false;

    public ?int $selectedTrackId = null;

    public string $newPath = '';

    public bool $hashMismatch = false;

    public bool $confirmedMismatch = false;

    public string $oldPath = '';

    /**
     * Open the modal for the given track ID.
     */
    #[On('open-relink-modal')]
    public function openForTrack(int $trackId): void
    {
        $track = Track::where('id', $trackId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $track) {
            return;
        }

        $this->selectedTrackId = $trackId;
        $this->oldPath = $track->file_path;
        $this->newPath = '';
        $this->hashMismatch = false;
        $this->confirmedMismatch = false;
        $this->showModal = true;
    }

    /**
     * Re-link the track to a new file path.
     */
    public function relink(): void
    {
        $this->validate([
            'newPath' => ['required', 'string'],
        ]);

        $normalisedPath = str_replace('\\', '/', $this->newPath);

        if (! file_exists($normalisedPath)) {
            $this->addError('newPath', 'The file does not exist at the given path.');

            return;
        }

        $track = Track::where('id', $this->selectedTrackId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $newHash = md5_file($normalisedPath);

        if ($newHash !== $track->file_hash && ! $this->confirmedMismatch) {
            $this->hashMismatch = true;

            return;
        }

        $newFolder = LinkedFolder::where('user_id', Auth::id())
            ->get()
            ->first(fn ($folder) => str_starts_with($normalisedPath, $folder->path.'/'));

        if (! $newFolder) {
            $this->addError('newPath', 'The file must be inside one of your linked folders.');

            return;
        }

        $track->update([
            'file_path' => $normalisedPath,
            'file_hash' => $newHash,
            'is_available' => true,
            'linked_folder_id' => $newFolder->id,
        ]);

        $this->closeModal();
        $this->success('Track re-linked successfully.');
    }

    /**
     * Confirm the hash mismatch and proceed with the re-link.
     */
    public function confirmMismatch(): void
    {
        $this->confirmedMismatch = true;
        $this->relink();
    }

    /**
     * Close the modal and reset all state.
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->selectedTrackId = null;
        $this->newPath = '';
        $this->hashMismatch = false;
        $this->confirmedMismatch = false;
        $this->oldPath = '';
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.relink-track-modal');
    }
}
