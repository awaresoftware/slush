<?php

use App\Models\Album;
use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

// ── Album show page ───────────────────────────────────────────────────────────

it('album show page returns 200 for the album owner', function () {
    $user  = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertOk();
});

it('album show page returns 403 for another authenticated user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->get(route('library.album.show', $album))
        ->assertForbidden();
});

it('album show page redirects unauthenticated user to login', function () {
    $user  = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    $this->get(route('library.album.show', $album))
        ->assertRedirect(route('login'));
});

it('album show page displays album title and artist', function () {
    $user  = User::factory()->create();
    $album = Album::factory()->create([
        'user_id' => $user->id,
        'title'   => 'Rumours',
        'artist'  => 'Fleetwood Mac',
    ]);

    $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertSee('Rumours')
        ->assertSee('Fleetwood Mac');
});

it('album show page displays Unknown Artist when artist is null', function () {
    $user  = User::factory()->create();
    $album = Album::factory()->create([
        'user_id' => $user->id,
        'artist'  => null,
    ]);

    $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertSee('Unknown Artist');
});

it('album show page lists all tracks ordered by title', function () {
    $user  = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    Track::factory()->create(['user_id' => $user->id, 'album_id' => $album->id, 'title' => 'Zephyr Song']);
    Track::factory()->create(['user_id' => $user->id, 'album_id' => $album->id, 'title' => 'Aeroplane']);

    $response = $this->actingAs($user)
        ->get(route('library.album.show', $album));

    $response->assertSee('Zephyr Song');
    $response->assertSee('Aeroplane');
    $response->assertSeeInOrder(['Aeroplane', 'Zephyr Song']);
});

it('album show page formats duration as M:SS', function () {
    $user  = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id'  => $user->id,
        'album_id' => $album->id,
        'duration' => 213, // 3:33
    ]);

    $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertSee('3:33');
});

it('album show page shows dash for null duration', function () {
    $user  = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id'  => $user->id,
        'album_id' => $album->id,
        'duration' => null,
    ]);

    $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertSee('—');
});

it('album show page shows empty state when album has no tracks', function () {
    $user  = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertSee('No tracks in this album yet');
});

it('album show page includes a Back to Library link', function () {
    $user  = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('library.album.show', $album))
        ->assertSee(route('library.index'));
});

// ── Upload summary page ───────────────────────────────────────────────────────

it('upload summary page returns 200 for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('library.upload.summary'))
        ->assertOk();
});

it('upload summary page redirects unauthenticated user to login', function () {
    $this->get(route('library.upload.summary'))
        ->assertRedirect(route('login'));
});

it('upload summary page shows tracks from session uploaded_track_ids', function () {
    $user  = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id, 'title' => 'My Upload Track']);

    $this->actingAs($user)
        ->withSession(['uploaded_track_ids' => [$track->id]])
        ->get(route('library.upload.summary'))
        ->assertSee('My Upload Track');
});

it('upload summary page only shows tracks belonging to the authenticated user', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $ownTrack   = Track::factory()->create(['user_id' => $user->id, 'title' => 'My Track']);
    $otherTrack = Track::factory()->create(['user_id' => $other->id, 'title' => 'Other Track']);

    $this->actingAs($user)
        ->withSession(['uploaded_track_ids' => [$ownTrack->id, $otherTrack->id]])
        ->get(route('library.upload.summary'))
        ->assertSee('My Track')
        ->assertDontSee('Other Track');
});

it('upload summary page shows Processing badge for tracks with null duration', function () {
    $user  = User::factory()->create();
    $track = Track::factory()->create([
        'user_id'  => $user->id,
        'duration' => null,
    ]);

    $this->actingAs($user)
        ->withSession(['uploaded_track_ids' => [$track->id]])
        ->get(route('library.upload.summary'))
        ->assertSee('Processing...');
});

it('upload summary page formats duration as M:SS when duration is set', function () {
    $user  = User::factory()->create();
    $track = Track::factory()->create([
        'user_id'  => $user->id,
        'duration' => 195, // 3:15
    ]);

    $this->actingAs($user)
        ->withSession(['uploaded_track_ids' => [$track->id]])
        ->get(route('library.upload.summary'))
        ->assertSee('3:15');
});

it('upload summary page shows album title for tracks with an album', function () {
    $user  = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id, 'title' => 'Greatest Hits']);
    $track = Track::factory()->create(['user_id' => $user->id, 'album_id' => $album->id]);

    $this->actingAs($user)
        ->withSession(['uploaded_track_ids' => [$track->id]])
        ->get(route('library.upload.summary'))
        ->assertSee('Greatest Hits');
});

it('upload summary page shows No album for tracks without an album', function () {
    $user  = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id, 'album_id' => null]);

    $this->actingAs($user)
        ->withSession(['uploaded_track_ids' => [$track->id]])
        ->get(route('library.upload.summary'))
        ->assertSee('No album');
});

it('upload summary page shows no recent uploads when session is empty', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('library.upload.summary'))
        ->assertSee('No recent uploads');
});

it('upload summary page includes a Go to Library link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('library.upload.summary'))
        ->assertSee(route('library.index'));
});
