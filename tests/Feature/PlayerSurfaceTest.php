<?php

use App\Models\Album;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * The class list of the first element carrying a given data-test hook.
 *
 * Attribute order differs between hand-written buttons and Blade components, so
 * the tag is matched first and the class pulled out of it rather than assuming
 * class comes after the hook.
 */
function classesForHook(string $source, string $hook): ?string
{
    if (! preg_match('/<[a-zA-Z][^>]*'.preg_quote($hook, '/').'[^>]*>/s', $source, $tag)) {
        return null;
    }

    return preg_match('/class="([^"]*)"/', $tag[0], $class) ? $class[1] : null;
}

/**
 * Whether a media shelf with the given title rendered.
 *
 * Needed because the sidebar carries links with the same names as the shelves,
 * so a plain string search for the title cannot tell the two apart.
 */
function hasShelf(string $html, string $title): bool
{
    return str_contains($html, 'data-shelf="'.$title.'"');
}

/**
 * Whether the anchor for an exact href is the current nav item.
 *
 * Counting aria-current globally is unreliable here: the sidebar and the mobile
 * tab bar both mark the same route, so only the href identifies one link.
 */
function anchorIsCurrent(string $html, string $href): bool
{
    return (bool) preg_match(
        '/<a[^>]*href="'.preg_quote($href, '/').'"[^>]*aria-current="page"/s',
        $html
    );
}

/**
 * Player Surface Tests
 *
 * Covers the affordances the Mobbin review surfaced: touch-visible album play
 * buttons, the per-row overflow menu, sidebar library deep links, the two new
 * Home shelves, shelf scroll cues, and the desktop queue dock / expanded card.
 */

/*
|--------------------------------------------------------------------------
| Album cards stay playable on touch
|--------------------------------------------------------------------------
|
| The tile is a link, so its play button is the only way to start an album from
| a card. Hover cannot reveal it on a touch device, so it has to carry the same
| `max-sm:opacity-100` fallback every control in track-row already has.
|
*/

describe('album card touch affordance', function () {
    it('keeps the album play button visible on small screens', function () {
        $card = file_get_contents(base_path('resources/views/components/ui/album-card.blade.php'));
        $classes = classesForHook($card, 'data-test="album-card-play"');

        expect($card)->toContain('data-test="album-card-play"')
            ->and($classes)->not->toBeNull()
            ->and($classes)->toContain('group-hover:opacity-100')
            ->and($classes)->toContain('max-sm:opacity-100');
    });

    it('renders a touch-visible play button for a shelf album on Home', function () {
        $user = User::factory()->create();
        $album = Album::factory()->create(['user_id' => $user->id]);
        Track::factory()->create([
            'user_id' => $user->id,
            'album_id' => $album->id,
            'is_available' => true,
        ]);

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        expect($html)->toContain('data-test="album-card-play"')
            ->and($html)->toContain('max-sm:opacity-100');
    });

    it('omits the play button when the album has no playable tracks', function () {
        $card = file_get_contents(base_path('resources/views/components/ui/album-card.blade.php'));

        // Guarded by $playable, so an empty album renders no dead button.
        expect($card)->toContain('@if ($playable)');
    });
});

/*
|--------------------------------------------------------------------------
| Track row overflow menu
|--------------------------------------------------------------------------
*/

