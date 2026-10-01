<?php

use App\Livewire\AddToPlaylistModal;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('user can add a track to their own playlist', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $track = Track::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(AddToPlaylistModal::class)
        ->call('openForTrack', $track->id)
        ->call('addToPlaylist', $playlist->id);

    expect($playlist->fresh()->tracks)->toHaveCount(1);
    expect($playlist->tracks->first()->id)->toBe($track->id);
});

test('adding same track twice is idempotent', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $track = Track::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(AddToPlaylistModal::class)
        ->call('openForTrack', $track->id)
        ->call('addToPlaylist', $playlist->id);

    Livewire::actingAs($user)
        ->test(AddToPlaylistModal::class)
        ->call('openForTrack', $track->id)
        ->call('addToPlaylist', $playlist->id);

    expect($playlist->fresh()->tracks)->toHaveCount(1);
});

test('user cannot add a track to another user playlist', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $owner->id]);
    $track = Track::factory()->create(['user_id' => $other->id]);

    expect(fn () => Livewire::actingAs($other)
        ->test(AddToPlaylistModal::class)
        ->call('openForTrack', $track->id)
        ->call('addToPlaylist', $playlist->id))
        ->toThrow(ModelNotFoundException::class);
});

test('tracks are added in order with sequential positions', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $track1 = Track::factory()->create(['user_id' => $user->id]);
    $track2 = Track::factory()->create(['user_id' => $user->id]);
    $track3 = Track::factory()->create(['user_id' => $user->id]);

    foreach ([$track1, $track2, $track3] as $i => $track) {
        Livewire::actingAs($user)
            ->test(AddToPlaylistModal::class)
            ->call('openForTrack', $track->id)
            ->call('addToPlaylist', $playlist->id);

        unset($i);
    }

    $tracks = $playlist->fresh()->tracks;
    expect($tracks)->toHaveCount(3);
    expect($tracks->first()->id)->toBe($track1->id);
    expect($tracks->last()->id)->toBe($track3->id);
});

test('user can remove a track from their own playlist', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $track = Track::factory()->create(['user_id' => $user->id]);
    $playlist->tracks()->attach($track->id, ['position' => 0]);

    $this->actingAs($user)
        ->delete(route('playlist-tracks.destroy', [$playlist, $track]))
        ->assertRedirect();

    expect($playlist->fresh()->tracks)->toHaveCount(0);
});

test('user cannot remove a track from another user playlist', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $owner->id]);
    $track = Track::factory()->create(['user_id' => $owner->id]);
    $playlist->tracks()->attach($track->id, ['position' => 0]);

    $this->actingAs($other)
        ->delete(route('playlist-tracks.destroy', [$playlist, $track]))
        ->assertForbidden();
});
