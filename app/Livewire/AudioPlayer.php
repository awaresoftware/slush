<?php

namespace App\Livewire;

use App\Models\Track;
use App\Models\TrackPlay;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class AudioPlayer extends Component
{
    /**
     * The ID of the currently loaded track (set from outside via loadTrack / loadQueue).
     */
    public ?int $currentTrackId = null;

    /**
     * Ordered list of track IDs in the current playback queue.
     *
     * @var list<int>
     */
    public array $queue = [];

    /**
     * Persisted volume level (0–100) restored on component mount.
     */
    public int $volume = 80;

    /**
     * Whether the queue is currently shuffled.
     */
    public bool $isShuffled = false;

    /**
     * The original (unshuffled) queue order, preserved when shuffle is active.
     *
     * @var list<int>
     */
    public array $originalQueue = [];

    /**
     * Repeat mode: 'off', 'all' (wrap at the end) or 'one' (replay the track).
     */
    public string $repeat = self::REPEAT_OFF;

    public const REPEAT_OFF = 'off';

    public const REPEAT_ALL = 'all';

    public const REPEAT_ONE = 'one';

    /**
     * How many upcoming tracks the queue panel shows at once.
     */
    private const QUEUE_WINDOW = 50;

    /**
     * Most tracks one queue load may hold. Keeps the ownership whereIn under
     * SQLite's 32,766 bound-parameter limit and the Livewire snapshot small.
     */
    public const MAX_QUEUE = 30000;

    /**
     * Strings the Alpine player needs for the queue toasts, which fire outside
     * any Blade view that could translate them itself.
     *
     * @return array{addedToQueue: string, alreadyQueued: string}
     */
    #[Computed]
    public function messages(): array
    {
        return [
            'addedToQueue' => __('Added to queue'),
            'alreadyQueued' => __('Already in queue'),
        ];
    }

    /**
     * Whether there is any track after the current one.
     *
     * The queue always holds the playing track — loadTrack seeds it with a
     * single id and loadQueue keeps the current one in place — so an empty queue
     * only ever happens before playback, and the whole player is hidden then
     * anyway. What the "Playing Next" control has to answer is whether there is
     * another track coming, so the count is over everything but the current one
     * rather than over the queue.
     */
    #[Computed]
    public function hasUpcoming(): bool
    {
        if ($this->currentTrackId === null) {
            return false;
        }

        return count(array_filter($this->queue, fn (int $id) => $id !== $this->currentTrackId)) > 0;
    }

    /**
     * Load a single track and start playback.
     */
    public function loadTrack(int $trackId): void
    {
        $track = Track::with('album')->where('user_id', Auth::id())->findOrFail($trackId);

        $this->currentTrackId = $track->id;
        $this->queue = [$track->id];

        $this->dispatch('player:load', ...$this->trackPayload($track, autoplay: true));
    }

    /**
     * Load an ordered queue of tracks and start playing from the given index.
     *
     * @param  list<int>  $trackIds
     */
    public function loadQueue(array $trackIds, int $startIndex = 0): void
    {
        ['trackIds' => $trackIds, 'index' => $startIndex] = self::windowAround($trackIds, $startIndex);

        // Reset shuffle state on every new queue load.
        $this->isShuffled = false;
        $this->originalQueue = [];

        // Filter to only include tracks the authenticated user owns and that are available.
        $ownedIds = Track::where('user_id', Auth::id())
            ->where('is_available', true)
            ->whereIn('id', $trackIds)
            ->pluck('id')
            ->flip();

        // Keyed lookup: a whole-library queue is thousands of ids, in_array would be quadratic.
        $this->queue = array_values(array_filter($trackIds, fn ($id) => $ownedIds->has($id)));

        if (empty($this->queue)) {
            return;
        }

        $startIndex = max(0, min($startIndex, count($this->queue) - 1));
        $this->currentTrackId = $this->queue[$startIndex];

        $track = Track::with('album')->findOrFail($this->currentTrackId);

        $this->dispatch('player:load', ...$this->trackPayload($track, autoplay: true, queueIndex: $startIndex));
    }

    /**
     * Cut an oversized id list down to MAX_QUEUE ids centred on the given
     * index, so the chosen track always stays in the queue.
     *
     * @param  list<int>  $ids
     * @return array{trackIds: list<int>, index: int}
     */
    public static function windowAround(array $ids, int $index): array
    {
        $ids = array_values($ids);
        $offset = max(0, min($index - intdiv(self::MAX_QUEUE, 2), count($ids) - self::MAX_QUEUE));

        return ['trackIds' => array_slice($ids, $offset, self::MAX_QUEUE), 'index' => $index - $offset];
    }

    /**
     * Advance to the next track in the queue. With repeat 'all' the queue
     * wraps around at the end; otherwise playback stops on the last track.
     */
    #[Renderless]
    public function nextTrack(): void
    {
        if (empty($this->queue) || $this->currentTrackId === null) {
            return;
        }

        $index = array_search($this->currentTrackId, $this->queue);

        if ($index === false) {
            return;
        }

        $isLast = $index >= count($this->queue) - 1;

        if ($isLast && $this->repeat !== self::REPEAT_ALL) {
            return;
        }

        // Dispatch queue-running-low when within the last 3 tracks so the library can extend the queue.
        if ($index >= count($this->queue) - 3) {
            $this->dispatch('queue-running-low');
        }

        $this->currentTrackId = $isLast ? $this->queue[0] : $this->queue[$index + 1];
        $track = Track::with('album')->findOrFail($this->currentTrackId);

        $this->dispatch('player:load', ...$this->trackPayload($track, autoplay: true));
    }

    /**
     * Cycle repeat between off, all and one.
     */
    public function cycleRepeat(): void
    {
        $this->repeat = match ($this->repeat) {
            self::REPEAT_OFF => self::REPEAT_ALL,
            self::REPEAT_ALL => self::REPEAT_ONE,
            default => self::REPEAT_OFF,
        };
    }

    /**
     * Jump straight to a position in the queue (clicking a row in the queue panel).
     */
    public function playQueueIndex(int $index): void
    {
        if (! array_key_exists($index, $this->queue)) {
            return;
        }

        $this->currentTrackId = $this->queue[$index];
        $track = Track::with('album')->findOrFail($this->currentTrackId);

        $this->dispatch('player:load', ...$this->trackPayload($track, autoplay: true));
    }

    /**
     * Drop one track from the queue. Removing the track that is playing moves
     * playback on to the next one, or clears the player when none is left.
     */
    public function removeFromQueue(int $index): void
    {
        if (! array_key_exists($index, $this->queue)) {
            return;
        }

        $removedId = $this->queue[$index];
        $wasCurrent = $removedId === $this->currentTrackId;

        array_splice($this->queue, $index, 1);
        $this->originalQueue = array_values(array_filter(
            $this->originalQueue,
            fn (int $id): bool => $id !== $removedId,
        ));

        if (! $wasCurrent) {
            return;
        }

        if (empty($this->queue)) {
            $this->clearQueue();

            return;
        }

        $this->playQueueIndex(min($index, count($this->queue) - 1));
    }

    /**
     * Move a queue entry from one position to another (drag or arrow keys).
     * Playback is untouched: the current track keeps playing wherever it lands.
     */
    public function moveInQueue(int $from, int $to): void
    {
        $count = count($this->queue);

        if ($from === $to
            || ! array_key_exists($from, $this->queue)
            || $to < 0 || $to >= $count) {
            return;
        }

        $moved = array_splice($this->queue, $from, 1);
        array_splice($this->queue, $to, 0, $moved);
        $this->queue = array_values($this->queue);
    }

    /**
     * Empty the queue and stop playback.
     */
    public function clearQueue(): void
    {
        $this->queue = [];
        $this->originalQueue = [];
        $this->currentTrackId = null;
        $this->isShuffled = false;

        $this->dispatch('player:clear');
    }

    /**
     * Go back to the previous track in the queue.
     */
    #[Renderless]
    public function previousTrack(): void
    {
        if (empty($this->queue) || $this->currentTrackId === null) {
            return;
        }

        $index = array_search($this->currentTrackId, $this->queue);

        if ($index === false || $index <= 0) {
            return;
        }

        $this->currentTrackId = $this->queue[$index - 1];
        $track = Track::with('album')->findOrFail($this->currentTrackId);

        $this->dispatch('player:load', ...$this->trackPayload($track, autoplay: true));
    }

    /**
     * Append new track IDs to the existing queue (called when playback nears the end).
     *
     * @param  list<int>  $newTrackIds
     */
    public function extendQueue(array $newTrackIds): void
    {
        // Filter to owned + available tracks only.
        $ownedIds = Track::where('user_id', Auth::id())
            ->where('is_available', true)
            ->whereIn('id', $newTrackIds)
            ->pluck('id')
            ->all();

        $filteredNew = array_values(array_filter($ownedIds, fn ($id) => ! in_array($id, $this->queue)));

        if (empty($filteredNew)) {
            return;
        }

        if ($this->isShuffled) {
            // Shuffle only the new portion and append after the current track position.
            $shuffled = $filteredNew;
            $count = count($shuffled);

            for ($i = $count - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$shuffled[$i], $shuffled[$j]] = [$shuffled[$j], $shuffled[$i]];
            }

            $currentIndex = array_search($this->currentTrackId, $this->queue);
            $insertAt = $currentIndex === false ? count($this->queue) : $currentIndex + 1;

            array_splice($this->queue, $insertAt, 0, $shuffled);
        } else {
            $this->queue = array_merge($this->queue, $filteredNew);
        }
    }

    /**
     * Toggle shuffle on/off. When turned on, the queue is shuffled with the current
     * track placed first. When turned off, the original queue order is restored.
     */
    public function toggleShuffle(): void
    {
        if (! $this->isShuffled) {
            $this->isShuffled = true;
            $this->originalQueue = $this->queue;

            $currentId = $this->currentTrackId;
            $remaining = array_values(array_filter($this->queue, fn ($id) => $id !== $currentId));
            $count = count($remaining);

            for ($i = $count - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$remaining[$i], $remaining[$j]] = [$remaining[$j], $remaining[$i]];
            }

            $this->queue = $currentId !== null
                ? array_merge([$currentId], $remaining)
                : $remaining;
        } else {
            $this->isShuffled = false;
            $this->queue = $this->originalQueue;
            $this->originalQueue = [];
        }
    }

    /**
     * Persist volume preference (called from Alpine when user adjusts slider).
     */
    #[Renderless]
    public function setVolume(int $volume): void
    {
        $this->volume = max(0, min(100, $volume));
    }

    /**
     * Count a listen. The client fires this once per loaded track after the
     * Last.fm threshold — half the track or 30 seconds, whichever is sooner.
     */
    #[Renderless]
    public function recordPlay(int $trackId): void
    {
        $track = Track::where('user_id', Auth::id())->find($trackId);

        if ($track === null) {
            return;
        }

        $now = now();

        TrackPlay::create([
            'user_id' => Auth::id(),
            'track_id' => $track->id,
            'played_at' => $now,
        ]);

        $track->increment('play_count', 1, ['last_played_at' => $now]);
    }

    /**
     * Heart or un-heart a track. Every heart in the UI funnels through this
     * component (it is persistent on all pages); the browser event fans the
     * new state back out to whichever rows are showing the track.
     */
    public function toggleFavorite(int $trackId): void
    {
        $track = Track::where('user_id', Auth::id())->find($trackId);

        if ($track === null) {
            return;
        }

        $track->favorited_at = $track->isFavorited() ? null : now();
        $track->save();

        $this->dispatch('favorite-changed', trackId: $track->id, favorited: $track->isFavorited());
    }

    /**
     * Build the event payload for player:load events.
     *
     * @return array<string, mixed>
     */
    protected function trackPayload(Track $track, bool $autoplay = false, ?int $queueIndex = null): array
    {
        $payload = [
            'trackId' => $track->id,
            'url' => $track->audioUrl(),
            'title' => $track->title,
            'artist' => $track->artist,
            'album' => $track->album?->title,
            'coverArtUrl' => $track->album?->coverArtUrl(),
            'favorited' => $track->isFavorited(),
            'lyrics' => $track->lyrics,
            'peaks' => $track->peaks ? json_decode($track->peaks, true) : null,
            'autoplay' => $autoplay,
        ];

        if ($queueIndex !== null) {
            $payload['queueIndex'] = $queueIndex;
        }

        return $payload;
    }

    /**
     * The slice of the queue the panel shows: the current track plus the tracks
     * that follow it. The queue grows without bound as the library scrolls, so
     * this stays windowed rather than loading every track on each render.
     *
     * @return Collection<int, Track>
     */
    #[Computed]
    public function queueTracks(): Collection
    {
        if (empty($this->queue)) {
            return new Collection;
        }

        $start = array_search($this->currentTrackId, $this->queue);
        $start = $start === false ? 0 : $start;

        $window = array_slice($this->queue, $start, self::QUEUE_WINDOW, preserve_keys: true);

        $tracks = Track::with('album')
            ->whereIn('id', $window)
            ->get()
            ->keyBy('id');

        return (new Collection(array_keys($window)))
            ->map(function (int $queueIndex) use ($window, $tracks): ?Track {
                $track = $tracks->get($window[$queueIndex]);

                return $track?->setAttribute('queue_index', $queueIndex);
            })
            ->filter()
            ->values();
    }

    /**
     * How many tracks sit beyond the window the panel renders.
     */
    #[Computed]
    public function queueOverflowCount(): int
    {
        $start = array_search($this->currentTrackId, $this->queue);
        $start = $start === false ? 0 : $start;

        return max(0, count($this->queue) - $start - self::QUEUE_WINDOW);
    }

    public function render(): View
    {
        return view('livewire.audio-player');
    }
}
