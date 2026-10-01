<?php

use App\Models\User;

test('a newly created user has is_admin false by default', function () {
    $user = User::factory()->create();

    expect($user->is_admin)->toBeFalse();
});

test('a user created with is_admin true has the cast working as PHP bool', function () {
    $user = User::factory()->create(['is_admin' => true]);

    expect($user->is_admin)->toBeTrue();
    expect($user->is_admin)->toBeBool();
});
