<?php

namespace App\Livewire;

use App\Enums\ArtworkSource;
use App\Jobs\FetchAlbumArtwork;
use App\Models\Album;
use App\Services\ArtworkSearch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use RuntimeException;
use Throwable;

/**
 * The album owner's way of choosing artwork: search, pick, upload, remove, or
 * hand the album back to automatic detection.
 */
class ArtworkModal extends Component
{
    use Toast;
    use WithFileUploads;

    /**
     * Matches the identifying User-Agent the automatic backfill sends.
     */
    private const USER_AGENT = 'foonk/1.0 ( https://github.com/awaresoftware/foonk )';

    public bool $showModal = false;

    public ?int $albumId = null;

    public string $query = '';

    public string $provider = 'itunes';

    /**
     * Normalised candidates for the current provider.
     *
     * @var array<int, array{provider: string, id: string, title: string, artist: string, image_url: string, year: ?string, meta: string}>
     */
    public array $results = [];

    public string $error = '';

    #[Rule(['nullable', 'image', 'max:2048'])]
    public $coverFile = null;

    /**
     * Open the modal for one of the current user's albums.
     *
     * No search is issued here: the query is only pre-filled, so opening the
     * modal makes no network call.
     */
    #[On('open-artwork-modal')]
    public function openForAlbum(int $albumId): void
    {
        $album = $this->ownedAlbum($albumId);

        $artist = trim((string) $album->artist);

        $this->albumId = $album->id;
        $this->query = trim($album->title.' '.$artist);
        $this->results = [];
        $this->error = '';
        $this->coverFile = null;
        $this->provider = 'itunes';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->albumId = null;
        $this->results = [];
        $this->error = '';
        $this->coverFile = null;
    }

    /**
     * Search the selected provider for the current query.
     */
    public function search(): void
    {
        $this->error = '';

        $provider = in_array($this->provider, ['itunes', 'deezer'], true) ? $this->provider : 'itunes';
        $this->provider = $provider;

        // iTunes asks for at most 20 requests a minute; refusing here gives the
        // user an explicit answer instead of a silently empty grid. The closure
        // returns true rather than the result array, because an empty result set
        // is a successful search and must not read as a throttle.
        $allowed = RateLimiter::attempt('artwork-search:'.Auth::id(), 20, function () use ($provider) {
            $this->results = (new ArtworkSearch)->{$provider}($this->query);

            return true;
        }, 60);

        if ($allowed === false) {
            $this->error = __('Too many searches. Please wait a moment and try again.');
        }
    }

    /**
     * Apply a search result as this album's cover.
     *
     * Takes a provider and a result id, never a URL: the image address is
     * looked up in the server-side result set, so a crafted Livewire payload
     * cannot turn the picker into a request proxy.
     */
    public function applyRemote(string $provider, string $id): void
    {
        $album = $this->ownedAlbum($this->albumId);

        $result = collect($this->results)
            ->first(fn (array $candidate) => $candidate['provider'] === $provider && $candidate['id'] === $id);

        if ($result === null) {
            $this->error = __('That result is no longer available. Please search again.');

            return;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(20)
                ->get($result['image_url'])
                ->throw();
        } catch (Throwable) {
            $this->error = __('That image could not be downloaded. Try another result.');

            return;
        }

        if ($this->storeCover($album, $response->body(), ArtworkSource::Manual)) {
            $this->success(__('Artwork updated'));
        }
    }

    /**
     * Store a local image as this album's cover.
     */
    public function upload(): void
    {
        $album = $this->ownedAlbum($this->albumId);

        $this->validateOnly('coverFile');

        if ($this->coverFile === null) {
            $this->error = __('Choose an image first.');

            return;
        }

        $bytes = file_get_contents($this->coverFile->getRealPath());

        if ($this->storeCover($album, $bytes === false ? '' : $bytes, ArtworkSource::Uploaded)) {
            $this->coverFile = null;
            $this->success(__('Artwork updated'));
        }
    }

    /**
     * Remove the cover, which re-arms the automatic online fetch.
     */
    public function remove(): void
    {
        $this->ownedAlbum($this->albumId)->clearCoverArt();

        $this->success(__('Artwork removed'));
    }

    /**
     * Hand the album back to detection and kick off a fetch now.
     */
    public function resetToAuto(): void
    {
        $album = $this->ownedAlbum($this->albumId);
        $album->clearCoverArt();

        FetchAlbumArtwork::dispatch($album);

        $this->success(__('Artwork will be detected automatically'));
    }

    /**
     * Write cover bytes, turning an unusable image into a message rather than
     * a fatal — a provider that answers 200 with an error page must not take
     * the page down.
     */
    private function storeCover(Album $album, string $bytes, ArtworkSource $source): bool
    {
        try {
            $album->storeCoverArt($bytes, $source);
        } catch (RuntimeException) {
            $this->error = __('That image could not be used. Try another one.');

            return false;
        }

        return true;
    }

    /**
     * Resolve an album the current user owns, or abort.
     *
     * Every action goes through here rather than trusting $albumId, which is a
     * client-supplied value.
     */
    private function ownedAlbum(?int $albumId): Album
    {
        $album = Album::whereKey($albumId)->where('user_id', Auth::id())->first();

        abort_unless($album !== null, 403);

        return $album;
    }

    public function render(): View
    {
        return view('livewire.artwork-modal');
    }
}
