<?php

use App\Enums\SourceType;
use App\Models\Album;
use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

/*
 * The library lists are paginated rather than accumulated: the page holds one
 * page of rows, and paging swaps them. These cover the page size, the controls,
 * and the reset that a filter change owes the user.
 */

/*
 * The page size is a design decision the redesign is free to retune, so these
 * read it off the component instead of pinning a number. What is under test is
 * that the list splits at that size and that paging swaps rather than appends.
 */
it('holds one page of tracks and reports the rest as more pages', function () {
    $user = User::factory()->create();

    $perPage = Livewire::actingAs($user)->test('pages::library.index')->instance()->perPage;

    Track::factory()->count($perPage + 5)->create(['user_id' => $user->id]);

    $tracks = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->instance()->tracks;

    expect($tracks->count())->toBe($perPage)
        ->and($tracks->total())->toBe($perPage + 5)
        ->and($tracks->lastPage())->toBe(2);
});

it('nextPage swaps in the following batch of tracks', function () {
    $user = User::factory()->create();

    $perPage = Livewire::actingAs($user)->test('pages::library.index')->instance()->perPage;

    Track::factory()->count($perPage + 5)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index');

    $firstPage = $component->instance()->tracks->pluck('id')->all();

    $component->call('nextPage', 'page');

    $secondPage = $component->instance()->tracks->pluck('id')->all();

    expect($secondPage)->toHaveCount(5)
        ->and($secondPage)->not->toContain($firstPage[0]);
});

it('previousPage returns to the earlier batch', function () {
    $user = User::factory()->create();
    Track::factory()->count(60)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index');

    $firstPage = $component->instance()->tracks->pluck('id')->all();

    $component->call('nextPage', 'page')
        ->call('previousPage', 'page');

    expect($component->instance()->tracks->pluck('id')->all())->toBe($firstPage);
});

it('renders pagination controls for the track list', function () {
    $user = User::factory()->create();
    Track::factory()->count(60)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->assertSee('gotoPage(2,', false)
        ->assertSee('nextPage(', false);
});

it('omits pagination controls when everything fits on one page', function () {
    $user = User::factory()->create();
    Track::factory()->count(3)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->assertDontSee('gotoPage(2,', false);
});

it('searching resets the track list to the first page', function () {
    $user = User::factory()->create();
    Track::factory()->count(30)->create(['user_id' => $user->id]);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Hello World']);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Goodbye Blues']);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->call('nextPage', 'page')
        ->set('search', 'hello');

    $titles = $component->instance()->tracks->pluck('title')->all();

    expect($titles)->toBe(['Hello World']);
});

it('changing the source filter resets the track list to the first page', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Uploaded Track', 'source_type' => SourceType::Uploaded]);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Local Track', 'source_type' => SourceType::Local]);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('sourceFilter', 'uploaded');

    $titles = $component->instance()->tracks->pluck('title')->all();

    expect($titles)->toBe(['Uploaded Track']);
});

it('renders the album and duration a track belongs to', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id, 'title' => 'My Album']);
    Track::factory()->create([
        'user_id' => $user->id,
        'album_id' => $album->id,
        'title' => 'Shape Test',
        'artist' => 'Some Artist',
        'duration' => 180,
    ]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->assertSee('Shape Test')
        ->assertSee('Some Artist')
        ->assertSee('My Album')
        ->assertSee('3:00');
});

/*
 * The three tab panels must be Livewire-rendered, not wrapped in an Alpine
 * <template x-if>. Livewire parses the server HTML with innerHTML, so the body
 * of an x-if template lands in `template.content`, which the morph algorithm
 * never walks, while the clone Alpine splices into the live DOM is absent from
 * the incoming tree. The result is a page whose lists, filters and paginators
 * all stop updating in the browser while the server keeps returning correct
 * HTML. Nothing server-side detects it, so the markup itself is asserted here.
 */

it('renders the tab panels without an Alpine x-if wrapper', function () {
    $view = file_get_contents(resource_path('views/pages/library/index.blade.php'));

    expect($view)->not->toContain('<template x-if');
});

it('swaps the panel for the selected tab', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Track Marker']);
    Album::factory()->create(['user_id' => $user->id, 'title' => 'Album Marker']);
    Track::factory()->create(['user_id' => $user->id, 'artist' => 'Artist Marker']);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->assertSee('Track Marker')
        ->assertDontSee('Album Marker')
        ->set('activeTab', 'albums')
        ->assertSee('Album Marker')
        ->assertDontSee('Track Marker')
        ->set('activeTab', 'artists')
        ->assertSee('Artist Marker')
        ->assertDontSee('Track Marker');
});

it('paginates the albums tab on its own page name', function () {
    $user = User::factory()->create();
    Album::factory()->create(['user_id' => $user->id, 'title' => 'Album First Page']);
    Album::factory()->create(['user_id' => $user->id, 'title' => 'Album Second Page']);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('activeTab', 'albums')
        ->set('albumsPerPage', 1)
        ->assertSee('Album First Page')
        ->call('nextPage', 'albums')
        ->assertSee('Album Second Page')
        ->assertDontSee('Album First Page');
});

it('paginates the artists tab on its own page name', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'artist' => 'Artist First Page']);
    Track::factory()->create(['user_id' => $user->id, 'artist' => 'Artist Second Page']);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('activeTab', 'artists')
        ->set('artistsPerPage', 1)
        ->assertSee('Artist First Page')
        ->call('nextPage', 'artists')
        ->assertSee('Artist Second Page')
        ->assertDontSee('Artist First Page');
});

it('drives the segmented control from Livewire rather than Alpine', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id]);

    expect(Livewire::actingAs($user)->test('pages::library.index')->html())
        ->toContain("wire:click=\"\$set('activeTab', 'albums')\"", false)
        ->not->toContain('@entangle');
});
