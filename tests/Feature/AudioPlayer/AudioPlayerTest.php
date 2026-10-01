<?php

use App\Livewire\AudioPlayer;
use App\Models\Album;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// ── Component rendering ────────────────────────────────────────────────────────

it('renders the audio player component', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->assertOk();
});

it('renders with default volume of 80', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->assertSet('volume', 80);
});

it('renders with no current track initially', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->assertSet('currentTrackId', null)
        ->assertSet('queue', []);
});

// ── loadTrack ──────────────────────────────────────────────────────────────────

it('loadTrack sets the current track and dispatches player:load', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id, 'title' => 'Test Song']);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadTrack', $track->id)
        ->assertSet('currentTrackId', $track->id)
        ->assertSet('queue', [$track->id])
        ->assertDispatched('player:load');
});

it('loadTrack dispatches player:load with track url and metadata', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Song',
        'artist' => 'Test Artist',
    ]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadTrack', $track->id)
        ->assertDispatched('player:load', function ($event, $params) use ($track) {
            return $params['trackId'] === $track->id
                && isset($params['url'])
                && $params['title'] === 'Test Song'
                && $params['artist'] === 'Test Artist';
        });
});

it('loadTrack rejects tracks belonging to another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($other)
        ->test(AudioPlayer::class)
        ->call('loadTrack', $track->id);
})->throws(ModelNotFoundException::class);

// ── loadQueue ──────────────────────────────────────────────────────────────────

it('loadQueue sets the queue and starts from startIndex', function () {
    $user = User::factory()->create();
    $track1 = Track::factory()->create(['user_id' => $user->id]);
    $track2 = Track::factory()->create(['user_id' => $user->id]);
    $track3 = Track::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$track1->id, $track2->id, $track3->id], 1)
        ->assertSet('currentTrackId', $track2->id)
        ->assertSet('queue', [$track1->id, $track2->id, $track3->id]);
});

it('loadQueue filters out tracks belonging to another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $ownTrack = Track::factory()->create(['user_id' => $user->id]);
    $otherTrack = Track::factory()->create(['user_id' => $other->id]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$ownTrack->id, $otherTrack->id], 0)
        ->assertSet('queue', [$ownTrack->id]);
});

it('loadQueue does nothing when all tracks belong to another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherTrack = Track::factory()->create(['user_id' => $other->id]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$otherTrack->id], 0)
        ->assertSet('queue', [])
        ->assertSet('currentTrackId', null)
        ->assertNotDispatched('player:load');
});

// ── nextTrack / previousTrack ──────────────────────────────────────────────────

it('nextTrack advances to the next track in the queue', function () {
    $user = User::factory()->create();
    $track1 = Track::factory()->create(['user_id' => $user->id]);
    $track2 = Track::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$track1->id, $track2->id], 0)
        ->call('nextTrack')
        ->assertSet('currentTrackId', $track2->id);
});

it('nextTrack does nothing when already at the last track', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$track->id], 0)
        ->call('nextTrack')
        ->assertSet('currentTrackId', $track->id); // stays on same track
});

it('previousTrack goes back to the previous track in the queue', function () {
    $user = User::factory()->create();
    $track1 = Track::factory()->create(['user_id' => $user->id]);
    $track2 = Track::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$track1->id, $track2->id], 1)
        ->call('previousTrack')
        ->assertSet('currentTrackId', $track1->id);
});

it('previousTrack does nothing when already at the first track', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$track->id], 0)
        ->call('previousTrack')
        ->assertSet('currentTrackId', $track->id); // stays on same track
});

// ── setVolume ──────────────────────────────────────────────────────────────────

it('setVolume updates the volume property', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('setVolume', 50)
        ->assertSet('volume', 50);
});

it('setVolume clamps values below 0 to 0', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('setVolume', -10)
        ->assertSet('volume', 0);
});

it('setVolume clamps values above 100 to 100', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('setVolume', 150)
        ->assertSet('volume', 100);
});

// ── Cover art integration ─────────────────────────────────────────────────────