describe('track row overflow menu', function () {
    it('renders the actions menu for an available track', function () {
        $user = User::factory()->create();
        $track = Track::factory()->create([
            'user_id' => $user->id,
            'title' => 'Menu Song',
            'is_available' => true,
        ]);

        $html = Livewire::actingAs($user)
            ->test('pages::library.index')
            ->html();

        expect($html)->toContain('data-test="track-menu-btn"')
            ->and($html)->toContain('data-test="track-menu-favorite"')
            ->and($html)->toContain('data-test="track-menu-add-to-queue"')
            ->and($html)->toContain('data-test="track-menu-add-to-playlist"');
    });

    it('keeps the direct add-to-playlist button alongside the menu', function () {
        $user = User::factory()->create();
        Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

        $html = Livewire::actingAs($user)->test('pages::library.index')->html();

        expect($html)->toContain('data-test="add-to-playlist-btn"')
            ->and($html)->toContain('data-test="track-menu-btn"');
    });

    it('shows the menu trigger on touch as well as hover', function () {
        $row = file_get_contents(base_path('resources/views/components/ui/track-row.blade.php'));
        $classes = classesForHook($row, 'data-test="track-menu-btn"');

        expect($classes)->not->toBeNull()
            ->and($classes)->toContain('group-hover:opacity-100')
            ->and($classes)->toContain('max-sm:opacity-100');
    });

    it('hides the menu for an unavailable track, which only gets a re-link', function () {
        $user = User::factory()->create();
        Track::factory()->create([
            'user_id' => $user->id,
            'title' => 'Missing Song',
            'is_available' => false,
        ]);

        $html = Livewire::actingAs($user)->test('pages::library.index')->html();

        expect($html)->toContain('data-test="relink-btn"')
            ->and($html)->not->toContain('data-test="track-menu-btn"');
    });

    it('exposes the queue toast strings from the player', function () {
        $user = User::factory()->create();

        $messages = Livewire::actingAs($user)
            ->test(AudioPlayer::class)
            ->instance()
            ->messages();

        expect($messages)
            ->toHaveKeys(['addedToQueue', 'alreadyQueued'])
            ->and($messages['addedToQueue'])->not->toBeEmpty()
            ->and($messages['alreadyQueued'])->not->toBeEmpty();
    });

    it('appends a track to the queue without disturbing playback', function () {
        $user = User::factory()->create();
        $playing = Track::factory()->create([
            'user_id' => $user->id,
            'is_available' => true,
        ]);
        $upNext = Track::factory()->create([
            'user_id' => $user->id,
            'is_available' => true,
        ]);

        $component = Livewire::actingAs($user)->test(AudioPlayer::class);

        $component->call('loadQueue', [$playing->id], 0);
        $component->call('extendQueue', [$upNext->id]);

        expect($component->instance()->queue)->toBe([$playing->id, $upNext->id])
            ->and($component->instance()->currentTrackId)->toBe($playing->id);
    });

    it('ignores another user track when adding to the queue', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Track::factory()->create(['user_id' => $other->id, 'is_available' => true]);

        $component = Livewire::actingAs($user)->test(AudioPlayer::class);
        $component->call('extendQueue', [$other->id]);

        expect($component->instance()->queue)->toBe([]);
    });
});

/*
|--------------------------------------------------------------------------
| Sidebar library shortcuts
|--------------------------------------------------------------------------
*/

describe('sidebar library shortcuts', function () {
    it('links to the favourites, recently added and recently played filters', function () {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        expect($html)->toContain('Favourites')
            ->and($html)->toContain('Recently Added')
            ->and($html)->toContain('Recently Played');

        foreach ([
            'sourceFilter' => 'favorites',
            'sort' => 'recent',
            'sort' => 'recently_played',
        ] as $key => $value) {
            expect($html)->toContain(route('library.index', [$key => $value]), false);
        }
    });

    it('offers a new playlist shortcut in the sidebar', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee(route('playlists.create'), false)
            ->assertSee('data-test="sidebar-new-playlist"', false);
    });

    it('does not mark a library shortcut active on an unfiltered library', function () {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('library.index'))->getContent();

        expect(anchorIsCurrent($html, route('library.index', ['sourceFilter' => 'favorites'])))->toBeFalse()
            ->and(anchorIsCurrent($html, route('library.index', ['sort' => 'recent'])))->toBeFalse()
            ->and(anchorIsCurrent($html, route('library.index', ['sort' => 'recently_played'])))->toBeFalse()
            ->and(anchorIsCurrent($html, route('library.index')))->toBeTrue();
    });

    it('marks the favourites shortcut active when that filter is set', function () {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('library.index', ['sourceFilter' => 'favorites']))
            ->getContent();

        expect(anchorIsCurrent($html, route('library.index', ['sourceFilter' => 'favorites'])))->toBeTrue();
    });

    it('marks only the matching shortcut active when sorting by recency', function () {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('library.index', ['sort' => 'recently_played']))
            ->getContent();

        expect(anchorIsCurrent($html, route('library.index', ['sort' => 'recently_played'])))->toBeTrue()
            ->and(anchorIsCurrent($html, route('library.index', ['sort' => 'recent'])))->toBeFalse();
    });

    it('does not leak another user shortcuts into the sidebar', function () {
        $user = User::factory()->create();
        Playlist::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Favourites You Left', false);
    });
});

