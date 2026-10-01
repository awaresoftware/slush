<?php

use App\Enums\FolderStatus;
use App\Jobs\ScanLinkedFolder;
use App\Models\Album;
use App\Models\LinkedFolder;
use App\Models\Playlist;
use App\Models\PlaylistTrack;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('linked folders settings page loads for authenticated user', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('settings.linked-folders'))->assertOk();
});

test('unauthenticated user is redirected to login', function () {
    $this->get(route('settings.linked-folders'))->assertRedirect(route('login'));
});

test('linkFolder creates a linked folder and dispatches scan job', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);

    $tmpDir = sys_get_temp_dir().'/rekodo-test-'.uniqid();
    mkdir($tmpDir);

    Livewire::test('pages::settings.linked-folders')
        ->set('path', $tmpDir)
        ->call('linkFolder')
        ->assertHasNoErrors();

    expect(LinkedFolder::where('user_id', $user->id)->count())->toBe(1);
    Queue::assertPushed(ScanLinkedFolder::class);

    rmdir($tmpDir);
});

test('linkFolder with invalid path shows validation error and creates no folder', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.linked-folders')
        ->set('path', '/nonexistent/path/that/does/not/exist')
        ->call('linkFolder')
        ->assertHasErrors(['path']);

    expect(LinkedFolder::where('user_id', $user->id)->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('user can link multiple folders and both appear in rendered output', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder1 = LinkedFolder::factory()->create(['user_id' => $user->id, 'name' => 'Jazz Collection']);
    $folder2 = LinkedFolder::factory()->create(['user_id' => $user->id, 'name' => 'Rock Collection']);

    Livewire::test('pages::settings.linked-folders')
        ->assertSee('Jazz Collection')
        ->assertSee('Rock Collection');
});

test('folder with scanning status shows scanning text', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    LinkedFolder::factory()->create([
        'user_id' => $user->id,
        'name' => 'My Music',
        'status' => FolderStatus::Scanning,
    ]);

    Livewire::test('pages::settings.linked-folders')
        ->assertSee('Scanning');
});

test('rescan dispatches scan job for existing folder', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = LinkedFolder::factory()->create(['user_id' => $user->id]);

    Livewire::test('pages::settings.linked-folders')
        ->call('rescan', $folder->id);

    Queue::assertPushed(ScanLinkedFolder::class, fn ($job) => $job->folder->id === $folder->id);
});

test('confirmRemove and removeFolder deletes folder and shows toast', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = LinkedFolder::factory()->create(['user_id' => $user->id, 'status' => FolderStatus::Active]);

    Livewire::test('pages::settings.linked-folders')
        ->call('confirmRemove', $folder->id)
        ->assertSet('showRemoveModal', true)
        ->call('removeFolder');

    expect(LinkedFolder::find($folder->id))->toBeNull();
});

test('remove is prevented when folder status is scanning', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = LinkedFolder::factory()->create([
        'user_id' => $user->id,
        'status' => FolderStatus::Scanning,
    ]);

    Livewire::test('pages::settings.linked-folders')
        ->call('confirmRemove', $folder->id)
        ->call('removeFolder');

    expect(LinkedFolder::find($folder->id))->not->toBeNull();
});

test('removal modal shows track count and affected playlist count', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = LinkedFolder::factory()->create(['user_id' => $user->id, 'status' => FolderStatus::Active]);
    $track = Track::factory()->create(['user_id' => $user->id, 'linked_folder_id' => $folder->id]);
    $playlist = Playlist::factory()->create(['user_id' => $user->id]);
    PlaylistTrack::factory()->create(['playlist_id' => $playlist->id, 'track_id' => $track->id]);

    Livewire::test('pages::settings.linked-folders')
        ->call('confirmRemove', $folder->id)
        ->assertSet('showRemoveModal', true)
        ->assertSeeHtml('1');
});

test('bulk path update replaces prefix on all tracks in folder', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);

    $oldDir = sys_get_temp_dir().'/rekodo-old-'.uniqid();
    $newDir = sys_get_temp_dir().'/rekodo-new-'.uniqid();
    mkdir($oldDir);
    mkdir($newDir);

    $folder = LinkedFolder::factory()->create([
        'user_id' => $user->id,
        'path' => $oldDir,
        'status' => FolderStatus::Active,
    ]);

    $tmpFile = $newDir.'/song.mp3';
    file_put_contents($tmpFile, 'audio');

    Track::factory()->create([
        'user_id' => $user->id,
        'linked_folder_id' => $folder->id,
        'file_path' => $oldDir.'/song.mp3',
    ]);

    Livewire::test('pages::settings.linked-folders')
        ->call('openUpdatePathModal', $folder->id)
        ->assertSet('showUpdatePathModal', true)
        ->set('newFolderPath', $newDir)
        ->call('updateFolderPath')
        ->assertHasNoErrors();

    expect($folder->fresh()->path)->toBe(str_replace('\\', '/', $newDir));

    rmdir($oldDir);
    unlink($tmpFile);
    rmdir($newDir);
});

