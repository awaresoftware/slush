<?php

use App\Enums\SourceType;
use App\Models\Album;
use App\Models\LinkedFolder;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;

/*
 * Asserted through the track row's data-test="local-badge" hook rather than a
 * Tailwind class, so restyling the badge does not fail the behaviour these
 * cover: a local track is labelled, an uploaded one is not.
 */

it('library page shows Local badge for a local track', function () {
    $user = User::factory()->create();
    $folder = LinkedFolder::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Local Song',
        'source_type' => SourceType::Local,
        'linked_folder_id' => $folder->id,
        'file_path' => '/fake/path/local-song.mp3',
    ]);

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertSee('data-test="local-badge"', false);
});

it('library page does not show Local badge for an uploaded track', function () {
    $user = User::factory()->create();

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Uploaded Song',
        'source_type' => SourceType::Uploaded,
    ]);

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertDontSee('data-test="local-badge"', false);
});

it('album show page shows Local badge for a local track', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);
    $folder = LinkedFolder::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'title' => 'Album Local Track',
        'source_type' => SourceType::Local,
        'linked_folder_id' => $folder->id,
        'file_path' => '/fake/path/album-local.mp3',
    ]);

    $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertSee('data-test="local-badge"', false);
});

it('album show page does not show Local badge for an uploaded track', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'title' => 'Album Uploaded Track',
        'source_type' => SourceType::Uploaded,
    ]);

    $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertDontSee('data-test="local-badge"', false);
});

it('playlist show page shows Local badge for a local track', function () {
    $user = User::factory()->create();
    $folder = LinkedFolder::factory()->create(['user_id' => $user->id]);
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Playlist Local Track',
        'source_type' => SourceType::Local,
        'linked_folder_id' => $folder->id,
        'file_path' => '/fake/path/playlist-local.mp3',
    ]);

    $playlist->tracks()->attach($track);

    $this->actingAs($user)
        ->get(route('playlists.show', $playlist))
        ->assertSee('data-test="local-badge"', false);
});

it('playlist show page does not show Local badge for an uploaded track', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Playlist Uploaded Track',
        'source_type' => SourceType::Uploaded,
    ]);

    $playlist->tracks()->attach($track);

    $this->actingAs($user)
        ->get(route('playlists.show', $playlist))
        ->assertDontSee('data-test="local-badge"', false);
});
