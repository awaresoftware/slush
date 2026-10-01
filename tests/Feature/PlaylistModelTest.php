<?php

use App\Models\Playlist;
use App\Models\PlaylistTrack;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('playlist belongs to a user', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);

    expect($playlist->user->id)->toBe($user->id);
});

test('playlist has many tracks via pivot', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $track = Track::factory()->create(['user_id' => $user->id]);

    $playlist->tracks()->attach($track->id, ['position' => 0]);

    expect($playlist->tracks)->toHaveCount(1);
    expect($playlist->tracks->first()->id)->toBe($track->id);
});

test('playlist has many playlist track records', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $track = Track::factory()->create(['user_id' => $user->id]);

    PlaylistTrack::create([
        'playlist_id' => $playlist->id,
        'track_id' => $track->id,
        'position' => 0,
    ]);

    expect($playlist->playlistTracks)->toHaveCount(1);
});

test('playlist track belongs to playlist and track', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $track = Track::factory()->create(['user_id' => $user->id]);

    $pivotRecord = PlaylistTrack::create([
        'playlist_id' => $playlist->id,
        'track_id' => $track->id,
        'position' => 0,
    ]);

    expect($pivotRecord->playlist->id)->toBe($playlist->id);
    expect($pivotRecord->track->id)->toBe($track->id);
});

test('playlist factory creates valid record', function () {
    $playlist = Playlist::factory()->create();

    expect($playlist->name)->not->toBeEmpty();
    expect($playlist->user_id)->not->toBeNull();
});

test('playlist unique constraint prevents duplicate tracks', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    $track = Track::factory()->create(['user_id' => $user->id]);

    PlaylistTrack::create(['playlist_id' => $playlist->id, 'track_id' => $track->id, 'position' => 0]);

    expect(fn () => PlaylistTrack::create(['playlist_id' => $playlist->id, 'track_id' => $track->id, 'position' => 1]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
