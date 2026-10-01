<?php

use App\Models\User;

test('users settings page loads for admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $this->get(route('settings.users'))->assertOk();
});

test('users settings page does not contain flux components', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $response = $this->get(route('settings.users'));
    $html = $response->getContent();
    expect($html)->not->toContain('flux:');
});

test('users settings page displays existing users', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $otherUser = User::factory()->create();
    $this->actingAs($admin);

    $response = $this->get(route('settings.users'));
    $response->assertSee($otherUser->name);
    $response->assertSee($otherUser->email);
});

test('users settings page shows the create user form', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->get(route('settings.users'));

    // The admin sets a temporary password rather than mailing an invite link.
    $response->assertSee(__('Create User'))
        ->assertSee(__('Confirm Password'));
});
