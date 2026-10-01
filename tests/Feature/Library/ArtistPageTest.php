<?php

use App\Models\Album;
use App\Models\Track;
use App\Models\User;

it('shows an artist page with albums and metadata', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id, 'title' => 'Blue Train', 'artist' => 'John Coltrane']);
    Track::factory()->count(3)->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'artist' => 'John Coltrane',
        'duration' => 120,
    ]);

    $this->actingAs($user)
        ->get(route('library.artist.show', ['artist' => 'John Coltrane']))
        ->assertOk()
        ->assertSee('John Coltrane')
        ->assertSee('Blue Train')
        ->assertSee('Top Tracks')
        ->assertSee('3 tracks')
        ->assertSee('6 minutes');
});

it('orders top tracks by play count', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'artist' => 'Miles', 'title' => 'Rarely Heard', 'play_count' => 1]);
    Track::factory()->create(['user_id' => $user->id, 'artist' => 'Miles', 'title' => 'On Repeat', 'play_count' => 20]);

    $html = $this->actingAs($user)
        ->get(route('library.artist.show', ['artist' => 'Miles']))
        ->getContent();

    expect(strpos($html, 'On Repeat'))->toBeLessThan(strpos($html, 'Rarely Heard'));
});

it('returns 404 for an artist the user does not have', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('library.artist.show', ['artist' => 'Nobody']))
        ->assertNotFound();
});

it("returns 404 for another user's artist", function () {
    $owner = User::factory()->create();
    Track::factory()->create(['user_id' => $owner->id, 'artist' => 'Private Artist']);

    $this->actingAs(User::factory()->create())
        ->get(route('library.artist.show', ['artist' => 'Private Artist']))
        ->assertNotFound();
});

it('handles artist names with unicode and spaces', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'artist' => 'Sigur Rós']);

    $this->actingAs($user)
        ->get(route('library.artist.show', ['artist' => 'Sigur Rós']))
        ->assertOk()
        ->assertSee('Sigur Rós');
});

it('links artist names from the library track rows', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'artist' => 'Linked Artist', 'title' => 'Some Song']);

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertSee(route('library.artist.show', ['artist' => 'Linked Artist']), false);
});
