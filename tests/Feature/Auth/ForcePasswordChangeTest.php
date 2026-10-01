<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('user with force_password_change is redirected to password page', function () {
    $user = User::factory()->forcePasswordChange()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('user-password.edit'));
});

test('user with force_password_change can access password page', function () {
    $user = User::factory()->forcePasswordChange()->create();

    $response = $this->actingAs($user)->get('/settings/password');

    $response->assertOk();
});

test('user with force_password_change can access logout', function () {
    $user = User::factory()->forcePasswordChange()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/');
});

test('user without force_password_change accesses dashboard normally', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
});

test('changing password clears force_password_change flag', function () {
    $user = User::factory()->forcePasswordChange()->create([
        'password' => Hash::make('OldPassword123!'),
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.password')
        ->set('current_password', 'OldPassword123!')
        ->set('password', 'NewSecurePassword123!')
        ->set('password_confirmation', 'NewSecurePassword123!')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect($user->fresh()->force_password_change)->toBeFalse();
});

test('user with force_password_change is redirected from settings pages', function () {
    $user = User::factory()->forcePasswordChange()->create();

    $response = $this->actingAs($user)->get('/settings/profile');

    $response->assertRedirect(route('user-password.edit'));
});
