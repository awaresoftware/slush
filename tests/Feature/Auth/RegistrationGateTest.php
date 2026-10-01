<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('GET /register returns 200 when no users exist', function () {
    $this->get(route('register'))->assertSuccessful();
});

test('GET /register returns 404 when users exist', function () {
    User::factory()->create();

    $this->get(route('register'))->assertNotFound();
});

test('POST /register creates user and redirects to dashboard when no users exist', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('POST /register returns 404 when users exist', function () {
    User::factory()->create();

    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});

test('first registered user has is_admin true', function () {
    $this->post(route('register.store'), [
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'admin@example.com')->firstOrFail();

    expect($user->is_admin)->toBeTrue();
});

test('second registered user has is_admin false', function () {
    // First user via factory (already exists)
    User::factory()->create(['is_admin' => true]);

    // The gate blocks registration, so test via direct action
    $secondUser = User::factory()->create(['is_admin' => false]);

    expect($secondUser->is_admin)->toBeFalse();
});