it('loadTrack includes coverArtUrl when track has an album with cover art', function () {
    Storage::fake('public');
    Storage::disk('public')->put('albums/cover.jpg', 'image-bytes');

    $user = User::factory()->create();
    $album = Album::factory()->create([
        'user_id' => $user->id,
        'cover_art_path' => 'albums/cover.jpg',
    ]);
    $track = Track::factory()->create(['user_id' => $user->id, 'album_id' => $album->id]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadTrack', $track->id)
        ->assertDispatched('player:load', function ($event, $params) {
            return $params['coverArtUrl'] !== null;
        });
});

it('loadTrack includes null coverArtUrl when the cover file is missing from disk', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $album = Album::factory()->create([
        'user_id' => $user->id,
        'cover_art_path' => 'albums/gone.jpg',
    ]);
    $track = Track::factory()->create(['user_id' => $user->id, 'album_id' => $album->id]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadTrack', $track->id)
        ->assertDispatched('player:load', function ($event, $params) {
            return $params['coverArtUrl'] === null;
        });
});

it('loadTrack includes null coverArtUrl when track has no album', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id, 'album_id' => null]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadTrack', $track->id)
        ->assertDispatched('player:load', function ($event, $params) {
            return $params['coverArtUrl'] === null;
        });
});

// ── Unavailable track exclusion ───────────────────────────────────────────────

it('loadQueue excludes unavailable tracks', function () {
    $user = User::factory()->create();
    $availableTrack = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
    $unavailableTrack = Track::factory()->create(['user_id' => $user->id, 'is_available' => false]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$availableTrack->id, $unavailableTrack->id], 0)
        ->assertSet('queue', [$availableTrack->id]);
});

it('loadQueue adjusts startIndex when unavailable tracks precede the desired track', function () {
    $user = User::factory()->create();
    $unavailableTrack = Track::factory()->create(['user_id' => $user->id, 'is_available' => false]);
    $availableTrack = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

    // Index 1 points to availableTrack, but after filtering unavailable, it lands at index 0
    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$unavailableTrack->id, $availableTrack->id], 1)
        ->assertSet('currentTrackId', $availableTrack->id);
});

// ── extendQueue ────────────────────────────────────────────────────────────────

it('extendQueue appends new track IDs to existing queue', function () {
    $user = User::factory()->create();
    $track1 = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
    $track2 = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
    $track3 = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
    $track4 = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$track1->id, $track2->id], 0)
        ->call('extendQueue', [$track3->id, $track4->id])
        ->assertSet('queue', [$track1->id, $track2->id, $track3->id, $track4->id]);
});

it('extendQueue filters out tracks from other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $own = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
    $theirs = Track::factory()->create(['user_id' => $other->id, 'is_available' => true]);
    $base = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

    $component = Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$base->id], 0)
        ->call('extendQueue', [$own->id, $theirs->id]);

    expect($component->get('queue'))->toContain($own->id)
        ->not->toContain($theirs->id);
});

it('extendQueue filters out unavailable tracks', function () {
    $user = User::factory()->create();
    $available = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
    $unavailable = Track::factory()->create(['user_id' => $user->id, 'is_available' => false]);
    $base = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

    $component = Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$base->id], 0)
        ->call('extendQueue', [$available->id, $unavailable->id]);

    expect($component->get('queue'))->toContain($available->id)
        ->not->toContain($unavailable->id);
});

it('extendQueue does not add duplicate track IDs', function () {
    $user = User::factory()->create();
    $track1 = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
    $track2 = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

    $component = Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$track1->id, $track2->id], 0)
        ->call('extendQueue', [$track1->id, $track2->id]);

    expect($component->get('queue'))->toHaveCount(2);
});

// ── nextTrack queue-running-low ────────────────────────────────────────────────

it('nextTrack dispatches queue-running-low when near end of queue', function () {
    $user = User::factory()->create();
    $track1 = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
    $track2 = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
    $track3 = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

    // Queue of 3 tracks, start at 0, call nextTrack → now at index 1, which is within last 3
    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$track1->id, $track2->id, $track3->id], 0)
        ->call('nextTrack')
        ->assertDispatched('queue-running-low');
});

it('nextTrack does not dispatch queue-running-low when not near end', function () {
    $user = User::factory()->create();
    $tracks = Track::factory()->count(10)->create(['user_id' => $user->id, 'is_available' => true]);
    $ids = $tracks->pluck('id')->all();

    // Queue of 10 tracks, start at 0, nextTrack moves to index 1 — not within last 3
    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', $ids, 0)
        ->call('nextTrack')
        ->assertNotDispatched('queue-running-low');
});

// ── toggleShuffle ─────────────────────────────────────────────────────────────

it('toggleShuffle shuffles the queue keeping current track first', function () {
    $user = User::factory()->create();
    $tracks = Track::factory()->count(5)->create(['user_id' => $user->id, 'is_available' => true]);
    $ids = $tracks->pluck('id')->all();

    $component = Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', $ids, 2)
        ->call('toggleShuffle');

    $queue = $component->get('queue');
    $shuffled = $component->get('isShuffled');
    $current = $component->get('currentTrackId');

    expect($shuffled)->toBeTrue();
    expect($queue[0])->toBe($current);
    expect($queue)->toHaveCount(5);
});

