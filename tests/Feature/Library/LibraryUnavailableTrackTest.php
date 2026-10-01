<?php

use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

it('library page shows dimmed row for unavailable track', function () {
    $user = User::factory()->create();

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Missing Song',
        'is_available' => false,
    ]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->assertSee('opacity-50');
});

it('library page shows Unavailable badge for unavailable track', function () {
    $user = User::factory()->create();

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Missing Song',
        'is_available' => false,
    ]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->assertSee('Unavailable');
});

it('library page hides play button for unavailable track', function () {
    $user = User::factory()->create();

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Missing Song',
        'is_available' => false,
    ]);

    $html = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->html();

    // Should NOT contain the play dispatch for this track
    expect($html)->not->toContain('aria-label="Play Missing Song"');
});

it('library page shows re-link button for unavailable track', function () {
    $user = User::factory()->create();

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Missing Song',
        'is_available' => false,
    ]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->assertSee('open-relink-modal');
});

it('source filter unavailable shows only unavailable tracks', function () {
    $user = User::factory()->create();

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Available Track',
        'is_available' => true,
    ]);

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Unavailable Track',
        'is_available' => false,
    ]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('sourceFilter', 'unavailable')
        ->assertSee('Unavailable Track')
        ->assertDontSee('Available Track');
});

it('library page hides add to playlist button for unavailable track', function () {
    $user = User::factory()->create();

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'Missing Song',
        'is_available' => false,
    ]);

    $html = Livewire::actingAs($user)
        ->test('pages::library.index')
        ->html();

    // The add-to-playlist button should not exist for this track
    // We check by looking at the data-test attribute not being there for unavailable tracks
    // The view shows all tracks so we check that the dispatch with the track id is not present
    // We look for the aria-label specifically for add-to-playlist
    expect($html)->not->toContain('aria-label="Add Missing Song to playlist"');
});
