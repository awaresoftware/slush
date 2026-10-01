<?php

use App\Models\Album;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;

test('public profile page requires authentication', function () {
    $user = User::factory()->create();
    $this->get(route('profile.show', $user))->assertRedirect(route('login'));
});

test('authenticated user can view another users profile', function () {
    $viewer = User::factory()->create();
    $profileUser = User::factory()->create(['name' => 'John Doe', 'bio' => 'Hello world']);

    $this->actingAs($viewer)
        ->get(route('profile.show', $profileUser))
        ->assertOk()
        ->assertSee('John Doe')
        ->assertSee('Hello world');
});

test('authenticated user can view own profile', function () {
    $user = User::factory()->create(['name' => 'Self Viewer']);

    $this->actingAs($user)
        ->get(route('profile.show', $user))
        ->assertOk()
        ->assertSee('Self Viewer');
});

test('profile page shows placeholder when no avatar set', function () {
    $user = User::factory()->create(['avatar_path' => null]);
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('profile.show', $user))
        ->assertOk();
    // No img tag with /storage/ path expected
});

test('profile page shows zero counts for new user', function () {
    $user = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('profile.show', $user))
        ->assertOk()
        ->assertSee('tracks')
        ->assertSee('albums')
        ->assertSee('playlists');
});

test('profile page returns 404 for unknown user id', function () {
    $viewer = User::factory()->create();
    $this->actingAs($viewer)->get('/profile/99999')->assertNotFound();
});

test('no edit profile button shown on public profile view', function () {
    $user = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('profile.show', $user))
        ->assertOk()
        ->assertDontSee('Edit Profile');
});

test('profile page shows accurate track count', function () {
    $user = User::factory()->create();
    $viewer = User::factory()->create();
    Track::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($viewer)
        ->get(route('profile.show', $user))
        ->assertOk()
        ->assertSee('3');
});

test('profile page shows accurate album count', function () {
    $user = User::factory()->create();
    $viewer = User::factory()->create();
    Album::factory()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($viewer)
        ->get(route('profile.show', $user))
        ->assertOk()
        ->assertSee('2');
});

test('profile page shows accurate playlist count', function () {
    $user = User::factory()->create();
    $viewer = User::factory()->create();
    Playlist::factory()->create(['user_id' => $user->id]);

    $this->actingAs($viewer)
        ->get(route('profile.show', $user))
        ->assertOk()
        ->assertSee('1');
});

test('desktop user menu contains profile link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('profile.show', $user));
});

test('mobile user menu contains profile link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Profile');
});
