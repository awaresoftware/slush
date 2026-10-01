<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('profile.edit'))->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('bio can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('bio', 'Hello world')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()->bio)->toBe('Hello world');
});

test('bio longer than 160 chars triggers validation error', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('bio', str_repeat('a', 161))
        ->call('updateProfileInformation')
        ->assertHasErrors(['bio']);
});

test('avatar upload stores resized image and updates avatar path', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    $file = UploadedFile::fake()->image('avatar.jpg', 800, 800);

    Livewire::test('pages::settings.profile')
        ->set('avatar', $file)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->avatar_path)->toBe('avatars/' . $user->id . '.webp');
    Storage::disk('public')->assertExists('avatars/' . $user->id . '.webp');
});

test('uploading a new avatar deletes the old avatar file', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => 'avatars/old.webp']);
    Storage::disk('public')->put('avatars/old.webp', 'fake-content');

    $this->actingAs($user);

    $file = UploadedFile::fake()->image('new_avatar.jpg', 800, 800);

    Livewire::test('pages::settings.profile')
        ->set('avatar', $file)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    Storage::disk('public')->assertMissing('avatars/old.webp');
    Storage::disk('public')->assertExists('avatars/' . $user->id . '.webp');
});

test('remove avatar deletes file and clears avatar path', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => 'avatars/' . 1 . '.webp']);
    Storage::disk('public')->put('avatars/' . $user->id . '.webp', 'fake-content');

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->call('removeAvatar')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing('avatars/' . $user->id . '.webp');
});

test('avatar file over 2mb triggers validation error', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    // Create a fake file over 2MB (2049 KB)
    $file = UploadedFile::fake()->image('large.jpg')->size(2049);

    Livewire::test('pages::settings.profile')
        ->set('avatar', $file)
        ->call('updateProfileInformation')
        ->assertHasErrors(['avatar']);
});

test('non-image file upload triggers validation error', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    Livewire::test('pages::settings.profile')
        ->set('avatar', $file)
        ->call('updateProfileInformation')
        ->assertHasErrors(['avatar']);
});

test('resending verification for an already verified user navigates via wire:navigate', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->call('resendVerificationNotification');

    $response->assertRedirect(route('dashboard'));

    expect($response->effects['redirectUsingNavigate'] ?? false)->toBeTrue();
});
