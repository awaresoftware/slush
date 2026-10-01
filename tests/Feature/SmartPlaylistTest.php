<?php

use App\Actions\ResolveSmartPlaylist;
use App\Livewire\AddToPlaylistModal;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

function smartPlaylist(User $user, array $rules): Playlist
{
    return Playlist::create([
        'user_id' => $user->id,
        'name' => 'Smart Test',
        'rules' => $rules,
    ]);
}

function resolveTitles(Playlist $playlist): array
{
    return app(ResolveSmartPlaylist::class)($playlist)->pluck('title')->all();
}

// ── engine: operators ─────────────────────────────────────────────────────────

it('matches string fields with contains, is, is_not and starts_with', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Blue Monday', 'artist' => 'New Order']);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Kind of Blue', 'artist' => 'Miles Davis']);

    $contains = smartPlaylist($user, ['match' => 'all', 'rules' => [['field' => 'title', 'op' => 'contains', 'value' => 'blue']]]);
    expect(resolveTitles($contains))->toBe(['Blue Monday', 'Kind of Blue']);

    $is = smartPlaylist($user, ['match' => 'all', 'rules' => [['field' => 'artist', 'op' => 'is', 'value' => 'Miles Davis']]]);
    expect(resolveTitles($is))->toBe(['Kind of Blue']);

    $isNot = smartPlaylist($user, ['match' => 'all', 'rules' => [['field' => 'artist', 'op' => 'is_not', 'value' => 'Miles Davis']]]);
    expect(resolveTitles($isNot))->toBe(['Blue Monday']);

    $starts = smartPlaylist($user, ['match' => 'all', 'rules' => [['field' => 'title', 'op' => 'starts_with', 'value' => 'Blue']]]);
    expect(resolveTitles($starts))->toBe(['Blue Monday']);
});

it('matches numeric fields with is, gt and lt', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Old', 'year' => 1971, 'play_count' => 2]);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'New', 'year' => 2020, 'play_count' => 9]);

    expect(resolveTitles(smartPlaylist($user, ['rules' => [['field' => 'year', 'op' => 'lt', 'value' => 2000]]])))->toBe(['Old'])
        ->and(resolveTitles(smartPlaylist($user, ['rules' => [['field' => 'year', 'op' => 'gt', 'value' => 2000]]])))->toBe(['New'])
        ->and(resolveTitles(smartPlaylist($user, ['rules' => [['field' => 'play_count', 'op' => 'is', 'value' => 9]]])))->toBe(['New']);
});

it('matches favourites and recency windows', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Loved', 'favorited_at' => now(), 'created_at' => now()->subDays(40)]);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Fresh', 'created_at' => now()->subDays(2), 'last_played_at' => now()->subDay()]);

    expect(resolveTitles(smartPlaylist($user, ['rules' => [['field' => 'favorited', 'op' => 'is_true']]])))->toBe(['Loved'])
        ->and(resolveTitles(smartPlaylist($user, ['rules' => [['field' => 'favorited', 'op' => 'is_false']]])))->toBe(['Fresh'])
        ->and(resolveTitles(smartPlaylist($user, ['rules' => [['field' => 'added_in_last_days', 'op' => 'within', 'value' => 7]]])))->toBe(['Fresh'])
        ->and(resolveTitles(smartPlaylist($user, ['rules' => [['field' => 'played_in_last_days', 'op' => 'within', 'value' => 7]]])))->toBe(['Fresh']);
});

it('combines rules with all versus any', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Jazz Loved', 'genre' => 'Jazz', 'favorited_at' => now()]);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Jazz Plain', 'genre' => 'Jazz']);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Rock Loved', 'genre' => 'Rock', 'favorited_at' => now()]);

    $rules = [
        ['field' => 'genre', 'op' => 'is', 'value' => 'Jazz'],
        ['field' => 'favorited', 'op' => 'is_true'],
    ];

    expect(resolveTitles(smartPlaylist($user, ['match' => 'all', 'rules' => $rules])))->toBe(['Jazz Loved'])
        ->and(resolveTitles(smartPlaylist($user, ['match' => 'any', 'rules' => $rules])))->toBe(['Jazz Loved', 'Jazz Plain', 'Rock Loved']);
});

it('applies sort and limit', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Aaa', 'play_count' => 1]);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Bbb', 'play_count' => 10]);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Ccc', 'play_count' => 5]);

    $playlist = smartPlaylist($user, [
        'rules' => [['field' => 'title', 'op' => 'contains', 'value' => '']],
        'sort' => 'play_count',
        'limit' => 2,
    ]);

    expect(resolveTitles($playlist))->toBe(['Bbb', 'Ccc']);
});

