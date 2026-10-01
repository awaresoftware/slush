<?php

use App\Models\Album;
use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

// ── tracks() computed property ────────────────────────────────────────────────

it('tracks computed property returns all tracks for authenticated user ordered by title', function () {
    $user = User::factory()->create();

    Track::factory()->create(['user_id' => $user->id, 'title' => 'Zephyr Song']);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Aeroplane']);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->assertSet('search', '')
        ->assertSeeInOrder(['Aeroplane', 'Zephyr Song']);
});

it('tracks computed property does not return tracks from other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Track::factory()->create(['user_id' => $user->id, 'title' => 'My Track']);
    Track::factory()->create(['user_id' => $other->id, 'title' => 'Other Track']);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index');

    $component->assertSee('My Track')
        ->assertDontSee('Other Track');
});

it('tracks computed property filters by title when search is set', function () {
    $user = User::factory()->create();

    Track::factory()->create(['user_id' => $user->id, 'title' => 'Hello World']);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Goodbye Blues']);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('search', 'hello')
        ->assertSee('Hello World')
        ->assertDontSee('Goodbye Blues');
});

it('tracks computed property filters by artist when search is set', function () {
    $user = User::factory()->create();

    Track::factory()->create(['user_id' => $user->id, 'title' => 'Song One', 'artist' => 'Hello Artist']);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Song Two', 'artist' => 'Different Artist']);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('search', 'hello')
        ->assertSee('Song One')
        ->assertDontSee('Song Two');
});

it('tracks computed property filters by album title when search is set', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id, 'title' => 'Hello Album']);

    Track::factory()->create(['user_id' => $user->id, 'title' => 'Track On Album', 'album_id' => $album->id]);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Track No Album', 'album_id' => null]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('search', 'hello')
        ->assertSee('Track On Album')
        ->assertDontSee('Track No Album');
});

it('tracks computed property search is case insensitive', function () {
    $user = User::factory()->create();

    Track::factory()->create(['user_id' => $user->id, 'title' => 'HELLO WORLD']);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('search', 'hello')
        ->assertSee('HELLO WORLD');
});

it('tracks computed property returns all tracks when search is empty', function () {
    $user = User::factory()->create();

    Track::factory()->create(['user_id' => $user->id, 'title' => 'Alpha']);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Beta']);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('search', '');

    $component->assertSee('Alpha')
        ->assertSee('Beta');
});

// ── albums paginator ──────────────────────────────────────────────────────

it('albums returns all albums for authenticated user ordered by title', function () {
    $user = User::factory()->create();

    Album::factory()->create(['user_id' => $user->id, 'title' => 'Zephyr']);
    Album::factory()->create(['user_id' => $user->id, 'title' => 'Alpha']);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('activeTab', 'albums')
        ->assertSeeInOrder(['Alpha', 'Zephyr']);
});

it('albums does not return albums from other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Album::factory()->create(['user_id' => $user->id, 'title' => 'My Album']);
    Album::factory()->create(['user_id' => $other->id, 'title' => 'Other Album']);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('activeTab', 'albums')
        ->assertSee('My Album')
        ->assertDontSee('Other Album');
});

it('albums filters by title when search is set', function () {
    $user = User::factory()->create();

    $matching = Album::factory()->create(['user_id' => $user->id, 'title' => 'Hello World Album']);
    $nonMatching = Album::factory()->create(['user_id' => $user->id, 'title' => 'Goodbye Blues']);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('search', 'hello');

    expect($component->instance()->albums->pluck('id'))->toContain($matching->id)
        ->not->toContain($nonMatching->id);
});

it('albums filters by artist when search is set', function () {
    $user = User::factory()->create();

    $matching = Album::factory()->create(['user_id' => $user->id, 'title' => 'Album One', 'artist' => 'Hello Artist']);
    $nonMatching = Album::factory()->create(['user_id' => $user->id, 'title' => 'Album Two', 'artist' => 'Different Artist']);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('search', 'hello');

    expect($component->instance()->albums->pluck('id'))->toContain($matching->id)
        ->not->toContain($nonMatching->id);
});

it('albums returns all albums when search is empty', function () {
    $user = User::factory()->create();

    Album::factory()->create(['user_id' => $user->id, 'title' => 'Alpha']);
    Album::factory()->create(['user_id' => $user->id, 'title' => 'Beta']);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('activeTab', 'albums')
        ->set('search', '');

    $component->assertSee('Alpha')
        ->assertSee('Beta');
});
