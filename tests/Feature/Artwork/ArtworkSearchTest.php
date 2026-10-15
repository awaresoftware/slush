<?php

use App\Services\ArtworkSearch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Real JPEG bytes: a candidate image has to survive the same decode and 600px
 * WebP re-encode a real cover would.
 */
function artworkSearchImageBytes(): string
{
    $image = imagecreatetruecolor(600, 600);
    imagefill($image, 0, 0, imagecolorallocate($image, 120, 60, 200));

    ob_start();
    imagejpeg($image, null, 90);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

test('itunes searches for albums and upgrades the thumbnail to 600px', function () {
    Http::fake([
        'itunes.apple.com/*' => Http::response(['results' => [[
            'collectionId' => 1441,
            'collectionName' => 'Justified',
            'artistName' => 'Justin Timberlake',
            'artworkUrl100' => 'https://is1-ssl.mzstatic.com/image/thumb/Music/v4/aa/100x100bb.jpg',
            'releaseDate' => '2002-11-05T08:00:00Z',
            'trackCount' => 13,
            'primaryGenreName' => 'R&B',
        ]]]),
    ]);

    $results = (new ArtworkSearch)->itunes('Justified Timberlake');

    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['entity'] ?? '') === 'album'
            && ($query['term'] ?? '') === 'Justified Timberlake'
            && ($query['limit'] ?? '') === '12';
    });

    expect($results)->toHaveCount(1)
        ->and($results[0]['provider'])->toBe('itunes')
        ->and($results[0]['id'])->toBe('1441')
        ->and($results[0]['title'])->toBe('Justified')
        ->and($results[0]['artist'])->toBe('Justin Timberlake')
        ->and($results[0]['image_url'])->toBe('https://is1-ssl.mzstatic.com/image/thumb/Music/v4/aa/600x600bb.jpg')
        ->and($results[0]['year'])->toBe('2002')
        ->and($results[0]['meta'])->toBe('13 tracks · R&B');
});

test('deezer searches albums and prefers the largest cover', function () {
    Http::fake([
        'api.deezer.com/*' => Http::response(['data' => [[
            'id' => 302127,
            'title' => 'Justified',
            'artist' => ['name' => 'Justin Timberlake'],
            'cover_xl' => 'https://e-cdns-images.dzcdn.net/images/cover/600x600.jpg',
            'cover_big' => 'https://e-cdns-images.dzcdn.net/images/cover/500x500.jpg',
            'nb_tracks' => 13,
            'release_date' => '2002-11-11',
        ]]]),
    ]);

    $results = (new ArtworkSearch)->deezer('Justified Timberlake');

    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['q'] ?? '') === 'Justified Timberlake' && ($query['limit'] ?? '') === '12';
    });

    expect($results)->toHaveCount(1)
        ->and($results[0]['provider'])->toBe('deezer')
        ->and($results[0]['id'])->toBe('302127')
        ->and($results[0]['artist'])->toBe('Justin Timberlake')
        ->and($results[0]['image_url'])->toBe('https://e-cdns-images.dzcdn.net/images/cover/600x600.jpg')
        ->and($results[0]['year'])->toBe('2002');
});

test('deezer falls back to cover_big when there is no extra large cover', function () {
    Http::fake([
        'api.deezer.com/*' => Http::response(['data' => [[
            'id' => 1,
            'title' => 'No Big Cover',
            'artist' => ['name' => 'Someone'],
            'cover_big' => 'https://e-cdns-images.dzcdn.net/images/cover/500x500.jpg',
        ]]]),
    ]);

    expect((new ArtworkSearch)->deezer('No Big Cover')[0]['image_url'])
        ->toBe('https://e-cdns-images.dzcdn.net/images/cover/500x500.jpg');
});

test('a provider failure leaves the other provider working', function () {
    Http::fake([
        'itunes.apple.com/*' => Http::response('boom', 500),
        'api.deezer.com/*' => Http::response(['data' => [[
            'id' => 9,
            'title' => 'Still Here',
            'artist' => ['name' => 'Deezer'],
            'cover_xl' => 'https://e-cdns-images.dzcdn.net/images/cover/600x600.jpg',
        ]]]),
    ]);

    $search = new ArtworkSearch;

    expect($search->itunes('Justified Timberlake'))->toBe([])
        ->and($search->deezer('Justified Timberlake'))->toHaveCount(1);
});

test('a connection failure is contained rather than thrown', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect((new ArtworkSearch)->itunes('anything'))->toBe([]);
});

test('results without an image or an id are skipped rather than rendered broken', function () {
    Http::fake([
        'itunes.apple.com/*' => Http::response(['results' => [
            ['collectionId' => 1, 'collectionName' => 'No Art', 'artworkUrl100' => ''],
            ['collectionId' => '', 'collectionName' => 'No Id', 'artworkUrl100' => 'https://x/100x100bb.jpg'],
            ['collectionId' => 3, 'collectionName' => 'Fine', 'artworkUrl100' => 'https://x/100x100bb.jpg'],
        ]]),
    ]);

    $results = (new ArtworkSearch)->itunes('Justified');

    expect($results)->toHaveCount(1)
        ->and($results[0]['title'])->toBe('Fine');
});

test('a malformed provider payload shortens the list instead of throwing', function () {
    Http::fake(['itunes.apple.com/*' => Http::response(['results' => 'not-a-list'])]);

    expect(fn () => (new ArtworkSearch)->itunes('Justified'))->not->toThrow(Exception::class);
});

test('both providers send the identifying User-Agent', function () {
    Http::fake([
        'itunes.apple.com/*' => Http::response(['results' => []]),
        'api.deezer.com/*' => Http::response(['data' => []]),
    ]);

    (new ArtworkSearch)->itunes('Justified');
    (new ArtworkSearch)->deezer('Justified');

    Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'foonk'));
});

test('two identical searches make one request and a changed query makes another', function () {
    Http::fake([
        'itunes.apple.com/*' => Http::response(['results' => []]),
    ]);

    $search = new ArtworkSearch;

    $search->itunes('Justified  Timberlake');
    $search->itunes('  Justified Timberlake  ');
    $search->itunes('Blue Boxes');

    // Whitespace and case differences are the same question.
    Http::assertSentCount(2);
});

test('a failed search is not cached, so the next attempt retries', function () {
    Http::fake(['itunes.apple.com/*' => Http::response('boom', 500)]);

    (new ArtworkSearch)->itunes('Justified');
    (new ArtworkSearch)->itunes('Justified');

    Http::assertSentCount(2);
    expect(Cache::has(ArtworkSearch::cacheKey('itunes', 'Justified')))->toBeFalse();
});

test('a blank query never reaches a provider', function () {
    Http::fake();

    expect((new ArtworkSearch)->itunes('   '))->toBe([]);

    Http::assertNothingSent();
});
