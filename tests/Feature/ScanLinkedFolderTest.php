<?php

use App\Enums\FolderStatus;
use App\Enums\SourceType;
use App\Jobs\ExtractAudioMetadata;
use App\Jobs\ScanLinkedFolder;
use App\Models\LinkedFolder;
use App\Models\Track;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/scan_test_'.uniqid();
    mkdir($this->tmpDir, 0755, true);
});

afterEach(function () {
    // Clean up temp directory recursively
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($this->tmpDir);
});

/**
 * Create a file with unique content so each file has a distinct md5 hash.
 */
function createAudioFile(string $path, string $name): void
{
    file_put_contents($path.'/'.$name, 'unique audio content: '.$name.uniqid());
}

test('job allows 3 retry attempts', function () {
    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    $job = new ScanLinkedFolder($folder);
    expect($job->tries)->toBe(3);
});

test('job has 600 second timeout for large folder scans', function () {
    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    $job = new ScanLinkedFolder($folder);
    expect($job->timeout)->toBe(600);
});

test('creates track records for each supported audio format', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    $formats = ['song.mp3', 'song.wav', 'song.flac', 'song.m4a', 'song.aac', 'song.ogg'];

    foreach ($formats as $filename) {
        createAudioFile($this->tmpDir, $filename);
    }

    (new ScanLinkedFolder($folder))->handle();

    expect(Track::where('linked_folder_id', $folder->id)->count())->toBe(6);
});

test('defaults to the documented audio extensions', function () {
    expect(config('rekodo.audio_extensions'))->toBe(['mp3', 'wav', 'flac', 'm4a', 'aac', 'ogg']);
});

test('honours a configured audio extension list', function () {
    Queue::fake();

    config(['rekodo.audio_extensions' => ['mp3']]);

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    createAudioFile($this->tmpDir, 'song.mp3');
    createAudioFile($this->tmpDir, 'other.flac');
    createAudioFile($this->tmpDir, 'song.m4p');

    (new ScanLinkedFolder($folder))->handle();

    $tracks = Track::where('linked_folder_id', $folder->id)->get();

    expect($tracks)->toHaveCount(1);
    expect($tracks->first()->title)->toBe('song');
});

test('ignores non-audio files', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    file_put_contents($this->tmpDir.'/readme.txt', 'text content');
    file_put_contents($this->tmpDir.'/image.jpg', 'image content');
    file_put_contents($this->tmpDir.'/document.pdf', 'pdf content');
    createAudioFile($this->tmpDir, 'song.mp3');

    (new ScanLinkedFolder($folder))->handle();

    expect(Track::where('linked_folder_id', $folder->id)->count())->toBe(1);
});

test('discovers files recursively in subdirectories', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    mkdir($this->tmpDir.'/subdir', 0755, true);
    mkdir($this->tmpDir.'/subdir/nested', 0755, true);

    createAudioFile($this->tmpDir, 'root.mp3');
    createAudioFile($this->tmpDir.'/subdir', 'sub.flac');
    createAudioFile($this->tmpDir.'/subdir/nested', 'deep.wav');

    (new ScanLinkedFolder($folder))->handle();

    expect(Track::where('linked_folder_id', $folder->id)->count())->toBe(3);
});

test('sets folder status to active on completion', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    createAudioFile($this->tmpDir, 'song.mp3');

    (new ScanLinkedFolder($folder))->handle();

    $folder->refresh();
    expect($folder->status)->toBe(FolderStatus::Active);
});

test('sets folder status to error when exception is thrown', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => '/nonexistent/path/that/does/not/exist']);

    expect(fn () => (new ScanLinkedFolder($folder))->handle())
        ->toThrow(Exception::class);

    $folder->refresh();
    expect($folder->status)->toBe(FolderStatus::Error);
});

test('updates track_count and last_scanned_at on completion', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    createAudioFile($this->tmpDir, 'a.mp3');
    createAudioFile($this->tmpDir, 'b.mp3');

    (new ScanLinkedFolder($folder))->handle();

    $folder->refresh();
    expect($folder->track_count)->toBe(2);
    expect($folder->last_scanned_at)->not->toBeNull();
});

test('track has correct attributes after scan', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    createAudioFile($this->tmpDir, 'my-song.mp3');

    (new ScanLinkedFolder($folder))->handle();

    $track = Track::where('linked_folder_id', $folder->id)->first();

    expect($track->source_type)->toBe(SourceType::Local);
    expect($track->linked_folder_id)->toBe($folder->id);
    expect($track->user_id)->toBe($folder->user_id);
    expect($track->title)->toBe('my-song');
    expect($track->artist)->toBe('Unknown Artist');
    expect($track->file_path)->not->toContain('\\');
    expect($track->file_hash)->not->toBeNull();
    expect($track->duration)->toBeNull();
});

test('dispatches ExtractAudioMetadata for each discovered track', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    createAudioFile($this->tmpDir, 'a.mp3');
    createAudioFile($this->tmpDir, 'b.flac');

    (new ScanLinkedFolder($folder))->handle();

    Queue::assertPushed(ExtractAudioMetadata::class, 2);
});

