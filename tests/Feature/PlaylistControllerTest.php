<?php

use App\Models\Playlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('playlists.index'))->assertRedirect(route('login'));
});

test('authenticated user can see their playlists list', function () {
    $user = User::factory()->create();
    Playlist::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('playlists.index'))
        ->assertSuccessful();
});

test('authenticated user can create a playlist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('playlists.store'), [
            'name' => 'My Playlist',
            'description' => 'A great playlist',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('playlists', [
        'user_id' => $user->id,
        'name' => 'My Playlist',
        'description' => 'A great playlist',
    ]);
});

test('playlist name is required on store', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('playlists.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('playlist name cannot exceed 255 characters on store', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('playlists.store'), ['name' => str_repeat('a', 256)])
        ->assertSessionHasErrors('name');
});

test('authenticated user can view their own playlist', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('playlists.show', $playlist))
        ->assertSuccessful();
});

test('user cannot view another user playlist', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->get(route('playlists.show', $playlist))
        ->assertForbidden();
});

test('authenticated user can update their own playlist', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('playlists.update', $playlist), [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ])
        ->assertRedirect(route('playlists.show', $playlist));

    expect($playlist->fresh()->name)->toBe('Updated Name');
});

test('user cannot update another user playlist', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->put(route('playlists.update', $playlist), ['name' => 'Hacked'])
        ->assertForbidden();
});

test('authenticated user can delete their own playlist', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('playlists.destroy', $playlist))
        ->assertRedirect(route('playlists.index'));

    $this->assertDatabaseMissing('playlists', ['id' => $playlist->id]);
});

test('user cannot delete another user playlist', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->delete(route('playlists.destroy', $playlist))
        ->assertForbidden();
});
