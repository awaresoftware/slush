---
phase: quick-06
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/pages/library/index.blade.php
  - tests/Feature/Library/LibrarySearchTest.php
  - tests/Feature/Library/LibraryAlbumsPaginationTest.php
  - tests/Feature/Library/LibraryArtistsTabTest.php
autonomous: false
requirements: [OOM-FIX, ALBUMS-PAGINATE, ARTISTS-TAB]

must_haves:
  truths:
    - "Hidden tab content produces zero DOM elements and zero image requests"
    - "Albums tab loads 20 albums at a time with infinite scroll"
    - "Artists tab shows distinct artists with track counts and album thumbnails"
    - "Artists tab paginates 20 artists at a time with infinite scroll"
    - "Search results also paginate albums instead of loading all"
    - "All album cover images use loading=lazy"
    - "Tab order is Tracks | Artists | Albums"
    - "Existing tracks infinite scroll still works"
  artifacts:
    - path: "resources/views/pages/library/index.blade.php"
      provides: "Lazy tabs, albums pagination, artists tab"
    - path: "tests/Feature/Library/LibraryAlbumsPaginationTest.php"
      provides: "Albums pagination tests"
    - path: "tests/Feature/Library/LibraryArtistsTabTest.php"
      provides: "Artists tab tests"
  key_links:
    - from: "index.blade.php x-if directives"
      to: "Alpine activeTab state"
      via: "template x-if removes DOM entirely when tab inactive"
    - from: "loadMoreAlbums() method"
      to: "wire:intersect sentinel"
      via: "infinite scroll trigger appends next 20 albums"
    - from: "loadMoreArtists() method"
      to: "wire:intersect sentinel"
      via: "infinite scroll trigger appends next 20 artists"
---

<objective>
Fix Herd Expose OOM crash and enhance library with Artists tab.

Purpose: The Albums tab uses x-show which renders ALL album cover art <img> tags even when hidden, causing 128MB WebSocket crash on Herd Expose. Also adds a new Artists tab for browsing by artist.

Output: Single-file Livewire component with lazy tabs (x-if), paginated albums, and a new Artists tab.
</objective>

<execution_context>
@./.claude/get-shit-done/workflows/execute-plan.md
@./.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/pages/library/index.blade.php
@tests/Feature/Library/LibrarySearchTest.php
@app/Models/Album.php
@app/Models/Track.php

<interfaces>
<!-- Existing track pagination pattern to replicate for albums and artists -->

PHP properties (tracks — already working, DO NOT modify):
- $page (int), $loadedTracks (array), $trackIds (array), $hasMoreTracks (bool), $perPage (50)
- loadTracks(): void (private), loadMore(): void (public), resetTracksState(): void (private)

PHP properties (upload/filter — DO NOT modify):
- $audioFiles, $selectedAlbumId, $duplicateWarnings, $search, $sourceFilter, $activeTab

Computed (keep separate from pagination):
- userAlbums(): Collection — used by upload album picker dropdown, must remain

Album model fields: id, user_id, title, artist, year, genre, cover_art_path
Album methods: coverArtUrl(): ?string

Track model fields: id, user_id, album_id, title, artist, file_path, file_hash, duration, source_type, is_available
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Lazy tabs (x-if) + Albums pagination PHP logic</name>
  <files>resources/views/pages/library/index.blade.php</files>
  <action>
**PHP section changes:**

1. Add album pagination properties alongside existing track properties:
   - `public int $albumPage = 1;`
   - `public array $loadedAlbums = [];` with PHPDoc `@var list<array{id: int, title: string, artist: ?string, cover_art_url: ?string}>`
   - `public bool $hasMoreAlbums = true;`
   - `public int $albumsPerPage = 20;`

2. Add artist pagination properties:
   - `public int $artistPage = 1;`
   - `public array $loadedArtists = [];` with PHPDoc `@var list<array{artist: string, track_count: int, albums: list<array{id: int, title: string, cover_art_url: ?string}>}>`
   - `public bool $hasMoreArtists = true;`
   - `public int $artistsPerPage = 20;`

3. In `mount()`, also call `$this->loadAlbums()` and `$this->loadArtists()`.

