---
phase: quick-5
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/pages/library/index.blade.php
  - app/Livewire/AudioPlayer.php
  - resources/views/livewire/audio-player.blade.php
  - resources/views/pages/library/album-show.blade.php
  - tests/Feature/Library/LibrarySearchTest.php
  - tests/Feature/AudioPlayer/AudioPlayerTest.php
  - tests/Feature/Library/InfiniteScrollTest.php
autonomous: true
requirements: [PERF-01, PERF-02, FEAT-SHUFFLE, FEAT-ALBUM-PAGINATE]

must_haves:
  truths:
    - "Library index loads only 50 tracks initially, not all 20k+"
    - "Scrolling to the bottom of the track list loads 50 more tracks without page reload"
    - "Track IDs are maintained in a single Alpine x-data array, not embedded per-row as JSON"
    - "Clicking a track row dispatches play-track with the Alpine-managed trackIds array"
    - "When playback reaches last 3 tracks in queue, more tracks auto-load and extend the queue"
    - "Shuffle toggle randomizes queue order and can be toggled off to restore original order"
    - "Album detail page paginates tracks for albums with 50+ tracks"
    - "Search/filter changes reset the loaded tracks and page counter"
  artifacts:
    - path: "resources/views/pages/library/index.blade.php"
      provides: "Infinite scroll library with Alpine-managed track IDs, loadMore sentinel"
    - path: "app/Livewire/AudioPlayer.php"
      provides: "extendQueue method, shuffle toggle, originalQueue property"
    - path: "resources/views/livewire/audio-player.blade.php"
      provides: "Shuffle button UI, queue-running-low event dispatch"
    - path: "resources/views/pages/library/album-show.blade.php"
      provides: "Paginated album tracks with simplePaginate"
    - path: "tests/Feature/Library/InfiniteScrollTest.php"
      provides: "Tests for loadMore, pagination reset on search/filter"
    - path: "tests/Feature/AudioPlayer/AudioPlayerTest.php"
      provides: "Tests for extendQueue, shuffle toggle"
  key_links:
    - from: "resources/views/pages/library/index.blade.php"
      to: "app/Livewire/AudioPlayer.php"
      via: "play-track browser event with Alpine trackIds array"
      pattern: "\\$dispatch\\('play-track'"
    - from: "resources/views/livewire/audio-player.blade.php"
      to: "resources/views/pages/library/index.blade.php"
      via: "queue-running-low browser event triggers loadMore"
      pattern: "queue-running-low"
    - from: "resources/views/pages/library/index.blade.php"
      to: "app/Livewire/AudioPlayer.php"
      via: "queue-extended event delivers new track IDs"
      pattern: "extendQueue"
---

<objective>
Fix OOM crash when loading library with 20k+ tracks by implementing infinite scroll with
batch loading, auto-extending playback queue, shuffle mode, and album detail pagination.

Purpose: The library page currently calls `->get()` loading ALL tracks into memory, and embeds
the full track IDs array (20k integers) as JSON in every single `<tr>` element. With 20k tracks
this exhausts 256MB memory. This plan replaces that with 50-track batches loaded on scroll,
a shared Alpine.js track ID array, queue auto-extension when playback nears the end, and shuffle.

Output: Paginated library that handles 20k+ tracks without OOM, with seamless infinite scroll,
auto-extending queue, shuffle toggle, and paginated album detail page.
</objective>

<execution_context>
@./.claude/get-shit-done/workflows/execute-plan.md
@./.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@resources/views/pages/library/index.blade.php
@app/Livewire/AudioPlayer.php
@resources/views/livewire/audio-player.blade.php
@resources/views/pages/library/album-show.blade.php
@tests/Feature/Library/LibrarySearchTest.php
@tests/Feature/AudioPlayer/AudioPlayerTest.php
@tests/Feature/Library/LibraryViewTest.php
@tests/Feature/AlbumShowTest.php

<interfaces>
<!-- Key patterns the executor needs from the existing codebase -->

From resources/views/pages/library/index.blade.php (current PHP section):
```php
// Current OOM-causing pattern — replace this:
#[Computed]
public function tracks(): \Illuminate\Database\Eloquent\Collection
{
    return Track::query()
        ->where('user_id', Auth::id())
        ->with('album')
        ->when($this->search, function ($q) { /* ... */ })
        ->when($this->sourceFilter === 'uploaded', fn ($q) => $q->uploaded())
        ->when($this->sourceFilter === 'local', fn ($q) => $q->local())
        ->when($this->sourceFilter === 'unavailable', fn ($q) => $q->unavailable())
        ->orderBy('title')
        ->get();  // <-- loads ALL tracks
}
```

