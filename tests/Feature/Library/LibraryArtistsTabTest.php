<?php

use App\Models\Album;
use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

it('loads artists with track counts on mount', function () {
    $user = User::factory()->create();

    Track::factory()->count(3)->create(['user_id' => $user->id, 'artist' => 'Artist Alpha']);
    Track::factory()->count(2)->create(['user_id' => $user->id, 'artist' => 'Artist Beta']);

    $artists = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->instance()->artists;

    expect($artists)->toHaveCount(2);

    $artistNames = $artists->pluck('artist')->all();
    expect($artistNames)->toContain('Artist Alpha');
    expect($artistNames)->toContain('Artist Beta');

    $alpha = $artists->firstWhere('artist', 'Artist Alpha');
    expect($alpha->track_count)->toBe(3);

    $beta = $artists->firstWhere('artist', 'Artist Beta');
    expect($beta->track_count)->toBe(2);
});

it('excludes null and empty artists', function () {
    $user = User::factory()->create();

    Track::factory()->create(['user_id' => $user->id, 'artist' => null]);
    Track::factory()->create(['user_id' => $user->id, 'artist' => '']);
    Track::factory()->create(['user_id' => $user->id, 'artist' => 'Real Artist']);

    $artists = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->instance()->artists;

    expect($artists)->toHaveCount(1);
    expect($artists->first()->artist)->toBe('Real Artist');
});

it('includes album thumbnails for each artist', function () {
    $user = User::factory()->create();

    $album = Album::factory()->create(['user_id' => $user->id, 'artist' => 'Foo Artist', 'title' => 'Foo Album']);
    Track::factory()->count(2)->create(['user_id' => $user->id, 'artist' => 'Foo Artist']);

    $artists = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->instance()->artists;

    expect($artists)->toHaveCount(1);

    $albums = $artists->first()->albums;
    expect($albums)->toHaveCount(1);
    expect($albums[0]['id'])->toBe($album->id);
    expect($albums[0]['title'])->toBe('Foo Album');
});

/*
 * The artist page size is a design decision, so it is read off the component
 * rather than pinned. What is under test is that the grid splits at that size
 * and that paging swaps rather than appends.
 */
it('holds one page of artists and reports the rest as more pages', function () {
    $user = User::factory()->create();

    $perPage = Livewire::actingAs($user)->test('pages::library.index')->instance()->artistsPerPage;

    foreach (range(1, $perPage + 5) as $i) {
        Track::factory()->create(['user_id' => $user->id, 'artist' => "Artist {$i}"]);
    }

    $artists = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->instance()->artists;

    expect($artists)->toHaveCount($perPage)
        ->and($artists->total())->toBe($perPage + 5)
        ->and($artists->lastPage())->toBe(2);
});

it('nextPage swaps in the following batch of artists', function () {
    $user = User::factory()->create();

    $perPage = Livewire::actingAs($user)->test('pages::library.index')->instance()->artistsPerPage;

    foreach (range(1, $perPage + 5) as $i) {
        Track::factory()->create(['user_id' => $user->id, 'artist' => "Artist {$i}"]);
    }

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->call('nextPage', 'artists');

    expect($component->instance()->artists)->toHaveCount(5)
        ->and($component->instance()->artists->currentPage())->toBe(2);
});

it('resets artists when search changes', function () {
    $user = User::factory()->create();

    foreach (range(1, 25) as $i) {
        Track::factory()->create(['user_id' => $user->id, 'artist' => "Artist {$i}"]);
    }

    Track::factory()->count(2)->create(['user_id' => $user->id, 'artist' => 'Unique Artist Name']);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->call('nextPage', 'artists')
        ->set('search', 'Unique Artist Name');

    expect($component->instance()->artists)->toHaveCount(1)
        ->and($component->instance()->artists->first()->artist)->toBe('Unique Artist Name');
});

it('artists tab button is rendered', function () {
    $user = User::factory()->create();

    Track::factory()->create(['user_id' => $user->id, 'artist' => 'Some Artist']);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->assertSee('Artists');
});
