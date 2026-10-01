<?php

use App\Enums\ArtworkSource;
use App\Jobs\FetchAlbumArtwork;
use App\Models\Album;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Real bytes at a real size, so the command exercises the same decode and
 * re-encode a 309 MB library would.
 */
function backfillSourceBytes(int $width = 1200, int $height = 1200): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 90, 140, 210));

    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

function backfillAlbum(array $attributes = []): Album
{
    return Album::factory()->create($attributes + [
        'user_id' => User::factory()->create()->id,
        'cover_art_path' => null,
    ]);
}

/**
 * A real but 1x1 image: decodable, and still the garbage D7 rejects.
 */
function buildTinyJpeg(): string
{
    $image = imagecreatetruecolor(1, 1);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

    ob_start();
    imagejpeg($image, null, 90);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

test('a referenced png is re-encoded to 600px webp and the old file is deleted', function () {
    Storage::fake('public');

    $album = backfillAlbum();
    $disk = Storage::disk('public');
    $disk->put('covers/'.$album->id.'.png', backfillSourceBytes());

    $album->update(['cover_art_path' => 'covers/'.$album->id.'.png', 'artwork_source' => ArtworkSource::Fetched]);

    $this->artisan('albums:backfill-artwork')
        ->expectsOutputToContain('re-encode 1 cover')
        ->assertSuccessful();

    $expected = 'covers/'.$album->id.'.webp';
    $disk->assertExists($expected);
    $disk->assertMissing('covers/'.$album->id.'.png');

    expect(getimagesize($disk->path($expected)))->toMatchArray([600, 600]);
    expect($album->fresh()->cover_art_path)->toBe($expected);
});

test('an unreferenced cover file is pruned', function () {
    Storage::fake('public');

    $album = backfillAlbum();
    $album->storeCoverArt(backfillSourceBytes(), ArtworkSource::Fetched);

    Storage::disk('public')->put('covers/999999.png', backfillSourceBytes(300, 300));

    $this->artisan('albums:backfill-artwork')
        ->expectsOutputToContain('prune 1 orphaned file')
        ->assertSuccessful();

    Storage::disk('public')->assertExists('covers/'.$album->id.'.webp');
    Storage::disk('public')->assertMissing('covers/999999.png');
});

test('a degenerate referenced cover has its row nulled and its file deleted', function () {
    Storage::fake('public');

    $album = backfillAlbum();
    Storage::disk('public')->put('covers/'.$album->id.'.webp', buildTinyJpeg());
    $album->update(['cover_art_path' => 'covers/'.$album->id.'.webp', 'artwork_source' => ArtworkSource::Fetched]);

    $this->artisan('albums:backfill-artwork')
        ->expectsOutputToContain('clear 1 degenerate cover')
        ->assertSuccessful();

    Storage::disk('public')->assertMissing('covers/'.$album->id.'.webp');
    expect($album->fresh()->cover_art_path)->toBeNull()
        ->and($album->fresh()->artwork_source)->toBeNull();
});

test('a cover whose file is gone is cleared rather than left dangling', function () {
    Storage::fake('public');

    $album = backfillAlbum();
    $album->update(['cover_art_path' => 'covers/'.$album->id.'.webp', 'artwork_source' => ArtworkSource::Fetched]);

    $this->artisan('albums:backfill-artwork')->assertSuccessful();

    expect($album->fresh()->cover_art_path)->toBeNull();
});

test('clearing a degenerate cover re-arms the automatic fetch', function () {
    Storage::fake('public');

    $album = backfillAlbum();
    Storage::disk('public')->put('covers/'.$album->id.'.webp', buildTinyJpeg());
    $album->update(['cover_art_path' => 'covers/'.$album->id.'.webp', 'artwork_source' => ArtworkSource::Fetched]);

    $this->artisan('albums:backfill-artwork')->assertSuccessful();

    expect(FetchAlbumArtwork::attemptKey($album->id))->not->toBeEmpty();
});

test('a second run changes no file and no row', function () {
    Storage::fake('public');

    $album = backfillAlbum();
    $album->update(['cover_art_path' => 'covers/'.$album->id.'.png', 'artwork_source' => ArtworkSource::Fetched]);
    Storage::disk('public')->put('covers/'.$album->id.'.png', backfillSourceBytes());
    Storage::disk('public')->put('covers/123456.jpg', backfillSourceBytes(300, 300));

    $this->artisan('albums:backfill-artwork')->assertSuccessful();

    $after = Storage::disk('public')->allFiles();
    $row = $album->fresh()->only(['cover_art_path', 'artwork_source']);

    // The whole summary arrives as one write, and expectsOutputToContain only
    // settles one substring per write, so the line is asserted as a whole.
    $this->artisan('albums:backfill-artwork')
        ->expectsOutputToContain('re-encode 0 cover(s) to 600px WebP, prune 0 orphaned file(s), clear 0 degenerate cover(s).')
        ->assertSuccessful();

    expect(Storage::disk('public')->allFiles())->toBe($after)
        ->and($album->fresh()->only(['cover_art_path', 'artwork_source']))->toBe($row);
});

test('a dry run reports the same numbers and deletes nothing', function () {
    Storage::fake('public');

    $album = backfillAlbum();
    $album->update(['cover_art_path' => 'covers/'.$album->id.'.png', 'artwork_source' => ArtworkSource::Fetched]);
    Storage::disk('public')->put('covers/'.$album->id.'.png', backfillSourceBytes());
    Storage::disk('public')->put('covers/999999.png', backfillSourceBytes(300, 300));

    $this->artisan('albums:backfill-artwork --dry-run')
        ->expectsOutputToContain('Would re-encode 1 cover(s) to 600px WebP, prune 1 orphaned file(s), clear 0 degenerate cover(s).')
        ->assertSuccessful();

    Storage::disk('public')->assertExists('covers/'.$album->id.'.png');
    Storage::disk('public')->assertExists('covers/999999.png');
    expect($album->fresh()->cover_art_path)->toBe('covers/'.$album->id.'.png');
});

test('a manual cover is left alone by the re-encode pass', function () {
    Storage::fake('public');

    $album = backfillAlbum();
    $album->update(['cover_art_path' => 'covers/'.$album->id.'.png', 'artwork_source' => ArtworkSource::Manual]);
    Storage::disk('public')->put('covers/'.$album->id.'.png', backfillSourceBytes());

    $this->artisan('albums:backfill-artwork')->assertSuccessful();

    // Re-encoding is a format normalisation, not a provenance change, so the
    // path is repointed but the owner still owns the choice.
    expect($album->fresh()->cover_art_path)->toBe('covers/'.$album->id.'.webp')
        ->and($album->fresh()->artwork_source)->toBe(ArtworkSource::Manual);
});

test('the command never touches files outside the covers directory', function () {
    Storage::fake('public');

    Storage::disk('public')->put('music/1/song.mp3', 'audio');
    Storage::disk('public')->put('avatars/1.webp', 'avatar');

    $this->artisan('albums:backfill-artwork')->assertSuccessful();

    Storage::disk('public')->assertExists('music/1/song.mp3');
    Storage::disk('public')->assertExists('avatars/1.webp');
});