it('toggleShuffle restores original queue order when toggled off', function () {
    $user = User::factory()->create();
    $tracks = Track::factory()->count(5)->create(['user_id' => $user->id, 'is_available' => true]);
    $ids = $tracks->pluck('id')->all();

    $component = Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', $ids, 0)
        ->call('toggleShuffle')
        ->call('toggleShuffle');

    expect($component->get('queue'))->toBe($ids);
    expect($component->get('isShuffled'))->toBeFalse();
    expect($component->get('originalQueue'))->toBeEmpty();
});

it('loadQueue resets shuffle state', function () {
    $user = User::factory()->create();
    $tracks = Track::factory()->count(3)->create(['user_id' => $user->id, 'is_available' => true]);
    $ids = $tracks->pluck('id')->all();

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', $ids, 0)
        ->call('toggleShuffle')
        ->call('loadQueue', $ids, 0)
        ->assertSet('isShuffled', false)
        ->assertSet('originalQueue', []);
});

// ── repeat ─────────────────────────────────────────────────────────────────────

it('defaults to repeat off', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(AudioPlayer::class)
        ->assertSet('repeat', AudioPlayer::REPEAT_OFF);
});

it('cycleRepeat moves through off, all, one and back', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(AudioPlayer::class)
        ->call('cycleRepeat')->assertSet('repeat', AudioPlayer::REPEAT_ALL)
        ->call('cycleRepeat')->assertSet('repeat', AudioPlayer::REPEAT_ONE)
        ->call('cycleRepeat')->assertSet('repeat', AudioPlayer::REPEAT_OFF);
});

it('nextTrack wraps to the first track when repeat is all', function () {
    $user = User::factory()->create();
    $track1 = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
    $track2 = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$track1->id, $track2->id], 1)
        ->set('repeat', AudioPlayer::REPEAT_ALL)
        ->call('nextTrack')
        ->assertSet('currentTrackId', $track1->id)
        ->assertDispatched('player:load');
});

it('nextTrack stops at the end when repeat is one', function () {
    $user = User::factory()->create();
    $track1 = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
    $track2 = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$track1->id, $track2->id], 1)
        ->set('repeat', AudioPlayer::REPEAT_ONE)
        ->call('nextTrack')
        ->assertSet('currentTrackId', $track2->id);
});

// ── queue panel actions ────────────────────────────────────────────────────────

it('playQueueIndex jumps to that position in the queue', function () {
    $user = User::factory()->create();
    $tracks = Track::factory()->count(3)->create(['user_id' => $user->id, 'is_available' => true]);
    $ids = $tracks->pluck('id')->all();

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', $ids, 0)
        ->call('playQueueIndex', 2)
        ->assertSet('currentTrackId', $ids[2])
        ->assertDispatched('player:load');
});

it('playQueueIndex ignores an out of range index', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$track->id], 0)
        ->call('playQueueIndex', 9)
        ->assertSet('currentTrackId', $track->id);
});

it('removeFromQueue drops a track that is not playing', function () {
    $user = User::factory()->create();
    $tracks = Track::factory()->count(3)->create(['user_id' => $user->id, 'is_available' => true]);
    $ids = $tracks->pluck('id')->all();

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', $ids, 0)
        ->call('removeFromQueue', 2)
        ->assertSet('queue', [$ids[0], $ids[1]])
        ->assertSet('currentTrackId', $ids[0]);
});

it('removeFromQueue moves playback on when removing the current track', function () {
    $user = User::factory()->create();
    $tracks = Track::factory()->count(3)->create(['user_id' => $user->id, 'is_available' => true]);
    $ids = $tracks->pluck('id')->all();

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', $ids, 0)
        ->call('removeFromQueue', 0)
        ->assertSet('currentTrackId', $ids[1])
        ->assertSet('queue', [$ids[1], $ids[2]]);
});

it('removeFromQueue clears the player when the last track is removed', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$track->id], 0)
        ->call('removeFromQueue', 0)
        ->assertSet('queue', [])
        ->assertSet('currentTrackId', null)
        ->assertDispatched('player:clear');
});

