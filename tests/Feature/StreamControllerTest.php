<?php

use App\Enums\SourceType;
use App\Models\LinkedFolder;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('authenticated owner can stream an uploaded track', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.mp3', 100, 'audio/mpeg');
    $path = $file->store('music', 'public');

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'file_path' => $path,
        'source_type' => SourceType::Uploaded,
    ]);

    $response = $this->actingAs($user)->get(route('stream.track', $track));

    $response->assertStatus(200);
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline');
});

test('authenticated owner can stream a local-linked track', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'track_').'.mp3';
    file_put_contents($tmpFile, str_repeat('x', 1024));

    $tmpDir = str_replace('\\', '/', dirname($tmpFile));

    $user = User::factory()->create();
    $folder = LinkedFolder::factory()->create([
        'user_id' => $user->id,
        'path' => $tmpDir,
    ]);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'linked_folder_id' => $folder->id,
        'file_path' => str_replace('\\', '/', $tmpFile),
        'source_type' => SourceType::Local,
    ]);

    $response = $this->actingAs($user)->get(route('stream.track', $track));

    $response->assertStatus(200);

    unlink($tmpFile);
});

test('unauthenticated request redirects to login', function () {
    $track = Track::factory()->create();

    $response = $this->get(route('stream.track', $track));

    $response->assertRedirect(route('login'));
});

test('authenticated non-owner gets 403', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $track = Track::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($other)->get(route('stream.track', $track));

    $response->assertStatus(403);
});

test('local track with file_path outside linked folder returns 403', function () {
    $user = User::factory()->create();
    $folder = LinkedFolder::factory()->create([
        'user_id' => $user->id,
        'path' => '/allowed/music',
    ]);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'linked_folder_id' => $folder->id,
        'file_path' => '/etc/passwd',
        'source_type' => SourceType::Local,
    ]);

    $response = $this->actingAs($user)->get(route('stream.track', $track));

    $response->assertStatus(403);
});

test('local track with no linked folder returns 403', function () {
    $user = User::factory()->create();

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'linked_folder_id' => null,
        'file_path' => '/some/path/track.mp3',
        'source_type' => SourceType::Local,
    ]);

    $response = $this->actingAs($user)->get(route('stream.track', $track));

    $response->assertStatus(403);
});

test('missing file on disk returns 404 json', function () {
    $user = User::factory()->create();

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'file_path' => 'music/nonexistent.mp3',
        'source_type' => SourceType::Uploaded,
    ]);

    $response = $this->actingAs($user)->get(route('stream.track', $track));

    $response->assertStatus(404);
    $response->assertJson(['error' => 'Track file not found.']);
});

test('range request returns 206 partial content', function () {
    $user = User::factory()->create();

    $content = str_repeat('A', 2048);
    $path = 'music/range-test.mp3';
    Storage::disk('public')->put($path, $content);

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'file_path' => $path,
        'source_type' => SourceType::Uploaded,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['Range' => 'bytes=0-1023'])
        ->get(route('stream.track', $track));

    $response->assertStatus(206);
});

test('response includes etag header', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.mp3', 100, 'audio/mpeg');
    $path = $file->store('music', 'public');

    $track = Track::factory()->create([
        'user_id' => $user->id,
        'file_path' => $path,
        'source_type' => SourceType::Uploaded,
    ]);

    $response = $this->actingAs($user)->get(route('stream.track', $track));

    $response->assertHeader('ETag');
});
