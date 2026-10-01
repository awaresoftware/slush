<?php

use App\Models\Album;
use App\Models\User;
use Livewire\Livewire;

/*
 * The album page size is a design decision, so it is read off the component
 * rather than pinned. What is under test is that the grid splits at that size
 * and that paging swaps rather than appends.
 */
it('holds one page of albums and reports the rest as more pages', function () {
    $user = User::factory()->create();

    $perPage = Livewire::actingAs($user)->test('pages::library.index')->instance()->albumsPerPage;

    Album::factory()->count($perPage + 5)->create(['user_id' => $user->id]);

    $albums = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->instance()->albums;

    expect($albums->count())->toBe($perPage)
        ->and($albums->total())->toBe($perPage + 5)
        ->and($albums->lastPage())->toBe(2);
});

it('nextPage swaps in the following batch of albums', function () {
    $user = User::factory()->create();

    $perPage = Livewire::actingAs($user)->test('pages::library.index')->instance()->albumsPerPage;

    Album::factory()->count($perPage + 5)->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->call('nextPage', 'albums');

    expect($component->instance()->albums)->toHaveCount(5)
        ->and($component->instance()->albums->currentPage())->toBe(2);
});

it('reports a single page when there are fewer albums than the page size', function () {
    $user = User::factory()->create();

    Album::factory()->count(5)->create(['user_id' => $user->id]);

    $albums = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->instance()->albums;

    expect($albums->lastPage())->toBe(1);
});

it('resets album pagination when search changes', function () {
    $user = User::factory()->create();

    Album::factory()->count(25)->create(['user_id' => $user->id]);
    Album::factory()->count(3)->create(['user_id' => $user->id, 'title' => 'Unique Search Term Album']);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->call('nextPage', 'albums')
        ->set('search', 'Unique Search Term');

    expect($component->instance()->albums)->toHaveCount(3)
        ->and($component->instance()->albums->currentPage())->toBe(1);
});

it('renders an album card for each album on the page', function () {
    $user = User::factory()->create();

    Album::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Album',
        'artist' => 'Test Artist',
    ]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('activeTab', 'albums')
        ->assertSee('Test Album')
        ->assertSee('Test Artist');
});
