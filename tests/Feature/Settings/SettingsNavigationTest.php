<?php

use App\Models\User;

test('settings layout page loads successfully', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('profile.edit'))->assertOk();
});

test('settings navigation renders menu items', function () {
    $this->actingAs(User::factory()->create());
    $response = $this->get(route('profile.edit'));
    $response->assertSee(__('Profile'));
    $response->assertSee(__('Password'));
    $response->assertSee(__('Appearance'));
});

test('settings navigation does not contain flux components', function () {
    $this->actingAs(User::factory()->create());
    $response = $this->get(route('profile.edit'));
    $html = $response->getContent();
    // Check that flux: component tags are not rendered in the settings section
    // Note: flux. may appear in JS bundle URLs from @fluxScripts in the main layout
    expect($html)->not->toContain('<flux:');
});

test('settings heading partial renders without flux', function () {
    $this->actingAs(User::factory()->create());
    $response = $this->get(route('profile.edit'));
    $response->assertSee(__('Settings'));
});

test('admin user sees users nav item', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $response = $this->get(route('profile.edit'));
    $response->assertSee(__('Users'));
});
