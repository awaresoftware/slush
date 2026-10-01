<?php

use App\Livewire\UploadModal;
use App\Models\Album;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('component renders with showModal false', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(UploadModal::class)
        ->assertOk()
        ->assertSet('showModal', false);
});

test('open-upload-modal event sets showModal to true', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(UploadModal::class)
        ->dispatch('open-upload-modal')
        ->assertSet('showModal', true);
});

test('closeModal resets showModal and uploadComplete to false', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(UploadModal::class)
        ->dispatch('open-upload-modal')
        ->assertSet('showModal', true)
        ->call('closeModal')
        ->assertSet('showModal', false)
        ->assertSet('uploadComplete', false);
});

test('save with valid audio files creates track records and sets uploadComplete true', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('song.mp3', 1024, 'audio/mpeg');

    Livewire::actingAs($user)
        ->test(UploadModal::class)
        ->set('audioFiles', [$file])
        ->call('save')
        ->assertSet('uploadComplete', true)
        ->assertNoRedirect();

    expect(Track::where('user_id', $user->id)->count())->toBe(1);
});

test('save with duplicate file hash populates duplicateWarnings', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('song.mp3', 1024, 'audio/mpeg');
    $hash = hash_file('sha256', $file->getRealPath());

    Track::factory()->create([
        'user_id' => $user->id,
        'file_hash' => $hash,
    ]);

    Livewire::actingAs($user)
        ->test(UploadModal::class)
        ->set('audioFiles', [$file])
        ->call('save')
        ->assertSet('uploadComplete', true);

    $component = Livewire::actingAs($user)->test(UploadModal::class);
    $component->set('audioFiles', [$file])->call('save');

    expect($component->get('duplicateWarnings'))->not->toBeEmpty();
});

test('save stores uploaded_track_ids in session', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('track.mp3', 512, 'audio/mpeg');

    $component = Livewire::actingAs($user)
        ->test(UploadModal::class)
        ->set('audioFiles', [$file])
        ->call('save');

    expect(session('uploaded_track_ids'))->not->toBeEmpty();
    expect(session('uploaded_track_ids'))->toBeArray();
});

test('sidebar contains upload trigger that dispatches open-upload-modal', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Upload')
        ->assertSee('open-upload-modal');
});

test('userAlbums computed property returns authenticated users albums', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Album::factory()->create(['user_id' => $user->id, 'title' => 'Zebra']);
    Album::factory()->create(['user_id' => $user->id, 'title' => 'Apple']);
    Album::factory()->create(['user_id' => $other->id, 'title' => 'Other Album']);

    $component = Livewire::actingAs($user)
        ->test(UploadModal::class);

    $albums = $component->instance()->userAlbums;

    expect($albums)->toHaveCount(2);
    expect($albums->first()->title)->toBe('Apple');
    expect($albums->last()->title)->toBe('Zebra');
});
