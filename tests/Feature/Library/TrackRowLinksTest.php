<?php

use App\Models\Album;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

it('links the album cell to the album page', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id, 'title' => 'Older']);
    $track = Track::factory()->for($user)->create(['album_id' => $album->id, 'artist' => 'George Michael']);
    $this->actingAs($user);

    $html = Blade::render('<x-ui.track-row :track="$track" :index="0" ids="[]" />', ['track' => $track]);

    expect($html)
        ->toContain('href="'.route('library.album.show', $album).'"')
        ->toContain('>Older</a>');
});

it('renders a plain dash when the track has no album', function () {
    $user = User::factory()->create();
    $track = Track::factory()->for($user)->create(['album_id' => null]);
    $this->actingAs($user);

    $html = Blade::render('<x-ui.track-row :track="$track" :index="0" ids="[]" />', ['track' => $track]);

    expect($html)->not->toContain('/library/albums/');
});

it('folds artist, album and duration into a second line for phones', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id, 'title' => 'Older']);
    $track = Track::factory()->for($user)->create(['album_id' => $album->id, 'artist' => 'George Michael', 'duration' => 323]);
    $this->actingAs($user);

    $html = Blade::render('<x-ui.track-row :track="$track" :index="0" ids="[]" />', ['track' => $track]);

    expect($html)->toMatch('#data-test="track-row-subline">\s*George Michael · Older · 5:23\s*</span>#');
});