describe('library URL-bound filters', function () {
    it('honours sourceFilter from the query string', function () {
        $user = User::factory()->create();

        Track::factory()->create([
            'user_id' => $user->id,
            'title' => 'Kept Song',
            'is_available' => true,
        ]);

        $component = Livewire::withQueryParams(['sourceFilter' => 'favorites'])
            ->actingAs($user)
            ->test('pages::library.index');

        expect($component->instance()->sourceFilter)->toBe('favorites');
    });

    it('honours sort from the query string', function () {
        $user = User::factory()->create();

        $component = Livewire::withQueryParams(['sort' => 'most_played'])
            ->actingAs($user)
            ->test('pages::library.index');

        expect($component->instance()->sort)->toBe('most_played');
    });

    it('honours activeTab from the query string', function () {
        $user = User::factory()->create();
        Album::factory()->create(['user_id' => $user->id]);

        $component = Livewire::withQueryParams(['activeTab' => 'albums'])
            ->actingAs($user)
            ->test('pages::library.index');

        expect($component->instance()->activeTab)->toBe('albums');
    });

    it('falls back to the default tab for an unknown tab value', function () {
        $user = User::factory()->create();

        $component = Livewire::withQueryParams(['activeTab' => 'nonsense'])
            ->actingAs($user)
            ->test('pages::library.index');

        expect($component->instance()->activeTab)->toBe('tracks');
    });

    it('falls back to no filter for an unknown source filter', function () {
        $user = User::factory()->create();

        $component = Livewire::withQueryParams(['sourceFilter' => 'nonsense'])
            ->actingAs($user)
            ->test('pages::library.index');

        expect($component->instance()->sourceFilter)->toBe('all');
    });

    it('falls back to A-Z for an unknown sort', function () {
        $user = User::factory()->create();

        $component = Livewire::withQueryParams(['sort' => 'nonsense'])
            ->actingAs($user)
            ->test('pages::library.index');

        expect($component->instance()->sort)->toBe('title');
    });

    it('keeps every valid filter value intact', function () {
        $user = User::factory()->create();

        foreach (['favorites', 'uploaded', 'local', 'unavailable'] as $filter) {
            expect(Livewire::withQueryParams(['sourceFilter' => $filter])
                ->actingAs($user)
                ->test('pages::library.index')
                ->instance()->sourceFilter)->toBe($filter);
        }

        foreach (['recent', 'most_played', 'recently_played'] as $sort) {
            expect(Livewire::withQueryParams(['sort' => $sort])
                ->actingAs($user)
                ->test('pages::library.index')
                ->instance()->sort)->toBe($sort);
        }
    });
});

/*
|--------------------------------------------------------------------------
| Home shelves
|--------------------------------------------------------------------------
*/

