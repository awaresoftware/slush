<?php

use App\Enums\ArtworkSource;
use App\Jobs\FetchAlbumArtwork;
use App\Models\Album;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

const MB_SEARCH = 'https://musicbrainz.org/ws/2/release-group/*';
const CAA_FRONT = 'https://coverartarchive.org/release-group/*';

/**
 * Real JPEG bytes — the archive is faked, so anything the job writes has to
 * survive the same decode and 600px WebP re-encode a real cover would.
 */
function coverImageBytes(): string
{
    $image = imagecreatetruecolor(600, 600);
    imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 90));

    ob_start();
    imagejpeg($image, null, 90);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

function albumWithoutArt(array $attributes = []): Album
{
    return Album::factory()->create($attributes + [
        'cover_art_path' => null,
        'title' => 'Justified',
        'artist' => 'Justin Timberlake',
    ]);
}

test('stores the cover when MusicBrainz and the Cover Art Archive both answer', function () {
    Storage::fake('public');

    Http::fake([
        MB_SEARCH => Http::response([
            'release-groups' => [[
                'id' => '424620c6-e416-3ce8-bfcc-d5c59952456d',
                'title' => 'Justified',
                'first-release-date' => '2002-11-04',
            ]],
        ]),
        CAA_FRONT => Http::response(coverImageBytes(), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $album = albumWithoutArt(['year' => 2002]);

    (new FetchAlbumArtwork($album))->handle();

    $expected = 'covers/'.$album->id.'.webp';
    Storage::disk('public')->assertExists($expected);
    expect($album->fresh()->cover_art_path)->toBe($expected);
});

test('prefers the release group whose title and year match the album', function () {
    Storage::fake('public');

    Http::fake([
        MB_SEARCH => Http::response([
            'release-groups' => [
                ['id' => 'wrong-year', 'title' => 'Justified', 'first-release-date' => '2010-01-01'],
                ['id' => 'right-year', 'title' => 'Justified', 'first-release-date' => '2002-11-04'],
            ],
        ]),
        CAA_FRONT => Http::response(coverImageBytes(), 200),
    ]);

    $album = albumWithoutArt(['year' => 2002]);

    (new FetchAlbumArtwork($album))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'right-year'));
});

test('sends the identifying User-Agent MusicBrainz requires', function () {
    Storage::fake('public');

    Http::fake([
        MB_SEARCH => Http::response(['release-groups' => []]),
        CAA_FRONT => Http::response('', 404),
    ]);

    (new FetchAlbumArtwork(albumWithoutArt()))->handle();

    Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'foonk'));
});

test('skips albums whose artwork was set manually', function () {
    Storage::fake('public');
    $album = albumWithoutArt();
    $album->storeCoverArt(coverImageBytes(), ArtworkSource::Manual);

    Http::fake([MB_SEARCH => Http::response(['release-groups' => []])]);

    (new FetchAlbumArtwork($album))->handle();

    Http::assertNothingSent();
});

test('skips albums whose artwork was uploaded by the owner', function () {
    Storage::fake('public');
    $album = albumWithoutArt();
    $album->storeCoverArt(coverImageBytes(), ArtworkSource::Uploaded);

    Http::fake([MB_SEARCH => Http::response(['release-groups' => []])]);

    (new FetchAlbumArtwork($album))->handle();

    Http::assertNothingSent();
});

test('upgrades an auto-sourced cover with the archive original', function () {
    Storage::fake('public');

    Http::fake([
        MB_SEARCH => Http::response([
            'release-groups' => [['id' => 'abc', 'title' => 'Justified', 'first-release-date' => '2002-11-04']],
        ]),
        CAA_FRONT => Http::response(coverImageBytes(), 200),
    ]);

    // A 300x300 embed is worth upgrading to a full-size Cover Art Archive copy.
    $album = albumWithoutArt(['year' => 2002]);
    $album->storeCoverArt(coverImageBytes(), ArtworkSource::Embedded);

    (new FetchAlbumArtwork($album))->handle();

    $expected = 'covers/'.$album->id.'.webp';
    expect($album->fresh()->cover_art_path)->toBe($expected)
        ->and($album->fresh()->artwork_source)->toBe(ArtworkSource::Fetched);
    Storage::disk('public')->assertExists($expected);
});

test('records a miss instead of throwing when the archive body is not an image', function () {
    Storage::fake('public');

    Http::fake([
        MB_SEARCH => Http::response([
            'release-groups' => [['id' => 'abc', 'title' => 'Justified', 'first-release-date' => '2002-11-04']],
        ]),
        // A gateway error page rather than image bytes.
        CAA_FRONT => Http::response('<html>503 from the archive</html>', 200),
    ]);

    $album = albumWithoutArt(['year' => 2002]);

    (new FetchAlbumArtwork($album))->handle();

    expect($album->fresh()->cover_art_path)->toBeNull()
        ->and(Cache::has('album-artwork-miss:'.$album->id))->toBeTrue();
});