4. Add `loadAlbums()` private method:
   ```php
   private function loadAlbums(): void
   {
       $results = Album::query()
           ->where('user_id', Auth::id())
           ->when($this->search, function ($q) {
               $q->where(function ($q) {
                   $q->where('title', 'like', "%{$this->search}%")
                       ->orWhere('artist', 'like', "%{$this->search}%");
               });
           })
           ->orderBy('title')
           ->forPage($this->albumPage, $this->albumsPerPage)
           ->get();

       $newAlbums = $results->map(fn ($album) => [
           'id'            => $album->id,
           'title'         => $album->title,
           'artist'        => $album->artist,
           'cover_art_url' => $album->coverArtUrl(),
       ])->all();

       $this->loadedAlbums = array_merge($this->loadedAlbums, $newAlbums);
       $this->hasMoreAlbums = $results->count() === $this->albumsPerPage;
   }
   ```

5. Add `loadMoreAlbums()` public method (called by wire:intersect):
   ```php
   public function loadMoreAlbums(): void
   {
       $this->albumPage++;
       $this->loadAlbums();
   }
   ```

6. Add `loadArtists()` private method:
   ```php
   private function loadArtists(): void
   {
       $artists = Track::query()
           ->where('user_id', Auth::id())
           ->whereNotNull('artist')
           ->where('artist', '!=', '')
           ->when($this->search, fn ($q) => $q->where('artist', 'like', "%{$this->search}%"))
           ->select('artist')
           ->selectRaw('COUNT(*) as track_count')
           ->groupBy('artist')
           ->orderBy('artist')
           ->forPage($this->artistPage, $this->artistsPerPage)
           ->get();

       $newArtists = $artists->map(function ($row) {
           $albums = Album::query()
               ->where('user_id', Auth::id())
               ->where('artist', $row->artist)
               ->orderBy('title')
               ->get();

           return [
               'artist'      => $row->artist,
               'track_count' => $row->track_count,
               'albums'      => $albums->map(fn ($a) => [
                   'id'            => $a->id,
                   'title'         => $a->title,
                   'cover_art_url' => $a->coverArtUrl(),
               ])->all(),
           ];
       })->all();

       $this->loadedArtists = array_merge($this->loadedArtists, $newArtists);
       $this->hasMoreArtists = $artists->count() === $this->artistsPerPage;
   }
   ```

7. Add `loadMoreArtists()` public method:
   ```php
   public function loadMoreArtists(): void
   {
       $this->artistPage++;
       $this->loadArtists();
   }
   ```

8. Add `resetAlbumsState()` and `resetArtistsState()` private methods (same pattern as resetTracksState):
   ```php
   private function resetAlbumsState(): void
   {
       $this->albumPage = 1;
       $this->loadedAlbums = [];
       $this->hasMoreAlbums = true;
       $this->loadAlbums();
   }

   private function resetArtistsState(): void
   {
       $this->artistPage = 1;
       $this->loadedArtists = [];
       $this->hasMoreArtists = true;
       $this->loadArtists();
   }
   ```

9. Update `updatedSearch()` and `updatedSourceFilter()` to also call `$this->resetAlbumsState()` and `$this->resetArtistsState()`.

10. **Remove** the `albums()` computed property (the `#[Computed] public function albums()`) — it's replaced by `$loadedAlbums`. Keep `userAlbums()` computed intact for the upload picker.

**Blade section changes:**

11. **Tab bar** — Change tab order to Tracks | Artists | Albums. Add the Artists tab button between Tracks and Albums with same styling pattern.

12. **Lazy tabs with x-if** — Replace ALL `x-show="activeTab === '...'"` with `<template x-if="activeTab === '...'"><div>...</div></template>`. This applies to:
    - Tracks tab content
    - Albums tab content
    - New Artists tab content

13. **Albums tab Blade** — Replace `$this->albums` foreach with `$this->loadedAlbums` foreach. Since loadedAlbums are arrays (not Eloquent models), change:
    - `$album->id` to `$album['id']`
    - `$album->title` to `$album['title']`
    - `$album->artist` to `$album['artist']`
    - `$album->coverArtUrl()` to `$album['cover_art_url']`
    - Add `loading="lazy"` to all album cover `<img>` tags
    - Add wire:intersect sentinel after the album grid:
      ```blade
      @if ($this->hasMoreAlbums)
          <div wire:intersect="loadMoreAlbums" class="flex justify-center py-4">
              <span class="loading loading-spinner loading-sm text-zinc-500"></span>
          </div>
      @endif
      ```