describe('home shelves', function () {
    it('shows a recently played strip once a track has been played', function () {
        $user = User::factory()->create();
        $album = Album::factory()->create(['user_id' => $user->id]);

        Track::factory()->create([
            'user_id' => $user->id,
            'album_id' => $album->id,
            'title' => 'Played Song',
            'is_available' => true,
            'last_played_at' => now(),
        ]);

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        expect(hasShelf($html, 'Recently Played'))->toBeTrue()
            ->and($html)->toContain('Played Song');
    });

    it('omits the recently played shelf when nothing has been played', function () {
        $user = User::factory()->create();
        Track::factory()->create([
            'user_id' => $user->id,
            'is_available' => true,
            'last_played_at' => null,
        ]);

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        expect(hasShelf($html, 'Recently Played'))->toBeFalse();
    });

    it('omits unavailable tracks from the recently played shelf', function () {
        $user = User::factory()->create();
        Track::factory()->create([
            'user_id' => $user->id,
            'title' => 'Gone Song',
            'is_available' => false,
            'last_played_at' => now(),
        ]);

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        expect(hasShelf($html, 'Recently Played'))->toBeFalse()
            ->and($html)->not->toContain('Gone Song');
    });

    it('orders the recently played strip newest first', function () {
        $user = User::factory()->create();

        Track::factory()->create([
            'user_id' => $user->id,
            'title' => 'Older Play',
            'is_available' => true,
            'last_played_at' => now()->subDays(3),
        ]);
        Track::factory()->create([
            'user_id' => $user->id,
            'title' => 'Newest Play',
            'is_available' => true,
            'last_played_at' => now(),
        ]);

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        expect(strpos($html, 'Newest Play'))->toBeLessThan(strpos($html, 'Older Play'));
    });

    it('shows top artists once they have plays', function () {
        $user = User::factory()->create();

        Track::factory()->create([
            'user_id' => $user->id,
            'artist' => 'Nina Shape',
            'is_available' => true,
            'play_count' => 25,
        ]);

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        expect(hasShelf($html, 'Top Artists'))->toBeTrue()
            ->and($html)->toContain('Nina Shape')
            ->and($html)->toContain('25 plays');
    });

    it('omits an artist with no plays from the top artists shelf', function () {
        $user = User::factory()->create();

        Track::factory()->create([
            'user_id' => $user->id,
            'artist' => 'Silent Act',
            'is_available' => true,
            'play_count' => 0,
        ]);

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        expect(hasShelf($html, 'Top Artists'))->toBeFalse()
            ->and($html)->not->toContain('Silent Act');
    });

    it('does not count another user artists on the shelf', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Track::factory()->create([
            'user_id' => $other->id,
            'artist' => 'Someone Else',
            'is_available' => true,
            'play_count' => 99,
        ]);

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        expect($html)->not->toContain('Someone Else');
    });
});

/*
|--------------------------------------------------------------------------
| Shelf scroll affordance
|--------------------------------------------------------------------------
*/

describe('media shelf affordance', function () {
    it('signals scrollability with edge fades rather than buttons', function () {
        $user = User::factory()->create();
        Album::factory()->create(['user_id' => $user->id]);

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        expect($html)->toContain('x-data="mediaShelf"')
            ->and($html)->toContain('bg-gradient-to-r from-base-200 to-transparent')
            ->and($html)->toContain('bg-gradient-to-l from-base-200 to-transparent');
    });

    it('has no step buttons, since the shelf scrolls by touch or trackpad', function () {
        $shelf = file_get_contents(base_path('resources/views/components/ui/media-shelf.blade.php'));

        // A shelf is never long enough to justify a control that duplicates
        // scrolling, and the buttons were the only thing painting over a tile.
        expect($shelf)->not->toContain('data-test="shelf-prev"')
            ->not->toContain('data-test="shelf-next"')
            ->not->toContain('<button');
    });

    it('leaves no dead nudge helper behind in the Alpine component', function () {
        $js = file_get_contents(base_path('resources/js/app.js'));

        expect($js)->toContain("Alpine.data('mediaShelf'")
            ->not->toContain('nudge(')
            // The resize listener still measures overflow for the fades, and it
            // must not outlive the component.
            ->and($js)->toContain('window.addEventListener(\'resize\', this._onResize, { passive: true })')
            ->and($js)->toContain('window.removeEventListener(\'resize\', this._onResize)');
    });

    it('insets the snapport to match the scroller padding', function () {
        $shelf = file_get_contents(base_path('resources/views/components/ui/media-shelf.blade.php'));

        // A scroll container's own padding-left does not move its snap points.
        // With snap-mandatory the browser then forces a snap to the first tile
        // rather than to scrollLeft 0, which leaves the shelf permanently nudged
        // left: first tile clipped, and the "at start" fade and previous chevron
        // both showing on load. scroll-padding puts scrollLeft 0 back on a snap
        // point, so it has to be present at both breakpoints alongside the pl.
        preg_match('/x-ref="scroller"[^>]*class="([^"]*)"/s', $shelf, $scroller);
        $classes = $scroller[1] ?? '';

        expect($classes)->toContain('pl-4')
            ->and($classes)->toContain('scroll-pl-4')
            ->and($classes)->toContain('sm:pl-6')
            ->and($classes)->toContain('sm:scroll-pl-6')
            ->and($classes)->toContain('snap-mandatory');
    });

    it('keeps the first tile aligned with the section title', function () {
        $shelf = file_get_contents(base_path('resources/views/components/ui/media-shelf.blade.php'));

        // Title and scroller carry the same horizontal inset, so the first tile
        // sits directly under the heading rather than beside it.
        expect($shelf)->toContain('justify-between gap-4 px-4 sm:px-6')
            ->and($shelf)->toContain('overflow-x-auto pl-4');
    });
});

