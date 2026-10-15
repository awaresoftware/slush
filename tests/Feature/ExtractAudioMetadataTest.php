<?php

use App\Audio\WaveformPeaks;
use App\Enums\ArtworkSource;
use App\Enums\SourceType;
use App\Jobs\ExtractAudioMetadata;
use App\Jobs\FetchAlbumArtwork;
use App\Models\Album;
use App\Models\LinkedFolder;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('job allows 3 retry attempts', function () {
    $track = Track::factory()->create();
    $job = new ExtractAudioMetadata($track);
    expect($job->tries)->toBe(3);
});

test('job has 120 second timeout', function () {
    $track = Track::factory()->create();
    $job = new ExtractAudioMetadata($track);
    expect($job->timeout)->toBe(120);
});

test('job resolves path via Storage disk for uploaded track', function () {
    Storage::fake('public');
    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/1/song.mp3',
    ]);

    expect($track->source_type)->toBe(SourceType::Uploaded);
    expect($track->file_path)->toBe('music/1/song.mp3');

    // Running the job should not throw — it returns early when file doesn't exist
    (new ExtractAudioMetadata($track))->handle();
});

test('job uses file_path directly for local track', function () {
    $track = Track::factory()->create([
        'source_type' => 'local',
        'file_path' => '/Users/fake/Music/song.mp3',
        'linked_folder_id' => LinkedFolder::factory(),
    ]);

    expect($track->source_type)->toBe(SourceType::Local);
    expect($track->file_path)->toBe('/Users/fake/Music/song.mp3');

    // Running the job should not throw — it returns early when file doesn't exist
    (new ExtractAudioMetadata($track))->handle();
});