From app/Livewire/AudioPlayer.php:
```php
public ?int $currentTrackId = null;
/** @var list<int> */
public array $queue = [];
public int $volume = 80;

public function loadQueue(array $trackIds, int $startIndex = 0): void;
public function nextTrack(): void;
public function previousTrack(): void;
protected function trackPayload(Track $track, bool $autoplay = false, ?int $queueIndex = null): array;
```

From resources/views/livewire/audio-player.blade.php (Alpine audioPlayer function):
```javascript
// onEnded and onAudioError already call this.$wire.nextTrack()
// syncQueueState() watches queue and currentTrackId
// onPlayTrack(detail) handles play-track events with { trackIds, index }
// _executePlayTrack(detail) calls this.$wire.loadQueue(detail.trackIds, detail.index)
```

Test pattern (from LibrarySearchTest.php):
```php
Livewire::actingAs($user)
    ->test('pages::library.index')
    ->set('search', 'hello')
    ->assertSee('Hello World');
```
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Library index infinite scroll with Alpine-managed track IDs</name>
  <files>resources/views/pages/library/index.blade.php, tests/Feature/Library/InfiniteScrollTest.php, tests/Feature/Library/LibrarySearchTest.php</files>
  <action>
**Replace the computed `tracks()` method with paginated batch loading in the PHP section of `index.blade.php`:**

1. Remove the `#[Computed] tracks()` method that calls `->get()`.
2. Add these properties:
   - `public int $page = 1;` (current page for batch loading)
   - `public array $loadedTracks = [];` (accumulated track data as arrays: `['id' => int, 'title' => string, 'artist' => ?string, 'album_title' => ?string, 'duration' => ?int, 'source_type' => string, 'is_available' => bool]`)
   - `public array $trackIds = [];` (accumulated track IDs for playback queue)
   - `public bool $hasMoreTracks = true;` (whether more tracks exist)
   - `public int $perPage = 50;`

3. Add `mount()` method that calls `$this->loadTracks()` to load the initial batch.

4. Add `loadTracks()` private method:
   - Build the same query as the old `tracks()` (with search, sourceFilter scopes)
   - Use `->forPage($this->page, $this->perPage)->get()`
   - Map results to the array shape above (include `$track->album?->title` as `album_title`)
   - Append to `$this->loadedTracks` and `$this->trackIds`
   - Set `$this->hasMoreTracks = $results->count() === $this->perPage`

5. Add `loadMore()` public method:
   - Increment `$this->page`
   - Call `$this->loadTracks()`
   - Dispatch browser event `tracks-loaded` with the new track IDs: `$this->dispatch('tracks-loaded', newIds: $newIds)`

6. Add Livewire lifecycle hook `updated($property)` or individual `updatedSearch()` and `updatedSourceFilter()` methods:
   - Reset `$this->page = 1`, `$this->loadedTracks = []`, `$this->trackIds = []`, `$this->hasMoreTracks = true`
   - Call `$this->loadTracks()` to reload first batch with new filter

**Update the Blade template (tracks tab, both search-results and default view):**

7. Replace both track tables (search results table around line 157 and default tracks tab table around line 322) to use `$this->loadedTracks` instead of `$this->tracks`:
   - Wrap the tracks tab content in an Alpine `x-data="{ trackIds: @js($this->trackIds) }"` container
   - Listen for the `tracks-loaded` browser event to append new IDs: `@tracks-loaded.window="trackIds = trackIds.concat($event.detail.newIds)"`
   - Each `<tr>` click handler uses Alpine's `trackIds` and the track's index: `x-on:click="... $dispatch('play-track', { trackIds: trackIds, index: {{ $index }} })"`
   - The play button inside each row also references Alpine's `trackIds`
   - Iterate over `$this->loadedTracks` (which are arrays, not models), so access via `$track['title']`, `$track['id']`, etc.
   - Use `$track['source_type']` to compare with `'local'` string (not enum object — serialize to string in loadTracks)
   - Use `$track['is_available']` for availability checks

8. After the `</tbody></table>`, add an infinite scroll sentinel:
   ```blade
   @if ($this->hasMoreTracks)
       <div wire:intersect="loadMore" class="flex justify-center py-4">
           <span class="loading loading-spinner loading-sm text-zinc-500"></span>
       </div>
   @endif
   ```