test('the job is unique per album and carries a real backoff', function () {
    $album = albumWithoutArt();
    $job = new FetchAlbumArtwork($album);

    // N tracks of one album must not enqueue N MusicBrainz lookups.
    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe((string) $album->id)
        ->and($job->backoff())->toBe([60, 300]);
});

test('records a miss when MusicBrainz has no matching release group', function () {
    Storage::fake('public');
    Http::fake([MB_SEARCH => Http::response(['release-groups' => []])]);

    $album = albumWithoutArt();

    (new FetchAlbumArtwork($album))->handle();

    expect($album->fresh()->cover_art_path)->toBeNull();
    expect(Cache::has('album-artwork-miss:'.$album->id))->toBeTrue();
});

test('records a miss when the cover archive has no image for the release', function () {
    Storage::fake('public');

    Http::fake([
        MB_SEARCH => Http::response([
            'release-groups' => [['id' => 'abc', 'title' => 'Justified', 'first-release-date' => '2002-11-04']],
        ]),
        CAA_FRONT => Http::response('', 404),
    ]);

    $album = albumWithoutArt(['year' => 2002]);

    (new FetchAlbumArtwork($album))->handle();

    expect($album->fresh()->cover_art_path)->toBeNull();
    expect(Cache::has('album-artwork-miss:'.$album->id))->toBeTrue();
});

test('skips a cached miss instead of re-asking about the same album', function () {
    Storage::fake('public');
    Http::fake([MB_SEARCH => Http::response(['release-groups' => []])]);

    $album = albumWithoutArt();
    Cache::put('album-artwork-miss:'.$album->id, true, 3600);

    (new FetchAlbumArtwork($album))->handle();

    Http::assertNothingSent();
});

test('does not record a miss when MusicBrainz throttles the request', function () {
    Storage::fake('public');
    Http::fake([MB_SEARCH => Http::response('', 503)]);

    $album = albumWithoutArt();

    // A throttle is transient: the job must fail so the queue retries it
    // rather than permanently marking the album as having no art.
    expect(fn () => (new FetchAlbumArtwork($album))->handle())->toThrow(RuntimeException::class);

    expect(Cache::has('album-artwork-miss:'.$album->id))->toBeFalse();
    expect($album->fresh()->cover_art_path)->toBeNull();
});

test('skips albums with no artist to search by', function () {
    Storage::fake('public');
    Http::fake([MB_SEARCH => Http::response(['release-groups' => []])]);

    (new FetchAlbumArtwork(albumWithoutArt(['artist' => ''])))->handle();

    Http::assertNothingSent();
});

test('escapes Lucene syntax in titles so they are searched literally', function () {
    Storage::fake('public');

    Http::fake([
        MB_SEARCH => Http::response(['release-groups' => []]),
        CAA_FRONT => Http::response('', 404),
    ]);

    (new FetchAlbumArtwork(albumWithoutArt(['title' => 'Sign(s) Of "Love"'])))->handle();

    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['query'] ?? '') === 'releasegroup:"Sign(s) Of Love" AND artist:"Justin Timberlake"';
    });
});

test('command only targets albums that are missing artwork', function () {
    Storage::fake('public');
    Http::fake([MB_SEARCH => Http::response(['release-groups' => []])]);

    $missing = albumWithoutArt();
    $present = albumWithoutArt();
    $present->storeCoverArt(coverImageBytes(), ArtworkSource::Embedded);

    $this->artisan('albums:fetch-missing-artwork --sync')
        ->expectsOutputToContain('Processed 1 album(s)')
        ->assertSuccessful();

    Http::assertSentCount(1);
    expect($missing->fresh()->cover_art_path)->toBeNull();
});

test('command honours the limit option', function () {
    Storage::fake('public');
    Http::fake([MB_SEARCH => Http::response(['release-groups' => []])]);

    albumWithoutArt(['title' => 'One', 'artist' => 'Artist One']);
    albumWithoutArt(['title' => 'Two', 'artist' => 'Artist Two']);
    albumWithoutArt(['title' => 'Three', 'artist' => 'Artist Three']);

    $this->artisan('albums:fetch-missing-artwork --sync --limit=2')
        ->expectsOutputToContain('Processed 2 album(s)')
        ->assertSuccessful();

    Http::assertSentCount(2);
});