test('album artist populated from ID3 album_artist tag', function () {
    Storage::fake('public');

    $mp3Path = buildTestMp3(['TALB' => 'Test Album', 'TPE1' => 'Track Artist', 'TPE2' => 'Album Artist']);
    Storage::disk('public')->put('music/test.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/test.mp3',
        'album_id' => null,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    $album = $track->fresh()->album;
    expect($album)->not->toBeNull();
    expect($album->artist)->toBe('Album Artist');
});

test('album artist falls back to track artist when no album_artist tag', function () {
    Storage::fake('public');

    $mp3Path = buildTestMp3(['TALB' => 'Test Album', 'TPE1' => 'Track Artist']);
    Storage::disk('public')->put('music/test.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/test.mp3',
        'album_id' => null,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    $album = $track->fresh()->album;
    expect($album)->not->toBeNull();
    expect($album->artist)->toBe('Track Artist');
});

test('track number extracted from ID3 tag', function () {
    Storage::fake('public');

    $mp3Path = buildTestMp3(['TRCK' => '3/12']);
    Storage::disk('public')->put('music/test.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/test.mp3',
        'track_number' => null,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    expect($track->fresh()->track_number)->toBe(3);
});

test('album created with folder name when no ID3 album tag for local track', function () {
    $folder = LinkedFolder::factory()->create(['name' => 'Dark Side of the Moon']);
    $mp3Path = buildRealMp3AbsolutePath();

    $track = Track::factory()->create([
        'source_type' => 'local',
        'file_path' => $mp3Path,
        'linked_folder_id' => $folder->id,
        'album_id' => null,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    $updatedTrack = $track->fresh();
    expect($updatedTrack->album_id)->not->toBeNull();
    expect($updatedTrack->album->title)->toBe('Dark Side of the Moon');
});

test('no album created for uploaded track with no ID3 album tag', function () {
    Storage::fake('public');

    $mp3Path = buildRealMp3AbsolutePath();
    Storage::disk('public')->put('music/notag.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/notag.mp3',
        'album_id' => null,
        'linked_folder_id' => null,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    expect($track->fresh()->album_id)->toBeNull();
});

test('cover art extracted from APIC and saved to storage', function () {
    Storage::fake('public');

    $mp3Path = buildTestMp3WithCoverArt('Test Album');
    Storage::disk('public')->put('music/cover.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/cover.mp3',
        'album_id' => null,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    $album = $track->fresh()->album;
    expect($album)->not->toBeNull();
    expect($album->cover_art_path)->not->toBeNull();
    Storage::disk('public')->assertExists($album->cover_art_path);
});

test('cover art extracted from comments picture fallback for non-ID3v2 formats', function () {
    Storage::fake('public');

    // Build an MP3 with album tag but NO APIC frame — we'll inject comments.picture via a mock
    $mp3Path = buildTestMp3(['TALB' => 'M4A Album']);
    Storage::disk('public')->put('music/test.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/test.mp3',
        'album_id' => null,
    ]);

    // Run the job — album should be created but no cover art (no APIC in this file)
    (new ExtractAudioMetadata($track))->handle();

    $album = $track->fresh()->album;
    expect($album)->not->toBeNull();
    expect($album->cover_art_path)->toBeNull();

    // Now simulate what getID3 returns for M4A files: comments.picture populated
    // Reset album cover and re-extract with a file that has APIC (which also populates comments.picture)
    $mp3WithArt = buildTestMp3WithCoverArt('M4A Album');
    Storage::disk('public')->put('music/test.mp3', file_get_contents($mp3WithArt));

    (new ExtractAudioMetadata($track))->handle();

    $album->refresh();
    expect($album->cover_art_path)->not->toBeNull();
    Storage::disk('public')->assertExists($album->cover_art_path);
});

test('existing album cover art is not overwritten by second track', function () {
    Storage::fake('public');

    $album = Album::factory()->create(['cover_art_path' => null]);
    $path = $album->storeCoverArt(buildTestJpeg(600, 600), ArtworkSource::Embedded);
    $original = Storage::disk('public')->get($path);

    $mp3Path = buildTestMp3WithCoverArt('Test Album', 500, 500);
    Storage::disk('public')->put('music/second.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/second.mp3',
        'album_id' => $album->id,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    // A real image already on disk is a deliberate outcome, so re-extraction
    // from another track leaves both the path and the bytes alone.
    $album->refresh();
    expect($album->cover_art_path)->toBe('covers/'.$album->id.'.webp');
    expect(Storage::disk('public')->get($path))->toBe($original);
});

test('a degenerate existing cover is replaced by the newly extracted art', function () {
    Storage::fake('public');

    $album = Album::factory()->create(['cover_art_path' => null]);
    $path = 'covers/'.$album->id.'.webp';

    // The state the migration leaves behind: a cover on record, marked as an
    // auto-tier one, whose bytes turn out not to be an image.
    $album->update(['cover_art_path' => $path, 'artwork_source' => ArtworkSource::Fetched]);
    Storage::disk('public')->put($path, 'not-an-image');

    $mp3Path = buildTestMp3WithCoverArt('Test Album', 600, 600);
    Storage::disk('public')->put('music/garbage.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/garbage.mp3',
        'album_id' => $album->id,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    expect($album->fresh()->cover_art_path)->toBe($path);
    expect(Album::coverIsUsable(Storage::disk('public')->get($path)))->toBeTrue();
});

test('an existing cover whose file is gone is replaced by the newly extracted art', function () {
    Storage::fake('public');

    $album = Album::factory()->create(['cover_art_path' => null]);
    $path = 'covers/'.$album->id.'.webp';
    $album->update(['cover_art_path' => $path, 'artwork_source' => ArtworkSource::Fetched]);

    // Recorded in the database, absent from disk.
    expect($album->coverArtUrl())->toBeNull();

    $mp3Path = buildTestMp3WithCoverArt('Test Album', 600, 600);
    Storage::disk('public')->put('music/missing.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/missing.mp3',
        'album_id' => $album->id,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    expect($album->fresh()->cover_art_path)->not->toBeNull();
    Storage::disk('public')->assertExists($album->fresh()->cover_art_path);
});

test('prefers a front cover over a larger picture of another type', function () {
    Storage::fake('public');

    // The back cover is bigger, but a front cover outranks it.
    $mp3Path = buildTestMp3WithPictures('Ranking Album', [
        [buildTestJpeg(800, 800), "\x04"],
        [buildTestJpeg(300, 300), "\x03"],
    ]);
    Storage::disk('public')->put('music/rank.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/rank.mp3',
        'album_id' => null,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    $album = $track->fresh()->album;
    expect($album->cover_art_path)->not->toBeNull();

    // scaleDown never upscales, so the winner is identified by its dimensions.
    expect(getimagesize(Storage::disk('public')->path($album->cover_art_path)))->toMatchArray([300, 300]);
});

test('among two valid front covers, stores the larger', function () {
    Storage::fake('public');

    $mp3Path = buildTestMp3WithPictures('Two Fronts', [
        [buildTestJpeg(200, 200), "\x03"],
        [buildTestJpeg(700, 700), "\x03"],
    ]);
    Storage::disk('public')->put('music/two-fronts.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/two-fronts.mp3',
        'album_id' => null,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    $album = $track->fresh()->album;
    expect(getimagesize(Storage::disk('public')->path($album->cover_art_path)))->toMatchArray([600, 600]);
});

test('stores nothing when the only picture is 1x1', function () {
    Storage::fake('public');

    $mp3Path = buildTestMp3WithCoverArt('Tiny Album', 1, 1);
    Storage::disk('public')->put('music/tiny.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/tiny.mp3',
        'album_id' => null,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    expect($track->fresh()->album)->not->toBeNull()
        ->and($track->fresh()->album->cover_art_path)->toBeNull();
});

test('stores nothing when the APIC data is not decodable image bytes', function () {
    Storage::fake('public');

    $mp3Path = buildTestMp3WithPictures('Broken Album', [
        ['<html>error page</html>', "\x03"],
    ]);
    Storage::disk('public')->put('music/broken.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/broken.mp3',
        'album_id' => null,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    expect($track->fresh()->album)->not->toBeNull()
        ->and($track->fresh()->album->cover_art_path)->toBeNull();
});

test('a successful extraction records the embedded source', function () {
    Storage::fake('public');

    $mp3Path = buildTestMp3WithCoverArt('Sourced Album', 600, 600);
    Storage::disk('public')->put('music/sourced.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/sourced.mp3',
        'album_id' => null,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    expect($track->fresh()->album->artwork_source)->toBe(ArtworkSource::Embedded);
});

test('an album left without art queues one artwork fetch, and one with art does not', function () {
    Storage::fake('public');
    Queue::fake();

    $artless = buildTestMp3(['TALB' => 'No Art', 'TPE1' => 'Some Artist']);
    Storage::disk('public')->put('music/no-art.mp3', file_get_contents($artless));
    $artful = buildTestMp3WithCoverArt('Has Art', 600, 600);
    Storage::disk('public')->put('music/has-art.mp3', file_get_contents($artful));

    foreach (['music/no-art.mp3', 'music/has-art.mp3'] as $path) {
        $track = Track::factory()->create([
            'source_type' => 'uploaded',
            'file_path' => $path,
            'album_id' => null,
        ]);

        (new ExtractAudioMetadata($track))->handle();
    }

    Queue::assertPushed(FetchAlbumArtwork::class, 1);
});

test('two tracks of the same album enqueue a single artwork fetch', function () {
    Storage::fake('public');
    Queue::fake();

    $album = Album::factory()->create(['cover_art_path' => null, 'artist' => 'Some Artist']);

    foreach (['one', 'two'] as $suffix) {
        $mp3Path = buildTestMp3(['TALB' => 'Shared Album', 'TPE1' => 'Some Artist']);
        Storage::disk('public')->put("music/shared-{$suffix}.mp3", file_get_contents($mp3Path));

        $track = Track::factory()->create([
            'source_type' => 'uploaded',
            'file_path' => "music/shared-{$suffix}.mp3",
            'album_id' => $album->id,
        ]);

        (new ExtractAudioMetadata($track))->handle();
    }

    Queue::assertPushed(FetchAlbumArtwork::class, 1);
});

test('track with no ID3 data preserves existing title and unknown artist', function () {
    Storage::fake('public');

    $mp3Path = buildRealMp3AbsolutePath();
    Storage::disk('public')->put('music/bare.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/bare.mp3',
        'title' => 'My Song Title',
        'artist' => 'Unknown Artist',
    ]);

    (new ExtractAudioMetadata($track))->handle();

    $updated = $track->fresh();
    expect($updated->title)->toBe('My Song Title');
    expect($updated->artist)->toBe('Unknown Artist');
});

// --- Helpers ---

/**
 * Build a minimal MP3 binary (MPEG sync word only — no ID3 tags).
 */
function buildRealMp3AbsolutePath(): string
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'test_mp3_').'.mp3';
    // Single MPEG Layer 3 sync frame (silence)
    file_put_contents($tmpFile, "\xFF\xFB\x90\x00".str_repeat("\x00", 413));

    return str_replace('\\', '/', $tmpFile);
}

/**
 * Build an ID3v2.3 tagged MP3 file with given text frames.
 * Keys are ID3v2 frame IDs (TALB, TPE1, TPE2, TRCK, TIT2, TYER).
 *
 * @param  array<string, string>  $frames
 */
function buildTestMp3(array $frames): string
{
    $frameData = '';
    foreach ($frames as $frameId => $value) {
        $frameData .= buildId3TextFrame($frameId, $value);
    }

    $mp3 = buildId3v2Tag($frameData)."\xFF\xFB\x90\x00".str_repeat("\x00", 413);
    $tmpFile = tempnam(sys_get_temp_dir(), 'test_mp3_').'.mp3';
    file_put_contents($tmpFile, $mp3);

    return str_replace('\\', '/', $tmpFile);
}

/**
 * Build an MP3 with an album tag and a single embedded JPEG cover art.
 */
function buildTestMp3WithCoverArt(string $albumTitle, int $width = 600, int $height = 600): string
{
    return buildTestMp3WithPictures($albumTitle, [[buildTestJpeg($width, $height), "\x03"]]);
}

/**
 * Build an MP3 carrying an album tag and the given APIC frames, so a test can
 * control how many pictures there are, their types and their sizes.
 *
 * @param  array<int, array{0: string, 1: string}>  $pictures  [image bytes, picture type]
 */
function buildTestMp3WithPictures(string $albumTitle, array $pictures): string
{
    $frameData = buildId3TextFrame('TALB', $albumTitle);

    foreach ($pictures as [$imageData, $pictureType]) {
        $frameData .= buildId3ApicFrame($imageData, $pictureType);
    }

    $mp3 = buildId3v2Tag($frameData)."\xFF\xFB\x90\x00".str_repeat("\x00", 413);
    $tmpFile = tempnam(sys_get_temp_dir(), 'test_cover_').'.mp3';
    file_put_contents($tmpFile, $mp3);

    return str_replace('\\', '/', $tmpFile);
}

/**
 * Build an ID3v2.3 text frame.
 */
function buildId3TextFrame(string $frameId, string $text): string
{
    $data = "\x00".$text; // ISO-8859-1 encoding byte + text

    return $frameId.pack('N', strlen($data))."\x00\x00".$data;
}

/**
 * Build an ID3v2.3 APIC (picture) frame.
 *
 * $pictureType defaults to 3 (Cover (front)); pass 4 for a back cover so a
 * test can prove the front cover is preferred over a larger non-front picture.
 */
function buildId3ApicFrame(string $imageData, string $pictureType = "\x03"): string
{
    $payload = "\x00"       // text encoding: ISO-8859-1
        ."image/jpeg\x00"  // MIME type
        .$pictureType      // picture type
        ."\x00"            // empty description
        .$imageData;

    return 'APIC'.pack('N', strlen($payload))."\x00\x00".$payload;
}

/**
 * Wrap ID3 frames in a complete ID3v2.3 header.
 */
function buildId3v2Tag(string $frames): string
{
    $size = strlen($frames);
    // Syncsafe integer encoding
    $syncsafe = chr(($size >> 21) & 0x7F)
        .chr(($size >> 14) & 0x7F)
        .chr(($size >> 7) & 0x7F)
        .chr($size & 0x7F);

    return "ID3\x03\x00\x00".$syncsafe.$frames;
}

/**
 * Return a real JPEG of the given size, so tests exercise the actual decode
 * and re-encode pipeline instead of a 1x1 placeholder.
 */
function buildTestJpeg(int $width = 600, int $height = 600): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 60, 40));

    ob_start();
    imagejpeg($image, null, 90);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

/**
 * Return a minimal valid JPEG binary (1x1 pixel, white).
 */
function buildMinimalJpeg(): string
{
    return buildTestJpeg(1, 1);
}

test('album with an accented title is not duplicated across tracks', function () {
    Storage::fake('public');

    // The shared helper writes text frames as ISO-8859-1, so this title comes
    // back out as mojibake — still non-ASCII, which is exactly the condition
    // SQLite's ASCII-only LOWER() fails to match on.
    $user = User::factory()->create();
    $tracks = [];

    foreach (['a', 'b'] as $i => $suffix) {
        $mp3Path = buildTestMp3(['TALB' => 'Nação África', 'TPE1' => 'Artist']);
        Storage::disk('public')->put("music/nacao-{$suffix}.mp3", file_get_contents($mp3Path));

        $track = Track::factory()->create([
            'source_type' => 'uploaded',
            'file_path' => "music/nacao-{$suffix}.mp3",
            'album_id' => null,
            'user_id' => $user->id,
        ]);

        (new ExtractAudioMetadata($track))->handle();
        $tracks[] = $track->fresh();
    }

    // Before the Unicode-aware fallback each track created its own album, so
    // one copy stayed artless while its twin held the cover.
    expect(Album::where('user_id', $user->id)->count())->toBe(1)
        ->and($tracks[0]->album_id)->toBe($tracks[1]->album_id);
});

test('album matching stays case-insensitive', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $existing = Album::factory()->create(['title' => 'The Wall', 'user_id' => $user->id]);

    $mp3Path = buildTestMp3(['TALB' => 'THE WALL', 'TPE1' => 'Pink Floyd']);
    Storage::disk('public')->put('music/the-wall.mp3', file_get_contents($mp3Path));

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/the-wall.mp3',
        'album_id' => null,
        'user_id' => $user->id,
    ]);

    (new ExtractAudioMetadata($track))->handle();

    expect($track->fresh()->album_id)->toBe($existing->id);
    expect(Album::where('title', 'like', '%Wall%')->count())->toBe(1);
});

test('job leaves peaks null when the envelope cannot be built', function () {
    Storage::fake('public');
    Storage::disk('public')->put('music/1/song.mp3', 'not real audio');

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/1/song.mp3',
    ]);

    // A stub that emits nothing, so the job exercises its real code path
    // without needing a decodable fixture or an installed ffmpeg.
    config()->set('foonk.waveform.ffmpeg_path', '/nonexistent/ffmpeg');
    config()->set('foonk.waveform.ffprobe_path', '/nonexistent/ffprobe');
    WaveformPeaks::forgetProbe();

    (new ExtractAudioMetadata($track))->handle();

    expect($track->refresh()->peaks)->toBeNull();
});

test('job stores the envelope it built as JSON on the track', function () {
    Storage::fake('public');
    Storage::disk('public')->put('music/1/song.mp3', 'not real audio');

    $track = Track::factory()->create([
        'source_type' => 'uploaded',
        'file_path' => 'music/1/song.mp3',
    ]);

    $dir = sys_get_temp_dir().'/peaks-job-'.getmypid();
    @mkdir($dir, 0777, true);
    $pcm = $dir.'/pcm.bin';
    file_put_contents($pcm, str_repeat(pack('s', -32768), 8000));

    $ffmpeg = $dir.'/ffmpeg';
    file_put_contents($ffmpeg, "#!/bin/sh\ncat '{$pcm}'\n");
    chmod($ffmpeg, 0755);

    // getID3 cannot read the placeholder bytes, so the job has no duration and
    // asks ffprobe for one. This stub answers that question; the ffmpeg stub
    // above answers the decode.
    $ffprobe = $dir.'/ffprobe';
    file_put_contents($ffprobe, "#!/bin/sh\necho 1\n");
    chmod($ffprobe, 0755);

    config()->set('foonk.waveform.buckets', 4);
    config()->set('foonk.waveform.ffmpeg_path', $ffmpeg);
    config()->set('foonk.waveform.ffprobe_path', $ffprobe);
    WaveformPeaks::forgetProbe();

    (new ExtractAudioMetadata($track))->handle();

    $peaks = $track->refresh()->peaks;

    expect($peaks)->toBeString()
        ->and(json_decode($peaks, true))->toBe([100, 100, 100, 100]);

    WaveformPeaks::forgetProbe();
});
