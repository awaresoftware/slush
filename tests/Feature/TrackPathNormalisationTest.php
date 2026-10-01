<?php

use App\Models\Track;

test('setting file_path with backslashes stores forward slashes', function () {
    $track = Track::factory()->create(['file_path' => 'C:\\Users\\Music\\song.mp3']);

    expect($track->file_path)->toBe('C:/Users/Music/song.mp3');
});

test('setting file_path with forward slashes stores unchanged', function () {
    $track = Track::factory()->create(['file_path' => 'C:/Users/Music/song.mp3']);

    expect($track->file_path)->toBe('C:/Users/Music/song.mp3');
});

test('mixed separators are all normalised to forward slashes', function () {
    $track = Track::factory()->create(['file_path' => 'C:\\Users/Music\\song.mp3']);

    expect($track->file_path)->toBe('C:/Users/Music/song.mp3');
});
