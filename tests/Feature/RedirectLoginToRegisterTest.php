<?php

use App\Models\User;

test('login redirects to register when no users exist', function () {
    $this->assertDatabaseCount('users', 0);

    $this->get(route('login'))
        ->assertRedirect(route('register'));
});

test('login page is accessible when users exist', function () {
    User::factory()->create();

    $this->get(route('login'))
        ->assertOk();
});

test('register page is accessible when no users exist', function () {
    $this->assertDatabaseCount('users', 0);

    $this->get(route('register'))
        ->assertOk();
});

test('register page returns 404 when users exist', function () {
    User::factory()->create();

    $this->get(route('register'))
        ->assertNotFound();
});
