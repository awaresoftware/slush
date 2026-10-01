<?php

use App\Jobs\ExtractAudioMetadata;
use App\Livewire\AudioPlayer;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('stores lyrics from a .lrc sidecar next to the audio file', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $track = Track::factory()->create([
        'user_id' => $user->id,
        'file_path' => 'music/'.$user->id.'/song.wav',
    ]);

    Storage::disk('public')->put('music/'.$user->id.'/song.wav', 'RIFF-not-really-audio');
    Storage::disk('public')->put('music/'.$user->id.'/song.lrc', "[00:01.00]Hello there\n[00:03.50]Second line");

    (new ExtractAudioMetadata($track))->handle();

    expect($track->fresh()->lyrics)
        ->toContain('[00:01.00]Hello there')
        ->toContain('Second line');
});

it('leaves lyrics empty when there is no sidecar and no embedded tag', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $track = Track::factory()->create([
        'user_id' => $user->id,
        'file_path' => 'music/'.$user->id.'/plain.wav',
    ]);

    Storage::disk('public')->put('music/'.$user->id.'/plain.wav', 'RIFF-not-really-audio');

    (new ExtractAudioMetadata($track))->handle();

    expect($track->fresh()->lyrics)->toBeNull();
});

it('caps sidecar lyrics at 20k characters', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $track = Track::factory()->create([
        'user_id' => $user->id,
        'file_path' => 'music/'.$user->id.'/long.wav',
    ]);

    Storage::disk('public')->put('music/'.$user->id.'/long.wav', 'RIFF-not-really-audio');
    Storage::disk('public')->put('music/'.$user->id.'/long.lrc', str_repeat('la', 30000));

    (new ExtractAudioMetadata($track))->handle();

    expect(mb_strlen($track->fresh()->lyrics))->toBe(20000);
});

it('player:load carries the lyrics', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create([
        'user_id' => $user->id,
        'lyrics' => '[00:01.00]First line',
    ]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadTrack', $track->id)
        ->assertDispatched('player:load', fn ($event, $params) => str_contains($params['lyrics'], 'First line'));
});
