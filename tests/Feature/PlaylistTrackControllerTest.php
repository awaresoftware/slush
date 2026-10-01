<?php

use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can add a track to their own playlist', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $track = Track::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('playlist-tracks.store', $playlist), ['track_id' => $track->id])
        ->assertRedirect();

    expect($playlist->fresh()->tracks)->toHaveCount(1);
    expect($playlist->tracks->first()->id)->toBe($track->id);
});

test('adding same track twice is idempotent', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $track = Track::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('playlist-tracks.store', $playlist), ['track_id' => $track->id]);
    $this->actingAs($user)->post(route('playlist-tracks.store', $playlist), ['track_id' => $track->id]);

    expect($playlist->fresh()->tracks)->toHaveCount(1);
});

test('user cannot add a track to another user playlist', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $owner->id]);
    $track = Track::factory()->create(['user_id' => $other->id]);

    $this->actingAs($other)
        ->post(route('playlist-tracks.store', $playlist), ['track_id' => $track->id])
        ->assertForbidden();
});

test('user cannot add another users track to their playlist', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $owner->id]);
    $track = Track::factory()->create(['user_id' => $other->id]);

    $this->actingAs($owner)
        ->post(route('playlist-tracks.store', $playlist), ['track_id' => $track->id])
        ->assertNotFound();
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

test('track id is required when adding to playlist', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('playlist-tracks.store', $playlist), [])
        ->assertSessionHasErrors('track_id');
});

test('tracks are added in order with sequential positions', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $track1 = Track::factory()->create(['user_id' => $user->id]);
    $track2 = Track::factory()->create(['user_id' => $user->id]);
    $track3 = Track::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('playlist-tracks.store', $playlist), ['track_id' => $track1->id]);
    $this->actingAs($user)->post(route('playlist-tracks.store', $playlist), ['track_id' => $track2->id]);
    $this->actingAs($user)->post(route('playlist-tracks.store', $playlist), ['track_id' => $track3->id]);

    $tracks = $playlist->fresh()->tracks;
    expect($tracks)->toHaveCount(3);
    expect($tracks->first()->id)->toBe($track1->id);
    expect($tracks->last()->id)->toBe($track3->id);
});
