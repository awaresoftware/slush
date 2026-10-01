<?php

use App\Models\Album;
use App\Models\Track;
use App\Models\User;

test('album show page renders tracks sorted by track_number not title', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'track_number' => 3,
        'title' => 'A Track',
    ]);
    Track::factory()->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'track_number' => 1,
        'title' => 'C Track',
    ]);
    Track::factory()->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'track_number' => 2,
        'title' => 'B Track',
    ]);

    $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertOk()
        ->assertSeeInOrder(['C Track', 'B Track', 'A Track']);
});

test('album show renders null track_number tracks after numbered tracks sorted by title', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'track_number' => 2,
        'title' => 'Second Track',
    ]);
    Track::factory()->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'track_number' => 1,
        'title' => 'First Track',
    ]);
    Track::factory()->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'track_number' => null,
        'title' => 'Zebra Song',
    ]);
    Track::factory()->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'track_number' => null,
        'title' => 'Alpha Song',
    ]);

    $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertOk()
        ->assertSeeInOrder(['First Track', 'Second Track', 'Alpha Song', 'Zebra Song']);
});

test('album show displays actual track_number metadata in number column', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'track_number' => 5,
        'title' => 'Track Five',
    ]);

    $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertOk()
        ->assertSeeInOrder(['5', 'Track Five']);
});