it('is scoped to the owner and survives hostile rules', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    Track::factory()->create(['user_id' => $owner->id, 'title' => 'Mine']);
    Track::factory()->create(['user_id' => $other->id, 'title' => 'Theirs']);

    $playlist = smartPlaylist($owner, ['rules' => [
        ['field' => 'user_id', 'op' => 'is', 'value' => $other->id],       // unknown field: skipped
        ['field' => 'title', 'op' => 'drop table', 'value' => 'x'],        // unknown op: skipped
        'garbage',                                                          // not even an array
        ['field' => 'title', 'op' => 'contains', 'value' => '%'],           // wildcard neutralised
    ]]);

    expect(resolveTitles($playlist))->toBe(['Mine']);
});

// ── integration ───────────────────────────────────────────────────────────────

it('shows resolved tracks on the playlist page', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Matching Song', 'genre' => 'Jazz']);
    Track::factory()->create(['user_id' => $user->id, 'title' => 'Other Song', 'genre' => 'Rock']);

    $playlist = smartPlaylist($user, ['rules' => [['field' => 'genre', 'op' => 'is', 'value' => 'Jazz']]]);

    $this->actingAs($user)
        ->get(route('playlists.show', $playlist))
        ->assertOk()
        ->assertSee('Matching Song')
        ->assertSee('Smart')
        ->assertDontSee('Other Song');
});

it('rejects manual track changes on smart playlists', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id]);
    $playlist = smartPlaylist($user, ['rules' => [['field' => 'favorited', 'op' => 'is_true']]]);

    Livewire::actingAs($user)
        ->test(AddToPlaylistModal::class)
        ->call('openForTrack', $track->id)
        ->call('addToPlaylist', $playlist->id)
        ->assertStatus(422);

    expect($playlist->fresh()->tracks)->toHaveCount(0);
});

it('redirects the classic edit page to the smart editor', function () {
    $user = User::factory()->create();
    $playlist = smartPlaylist($user, ['rules' => [['field' => 'favorited', 'op' => 'is_true']]]);

    $this->actingAs($user)
        ->get(route('playlists.edit', $playlist))
        ->assertRedirect(route('playlists.smart.edit', $playlist));
});

it('hides smart playlists from the add-to-playlist modal', function () {
    $user = User::factory()->create();
    Playlist::factory()->create(['user_id' => $user->id, 'name' => 'Manual List']);
    smartPlaylist($user, ['rules' => [['field' => 'favorited', 'op' => 'is_true']]]);

    $playlists = Livewire::actingAs($user)
        ->test(AddToPlaylistModal::class)
        ->instance()->userPlaylists;

    expect($playlists->pluck('name')->all())->toBe(['Manual List']);
});

// ── editor ────────────────────────────────────────────────────────────────────

it('creates a smart playlist from the editor', function () {
    $user = User::factory()->create();
    Track::factory()->create(['user_id' => $user->id, 'genre' => 'Ambient', 'title' => 'Drift']);

    Livewire::actingAs($user)
        ->test('pages::playlists.smart-editor')
        ->set('name', 'Ambient Nights')
        ->set('rules', [['field' => 'genre', 'op' => 'is', 'value' => 'Ambient']])
        ->set('sort', 'recently_added')
        ->set('limit', 25)
        ->call('save')
        ->assertHasNoErrors();

    $playlist = Playlist::where('name', 'Ambient Nights')->first();

    expect($playlist)->not->toBeNull()
        ->and($playlist->isSmart())->toBeTrue()
        ->and($playlist->rules['sort'])->toBe('recently_added')
        ->and(resolveTitles($playlist))->toBe(['Drift']);
});

it('previews matches live in the editor', function () {
    $user = User::factory()->create();
    Track::factory()->count(3)->create(['user_id' => $user->id, 'genre' => 'Jazz']);
    Track::factory()->create(['user_id' => $user->id, 'genre' => 'Rock']);

    $component = Livewire::actingAs($user)
        ->test('pages::playlists.smart-editor')
        ->set('rules', [['field' => 'genre', 'op' => 'is', 'value' => 'Jazz']]);

    expect($component->instance()->preview['count'])->toBe(3);
});

it('rejects an editor payload with an unknown field', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::playlists.smart-editor')
        ->set('name', 'Bad')
        ->set('rules', [['field' => 'password', 'op' => 'contains', 'value' => 'x']])
        ->call('save')
        ->assertHasErrors(['rules.0.field']);
});

it('loads an existing smart playlist into the editor and blocks strangers', function () {
    $owner = User::factory()->create();
    $playlist = smartPlaylist($owner, ['match' => 'any', 'rules' => [['field' => 'genre', 'op' => 'is', 'value' => 'Jazz']], 'sort' => 'random', 'limit' => 10]);

    Livewire::actingAs($owner)
        ->test('pages::playlists.smart-editor', ['playlist' => $playlist])
        ->assertSet('name', 'Smart Test')
        ->assertSet('matchMode', 'any')
        ->assertSet('sort', 'random')
        ->assertSet('limit', 10);

    $this->actingAs(User::factory()->create())
        ->get(route('playlists.smart.edit', $playlist))
        ->assertForbidden();
});
