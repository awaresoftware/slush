<?php

use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

it('favourites filter shows only hearted tracks', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Hearted Song', 'favorited_at' => now()]);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Plain Song']);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('sourceFilter', 'favorites')
        ->assertSee('Hearted Song')
        ->assertDontSee('Plain Song');
});

it('favourites filter shows a tailored empty state', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Plain Song']);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('sourceFilter', 'favorites')
        ->assertSee('No favourites yet');
});

it('sorts by most played', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Aardvark', 'play_count' => 1]);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Zebra', 'play_count' => 9]);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('sort', 'most_played');

    $titles = $component->instance()->tracks->pluck('title')->all();

    expect($titles)->toBe(['Zebra', 'Aardvark']);
});

it('sorts by recently played with never-played tracks last', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Never Played']);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Old Listen', 'last_played_at' => now()->subDays(3)]);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Fresh Listen', 'last_played_at' => now()]);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('sort', 'recently_played');

    $titles = $component->instance()->tracks->pluck('title')->all();

    expect($titles)->toBe(['Fresh Listen', 'Old Listen', 'Never Played']);
});

it('sorts by recently added', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Old Import', 'created_at' => now()->subDay()]);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'New Import', 'created_at' => now()]);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('sort', 'recent');

    $titles = $component->instance()->tracks->pluck('title')->all();

    expect($titles)->toBe(['New Import', 'Old Import']);
});

it('falls back to alphabetical for an unknown sort value', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Beta']);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Alpha']);

    $component = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('sort', 'evil-sort');

    expect($component->get('sort'))->toBe('title');
    expect($component->instance()->tracks->pluck('title')->all())->toBe(['Alpha', 'Beta']);
});

it('renders a heart on track rows', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Some Song']);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->assertSeeHtml('data-test="favorite-btn"');
});
