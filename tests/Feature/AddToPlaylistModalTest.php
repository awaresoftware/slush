<?php

use App\Livewire\AddToPlaylistModal;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

test('component renders without errors', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AddToPlaylistModal::class)
        ->assertOk();
});

test('dispatching open-add-to-playlist sets selectedTrackId and showModal', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(AddToPlaylistModal::class)
        ->call('openForTrack', $track->id)
        ->assertSet('showModal', true)
        ->assertSet('selectedTrackId', $track->id);
});

test('closeModal sets showModal to false and selectedTrackId to null', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(AddToPlaylistModal::class)
        ->call('openForTrack', $track->id)
        ->call('closeModal')
        ->assertSet('showModal', false)
        ->assertSet('selectedTrackId', null);
});

test('addToPlaylist attaches track to playlist and closes modal', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id]);
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(AddToPlaylistModal::class)
        ->call('openForTrack', $track->id)
        ->call('addToPlaylist', $playlist->id)
        ->assertSet('showModal', false)
        ->assertSet('selectedTrackId', null);

    expect($playlist->fresh()->tracks)->toHaveCount(1);
    expect($playlist->fresh()->tracks->first()->id)->toBe($track->id);
});

test('addToPlaylist does not create duplicate attachment', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id]);
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test(AddToPlaylistModal::class);

    $component->call('openForTrack', $track->id)->call('addToPlaylist', $playlist->id);
    $component->call('openForTrack', $track->id)->call('addToPlaylist', $playlist->id);

    expect($playlist->fresh()->tracks)->toHaveCount(1);
});

test('addToPlaylist fires toast notification with playlist name', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id]);
    $playlist = Playlist::factory()->create(['user_id' => $user->id, 'name' => 'My Favorites']);

    $component = Livewire::actingAs($user)
        ->test(AddToPlaylistModal::class)
        ->call('openForTrack', $track->id)
        ->call('addToPlaylist', $playlist->id);

    $jsEffects = $component->effects['xjs'] ?? [];
    $hasToast = collect($jsEffects)->contains(fn ($item) => str_starts_with($item['expression'] ?? '', 'toast('));

    expect($hasToast)->toBeTrue();
});

test('userPlaylists computed returns only authenticated users playlists ordered by name', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Playlist::factory()->create(['user_id' => $user->id, 'name' => 'Zebra']);
    Playlist::factory()->create(['user_id' => $user->id, 'name' => 'Apple']);
    Playlist::factory()->create(['user_id' => $other->id, 'name' => 'Other Playlist']);

    $component = Livewire::actingAs($user)
        ->test(AddToPlaylistModal::class);

    $playlists = $component->instance()->userPlaylists;

    expect($playlists)->toHaveCount(2);
    expect($playlists->first()->name)->toBe('Apple');
    expect($playlists->last()->name)->toBe('Zebra');
});