9. Also listen for `queue-running-low` event on the tracks tab wrapper to auto-load more:
   ```
   @queue-running-low.window="if ($wire.hasMoreTracks) { $wire.loadMore() }"
   ```

10. For the **search results view** (when `strlen($this->search) > 0`): Use the same `$this->loadedTracks` and `$this->trackIds` since `updatedSearch()` resets and reloads. Add the same sentinel and Alpine trackIds pattern.

11. The `albums()` computed property remains unchanged (albums are typically few).

**Important implementation notes:**
- Use `Js::from()` not `@js()` inside `x-on:click` attribute strings on tr elements (per project decisions)
- The `$index` in the foreach must reflect the absolute position in `$this->loadedTracks`, not just the current batch
- Source type comparison: serialize `$track->source_type->value` (enum backed value) to the array as a string in `loadTracks()`
- Keep `wire:key` on each `<tr>` using the track ID

**Create `tests/Feature/Library/InfiniteScrollTest.php`:**

```
php artisan make:test --pest Library/InfiniteScrollTest
```

Test cases:
- `loadMore increases page and appends tracks` — create 60 tracks, verify initial load returns 50, call loadMore, verify 60 total
- `loadMore sets hasMoreTracks to false when no more tracks` — create 30 tracks (< 50), verify hasMoreTracks is false after mount
- `updatedSearch resets loaded tracks` — load tracks, set search, verify loadedTracks reset to filtered results
- `updatedSourceFilter resets loaded tracks` — load tracks, set sourceFilter, verify loadedTracks reset
- `loadMore dispatches tracks-loaded event` — call loadMore, assert event dispatched
- `trackIds accumulates across pages` — create 60 tracks, loadMore, verify trackIds has 60 entries
- `loadedTracks contains correct data shape` — verify array has expected keys (id, title, artist, album_title, duration, source_type, is_available)

**Update `tests/Feature/Library/LibrarySearchTest.php`:**

The existing tests use `$this->tracks` computed property and `assertSee`. Since the page now uses `$this->loadedTracks`, update tests that access the computed property directly to use `$this->loadedTracks` instead. Tests that just use `assertSee` on rendered output should still work if the template renders the same data. Review each test — those using `->get('tracks')` or `->assertSet` on tracks need updating to reference `loadedTracks`.

Run `vendor/bin/pint --dirty` after all changes.
  </action>
  <verify>
    <automated>cd /Users/defou/Herd/foonk && php artisan test --compact tests/Feature/Library/InfiniteScrollTest.php tests/Feature/Library/LibrarySearchTest.php</automated>
  </verify>
  <done>
    - Library index loads only first 50 tracks on mount (not all 20k)
    - loadMore loads next 50 tracks and appends to loadedTracks
    - wire:intersect sentinel triggers loadMore on scroll
    - Search/filter reset reloads fresh first batch
    - Track IDs maintained in Alpine x-data, not embedded per-row
    - All infinite scroll tests pass
    - All existing library search tests pass (updated as needed)
  </done>
</task>

<task type="auto">
  <name>Task 2: AudioPlayer shuffle mode and auto-extend queue</name>
  <files>app/Livewire/AudioPlayer.php, resources/views/livewire/audio-player.blade.php, tests/Feature/AudioPlayer/AudioPlayerTest.php</files>
  <action>
**Add auto-extend queue support to `AudioPlayer.php`:**

1. Add `extendQueue(array $newTrackIds)` public method:
   - Filter to only include owned + available tracks (same pattern as loadQueue)
   - Append filtered IDs to `$this->queue` (avoid duplicates: `array_diff($newIds, $this->queue)`)
   - If `$this->isShuffled`, shuffle only the newly appended portion using Fisher-Yates, then insert them after the current track position in the shuffled queue
   - No dispatch needed — the queue property update triggers Alpine's `$wire.$watch('queue')` which calls `syncQueueState()`

2. Modify `nextTrack()`:
   - After determining the current index, check if the current position is within the last 3 tracks of the queue: `$index >= count($this->queue) - 3`
   - If so, dispatch a browser event: `$this->dispatch('queue-running-low')` — this is a browser event that the library page listens for

**Add shuffle support to `AudioPlayer.php`:**