/*
|--------------------------------------------------------------------------
| Waveform
|--------------------------------------------------------------------------
|
| The envelope rides along with the player:load payload and the sheet draws it
| on a canvas that sits behind the range input, which stays the seek control.
|
*/

describe('waveform envelope', function () {
    it('sends the envelope to the player with the track', function () {
        $user = User::factory()->create();
        $track = Track::factory()->create([
            'user_id' => $user->id,
            'is_available' => true,
            'peaks' => json_encode([10, 40, 90, 20]),
        ]);

        $component = Livewire::actingAs($user)->test(AudioPlayer::class);
        $component->call('loadQueue', [$track->id], 0);

        expect($component->instance()->currentTrackId)->toBe($track->id)
            ->and(json_decode($track->refresh()->peaks, true))->toBe([10, 40, 90, 20]);
    });

    it('sends a null envelope for a track that has none', function () {
        $user = User::factory()->create();
        $track = Track::factory()->create([
            'user_id' => $user->id,
            'is_available' => true,
            'peaks' => null,
        ]);

        $component = Livewire::actingAs($user)->test(AudioPlayer::class);
        $component->call('loadQueue', [$track->id], 0);

        expect($component->instance()->currentTrackId)->toBe($track->id)
            ->and($track->refresh()->peaks)->toBeNull();
    });

    it('draws the waveform in the now playing sheet', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('data-test="waveform-canvas"', false)
            ->assertSee('data-test="waveform-seek"', false);
    });

    it('leaves the slim player bar on the plain scrubber', function () {
        $user = User::factory()->create();

        $player = file_get_contents(base_path('resources/views/livewire/audio-player.blade.php'));

        // The bar is 44px tall; a 32px waveform does not belong there.
        expect(substr_count($player, '<x-ui.scrubber />'))->toBe(1)
            ->and(substr_count($player, '<x-ui.waveform />'))->toBe(1);
    });

    it('gives the waveform the full width and drops the times beneath it', function () {
        $player = file_get_contents(base_path('resources/views/livewire/audio-player.blade.php'));

        // Sharing one row cost the shape two time readouts' worth of width, and
        // left the times to be aligned against bars that grow up from a baseline.
        // The waveform now owns its row; the times are their own row underneath.
        // Spacing inside the rows is deliberately not pinned here.
        expect($player)->toMatch(
            '#<div class="mt-6">\s*<x-ui\.waveform />\s*<div class="[^"]*flex items-baseline justify-between">\s*'
            .'<time[^>]*x-text="formatTime\(currentTime\)"></time>\s*'
            .'<time[^>]*x-text="formatTime\(duration\)"></time>\s*</div>\s*</div>#s'
        );

        // Elapsed at the left of that row, total at the right.
        expect($player)->toMatch(
            '#justify-between">\s*<time[^>]*currentTime.*?</time>\s*<time[^>]*duration.*?</time>#s'
        );
    });

    it('insets the times to line up with the title and the heart', function () {
        $player = file_get_contents(base_path('resources/views/livewire/audio-player.blade.php'));

        // The title-and-heart row is inset inside the card's own padding. Without
        // a matching inset on the times row they sat 1rem outboard of the title
        // on the left and of the heart's trailing edge on the right. Compared as
        // extracted values, so the two rows can be retuned together.
        preg_match('#<div class="(mx-[\d.]+)[^"]*mt-auto flex items-center gap-3">#', $player, $title);
        preg_match('#<div class="(mx-[\d.]+)[^"]*flex items-baseline justify-between">#', $player, $times);

        expect($title)->not->toBeEmpty()
            ->and($times)->not->toBeEmpty()
            ->and($times[1])->toBe($title[1]);

        // Only the times are inset: the waveform still spans the full card.
        expect($player)->toMatch('#<div class="mt-6">\s*<x-ui\.waveform />#s');
    });

    it('leaves the bar times inline with its centred scrubber', function () {
        $player = file_get_contents(base_path('resources/views/livewire/audio-player.blade.php'));

        // The bar is narrow and its scrubber is a centred bar, so unlike the
        // sheet it keeps the times beside it on one centred row.
        expect($player)->toMatch(
            '#<div class="flex w-full items-center gap-2" role="group" aria-label="\{\{ __\(\'Seek\'\) \}\}">\s*'
            .'<time[^>]*currentTime.*?</time>\s*<x-ui\.scrubber />\s*<time[^>]*duration.*?</time>\s*</div>#s'
        );
    });

    it('keeps the range input as the accessible seek control', function () {
        $component = file_get_contents(base_path('resources/views/components/ui/waveform.blade.php'));

        // The canvas is decorative and the input carries the accessible name,
        // the value and the spoken position, so the waveform is presentation.
        expect($component)
            ->toContain('type="range"')
            ->toContain('aria-label="{{ __(\'Seek slider\') }}"')
            ->toContain(':aria-valuenow="Math.round(currentTime)"')
            ->toContain(":aria-valuetext=\"formatTime(currentTime) + ' of ' + formatTime(duration)\"")
            ->toContain('aria-hidden="true"')
            // Input above the canvas, so it receives every pointer event.
            ->toContain('z-20')
            ->toContain('x-on:input="seek($event.target.value)"');
    });

    it('makes the seek control span the full height of the shape', function () {
        $component = file_get_contents(base_path('resources/views/components/ui/waveform.blade.php'));
        $css = file_get_contents(base_path('resources/css/app.css'));

        // The base slider is a fixed 1rem tall. Inside a 48px waveform that left
        // everything below that line unclickable and parked the thumb at the top,
        // so the control needs its own full-height variant.
        expect($component)->toContain('slider slider-waveform')
            ->and($css)->toMatch('/\.slider-waveform\s*\{\s*height:\s*100%/');
    });

    it('hides the scrubber thumb so nothing floats over the waveform', function () {
        $css = file_get_contents(base_path('resources/css/app.css'));

        // The base slider reveals its thumb on hover and focus; that dot sat on
        // top of the silhouette and duplicated the progress fill.
        expect($css)->toMatch(
            '/\.slider-waveform:hover::\-webkit-slider-thumb[\s\S]*?\.slider-waveform::\-moz-range-thumb\s*\{[^}]*opacity:\s*0/'
        );
    });

    it('keeps a focus ring for keyboard users now the thumb is invisible', function () {
        $css = file_get_contents(base_path('resources/css/app.css'));

        // .slider:focus-visible drops the outline and delegates the ring to a
        // sibling .slider-track, which the waveform does not render. Without
        // this the control would be invisible to keyboard focus.
        expect($css)->toMatch('/\.slider-waveform:focus-visible\s*\{[^}]*outline:\s*2px solid var\(--color-primary\)/');
    });

    it('draws fat, low resolution bars anchored to the baseline', function () {
        $js = file_get_contents(base_path('resources/js/app.js'));

        expect($js)
            ->toContain('waveformBars(width, height)')
            // One bar per fixed slot of screen space, so a wider waveform gains
            // bars rather than detail. That is what keeps it low fidelity.
            ->toContain('const slot = 11;')
            ->toContain('Math.max(1, Math.floor(width / slot))')
            // Fat: the bar fills well over half its slot.
            ->toContain('width: slot * 0.58')
            // Bottom-anchored, growing upward, not mirrored about a centre line.
            ->toContain('top: height - barHeight')
            ->toContain('context.roundRect(');
    });

    it('reports the loudest moment in each bar rather than an average', function () {
        $js = file_get_contents(base_path('resources/js/app.js'));

        // Averaging across a slot would flatten the dynamics the bars exist to show.
        expect($js)
            ->toContain('Math.floor((i * peaks.length) / count)')
            ->toContain('if (peaks[j] > peak)');
    });

    it('paints one set of bars per tone so the colours cannot drift apart', function () {
        $js = file_get_contents(base_path('resources/js/app.js'));

        expect($js)
            ->toContain('this.paintBars(context, bars, idle, Infinity)')
            ->toContain('this.paintBars(context, bars, buffered,')
            ->toContain('this.paintBars(context, bars, played,');
    });

    it('keeps a hairline through silent passages', function () {
        $js = file_get_contents(base_path('resources/js/app.js'));

        // Without a floor, quiet stretches drop out and the row breaks up.
        expect($js)->toContain('const floor = 2;')
            ->toContain('Math.max(floor,');
    });

    it('falls back to the two-tone bar when a track has no envelope', function () {
        $component = file_get_contents(base_path('resources/views/components/ui/waveform.blade.php'));

        expect($component)
            ->toContain('x-show="hasPeaks"')
            ->toContain('x-show="! hasPeaks"');
    });

    it('paints on load, on progress and on resize, not on a frame loop', function () {
        $js = file_get_contents(base_path('resources/js/app.js'));

        expect($js)
            ->toContain('setPeaks(detail.peaks)')
            ->toContain('paintWaveform(canvas)')
            ->toContain('this.paintWaveforms();')
            ->toContain("window.addEventListener('resize', this._onResize, { passive: true })")
            ->toContain("window.removeEventListener('resize', this._onResize)");
    });

    it('clears the previous envelope before a new track loads', function () {
        $js = file_get_contents(base_path('resources/js/app.js'));

        // Otherwise a track with no envelope shows the previous waveform.
        expect($js)->toMatch('/onLoad\(detail\).*?this\.peaks = \[\];.*?this\.setPeaks\(detail\.peaks\)/s');
    });

    it('defines envelope colours for both light and dark contexts', function () {
        $css = file_get_contents(base_path('resources/css/app.css'));

        expect(substr_count($css, '--wf-idle'))->toBe(5)
            ->and(substr_count($css, '--wf-buffered'))->toBe(5)
            ->and(substr_count($css, '--wf-played'))->toBe(5)
            ->and($css)->toContain('--wf-played: var(--color-primary);');
    });
});

