<?php

use App\Jobs\ExtractAudioMetadata;
use App\Models\Album;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// ── ExtractAudioMetadata job ─────────────────────────────────────────────────

it('extract audio metadata job handles missing file gracefully', function () {
    $track = Track::factory()->create([
        'file_path' => 'music/1/nonexistent.mp3',
        'title' => 'Original Title',
    ]);

    Storage::fake('public');

    // Should not throw, should leave title as-is
    (new ExtractAudioMetadata($track))->handle();

    expect($track->fresh()->title)->toBe('Original Title');
});

it('extract audio metadata job implements ShouldQueue', function () {
    expect(ExtractAudioMetadata::class)
        ->toImplement(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('extract audio metadata job accepts a track in constructor', function () {
    $track = Track::factory()->create();

    $job = new ExtractAudioMetadata($track);

    expect($job->track->id)->toBe($track->id);
});

// ── Library index page ───────────────────────────────────────────────────────

it('unauthenticated user is redirected to login when accessing library', function () {
    $this->get(route('library.index'))->assertRedirect(route('login'));
});

it('authenticated user can access library page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertOk();
});

it('library page renders an upload button', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertSee('Upload');
});

// ── Upload action ────────────────────────────────────────────────────────────

it('upload action creates track records from uploaded files', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('my-song.mp3', 100, 'audio/mpeg');

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('audioFiles', [$file])
        ->call('save');

    expect(Track::where('user_id', $user->id)->count())->toBe(1);

    $track = Track::where('user_id', $user->id)->first();
    expect($track->title)->toBe('my-song');
    expect($track->file_hash)->not->toBeNull();
    expect($track->file_path)->toContain("music/{$user->id}");
});

it('upload action dispatches ExtractAudioMetadata job for each uploaded file', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $file1 = UploadedFile::fake()->create('track-one.mp3', 100, 'audio/mpeg');
    $file2 = UploadedFile::fake()->create('track-two.mp3', 100, 'audio/mpeg');

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('audioFiles', [$file1, $file2])
        ->call('save');

    Queue::assertPushed(ExtractAudioMetadata::class, 2);
});

it('upload action redirects to upload summary route after processing', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('song.mp3', 100, 'audio/mpeg');

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('audioFiles', [$file])
        ->call('save')
        ->assertRedirect(route('library.upload.summary'));
});

it('upload action rejects files with invalid mime types', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('audioFiles', [$file])
        ->call('save')
        ->assertHasErrors(['audioFiles.*']);
});

it('upload action adds duplicate warning when file hash already exists for user', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $file = UploadedFile::fake()->create('song.mp3', 100, 'audio/mpeg');
    $hash = hash_file('sha256', $file->getRealPath());

    Track::factory()->create([
        'user_id' => $user->id,
        'file_hash' => $hash,
    ]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('audioFiles', [$file])
        ->call('save')
        ->assertSet('duplicateWarnings', ['song.mp3'])
        ->assertSessionHas('duplicate_warnings', ['song.mp3']);
});

it('upload action does not add duplicate warning for different user with same hash', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $other = User::factory()->create();

    $file = UploadedFile::fake()->create('song.mp3', 100, 'audio/mpeg');
    $hash = hash_file('sha256', $file->getRealPath());

    Track::factory()->create([
        'user_id' => $other->id,
        'file_hash' => $hash,
    ]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('audioFiles', [$file])
        ->call('save')
        ->assertSet('duplicateWarnings', []);
});

it('upload action passes selected album id to created track', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);
    $file = UploadedFile::fake()->create('song.mp3', 100, 'audio/mpeg');

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('selectedAlbumId', $album->id)
        ->set('audioFiles', [$file])
        ->call('save');

    $track = Track::where('user_id', $user->id)->first();
    expect($track->album_id)->toBe($album->id);
});

// ── Routes ───────────────────────────────────────────────────────────────────

it('library routes are registered correctly', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->getName());

    expect($routes)->toContain('library.index')
        ->toContain('library.upload.summary')
        ->toContain('library.album.show');
});
