<?php

use App\Livewire\RelinkTrackModal;
use App\Models\LinkedFolder;
use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

test('opening modal loads track old path', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tmpDir = sys_get_temp_dir().'/rekodo-folder-'.uniqid();
    mkdir($tmpDir);
    $tmpFile = $tmpDir.'/song.mp3';
    file_put_contents($tmpFile, 'audio content');

    LinkedFolder::factory()->create(['user_id' => $user->id, 'path' => $tmpDir]);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'file_path' => $tmpFile,
        'file_hash' => md5_file($tmpFile),
        'is_available' => false,
    ]);

    Livewire::test(RelinkTrackModal::class)
        ->dispatch('open-relink-modal', trackId: $track->id)
        ->assertSet('oldPath', str_replace('\\', '/', $tmpFile))
        ->assertSet('showModal', true);

    unlink($tmpFile);
    rmdir($tmpDir);
});

test('relink with valid existing file path updates track and sets is_available true', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tmpDir = sys_get_temp_dir().'/rekodo-folder-'.uniqid();
    mkdir($tmpDir);
    $tmpFile = $tmpDir.'/song.mp3';
    file_put_contents($tmpFile, 'audio content unique '.uniqid());

    $folder = LinkedFolder::factory()->create(['user_id' => $user->id, 'path' => $tmpDir]);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'linked_folder_id' => $folder->id,
        'file_path' => '/old/path/song.mp3',
        'file_hash' => md5_file($tmpFile),
        'is_available' => false,
    ]);

    Livewire::test(RelinkTrackModal::class)
        ->dispatch('open-relink-modal', trackId: $track->id)
        ->set('newPath', $tmpFile)
        ->call('relink')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    expect($track->fresh()->file_path)->toBe(str_replace('\\', '/', $tmpFile))
        ->and($track->fresh()->is_available)->toBeTrue()
        ->and($track->fresh()->linked_folder_id)->toBe($folder->id);

    unlink($tmpFile);
    rmdir($tmpDir);
});

test('relink with nonexistent path shows validation error', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'file_path' => '/old/path/song.mp3',
        'is_available' => false,
    ]);

    Livewire::test(RelinkTrackModal::class)
        ->dispatch('open-relink-modal', trackId: $track->id)
        ->set('newPath', '/nonexistent/path/song.mp3')
        ->call('relink')
        ->assertHasErrors(['newPath']);
});

test('relink with different hash shows mismatch warning', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tmpDir = sys_get_temp_dir().'/rekodo-folder-'.uniqid();
    mkdir($tmpDir);
    $tmpFile = $tmpDir.'/song.mp3';
    file_put_contents($tmpFile, 'different audio content '.uniqid());

    LinkedFolder::factory()->create(['user_id' => $user->id, 'path' => $tmpDir]);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'file_path' => '/old/path/song.mp3',
        'file_hash' => 'original_hash_that_differs',
        'is_available' => false,
    ]);

    Livewire::test(RelinkTrackModal::class)
        ->dispatch('open-relink-modal', trackId: $track->id)
        ->set('newPath', $tmpFile)
        ->call('relink')
        ->assertSet('hashMismatch', true)
        ->assertSet('showModal', true);

    unlink($tmpFile);
    rmdir($tmpDir);
});

test('relink with different hash allows confirm and proceed', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tmpDir = sys_get_temp_dir().'/rekodo-folder-'.uniqid();
    mkdir($tmpDir);
    $tmpFile = $tmpDir.'/song.mp3';
    file_put_contents($tmpFile, 'different audio content '.uniqid());

    $folder = LinkedFolder::factory()->create(['user_id' => $user->id, 'path' => $tmpDir]);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'linked_folder_id' => $folder->id,
        'file_path' => '/old/path/song.mp3',
        'file_hash' => 'original_hash_that_differs',
        'is_available' => false,
    ]);

    Livewire::test(RelinkTrackModal::class)
        ->dispatch('open-relink-modal', trackId: $track->id)
        ->set('newPath', $tmpFile)
        ->call('relink')
        ->assertSet('hashMismatch', true)
        ->call('confirmMismatch')
        ->assertSet('showModal', false);

    expect($track->fresh()->is_available)->toBeTrue();

    unlink($tmpFile);
    rmdir($tmpDir);
});

test('relink is blocked for tracks not owned by current user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $track = Track::factory()->create([
        'user_id' => $otherUser->id,
        'file_path' => '/old/path/song.mp3',
        'is_available' => false,
    ]);

    Livewire::test(RelinkTrackModal::class)
        ->dispatch('open-relink-modal', trackId: $track->id)
        ->assertSet('showModal', false);
});

test('relink to path in different linked folder updates linked_folder_id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folderADir = sys_get_temp_dir().'/rekodo-folder-a-'.uniqid();
    $folderBDir = sys_get_temp_dir().'/rekodo-folder-b-'.uniqid();
    mkdir($folderADir);
    mkdir($folderBDir);

    $newFile = $folderBDir.'/song.mp3';
    file_put_contents($newFile, 'audio content '.uniqid());

    $folderA = LinkedFolder::factory()->create(['user_id' => $user->id, 'path' => $folderADir]);
    $folderB = LinkedFolder::factory()->create(['user_id' => $user->id, 'path' => $folderBDir]);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'linked_folder_id' => $folderA->id,
        'file_path' => $folderADir.'/old.mp3',
        'file_hash' => md5_file($newFile),
        'is_available' => false,
    ]);

    Livewire::test(RelinkTrackModal::class)
        ->dispatch('open-relink-modal', trackId: $track->id)
        ->set('newPath', $newFile)
        ->call('relink')
        ->assertHasNoErrors();

    expect($track->fresh()->linked_folder_id)->toBe($folderB->id)
        ->and($track->fresh()->is_available)->toBeTrue();

    unlink($newFile);
    rmdir($folderADir);
    rmdir($folderBDir);
});

test('relink to path outside all linked folders shows validation error', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folderDir = sys_get_temp_dir().'/rekodo-folder-'.uniqid();
    $outsideDir = sys_get_temp_dir().'/rekodo-outside-'.uniqid();
    mkdir($folderDir);
    mkdir($outsideDir);

    $outsideFile = $outsideDir.'/song.mp3';
    file_put_contents($outsideFile, 'audio content '.uniqid());

    $folder = LinkedFolder::factory()->create(['user_id' => $user->id, 'path' => $folderDir]);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'linked_folder_id' => $folder->id,
        'file_path' => $folderDir.'/old.mp3',
        'file_hash' => md5_file($outsideFile),
        'is_available' => false,
    ]);

    Livewire::test(RelinkTrackModal::class)
        ->dispatch('open-relink-modal', trackId: $track->id)
        ->set('newPath', $outsideFile)
        ->call('relink')
        ->assertHasErrors(['newPath']);

    unlink($outsideFile);
    rmdir($folderDir);
    rmdir($outsideDir);
});