test('rescan does not create duplicate tracks for same file_path', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    createAudioFile($this->tmpDir, 'song.mp3');

    (new ScanLinkedFolder($folder))->handle();
    (new ScanLinkedFolder($folder))->handle();

    expect(Track::where('linked_folder_id', $folder->id)->count())->toBe(1);
});

test('rescan updates file_hash when file content changes', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    file_put_contents($this->tmpDir.'/song.mp3', 'original content '.uniqid());

    (new ScanLinkedFolder($folder))->handle();

    $originalHash = Track::where('linked_folder_id', $folder->id)->first()->file_hash;

    file_put_contents($this->tmpDir.'/song.mp3', 'different content '.uniqid());
    (new ScanLinkedFolder($folder))->handle();

    $updatedHash = Track::where('linked_folder_id', $folder->id)->first()->file_hash;
    expect($updatedHash)->not->toBe($originalHash);
    expect(Track::where('linked_folder_id', $folder->id)->count())->toBe(1);
});

test('moved file updates file_path instead of creating a new track', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    $content = 'unique audio content '.uniqid();
    file_put_contents($this->tmpDir.'/original.mp3', $content);

    (new ScanLinkedFolder($folder))->handle();

    expect(Track::where('linked_folder_id', $folder->id)->count())->toBe(1);

    // Move the file to a new path
    rename($this->tmpDir.'/original.mp3', $this->tmpDir.'/moved.mp3');
    (new ScanLinkedFolder($folder))->handle();

    $tracks = Track::where('linked_folder_id', $folder->id)->get();
    expect($tracks->count())->toBe(1);
    expect($tracks->first()->file_path)->toContain('moved.mp3');
});

test('unreadable file is skipped and rest of folder continues scanning', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    createAudioFile($this->tmpDir, 'good.mp3');
    createAudioFile($this->tmpDir, 'another.mp3');

    (new ScanLinkedFolder($folder))->handle();

    expect(Track::where('linked_folder_id', $folder->id)->count())->toBe(2);
});

test('rescan marks track unavailable when file is deleted from disk', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    createAudioFile($this->tmpDir, 'song.mp3');

    (new ScanLinkedFolder($folder))->handle();

    $track = Track::where('linked_folder_id', $folder->id)->first();
    expect($track->is_available)->toBeTrue();

    unlink($this->tmpDir.'/song.mp3');
    (new ScanLinkedFolder($folder))->handle();

    expect($track->fresh()->is_available)->toBeFalse();
});

test('rescan restores track when file reappears at original path', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    $filePath = $this->tmpDir.'/song.mp3';
    createAudioFile($this->tmpDir, 'song.mp3');

    (new ScanLinkedFolder($folder))->handle();

    $track = Track::where('linked_folder_id', $folder->id)->first();
    $track->update(['is_available' => false]);

    // File still exists — rescan should restore it
    (new ScanLinkedFolder($folder))->handle();

    expect($track->fresh()->is_available)->toBeTrue();
});

test('processes files in configurable batch size', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);

    foreach (['a.mp3', 'b.mp3', 'c.mp3', 'd.mp3', 'e.mp3'] as $filename) {
        createAudioFile($this->tmpDir, $filename);
    }

    config(['rekodo.scan_batch_size' => 2]);

    (new ScanLinkedFolder($folder))->handle();

    expect(Track::where('linked_folder_id', $folder->id)->count())->toBe(5);
    Queue::assertPushed(ExtractAudioMetadata::class, 5);
    expect($folder->fresh()->scanned_count)->toBe(5);
    expect($folder->fresh()->total_count)->toBe(5);
});

test('updates scanned_count progressively during processing', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);

    foreach (['a.mp3', 'b.mp3', 'c.mp3', 'd.mp3'] as $filename) {
        createAudioFile($this->tmpDir, $filename);
    }

    config(['rekodo.scan_batch_size' => 2]);

    (new ScanLinkedFolder($folder))->handle();

    expect($folder->fresh()->scanned_count)->toBe(4);
    expect($folder->fresh()->total_count)->toBe(4);
});

test('sets total_count before processing begins', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);

    foreach (['a.mp3', 'b.mp3', 'c.mp3'] as $filename) {
        createAudioFile($this->tmpDir, $filename);
    }

    (new ScanLinkedFolder($folder))->handle();

    expect($folder->fresh()->total_count)->toBe(3);
});

test('updates unavailable_count on folder after rescan with missing files', function () {
    Queue::fake();

    $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
    createAudioFile($this->tmpDir, 'a.mp3');
    createAudioFile($this->tmpDir, 'b.mp3');

    (new ScanLinkedFolder($folder))->handle();

    unlink($this->tmpDir.'/a.mp3');
    (new ScanLinkedFolder($folder))->handle();

    expect($folder->fresh()->unavailable_count)->toBe(1);
});
