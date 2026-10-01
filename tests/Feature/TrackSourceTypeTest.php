<?php

use App\Enums\SourceType;
use App\Models\LinkedFolder;
use App\Models\Track;
use App\Models\User;

test('track factory creates track with uploaded source_type by default', function () {
    $track = Track::factory()->create();

    expect($track->source_type)->toBe(SourceType::Uploaded);
});

test('source_type attribute returns SourceType enum instance', function () {
    $track = Track::factory()->create(['source_type' => 'uploaded']);

    expect($track->source_type)->toBeInstanceOf(SourceType::class);
});

test('scopeUploaded returns only uploaded tracks', function () {
    $user = User::factory()->create();
    Track::factory()->count(2)->create(['user_id' => $user->id, 'source_type' => 'uploaded']);
    Track::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'local',
        'linked_folder_id' => LinkedFolder::factory()->create(['user_id' => $user->id]),
    ]);

    $uploaded = Track::query()->uploaded()->get();

    expect($uploaded)->toHaveCount(2);
    $uploaded->each(fn ($track) => expect($track->source_type)->toBe(SourceType::Uploaded));
});

test('scopeLocal returns only local tracks', function () {
    $user = User::factory()->create();
    Track::factory()->count(2)->create(['user_id' => $user->id, 'source_type' => 'uploaded']);
    Track::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'local',
        'linked_folder_id' => LinkedFolder::factory()->create(['user_id' => $user->id]),
    ]);

    $local = Track::query()->local()->get();

    expect($local)->toHaveCount(1);
    $local->each(fn ($track) => expect($track->source_type)->toBe(SourceType::Local));
});

test('audioUrl returns stream route URL for uploaded track', function () {
    $track = Track::factory()->create(['source_type' => 'uploaded', 'file_path' => 'music/1/song.mp3']);

    $url = $track->audioUrl();

    expect($url)->toBe(route('stream.track', $track));
});

test('audioUrl returns stream route URL for local track', function () {
    $folder = \App\Models\LinkedFolder::factory()->create();
    $track = Track::factory()->create([
        'user_id' => $folder->user_id,
        'source_type' => 'local',
        'linked_folder_id' => $folder->id,
        'file_path' => '/music/song.mp3',
    ]);

    $url = $track->audioUrl();

    expect($url)->toBe(route('stream.track', $track));
});