3. Add properties:
   - `public bool $isShuffled = false;`
   - `public array $originalQueue = [];` (stores the unshuffled queue order)

4. Add `toggleShuffle()` public method:
   - If turning ON (`$this->isShuffled = true`):
     - Save `$this->originalQueue = $this->queue`
     - Get the current track's position
     - Remove current track from queue, Fisher-Yates shuffle remaining tracks, put current track at position 0
     - Set `$this->queue` to shuffled result
   - If turning OFF (`$this->isShuffled = false`):
     - Restore `$this->queue = $this->originalQueue`
     - Clear `$this->originalQueue = []`

5. Update `loadQueue()`:
   - Reset shuffle state: `$this->isShuffled = false; $this->originalQueue = [];`

**Update `audio-player.blade.php` (Alpine + Blade):**

6. Add shuffle button in the playback controls `<div>`, between the previous button and play/pause:
   ```blade
   <button
       type="button"
       wire:click="toggleShuffle"
       :class="$wire.isShuffled ? 'text-primary' : 'text-zinc-400'"
       class="flex size-8 items-center justify-center rounded-full transition-colors hover:text-white"
       aria-label="Toggle shuffle"
       :aria-pressed="$wire.isShuffled"
   >
       <x-mary-icon name="o-arrows-right-left" class="size-4" />
   </button>
   ```
   Place it before the previous-track button so the layout is: [shuffle] [prev] [play/pause] [next].

7. In the Alpine `onEnded()` function, before calling `this.$wire.nextTrack()`, add a check:
   - If near end of queue (check `this.$wire.queue` length vs current index), dispatch `queue-running-low`:
     ```javascript
     const queue = this.$wire.queue;
     const idx = queue.indexOf(this.$wire.currentTrackId);
     if (idx >= queue.length - 3) {
         this.$dispatch('queue-running-low');
     }
     ```
   - This is a belt-and-suspenders approach alongside the server-side dispatch in `nextTrack()`.

**Add tests to `AudioPlayerTest.php`:**

Add these test cases at the end of the file:

- `extendQueue appends new track IDs to existing queue` — load a queue of 2 tracks, call extendQueue with 2 more, verify queue has 4
- `extendQueue filters out tracks from other users` — verify only owned tracks get appended
- `extendQueue filters out unavailable tracks` — verify unavailable tracks excluded
- `extendQueue does not add duplicate track IDs` — verify calling extendQueue with already-queued IDs doesn't duplicate
- `nextTrack dispatches queue-running-low when near end of queue` — load queue of 3 tracks, start at index 0, call nextTrack, verify `queue-running-low` dispatched (now at index 1, which is within last 3)
- `nextTrack does not dispatch queue-running-low when not near end` — load queue of 10 tracks, start at 0, call nextTrack, verify NOT dispatched
- `toggleShuffle shuffles the queue keeping current track first` — load queue of 5 tracks, start at index 2, call toggleShuffle, verify currentTrackId is first in queue, isShuffled is true
- `toggleShuffle restores original queue order when toggled off` — toggle shuffle on then off, verify queue matches original
- `loadQueue resets shuffle state` — toggle shuffle on, then loadQueue, verify isShuffled is false

Run `vendor/bin/pint --dirty` after all changes.
  </action>
  <verify>
    <automated>cd /Users/defou/Herd/foonk && php artisan test --compact tests/Feature/AudioPlayer/AudioPlayerTest.php</automated>
  </verify>
  <done>
    - extendQueue appends owned+available tracks to queue without duplicates
    - nextTrack dispatches queue-running-low when within last 3 tracks
    - toggleShuffle randomizes queue (current track first), toggling off restores original order
    - loadQueue resets shuffle state
    - Shuffle button visible in audio player with active state styling
    - All AudioPlayer tests pass (existing + new)
  </done>
</task>

<task type="auto">
  <name>Task 3: Album detail pagination and final test verification</name>
  <files>resources/views/pages/library/album-show.blade.php, tests/Feature/AlbumShowTest.php, tests/Feature/Library/LibraryViewTest.php</files>
  <action>
**Add pagination to album detail page (`album-show.blade.php`):**

1. In the PHP section, add `use Livewire\WithPagination;` and add the trait: `use WithPagination;`

