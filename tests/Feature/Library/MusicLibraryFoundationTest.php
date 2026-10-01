<?php

use App\Enums\ArtworkSource;
use App\Models\Album;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Build a real PNG of the given size, so tests exercise the actual decode and
 * re-encode pipeline rather than a placeholder string.
 */
function buildTestPng(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 40, 90, 160));

    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

// ── Album model ──────────────────────────────────────────────────────────────

it('creates an album with factory', function () {
    $album = Album::factory()->create();

    expect($album->title)->not->toBeEmpty();
    expect($album->user_id)->not->toBeNull();
});

it('album belongs to a user', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);

    expect($album->user)->toBeInstanceOf(User::class);
    expect($album->user->id)->toBe($user->id);
});

it('album coverArtUrl returns null when cover_art_path is null', function () {
    $album = Album::factory()->create(['cover_art_path' => null]);

    expect($album->coverArtUrl())->toBeNull();
});

it('album coverArtUrl resolves against the public disk, where cover art is written', function () {
    Storage::fake('public');
    Storage::disk('public')->put('covers/album.jpg', 'image-bytes');
    $album = Album::factory()->create(['cover_art_path' => 'covers/album.jpg']);

    // The public disk serves from /storage; the default (local) disk would
    // resolve to storage/app/private and 403 on any host without a symlink.
    expect($album->coverArtUrl())->toContain('/storage/covers/album.jpg');
});

it('album coverArtUrl returns null when the cover file is missing from disk', function () {
    Storage::fake('public');
    $album = Album::factory()->create(['cover_art_path' => 'covers/gone.jpg']);

    // Falls back to the view's placeholder instead of rendering a broken image.
    expect($album->coverArtUrl())->toBeNull();
});

it('album storeCoverArt writes to the public disk and records the path', function () {
    Storage::fake('public');
    $album = Album::factory()->create(['cover_art_path' => null]);

    $path = $album->storeCoverArt(buildTestPng(600, 600), ArtworkSource::Fetched);

    // D4: the extension is fixed at .webp regardless of what the source was,
    // so there is no MIME→extension mapping left to get wrong.
    expect($path)->toBe('covers/'.$album->id.'.webp');
    Storage::disk('public')->assertExists($path);
    expect(getimagesize(Storage::disk('public')->path($path))['mime'])->toBe('image/webp');
    expect($album->fresh()->cover_art_path)->toBe($path);
    expect($album->fresh()->artwork_source)->toBe(ArtworkSource::Fetched);
});

it('album storeCoverArt scales to 600px and never upscales', function () {
    Storage::fake('public');
    $album = Album::factory()->create(['cover_art_path' => null]);

    $large = $album->storeCoverArt(buildTestPng(900, 600), ArtworkSource::Fetched);
    expect(getimagesize(Storage::disk('public')->path($large)))->toMatchArray([600, 400]);

    $small = $album->storeCoverArt(buildTestPng(300, 300), ArtworkSource::Fetched);
    expect(getimagesize(Storage::disk('public')->path($small)))->toMatchArray([300, 300]);
});

it('album storeCoverArt rejects bytes that are not an image', function () {
    Storage::fake('public');
    $album = Album::factory()->create(['cover_art_path' => null]);

    expect(fn () => $album->storeCoverArt('not-an-image', ArtworkSource::Fetched))
        ->toThrow(RuntimeException::class);

    Storage::disk('public')->assertMissing('covers/'.$album->id.'.webp');
    expect($album->fresh()->cover_art_path)->toBeNull();
});

it('album storeCoverArt rejects a 1x1 image', function () {
    Storage::fake('public');
    $album = Album::factory()->create(['cover_art_path' => null]);

    expect(fn () => $album->storeCoverArt(buildTestPng(1, 1), ArtworkSource::Fetched))
        ->toThrow(RuntimeException::class);
});

it('a manual cover survives auto writes but an uploaded one replaces it', function () {
    Storage::fake('public');
    $album = Album::factory()->create(['cover_art_path' => null]);
    $album->storeCoverArt(buildTestPng(600, 600), ArtworkSource::Manual);

    // D3: re-extraction and the online backfill both decline to touch it.
    expect($album->storeCoverArt(buildTestPng(500, 500), ArtworkSource::Embedded))->toBe('covers/'.$album->id.'.webp');
    expect($album->storeCoverArt(buildTestPng(500, 500), ArtworkSource::Fetched))->toBe('covers/'.$album->id.'.webp');
    expect($album->fresh()->artwork_source)->toBe(ArtworkSource::Manual);

    // A fresh user decision does replace it, so Remove-then-re-pick works.
    $album->storeCoverArt(buildTestPng(500, 500), ArtworkSource::Uploaded);
    expect($album->fresh()->artwork_source)->toBe(ArtworkSource::Uploaded);
});

