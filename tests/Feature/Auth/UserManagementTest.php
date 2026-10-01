<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('admin can access /settings/users', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)->get('/settings/users');

    $response->assertOk();
});

test('non-admin cannot access /settings/users', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)->get('/settings/users');

    $response->assertForbidden();
});

test('guest is redirected from /settings/users', function () {
    $response = $this->get('/settings/users');

    $response->assertRedirect('/login');
});

test('admin can create a new user', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('pages::settings.users')
        ->set('name', 'Jane Smith')
        ->set('email', 'newuser@example.com')
        ->set('password', 'SecurePassword123!')
        ->set('password_confirmation', 'SecurePassword123!')
        ->call('createUser')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('users', [
        'email' => 'newuser@example.com',
        'force_password_change' => true,
    ]);
});

test('admin create user fails with invalid email', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('pages::settings.users')
        ->set('name', 'Jane Smith')
        ->set('email', 'not-an-email')
        ->set('password', 'SecurePassword123!')
        ->set('password_confirmation', 'SecurePassword123!')
        ->call('createUser')
        ->assertHasErrors(['email']);
});

test('admin cannot create user with duplicate email', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['email' => 'existing@example.com']);

    Livewire::actingAs($admin)
        ->test('pages::settings.users')
        ->set('name', 'Jane Smith')
        ->set('email', 'existing@example.com')
        ->set('password', 'SecurePassword123!')
        ->set('password_confirmation', 'SecurePassword123!')
        ->call('createUser')
        ->assertHasErrors(['email']);
});

test('admin create user fails without password confirmation', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('pages::settings.users')
        ->set('name', 'Jane Smith')
        ->set('email', 'newuser@example.com')
        ->set('password', 'SecurePassword123!')
        ->set('password_confirmation', 'WrongPassword123!')
        ->call('createUser')
        ->assertHasErrors(['password']);
});

test('admin can delete a non-admin user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);

    Livewire::actingAs($admin)
        ->test('pages::settings.users')
        ->call('deleteUser', $user->id);

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('admin cannot delete themselves', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('pages::settings.users')
        ->call('deleteUser', $admin->id);

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('admin can trigger password reset for a user', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);

    Livewire::actingAs($admin)
        ->test('pages::settings.users')
        ->call('resetPassword', $user->id);

    Notification::assertSentTo($user, ResetPassword::class);
});
