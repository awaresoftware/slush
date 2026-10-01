<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * Resolve Livewire's POST update endpoint.
 *
 * Deliberately looked up from the router rather than hardcoded: the path
 * carries a build-specific hash prefix (livewire-<hash>/update), which is
 * exactly what made a path-substring check in the middleware silently wrong.
 */
function livewireUpdatePath(): string
{
    return Route::getRoutes()->getByName('default-livewire.update')->uri();
}

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

test('forced password change redirects to the dashboard', function () {
    $user = User::factory()->forcePasswordChange()->create([
        'password' => Hash::make('OldPassword123!'),
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.password')
        ->set('current_password', 'OldPassword123!')
        ->set('password', 'NewSecurePassword123!')
        ->set('password_confirmation', 'NewSecurePassword123!')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertRedirectToRoute('dashboard');

    expect($user->fresh()->force_password_change)->toBeFalse();
});

test('voluntary password change does not redirect', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldPassword123!'),
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.password')
        ->set('current_password', 'OldPassword123!')
        ->set('password', 'NewSecurePassword123!')
        ->set('password_confirmation', 'NewSecurePassword123!')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertNoRedirect();
});

/**
 * The Livewire update request must never be redirected.
 *
 * This is a real HTTP request through the middleware stack, not Livewire's
 * in-process test harness — the harness calls component methods directly and
 * therefore never exercised ForcePasswordChange against the update endpoint.
 * A redirect here answers the client's XHR with HTML, Livewire fails to parse
 * it as JSON, and the action is dropped with no visible error to the user.
 */
test('livewire update requests are not redirected for a forced user', function () {
    $user = User::factory()->forcePasswordChange()->create();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Livewire' => true])
        ->postJson('/'.livewireUpdatePath(), ['components' => []]);

    expect($response->headers->get('Location'))->toBeNull();
    expect($response->status())->not->toBe(302);
});

test('livewire update endpoint path is not matched by a livewire/update substring', function () {
    // Guards the trap itself: if a future Livewire version ever moves the
    // endpoint back to a bare 'livewire/update', this assertion documents that
    // the header check still holds and the substring check would be redundant.
    expect(livewireUpdatePath())->not->toContain('livewire/update');
});