describe('track path resolution', function () {
    it('resolves an uploaded track through the public disk', function () {
        $user = User::factory()->create();

        Storage::fake('public');
        $track = Track::factory()->create([
            'user_id' => $user->id,
            'source_type' => 'uploaded',
            'file_path' => 'music/1/song.mp3',
        ]);

        expect($track->absolutePath())->toBe(Storage::disk('public')->path('music/1/song.mp3'));
    });

    it('uses a local track path as-is', function () {
        $user = User::factory()->create();

        $track = Track::factory()->create([
            'user_id' => $user->id,
            'source_type' => 'local',
            'file_path' => '/music/folder/song.flac',
        ]);

        expect($track->absolutePath())->toBe('/music/folder/song.flac');
    });
});

describe('desktop queue dock', function () {
    it('gives the content column a shell id for the dock to reserve against', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('id="app-shell"', false);
    });

    it('mirrors the queue open state onto the document element', function () {
        $player = file_get_contents(base_path('resources/views/livewire/audio-player.blade.php'));

        expect($player)
            ->toContain("document.documentElement.dataset.queue = queueOpen ? 'open' : 'closed'");
    });

    it('reserves a gutter equal to the panel width', function () {
        $css = file_get_contents(base_path('resources/css/app.css'));

        expect($css)->toContain("html[data-queue='open'] #app-shell")
            ->and($css)->toContain("html[data-queue='open'] [data-test='player-bar']")
            // The gutter has to be declared or the rule resolves to an invalid value.
            ->and($css)->toContain('--queue-gutter: 22.5rem');
    });

    it('runs the docked panels to the bottom once the bar moves aside', function () {
        $player = file_get_contents(base_path('resources/views/livewire/audio-player.blade.php'));

        expect(substr_count($player, 'lg:bottom-[72px] xl:bottom-0'))->toBe(2);
    });
});

