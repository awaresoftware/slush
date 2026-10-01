<?php

use App\Livewire\CommandPalette;
use App\Models\Album;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

it('mounts inside the app shell', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('data-test="command-palette"', false);
});

it('finds tracks, albums, artists and playlists for the owner', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id, 'title' => 'Blue Train', 'artist' => 'John Coltrane']);
    Track::factory()->create(['user_id' => $user->id, 'album_id' => $album->id, 'title' => 'Blue Groove', 'artist' => 'John Coltrane']);
    Playlist::factory()->create(['user_id' => $user->id, 'name' => 'Blue Evenings']);

    $component = Livewire::actingAs($user)
        ->test(CommandPalette::class)
        ->set('query', 'blue');

    $results = $component->instance()->results;

    expect(array_keys($results))->toContain('Tracks', 'Albums', 'Playlists')
        ->and(collect($results['Tracks'])->pluck('label'))->toContain('Blue Groove')
        ->and(collect($results['Albums'])->pluck('label'))->toContain('Blue Train')
        ->and(collect($results['Playlists'])->pluck('label'))->toContain('Blue Evenings');
});

it('matches artists by name and links to their page', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'artist' => 'Aphex Twin']);

    $results = Livewire::actingAs($user)
        ->test(CommandPalette::class)
        ->set('query', 'aphex')
        ->instance()->results;

    expect($results['Artists'][0]['url'])
        ->toBe(route('library.artist.show', ['artist' => 'Aphex Twin']));
});

it("never returns another user's library", function () {
    $owner = User::factory()->create();
    Track::factory()->create(['user_id' => $owner->id, 'title' => 'Private Song']);

    $results = Livewire::actingAs(User::factory()->create())
        ->test(CommandPalette::class)
        ->set('query', 'private')
        ->instance()->results;

    expect($results)->toBe([]);
});

it('lists unavailable tracks without a play action', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Missing Song', 'is_available' => false]);

    $results = Livewire::actingAs($user)
        ->test(CommandPalette::class)
        ->set('query', 'missing')
        ->instance()->results;

    expect($results['Tracks'][0]['trackId'])->toBeNull();
});

it('neutralises LIKE wildcards in the query', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Ordinary Song']);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Literally 100% Done']);

    $component = Livewire::actingAs($user)->test(CommandPalette::class);

    // A wildcard-only query must not match the whole library…
    expect($component->set('query', '%%%')->instance()->results)->toBe([]);

    // …and wildcards inside a query are ignored, not interpreted.
    $results = $component->set('query', '100%')->instance()->results;
    expect(collect($results['Tracks'] ?? [])->pluck('label')->all())->toBe(['Literally 100% Done']);
});

it('returns nothing for a blank query', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id]);

    $results = Livewire::actingAs($user)
        ->test(CommandPalette::class)
        ->set('query', '  ')
        ->instance()->results;

    expect($results)->toBe([]);
});

it('ranks most played tracks first', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Song Quiet', 'play_count' => 0]);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Song Loved', 'play_count' => 12]);

    $results = Livewire::actingAs($user)
        ->test(CommandPalette::class)
        ->set('query', 'song')
        ->instance()->results;

    expect($results['Tracks'][0]['label'])->toBe('Song Loved');
});