it('album storeCoverArt deletes a differently extended predecessor', function () {
    Storage::fake('public');
    Storage::disk('public')->put('covers/legacy.jpg', 'bytes');
    $album = Album::factory()->create([
        'cover_art_path' => 'covers/legacy.jpg',
        'artwork_source' => ArtworkSource::Embedded,
    ]);

    $path = $album->storeCoverArt(buildTestPng(600, 600), ArtworkSource::Fetched);

    expect($path)->toBe('covers/'.$album->id.'.webp');
    Storage::disk('public')->assertMissing('covers/legacy.jpg');
    Storage::disk('public')->assertExists($path);
});

it('a cover with no recorded source is left alone by an auto write', function () {
    Storage::fake('public');
    Storage::disk('public')->put('covers/1.jpg', 'bytes');
    $album = Album::factory()->create(['cover_art_path' => 'covers/1.jpg']);

    // Pre-backfill rows have no provenance; refusing to clobber them is what
    // stops an auto writer from replacing a cover the user may have put there.
    expect($album->storeCoverArt(buildTestPng(600, 600), ArtworkSource::Fetched))
        ->toBe('covers/1.jpg');
    expect($album->fresh()->cover_art_path)->toBe('covers/1.jpg');
});

it('album clearCoverArt deletes the file and clears both columns', function () {
    Storage::fake('public');
    $album = Album::factory()->create(['cover_art_path' => null]);
    $album->storeCoverArt(buildTestPng(600, 600), ArtworkSource::Manual);

    $album->clearCoverArt();

    Storage::disk('public')->assertMissing('covers/'.$album->id.'.webp');
    expect($album->fresh()->cover_art_path)->toBeNull()
        ->and($album->fresh()->artwork_source)->toBeNull();
});

it('artwork_source is backfilled to fetched for albums that already had a cover', function () {
    $covered = Album::factory()->create(['cover_art_path' => 'covers/1.jpg']);
    $artless = Album::factory()->create(['cover_art_path' => null]);

    // RefreshDatabase has already applied the migration to an empty table, so
    // roll the column back and replay the real migration against these rows.
    Schema::table('albums', fn ($table) => $table->dropColumn('artwork_source'));

    $migration = require collect(File::files(database_path('migrations')))
        ->first(fn ($file) => str_contains($file->getFilename(), 'add_artwork_source_to_albums_table'))
        ->getPathname();

    $migration->up();

    expect(Album::find($covered->id)->artwork_source)->toBe(ArtworkSource::Fetched)
        ->and(Album::find($artless->id)->artwork_source)->toBeNull();
});

// ── Track model ───────────────────────────────────────────────────────────────

it('creates a track with factory', function () {
    $track = Track::factory()->create();

    expect($track->file_path)->not->toBeEmpty();
    expect($track->file_hash)->not->toBeEmpty();
    expect($track->title)->not->toBeEmpty();
});

it('track belongs to a user', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id]);

    expect($track->user)->toBeInstanceOf(User::class);
    expect($track->user->id)->toBe($user->id);
});

it('track album is null when album_id is null', function () {
    $track = Track::factory()->create(['album_id' => null]);

    expect($track->album)->toBeNull();
});

it('track belongs to an album', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);
    $track = Track::factory()->create(['user_id' => $user->id, 'album_id' => $album->id]);

    expect($track->album)->toBeInstanceOf(Album::class);
    expect($track->album->id)->toBe($album->id);
});

it('track audioUrl returns the streaming URL', function () {
    Storage::fake('public');
    $track = Track::factory()->create(['file_path' => 'music/1/song.mp3']);

    // Audio is served through StreamController, which authorizes the owner
    // before touching the file, so the URL points at the app and not the disk.
    expect($track->audioUrl())->toBe(route('stream.track', $track));
});

// ── Album → Tracks relationship ────────────────────────────────────────────────

it('album has many tracks', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);
    Track::factory()->count(3)->create(['user_id' => $user->id, 'album_id' => $album->id]);

    expect($album->tracks)->toHaveCount(3);
});

// ── Cascade behaviour ─────────────────────────────────────────────────────────

it('deleting a user cascades and deletes their albums', function () {
    $user = User::factory()->create();
    Album::factory()->count(2)->create(['user_id' => $user->id]);

    $user->delete();

    expect(Album::where('user_id', $user->id)->count())->toBe(0);
});

it('deleting a user cascades and deletes their tracks', function () {
    $user = User::factory()->create();
    Track::factory()->count(2)->create(['user_id' => $user->id]);

    $user->delete();

    expect(Track::where('user_id', $user->id)->count())->toBe(0);
});

it('deleting an album nullifies album_id on its tracks', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);
    $track = Track::factory()->create(['user_id' => $user->id, 'album_id' => $album->id]);

    $album->delete();

    expect($track->fresh()->album_id)->toBeNull();
});

// ── withAlbum factory state ────────────────────────────────────────────────────

it('track factory withAlbum state creates album relationship', function () {
    $track = Track::factory()->withAlbum()->create();

    expect($track->album_id)->not->toBeNull();
    expect($track->album)->toBeInstanceOf(Album::class);
});
