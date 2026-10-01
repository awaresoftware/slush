<?php

use App\Enums\ArtworkSource;
use App\Jobs\FetchAlbumArtwork;
use App\Livewire\ArtworkModal;
use App\Models\Album;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function ownedAlbumForArtwork(array $attributes = []): Album
{
    return Album::factory()->create($attributes + [
        'user_id' => User::factory()->create()->id,
        'title' => 'Justified',
        'artist' => 'Justin Timberlake',
        'cover_art_path' => null,
    ]);
}

function pickerImageBytes(array $rgb = [120, 60, 200]): string
{
    $image = imagecreatetruecolor(600, 600);
    imagefill($image, 0, 0, imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]));

    ob_start();
    imagejpeg($image, null, 90);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

function fakeArtworkSearch(): void
{
    Http::fake([
        'itunes.apple.com/*' => Http::response(['results' => [[
            'collectionId' => 1441,
            'collectionName' => 'Justified',
            'artistName' => 'Justin Timberlake',
            'artworkUrl100' => 'https://is1-ssl.mzstatic.com/image/thumb/Music/100x100bb.jpg',
            'releaseDate' => '2002-11-05T08:00:00Z',
            'trackCount' => 13,
            'primaryGenreName' => 'R&B',
        ]]]),
        'api.deezer.com/*' => Http::response(['data' => [[
            'id' => 302127,
            'title' => 'Justified',
            'artist' => ['name' => 'Justin Timberlake'],
            'cover_xl' => 'https://e-cdns-images.dzcdn.net/images/cover/600x600.jpg',
        ]]]),
        'is1-ssl.mzstatic.com/*' => Http::response(pickerImageBytes(), 200, ['Content-Type' => 'image/jpeg']),
        'e-cdns-images.dzcdn.net/*' => Http::response(pickerImageBytes([10, 200, 90]), 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

test('opening the modal pre-fills the query and searches nothing', function () {
    Storage::fake('public');
    fakeArtworkSearch();

    $user = User::factory()->create();
    $album = ownedAlbumForArtwork(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ArtworkModal::class)
        ->call('openForAlbum', $album->id)
        ->assertSet('showModal', true)
        ->assertSet('query', 'Justified Justin Timberlake')
        ->assertSet('results', []);

    Http::assertNothingSent();
});

test('the pre-filled query drops a missing artist rather than leaving a trailing space', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $album = ownedAlbumForArtwork(['user_id' => $user->id, 'artist' => null]);

    Livewire::actingAs($user)
        ->test(ArtworkModal::class)
        ->call('openForAlbum', $album->id)
        ->assertSet('query', 'Justified');
});

test('searching populates results for the selected provider', function () {
    Storage::fake('public');
    fakeArtworkSearch();

    $user = User::factory()->create();
    $album = ownedAlbumForArtwork(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test(ArtworkModal::class)
        ->call('openForAlbum', $album->id)
        ->call('search')
        ->assertSet('provider', 'itunes');

    expect($component->get('results'))->toHaveCount(1)
        ->and($component->get('results')[0]['provider'])->toBe('itunes');

    $component->set('provider', 'deezer')->call('search');

    expect($component->get('results')[0]['provider'])->toBe('deezer');
});

test('applying a result stores it as a manual cover', function () {
    Storage::fake('public');
    fakeArtworkSearch();

    $user = User::factory()->create();
    $album = ownedAlbumForArtwork(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ArtworkModal::class)
        ->call('openForAlbum', $album->id)
        ->call('search')
        ->call('applyRemote', 'itunes', '1441');

    $expected = 'covers/'.$album->id.'.webp';
    Storage::disk('public')->assertExists($expected);
    expect($album->fresh()->cover_art_path)->toBe($expected)
        ->and($album->fresh()->artwork_source)->toBe(ArtworkSource::Manual);
});

test('a result id that is not in the result set is refused without any outbound fetch', function () {
    Storage::fake('public');
    fakeArtworkSearch();

    $user = User::factory()->create();
    $album = ownedAlbumForArtwork(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test(ArtworkModal::class)
        ->call('openForAlbum', $album->id)
        ->call('search')
        ->call('applyRemote', 'itunes', 'not-a-real-id');

    expect($album->fresh()->cover_art_path)->toBeNull();
    $component->assertSet('error', __('That result is no longer available. Please search again.'));

    // Only the search went out; the hostile id never reached the network.
    Http::assertSentCount(1);
});

test('a provider image that is not an image reports an error instead of crashing', function () {
    Storage::fake('public');
    Http::fake([
        'itunes.apple.com/*' => Http::response(['results' => [[
            'collectionId' => 7,
            'collectionName' => 'Broken',
            'artistName' => 'Nobody',
            'artworkUrl100' => 'https://cdn.example.com/100x100bb.jpg',
        ]]]),
        'cdn.example.com/*' => Http::response('<html>not an image</html>', 200),
    ]);

    $user = User::factory()->create();
    $album = ownedAlbumForArtwork(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test(ArtworkModal::class)
        ->call('openForAlbum', $album->id)
        ->call('search')
        ->call('applyRemote', 'itunes', '7');

    $component->assertSet('error', __('That image could not be used. Try another one.'));
    expect($album->fresh()->cover_art_path)->toBeNull();
});

test('uploading a local image stores it as an uploaded cover', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $album = ownedAlbumForArtwork(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ArtworkModal::class)
        ->call('openForAlbum', $album->id)
        // 600px so it clears the same minimum-edge gate a real upload must.
        ->set('coverFile', UploadedFile::fake()->image('cover.jpg', 600, 600)->size(100))
        ->call('upload');

    $expected = 'covers/'.$album->id.'.webp';
    Storage::disk('public')->assertExists($expected);
    expect($album->fresh()->cover_art_path)->toBe($expected)
        ->and($album->fresh()->artwork_source)->toBe(ArtworkSource::Uploaded);
});

test('remove clears both columns and deletes the file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $album = ownedAlbumForArtwork(['user_id' => $user->id]);
    $path = $album->storeCoverArt(pickerImageBytes(), ArtworkSource::Manual);

    Livewire::actingAs($user)
        ->test(ArtworkModal::class)
        ->call('openForAlbum', $album->id)
        ->call('remove');

    Storage::disk('public')->assertMissing($path);
    expect($album->fresh()->cover_art_path)->toBeNull()
        ->and($album->fresh()->artwork_source)->toBeNull();
});

test('reset to auto clears the label and queues a fetch', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $album = ownedAlbumForArtwork(['user_id' => $user->id]);
    $album->storeCoverArt(pickerImageBytes(), ArtworkSource::Manual);

    Livewire::actingAs($user)
        ->test(ArtworkModal::class)
        ->call('openForAlbum', $album->id)
        ->call('resetToAuto');

    expect($album->fresh()->cover_art_path)->toBeNull()
        ->and($album->fresh()->artwork_source)->toBeNull();

    Queue::assertPushed(FetchAlbumArtwork::class);
});

test('another user cannot open or mutate the album', function () {
    Storage::fake('public');
    fakeArtworkSearch();

    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $album = ownedAlbumForArtwork(['user_id' => $owner->id]);

    Livewire::actingAs($intruder)
        ->test(ArtworkModal::class)
        ->call('openForAlbum', $album->id)
        ->assertForbidden();

    Livewire::actingAs($intruder)
        ->test(ArtworkModal::class)
        ->set('albumId', $album->id)
        ->call('remove')
        ->assertForbidden();

    expect($album->fresh()->cover_art_path)->toBeNull();
});

test('a forged album id in the payload cannot reach another user album', function () {
    Storage::fake('public');

    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $album = ownedAlbumForArtwork(['user_id' => $owner->id]);
    $album->storeCoverArt(pickerImageBytes(), ArtworkSource::Manual);

    Livewire::actingAs($intruder)
        ->test(ArtworkModal::class)
        ->set('albumId', $album->id)
        ->call('remove')
        ->assertForbidden();

    expect($album->fresh()->cover_art_path)->not->toBeNull();
});

test('the twenty-first search in a minute is refused with a message', function () {
    Storage::fake('public');
    fakeArtworkSearch();

    $user = User::factory()->create();
    $album = ownedAlbumForArtwork(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test(ArtworkModal::class)
        ->call('openForAlbum', $album->id);

    for ($i = 0; $i < 20; $i++) {
        $component->call('search');
    }

    $component->call('search')
        ->assertSet('error', __('Too many searches. Please wait a moment and try again.'));
});

test('an empty result set is a successful search, not a throttle', function () {
    Storage::fake('public');
    Http::fake(['itunes.apple.com/*' => Http::response(['results' => []])]);

    $user = User::factory()->create();
    $album = ownedAlbumForArtwork(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ArtworkModal::class)
        ->call('openForAlbum', $album->id)
        ->call('search')
        ->assertSet('error', '')
        ->assertSet('results', []);
});