it('clearQueue empties the queue and stops playback', function () {
    $user = User::factory()->create();
    $tracks = Track::factory()->count(2)->create(['user_id' => $user->id, 'is_available' => true]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', $tracks->pluck('id')->all(), 0)
        ->call('clearQueue')
        ->assertSet('queue', [])
        ->assertSet('currentTrackId', null)
        ->assertSet('isShuffled', false)
        ->assertDispatched('player:clear');
});

// ── queueTracks window ─────────────────────────────────────────────────────────

it('queueTracks returns the current track first, in queue order', function () {
    $user = User::factory()->create();
    $tracks = Track::factory()->count(4)->create(['user_id' => $user->id, 'is_available' => true]);
    $ids = $tracks->pluck('id')->all();

    $component = Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', $ids, 1);

    $window = $component->instance()->queueTracks;

    expect($window->pluck('id')->all())->toBe([$ids[1], $ids[2], $ids[3]]);
    expect($window->first()->queue_index)->toBe(1);
});

it('queueTracks windows a long queue and reports the overflow', function () {
    $user = User::factory()->create();
    $tracks = Track::factory()->count(55)->create(['user_id' => $user->id, 'is_available' => true]);

    $component = Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', $tracks->pluck('id')->all(), 0);

    expect($component->instance()->queueTracks)->toHaveCount(50);
    expect($component->instance()->queueOverflowCount)->toBe(5);
});

it('queueTracks is empty when nothing is queued', function () {
    $component = Livewire::actingAs(User::factory()->create())->test(AudioPlayer::class);

    expect($component->instance()->queueTracks)->toBeEmpty();
});

it('player:load carries the album title for the OS media session', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id, 'title' => 'Kind of Blue']);
    $track = Track::factory()->create(['user_id' => $user->id, 'album_id' => $album->id]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadTrack', $track->id)
        ->assertDispatched('player:load', fn ($event, $params) => $params['album'] === 'Kind of Blue');
});

// ── moveInQueue ────────────────────────────────────────────────────────────────

it('moveInQueue moves a track to a new position', function () {
    $user = User::factory()->create();
    $tracks = Track::factory()->count(4)->create(['user_id' => $user->id, 'is_available' => true]);
    $ids = $tracks->pluck('id')->all();

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', $ids, 0)
        ->call('moveInQueue', 3, 1)
        ->assertSet('queue', [$ids[0], $ids[3], $ids[1], $ids[2]]);
});

it('moveInQueue leaves the playing track playing', function () {
    $user = User::factory()->create();
    $tracks = Track::factory()->count(3)->create(['user_id' => $user->id, 'is_available' => true]);
    $ids = $tracks->pluck('id')->all();

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', $ids, 0)
        ->call('moveInQueue', 0, 2)
        ->assertSet('currentTrackId', $ids[0])
        ->assertSet('queue', [$ids[1], $ids[2], $ids[0]])
        ->assertNotDispatched('player:load');
});

it('moveInQueue ignores out-of-range positions', function () {
    $user = User::factory()->create();
    $tracks = Track::factory()->count(2)->create(['user_id' => $user->id, 'is_available' => true]);
    $ids = $tracks->pluck('id')->all();

    $component = Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', $ids, 0);

    $component->call('moveInQueue', 0, 5)->assertSet('queue', $ids);
    $component->call('moveInQueue', 9, 0)->assertSet('queue', $ids);
    $component->call('moveInQueue', 1, 1)->assertSet('queue', $ids);
    $component->call('moveInQueue', 0, -1)->assertSet('queue', $ids);
});

// ── Replace-queue dialog visibility ────────────────────────────────────────────

it('opens the replace-queue dialog as a daisyUI modal only while it is showing', function () {
    $user = User::factory()->create();

    $html = Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->html();

    // daisyUI keeps `.modal-box` at opacity:0 unless the dialog carries
    // `.modal.modal-open`, so the open state has to be bound to the same Alpine
    // flag that drives x-show. Hardcoding `modal-open` instead makes
    // `:root:has(.modal.modal-open)` match on every page, and daisyUI answers
    // that with `overflow: hidden` on the root — locking scroll sitewide.
    expect($html)
        ->toContain('class="modal fixed inset-0')
        ->toContain('modal-backdrop')
        ->not->toContain('modal modal-open');
});

it('leaves no modal open at rest, so the page stays scrollable', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id]);

    $html = $this->actingAs($user)->get(route('library.index'))->getContent();

    preg_match_all('/(?<![:\w-])class="([^"]*)"/', $html, $matches);

    // daisyUI: `:root:has(:is(.modal.modal-open, ...)) { --page-overflow: hidden }`
    // and `:root:not(span) { overflow: var(--page-overflow) }`. Any element
    // carrying both classes freezes scrolling for the whole app, so look at
    // settled class attributes rather than the string "modal-open", which the
    // Alpine :class binding legitimately mentions.
    $openModals = array_filter(
        $matches[1],
        fn (string $classes) => str_contains($classes, 'modal') && str_contains($classes, 'modal-open')
    );

    expect($openModals)->toBeEmpty();
});