2. Replace the eager-loaded `$this->album = $album->load('tracks')` in `mount()` with just `$this->album = $album` (don't eager load all tracks).

3. Add a `tracks()` computed property that queries the album's tracks with `simplePaginate(50)`:
   ```php
   #[Computed]
   public function tracks(): \Illuminate\Contracts\Pagination\Paginator
   {
       return Track::query()
           ->where('album_id', $this->album->id)
           ->orderByRaw('CASE WHEN track_number IS NULL THEN 1 ELSE 0 END')
           ->orderBy('track_number')
           ->orderBy('title')
           ->simplePaginate(50);
   }
   ```
   This replicates the existing sort logic (numbered tracks first by track_number, null track_number tracks after sorted by title) but at the DB level instead of PHP collection sorting.

4. Update the Blade template:
   - Replace `$album->tracks->isEmpty()` with `$this->tracks->isEmpty()`
   - Remove the `@php $sortedTracks = ...` block — sorting is now in the query
   - Replace `@foreach ($sortedTracks as $index => $track)` with `@foreach ($this->tracks as $index => $track)`
   - Build track IDs array from the current page: `@php $albumTrackIds = $this->tracks->pluck('id')->all(); @endphp`
   - After the `</table>`, add pagination links: `{{ $this->tracks->links() }}`
   - The `$index` for play-track dispatch should be the position within the current page

5. Since most albums have <30 tracks, pagination only appears when needed (simplePaginate shows next/previous links).

**Update `tests/Feature/AlbumShowTest.php`:**

6. The existing test `album show page lists all tracks ordered by title` uses `assertSeeInOrder`. Since tracks are now sorted by track_number first (nulls last, then by title), update the test to create tracks with specific track_numbers to match the new ordering.

7. Review existing tests in `tests/Feature/Library/LibraryViewTest.php` — these test album show and upload summary. The album show tests access `$album->tracks` which is now loaded via computed property instead of eager loading. Tests that use `assertSee` on rendered HTML should still work. If any test references `$album->tracks->isEmpty()` indirectly, it will still work because the template now calls `$this->tracks->isEmpty()`.

**Run full test suite for all modified files:**

8. Run all affected tests:
   - `tests/Feature/Library/InfiniteScrollTest.php`
   - `tests/Feature/Library/LibrarySearchTest.php`
   - `tests/Feature/Library/LibrarySourceFilterTest.php`
   - `tests/Feature/Library/LibraryViewTest.php`
   - `tests/Feature/AudioPlayer/AudioPlayerTest.php`
   - `tests/Feature/AlbumShowTest.php`

9. Fix any failures — common issues:
   - Tests that reference `$this->tracks` computed property (now removed from library index) — update to use `$this->loadedTracks`
   - Tests that check `assertSeeInOrder` may need adjustment if DOM structure changed
   - Source filter tests may need updates if they access the old computed property

Run `vendor/bin/pint --dirty` after all changes.
  </action>
  <verify>
    <automated>cd /Users/defou/Herd/foonk && php artisan test --compact tests/Feature/Library/ tests/Feature/AudioPlayer/ tests/Feature/AlbumShowTest.php</automated>
  </verify>
  <done>
    - Album detail page uses simplePaginate(50) with DB-level sorting
    - Pagination links appear only when album has 50+ tracks
    - All existing album show tests pass (updated for new sort approach)
    - All library tests pass (infinite scroll, search, source filter, view)
    - All AudioPlayer tests pass
    - No test regressions across the full affected test suite
  </done>
</task>

</tasks>

<verification>
1. Run the full affected test suite: `php artisan test --compact tests/Feature/Library/ tests/Feature/AudioPlayer/ tests/Feature/AlbumShowTest.php`
2. All tests pass including new infinite scroll tests, shuffle tests, and updated existing tests
3. Library index page loads without OOM for large track counts (verify with 60+ factory tracks that pagination works)
4. Code style clean: `vendor/bin/pint --dirty` produces no changes
</verification>

<success_criteria>
- Library index loads 50 tracks initially, loads more on scroll via wire:intersect
- Track IDs stored in Alpine x-data, not embedded in every table row
- Search and filter changes reset pagination and reload first batch
- AudioPlayer queue auto-extends when playback nears end (last 3 tracks)
- Shuffle toggle randomizes queue, can be toggled off to restore order
- Album detail page uses simplePaginate(50)
- All tests pass: new infinite scroll tests, new AudioPlayer shuffle/extend tests, all updated existing tests
</success_criteria>

<output>
After completion, create `.planning/quick/5-optimize-track-loading-with-livewire-laz/5-SUMMARY.md`
</output>
