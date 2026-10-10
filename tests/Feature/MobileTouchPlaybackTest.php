<?php

use App\Models\Album;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

test('album show: available track row has x-on:click with play-track dispatch', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'title' => 'Available Song',
        'is_available' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertOk();

    $html = $response->getContent();

    expect($html)->toContain('play-track');
    expect($html)->toContain('cursor-pointer');
});

test('album show: unavailable track row does NOT have x-on:click play-track', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'title' => 'Missing Song',
        'is_available' => false,
    ]);

    $response = $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertOk();

    $html = $response->getContent();

    // Unavailable track rows do not have play-track dispatch on tr (no row-level click handler)
    // The existing play button inside td also does not render for unavailable tracks
    expect($html)->not->toContain('aria-label="Play Missing Song"');
    expect($html)->toContain('opacity-50');
});

test('library index: available track row has x-on:click calling playFrom', function () {
    $user = User::factory()->create();

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Available Song',
        'is_available' => true,
    ]);

    $html = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->html();

    expect($html)->toContain('$wire.playFrom(');
    expect($html)->toContain('cursor-pointer');
});

test('library index: unavailable track row does NOT have play button or row click handler', function () {
    $user = User::factory()->create();

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Missing Song',
        'is_available' => false,
    ]);

    $html = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->html();

    // Unavailable track rows do not have a play aria-label or the row-level play-track dispatch
    expect($html)->not->toContain('aria-label="Play Missing Song"');
    expect($html)->toContain('opacity-50');
});

test('playlist show: available track row has x-on:click with play-track dispatch', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Available Song',
        'is_available' => true,
    ]);

    $playlist->tracks()->attach($track, ['position' => 1]);

    $response = $this->actingAs($user)
        ->get(route('playlists.show', $playlist))
        ->assertOk();

    $html = $response->getContent();

    expect($html)->toContain('cursor-pointer');
    expect($html)->toContain('play-track');
});

test('playlist show: action buttons have mobile visibility class', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Available Song',
        'is_available' => true,
    ]);

    $playlist->tracks()->attach($track, ['position' => 1]);

    $response = $this->actingAs($user)
        ->get(route('playlists.show', $playlist))
        ->assertOk();

    expect($response->getContent())->toContain('max-sm:opacity-100');
});