test('bulk path update sets is_available based on file_exists at new path', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);

    $oldDir = sys_get_temp_dir().'/rekodo-old-'.uniqid();
    $newDir = sys_get_temp_dir().'/rekodo-new-'.uniqid();
    mkdir($oldDir);
    mkdir($newDir);

    $folder = LinkedFolder::factory()->create([
        'user_id' => $user->id,
        'path' => $oldDir,
        'status' => FolderStatus::Active,
    ]);

    $tmpFile = $newDir.'/present.mp3';
    file_put_contents($tmpFile, 'audio');

    $presentTrack = Track::factory()->create([
        'user_id' => $user->id,
        'linked_folder_id' => $folder->id,
        'file_path' => $oldDir.'/present.mp3',
        'is_available' => false,
    ]);

    $missingTrack = Track::factory()->create([
        'user_id' => $user->id,
        'linked_folder_id' => $folder->id,
        'file_path' => $oldDir.'/missing.mp3',
        'is_available' => true,
    ]);

    Livewire::test('pages::settings.linked-folders')
        ->call('openUpdatePathModal', $folder->id)
        ->set('newFolderPath', $newDir)
        ->call('updateFolderPath');

    expect($presentTrack->fresh()->is_available)->toBeTrue()
        ->and($missingTrack->fresh()->is_available)->toBeFalse();

    rmdir($oldDir);
    unlink($tmpFile);
    rmdir($newDir);
});

test('bulk path update with invalid directory shows error', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $oldDir = sys_get_temp_dir().'/rekodo-old-'.uniqid();
    mkdir($oldDir);

    $folder = LinkedFolder::factory()->create([
        'user_id' => $user->id,
        'path' => $oldDir,
        'status' => FolderStatus::Active,
    ]);

    Livewire::test('pages::settings.linked-folders')
        ->call('openUpdatePathModal', $folder->id)
        ->set('newFolderPath', '/nonexistent/directory/path')
        ->call('updateFolderPath')
        ->assertHasErrors(['newFolderPath']);

    rmdir($oldDir);
});

test('bulk path update updates the folder own path', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);

    $oldDir = sys_get_temp_dir().'/rekodo-old-'.uniqid();
    $newDir = sys_get_temp_dir().'/rekodo-new-'.uniqid();
    mkdir($oldDir);
    mkdir($newDir);

    $folder = LinkedFolder::factory()->create([
        'user_id' => $user->id,
        'path' => $oldDir,
        'status' => FolderStatus::Active,
    ]);

    Livewire::test('pages::settings.linked-folders')
        ->call('openUpdatePathModal', $folder->id)
        ->set('newFolderPath', $newDir)
        ->call('updateFolderPath')
        ->assertHasNoErrors();

    expect($folder->fresh()->path)->toBe(str_replace('\\', '/', $newDir));

    rmdir($oldDir);
    rmdir($newDir);
});

test('unavailable_count is displayed in folder list', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    LinkedFolder::factory()->create([
        'user_id' => $user->id,
        'name' => 'My Music',
        'status' => FolderStatus::Active,
        'unavailable_count' => 3,
    ]);

    Livewire::test('pages::settings.linked-folders')
        ->assertSee('3 unavailable');
});

test('removing folder deletes orphaned empty albums', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = LinkedFolder::factory()->create(['user_id' => $user->id, 'status' => FolderStatus::Active]);
    $album = Album::factory()->create(['user_id' => $user->id]);
    Track::factory()->create([
        'user_id' => $user->id,
        'linked_folder_id' => $folder->id,
        'album_id' => $album->id,
        'source_type' => 'local',
    ]);

    Livewire::test('pages::settings.linked-folders')
        ->call('confirmRemove', $folder->id)
        ->call('removeFolder');

    expect(Album::find($album->id))->toBeNull();
});

test('removing folder preserves albums that still have tracks from other sources', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = LinkedFolder::factory()->create(['user_id' => $user->id, 'status' => FolderStatus::Active]);
    $album = Album::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id' => $user->id,
        'linked_folder_id' => $folder->id,
        'album_id' => $album->id,
        'source_type' => 'local',
    ]);

    // Uploaded track in same album — album should survive
    Track::factory()->create([
        'user_id' => $user->id,
        'linked_folder_id' => null,
        'album_id' => $album->id,
        'source_type' => 'uploaded',
    ]);

    Livewire::test('pages::settings.linked-folders')
        ->call('confirmRemove', $folder->id)
        ->call('removeFolder');

    expect(Album::find($album->id))->not->toBeNull();
});

test('scanning folder shows progress when total_count is set', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    LinkedFolder::factory()->create([
        'user_id' => $user->id,
        'name' => 'My Music',
        'status' => FolderStatus::Scanning,
        'scanned_count' => 2000,
        'total_count' => 6000,
    ]);

    Livewire::test('pages::settings.linked-folders')
        ->assertSee('2,000')
        ->assertSee('6,000');
});

test('rescan completion toast includes unavailable count', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = LinkedFolder::factory()->create([
        'user_id' => $user->id,
        'name' => 'My Music',
        'status' => FolderStatus::Active,
        'track_count' => 10,
        'unavailable_count' => 2,
    ]);

    // Simulate poll detecting folder transitioned from scanning to active by having
    // a previously tracked scanning folder ID in scanningFolderIds
    $component = Livewire::test('pages::settings.linked-folders')
        ->set('scanningFolderIds', [$folder->id])
        ->call('pollFolders');

    $jsEffects = $component->effects['xjs'] ?? [];
    $hasToast = collect($jsEffects)->contains(fn ($item) => str_starts_with($item['expression'] ?? '', 'toast('));

    expect($hasToast)->toBeTrue();
});
