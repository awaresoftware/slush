<?php

use App\Enums\FolderStatus;
use App\Models\LinkedFolder;
use App\Models\Track;
use App\Models\User;

test('LinkedFolder factory creates a valid record', function () {
    $folder = LinkedFolder::factory()->create();

    expect($folder->id)->not->toBeNull()
        ->and($folder->path)->not->toBeEmpty()
        ->and($folder->name)->not->toBeEmpty()
        ->and($folder->status)->toBe(FolderStatus::Active);
});

test('LinkedFolder belongs to a user', function () {
    $user = User::factory()->create();
    $folder = LinkedFolder::factory()->create(['user_id' => $user->id]);

    expect($folder->user)->toBeInstanceOf(User::class)
        ->and($folder->user->id)->toBe($user->id);
});

test('LinkedFolder has many tracks', function () {
    $folder = LinkedFolder::factory()->create();
    Track::factory()->count(3)->create([
        'user_id' => $folder->user_id,
        'linked_folder_id' => $folder->id,
        'source_type' => 'local',
    ]);

    expect($folder->tracks)->toHaveCount(3);
});

test('deleting a linked folder cascade-deletes its tracks', function () {
    $folder = LinkedFolder::factory()->create();
    Track::factory()->count(2)->create([
        'user_id' => $folder->user_id,
        'linked_folder_id' => $folder->id,
        'source_type' => 'local',
    ]);

    $folder->delete();

    expect(Track::query()->where('linked_folder_id', $folder->id)->count())->toBe(0);
});