14. **Artists tab Blade** — New tab content inside `<template x-if="activeTab === 'artists'">`:
    - If `empty($this->loadedArtists)`: show "No artists found." empty state
    - Otherwise: render a list of artist cards. For each artist in `$this->loadedArtists`:
      ```blade
      <div wire:key="artist-{{ $loop->index }}" class="rounded-lg bg-zinc-800 p-4">
          <div class="flex items-center justify-between mb-3">
              <h3 class="font-semibold text-sm">{{ $artist['artist'] }}</h3>
              <span class="text-xs text-zinc-400">{{ $artist['track_count'] }} {{ Str::plural('track', $artist['track_count']) }}</span>
          </div>
          @if (! empty($artist['albums']))
              <div class="flex gap-2 overflow-x-auto">
                  @foreach ($artist['albums'] as $album)
                      <a href="{{ route('library.album.show', $album['id']) }}" wire:navigate
                         class="shrink-0 w-16 group">
                          <div class="aspect-square bg-zinc-700 rounded overflow-hidden">
                              @if ($album['cover_art_url'])
                                  <img src="{{ $album['cover_art_url'] }}" alt="{{ $album['title'] }}"
                                       loading="lazy" class="w-full h-full object-cover">
                              @else
                                  <div class="w-full h-full flex items-center justify-center">
                                      <x-mary-icon name="o-musical-note" class="size-6 text-zinc-500" />
                                  </div>
                              @endif
                          </div>
                          <p class="text-xs text-zinc-400 truncate mt-1">{{ $album['title'] }}</p>
                      </a>
                  @endforeach
              </div>
          @endif
      </div>
      ```
    - Add wire:intersect sentinel after artist list
    - Use a vertical stack layout: `<div class="space-y-3">` wrapping the artist cards

15. **Search results albums section** — Replace the `$this->albums` usage in the search results view (the section below "Albums" h2) with `$this->loadedAlbums` using the same array-access pattern as #13. Add `loading="lazy"` to those img tags too. Add the same wire:intersect sentinel for hasMoreAlbums.

16. **Empty state check** — Update the `$this->albums->isEmpty()` check at line 361 to `empty($this->loadedAlbums)` since albums is no longer a computed.

17. **All img tags** — Add `loading="lazy"` attribute to every `<img>` tag in the file (album covers in all locations).

Run `vendor/bin/pint --dirty` after all changes.
  </action>
  <verify>
    <automated>cd /Users/defou/Herd/rekodo && php -l resources/views/pages/library/index.blade.php && vendor/bin/pint --dirty</automated>
  </verify>
  <done>
    - x-show replaced with template x-if for all 3 tabs
    - Albums tab uses loadedAlbums array with infinite scroll (20 per page)
    - Artists tab shows artist name, track count, album thumbnails with infinite scroll
    - Tab order: Tracks | Artists | Albums
    - Search results albums also paginated via loadedAlbums
    - All img tags have loading="lazy"
    - albums() computed removed, userAlbums() computed preserved
    - PHP syntax valid, Pint clean
  </done>
</task>

<task type="auto">
  <name>Task 2: Update existing tests + add albums pagination and artists tab tests</name>
  <files>
    tests/Feature/Library/LibrarySearchTest.php,
    tests/Feature/Library/LibraryAlbumsPaginationTest.php,
    tests/Feature/Library/LibraryArtistsTabTest.php
  </files>
  <action>
**Update LibrarySearchTest.php:**

The existing tests at lines 103-171 reference `$component->get('albums')` which returns a computed Collection. Since the `albums()` computed is removed and replaced by `$loadedAlbums` (a public array property), update these tests:

- `it('albums computed property returns all albums...')` — Change to test `loadedAlbums` property. Use `->assertSet(...)` or `->get('loadedAlbums')` and check the array structure.
- `it('albums computed property does not return albums from other users')` — Same adaptation.
- `it('albums computed property filters by title when search is set')` — Use `->get('loadedAlbums')` and check plucked IDs from arrays: `collect($loadedAlbums)->pluck('id')`.
- `it('albums computed property filters by artist when search is set')` — Same.
- `it('albums computed property returns all albums when search is empty')` — Same.

Update test descriptions to say "loadedAlbums" instead of "albums computed property".

**Create LibraryAlbumsPaginationTest.php** using `php artisan make:test --pest Library/LibraryAlbumsPaginationTest`:

Tests to write:
1. `it('loads first batch of 20 albums on mount')` — Create 25 albums, assert `loadedAlbums` count is 20 and `hasMoreAlbums` is true.
2. `it('loadMoreAlbums appends next batch')` — Create 25 albums, call `loadMoreAlbums`, assert `loadedAlbums` count is 25 and `hasMoreAlbums` is false.
3. `it('sets hasMoreAlbums to false when fewer albums than page size')` — Create 5 albums, assert `hasMoreAlbums` is false.
4. `it('resets album pagination when search changes')` — Create 25 albums, call `loadMoreAlbums` (25 loaded), then set search term that matches only 3, assert `loadedAlbums` count is 3.
5. `it('loadedAlbums contains correct data shape')` — Create 1 album with known title/artist, assert first element of `loadedAlbums` has keys id, title, artist, cover_art_url.

