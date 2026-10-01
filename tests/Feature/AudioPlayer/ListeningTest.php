<?php

use App\Livewire\AudioPlayer;
use App\Models\Track;
use App\Models\TrackPlay;
use App\Models\User;
use Livewire\Livewire;

// ── recordPlay ─────────────────────────────────────────────────────────────────

it('recordPlay writes a play row and bumps the denormalised counters', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id, 'play_count' => 0]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('recordPlay', $track->id);

    $track->refresh();

    expect(TrackPlay::where('track_id', $track->id)->where('user_id', $user->id)->count())->toBe(1)
        ->and($track->play_count)->toBe(1)
        ->and($track->last_played_at)->not->toBeNull();
});

it('recordPlay accumulates across repeat listens', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test(AudioPlayer::class);
    $component->call('recordPlay', $track->id);
    $component->call('recordPlay', $track->id);

    expect($track->fresh()->play_count)->toBe(2)
        ->and(TrackPlay::where('track_id', $track->id)->count())->toBe(2);
});

it('recordPlay ignores tracks that belong to another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($other)
        ->test(AudioPlayer::class)
        ->call('recordPlay', $track->id);

    expect(TrackPlay::count())->toBe(0)
        ->and($track->fresh()->play_count)->toBe(0);
});

// ── toggleFavorite ─────────────────────────────────────────────────────────────

it('toggleFavorite hearts a track and broadcasts the new state', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('toggleFavorite', $track->id)
        ->assertDispatched('favorite-changed', trackId: $track->id, favorited: true);

    expect($track->fresh()->isFavorited())->toBeTrue();
});

it('toggleFavorite un-hearts on the second call', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id, 'favorited_at' => now()]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('toggleFavorite', $track->id)
        ->assertDispatched('favorite-changed', trackId: $track->id, favorited: false);

    expect($track->fresh()->isFavorited())->toBeFalse();
});

it('toggleFavorite ignores tracks that belong to another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($other)
        ->test(AudioPlayer::class)
        ->call('toggleFavorite', $track->id)
        ->assertNotDispatched('favorite-changed');

    expect($track->fresh()->isFavorited())->toBeFalse();
});

// ── payload ────────────────────────────────────────────────────────────────────

it('player:load carries the favorited state', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id, 'favorited_at' => now()]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadTrack', $track->id)
        ->assertDispatched('player:load', fn ($event, $params) => $params['favorited'] === true);
});

it('deleting a track cascades its play history', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id]);
    TrackPlay::factory()->create(['user_id' => $user->id, 'track_id' => $track->id]);

    $track->delete();

    expect(TrackPlay::count())->toBe(0);
});
