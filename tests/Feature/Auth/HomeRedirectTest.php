<?php

use App\Models\User;

test('redirects to register when no users exist', function () {
    $this->get('/')->assertRedirect(route('register'));
});

test('redirects to login when users exist and unauthenticated', function () {
    User::factory()->create();

    $this->get('/')->assertRedirect(route('login'));
});

test('redirects to dashboard when authenticated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
});