**Create LibraryArtistsTabTest.php** using `php artisan make:test --pest Library/LibraryArtistsTabTest`:

Tests to write:
1. `it('loads artists with track counts on mount')` — Create tracks with distinct artists, assert `loadedArtists` has expected entries with correct track_count.
2. `it('excludes null and empty artists')` — Create tracks with null artist and empty string artist, assert they do not appear in `loadedArtists`.
3. `it('includes album thumbnails for each artist')` — Create an album with artist "Foo", create tracks by "Foo", assert the artist entry in `loadedArtists` has albums array containing the album.
4. `it('paginates artists at 20 per page')` — Create 25 distinct artists (each with 1 track), assert `loadedArtists` count is 20 and `hasMoreArtists` is true.
5. `it('loadMoreArtists appends next batch')` — Same setup, call `loadMoreArtists`, assert count is 25.
6. `it('resets artists when search changes')` — Create 25 artists, load more (25 loaded), set search to match only 2, assert `loadedArtists` count is 2.
7. `it('artists tab button is rendered')` — Livewire test asserting the "Artists" tab button text is present.

All tests use `Livewire::actingAs($user)->test('pages::library.index')` pattern per existing convention.

Run `vendor/bin/pint --dirty` on all test files.
  </action>
  <verify>
    <automated>cd /Users/defou/Herd/rekodo && php artisan test --compact tests/Feature/Library/LibrarySearchTest.php tests/Feature/Library/LibraryAlbumsPaginationTest.php tests/Feature/Library/LibraryArtistsTabTest.php</automated>
  </verify>
  <done>
    - Existing LibrarySearchTest album tests updated for loadedAlbums array (no longer computed)
    - LibraryAlbumsPaginationTest: 5 tests covering batch loading, loadMore, hasMore flag, search reset, data shape
    - LibraryArtistsTabTest: 7 tests covering artist loading, null exclusion, album thumbnails, pagination, search reset, tab visibility
    - All tests pass
  </done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <name>Task 3: Verify lazy tabs, albums pagination, and artists tab in browser</name>
  <action>
    Human verifies that lazy tabs prevent OOM, albums paginate with infinite scroll, and the new Artists tab works correctly in the browser.
  </action>
  <what-built>
    Lazy tabs (x-if), paginated albums grid with infinite scroll, new Artists tab with track counts and album thumbnails, and loading="lazy" on all images. This fixes the Herd Expose OOM crash.
  </what-built>
  <how-to-verify>
    1. Visit the Library page (use get-absolute-url tool for correct URL)
    2. Verify 3 tabs visible: Tracks | Artists | Albums
    3. Click Albums tab — should see album grid load (max 20 initially). Scroll down to see infinite scroll load more if you have >20 albums.
    4. Click Artists tab — should see artist list with track counts and small album thumbnails. Scroll to test infinite scroll.
    5. Click Tracks tab — verify existing infinite scroll still works.
    6. Type a search term — verify search results show paginated albums below search tracks.
    7. Open browser DevTools Network tab, switch between tabs — confirm NO image requests fire for hidden tab content (x-if prevents DOM rendering).
    8. If using Herd Expose: verify no OOM crash when exposing with many albums.
  </how-to-verify>
  <verify>Human visual verification in browser</verify>
  <done>All 3 tabs render correctly, infinite scroll works for albums and artists, no OOM on Expose</done>
  <resume-signal>Type "approved" or describe issues</resume-signal>
</task>

</tasks>

<verification>
- `php artisan test --compact tests/Feature/Library/` — all library tests pass
- `vendor/bin/pint --dirty` — no formatting issues
- Browser: switching tabs does not trigger image loads for inactive tabs
- Browser: albums and artists load in batches of 20 with infinite scroll
</verification>

<success_criteria>
- OOM root cause eliminated: hidden tab content has zero DOM elements (x-if, not x-show)
- Albums paginated at 20/batch with infinite scroll sentinels
- Artists tab shows distinct artists with track counts and album thumbnails, paginated at 20/batch
- Tab order: Tracks | Artists | Albums
- All img tags use loading="lazy"
- userAlbums() computed preserved for upload picker
- Existing tracks infinite scroll unaffected
- All tests pass (existing + new)
</success_criteria>

<output>
After completion, create `.planning/quick/6-paginate-albums-grid-with-infinite-scrol/6-SUMMARY.md`
</output>