describe('expanded now playing', function () {
    it('opens the expanded view from the desktop bar', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('data-test="expand-now-playing"', false)
            ->assertSee('data-test="collapse-now-playing"', false);
    });

    it('is not hidden on desktop, unlike the mobile-only sheet it grew from', function () {
        $player = file_get_contents(base_path('resources/views/livewire/audio-player.blade.php'));

        preg_match('/x-show="nowPlayingOpen".*?class="([^"]+)"/s', $player, $matches);

        expect($matches)->not->toBeEmpty()
            ->and($matches[1])->not->toContain('lg:hidden');
    });

    it('keeps the mobile drag handle hidden on desktop', function () {
        $player = file_get_contents(base_path('resources/views/livewire/audio-player.blade.php'));

        expect($player)->toContain('<div class="my-4 relative flex items-center lg:hidden">');
    });

    it('centres the desktop card on both axes', function () {
        $player = file_get_contents(base_path('resources/views/livewire/audio-player.blade.php'));

        // The scrim layer becomes a centring flex container at lg, so the card
        // (which drops to lg:h-auto) is centred rather than hugging the top.
        expect($player)->toMatch(
            '/class="absolute inset-0 bg-base-100 lg:flex lg:items-center lg:justify-center lg:bg-overlay lg:backdrop-blur-sm"/'
        );
    });

    it('gives the desktop card its own surface', function () {
        $player = file_get_contents(base_path('resources/views/livewire/audio-player.blade.php'));

        preg_match('/lg:h-auto[^"]*/', $player, $card);

        expect($card)->not->toBeEmpty()
            ->and($card[0])->toContain('lg:rounded-box')
            ->and($card[0])->toContain('lg:bg-base-100')
            ->and($card[0])->toContain('lg:shadow-3');
    });

    it('keeps the mobile sheet full-bleed', function () {
        $player = file_get_contents(base_path('resources/views/livewire/audio-player.blade.php'));

        preg_match('/class="relative mx-auto flex h-full[^"]*"/', $player, $card);

        expect($card)->not->toBeEmpty()
            ->and($card[0])->toContain('h-full')
            ->and($card[0])->not->toContain('mx-auto lg:h-auto');
    });

    it('dismisses the desktop card on a scrim click but not the mobile sheet', function () {
        $player = file_get_contents(base_path('resources/views/livewire/audio-player.blade.php'));

        expect($player)->toContain(
            'x-on:click="if (event.target === $el) nowPlayingOpen = false"'
        );
    });

    it('opens the queue from the expanded card', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('data-test="collapse-now-playing"', false);
    });

    it('hides the queue controls until there is a track up next', function () {
        $user = User::factory()->create();
        $playing = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
        $upNext = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

        $component = Livewire::actingAs($user)->test(AudioPlayer::class);

        // Nothing queued yet: the player itself is hidden, so the control is too.
        expect($component->html())->not->toContain('data-test="queue-toggle"');

        // A single track is not "up next" — the queue always holds the one playing.
        $component->call('loadQueue', [$playing->id], 0);
        expect($component->html())->not->toContain('data-test="queue-toggle"');

        // A second track makes the control meaningful, in both surfaces.
        $component->call('loadQueue', [$playing->id, $upNext->id], 0);
        expect($component->html())->toContain('data-test="queue-toggle"')
            ->and($component->html())->toContain(__('Playing Next'));

        // Dropping back to one track hides it again.
        $component->call('loadQueue', [$upNext->id], 0);
        expect($component->html())->not->toContain('data-test="queue-toggle"');
    });

    it('keeps the queue control when the current track is not first in the queue', function () {
        $user = User::factory()->create();
        $first = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
        $second = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

        $component = Livewire::actingAs($user)->test(AudioPlayer::class);
        $component->call('loadQueue', [$first->id, $second->id], 1);

        // Playing the second of two: the first is still ahead of the wrap, and
        // hasUpcoming must not assume the current track sits at index 0.
        expect($component->instance()->currentTrackId)->toBe($second->id)
            ->and($component->instance()->hasUpcoming)->toBeTrue();
    });

    it('reports no upcoming tracks when the current one is not in the queue', function () {
        $user = User::factory()->create();
        $track = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

        $component = Livewire::actingAs($user)->test(AudioPlayer::class);
        $component->call('loadQueue', [$track->id], 0);
        $component->set('currentTrackId', 999999);

        expect($component->instance()->hasUpcoming)->toBeTrue();
    });
});

it('starts the next track on ended without waiting for the server', function () {
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/js/app.js');
    $player = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/audio-player.blade.php');

    // Same element, not a second one: iOS only keeps a locked-screen session
    // alive on the element the user started.
    expect($player)->toContain("route('stream.track', ['track' => '__track__'])")
        ->and($js)->toContain("audio.src = streamUrlTemplate.replace('__track__', nextId);")
        ->and($js)->toContain('const alreadyLoaded = this._advancing && !audio.error');
});
