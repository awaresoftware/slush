---
phase: quick-4
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/pages/library/album-show.blade.php
  - resources/views/playlists/show.blade.php
  - resources/views/pages/library/index.blade.php
  - tests/Feature/MobileTouchPlaybackTest.php
autonomous: true
requirements: [QUICK-4]

must_haves:
  truths:
    - "Tapping an available track row on touch devices dispatches play-track event"
    - "Tapping action buttons (add-to-playlist, relink, remove) does NOT trigger row playback"
    - "Unavailable track rows do NOT respond to tap/click for playback"
    - "Desktop hover-based play buttons continue to work as before"
  artifacts:
    - path: "resources/views/pages/library/album-show.blade.php"
      provides: "Album track rows with x-on:click touch handler"
      contains: "x-on:click"
    - path: "resources/views/playlists/show.blade.php"
      provides: "Playlist track rows with x-on:click touch handler"
      contains: "x-on:click"
    - path: "resources/views/pages/library/index.blade.php"
      provides: "Library search + tab track rows with x-on:click touch handler"
      contains: "x-on:click"
    - path: "tests/Feature/MobileTouchPlaybackTest.php"
      provides: "Tests verifying x-on:click attributes render correctly"
  key_links:
    - from: "tr[x-on:click]"
      to: "play-track event"
      via: "Alpine.js $dispatch"
      pattern: "\\$dispatch\\('play-track'"
    - from: "tr[x-on:click]"
      to: "action buttons"
      via: "event.target.closest guard"
      pattern: "closest.*button.*form"
---

<objective>
Add mobile touch playback to all track listing views by placing Alpine.js `x-on:click` handlers on track `<tr>` elements. On touch devices, CSS `group-hover` never activates, making play buttons invisible. This fix makes the entire row tappable for available tracks while preventing action button clicks from double-firing playback.

Purpose: Users on mobile/touch devices currently cannot play tracks because play buttons are hidden behind hover states.
Output: All three track listing views updated with row-level click handlers, plus feature tests.
</objective>

<execution_context>
@./.claude/get-shit-done/workflows/execute-plan.md
@./.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/pages/library/album-show.blade.php
@resources/views/playlists/show.blade.php
@resources/views/pages/library/index.blade.php
@tests/Feature/Library/LibraryUnavailableTrackTest.php
@tests/Feature/AlbumShowTest.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add x-on:click row handlers to all track listing views</name>
  <files>resources/views/pages/library/album-show.blade.php, resources/views/playlists/show.blade.php, resources/views/pages/library/index.blade.php</files>
  <action>
Add an `x-on:click` handler to each track `<tr>` element for available tracks across all three views. The handler must:

1. Guard against action button clicks: `if ($event.target.closest('button, a, form')) return;`
2. Dispatch the appropriate play-track event with the correct trackIds and index
3. Only be added when `$track->is_available` is true

**album-show.blade.php** (line ~71, the `<tr>` element):
Change the `<tr>` from:
```
<tr wire:key="{{ $track->id }}" class="group border-b border-zinc-800 hover:bg-zinc-800/50 {{ $track->is_available ? '' : 'opacity-50' }}">
```
To:
```
<tr wire:key="{{ $track->id }}"
    @if ($track->is_available)
        x-on:click="if ($event.target.closest('button, a, form')) return; $dispatch('play-track', { trackIds: {{ Js::from($albumTrackIds) }}, index: {{ $index }} })"
    @endif
    class="group border-b border-zinc-800 hover:bg-zinc-800/50 {{ $track->is_available ? 'cursor-pointer' : 'opacity-50' }}"
>
```

**playlists/show.blade.php** (line ~64, the `<tr>` element):
Same pattern. Change the `<tr>` to add conditional `x-on:click`:
```
<tr wire:key="{{ $track->id }}"
    @if ($track->is_available)
        x-on:click="if ($event.target.closest('button, a, form')) return; $dispatch('play-track', { trackIds: {{ Js::from($trackIds) }}, index: {{ $index }} })"
    @endif
    class="group border-b border-zinc-800 hover:bg-zinc-800/50 {{ $track->is_available ? 'cursor-pointer' : 'opacity-50' }}"
>
```

**pages/library/index.blade.php** — TWO track listing sections:

Search results tracks (line ~170, `<tr>` element):
```
<tr wire:key="{{ $track->id }}"
    @if ($track->is_available)
        x-on:click="if ($event.target.closest('button, a, form')) return; $dispatch('play-track', { trackIds: {{ Js::from($searchTrackIds) }}, index: {{ $index }} })"
    @endif
    class="group border-b border-zinc-800 hover:bg-zinc-800/50 {{ $track->is_available ? 'cursor-pointer' : 'opacity-50' }}"
>
```

Tab tracks list (line ~330, `<tr>` element):
```
<tr wire:key="{{ $track->id }}"
    @if ($track->is_available)
        x-on:click="if ($event.target.closest('button, a, form')) return; $dispatch('play-track', { trackIds: {{ Js::from($tabTrackIds) }}, index: {{ $index }} })"
    @endif
    class="group border-b border-zinc-800 hover:bg-zinc-800/50 {{ $track->is_available ? 'cursor-pointer' : 'opacity-50' }}"
>
```

Also make action buttons (add-to-playlist, relink, remove) always visible on mobile by adding `max-sm:opacity-100` alongside the existing `opacity-0 group-hover:opacity-100` classes on each action button in all three views. This ensures action buttons are tappable on touch devices without requiring hover. The pattern to find these buttons: search for `opacity-0 transition-opacity group-hover:opacity-100` in each file and add `max-sm:opacity-100` after `opacity-0`.

Use `Js::from()` (Laravel's JavaScript encoder) instead of `@js()` inside attribute strings since `@js()` is a Blade directive best used in standalone contexts. The existing play buttons inside `<td>` cells already use `@js()` correctly — leave those untouched.

Run `vendor/bin/pint --dirty` after changes.
  </action>
  <verify>
    <automated>cd /Users/defou/Herd/rekodo && grep -c "x-on:click.*play-track" resources/views/pages/library/album-show.blade.php resources/views/playlists/show.blade.php resources/views/pages/library/index.blade.php</automated>
  </verify>
  <done>All four track `<tr>` elements (1 in album-show, 1 in playlist show, 2 in library index) have x-on:click handlers that dispatch play-track with button/form guard. Available rows have cursor-pointer. Action buttons have max-sm:opacity-100 for mobile visibility.</done>
</task>

<task type="auto">
  <name>Task 2: Create feature tests for mobile touch playback attributes</name>
  <files>tests/Feature/MobileTouchPlaybackTest.php</files>
  <action>
Create `tests/Feature/MobileTouchPlaybackTest.php` using `php artisan make:test --pest MobileTouchPlaybackTest --no-interaction`.

Write tests following the existing pattern in `LibraryUnavailableTrackTest.php` (uses `Livewire::actingAs($user)->test('pages::library.index')->html()` and `$this->actingAs($user)->get(route(...))->assertOk()` patterns).

Tests to write:

1. **album show: available track row has x-on:click with play-track dispatch**
   - Create user, album, available track
   - GET route('library.album.show', $album)
   - Assert response HTML contains `play-track` inside an `x-on:click` attribute (use assertSee or check raw HTML content)
   - Also assert `cursor-pointer` is present

2. **album show: unavailable track row does NOT have x-on:click play-track on tr**
   - Create user, album, track with `is_available => false`
   - GET route
   - Get HTML, confirm the `<tr>` for this track does NOT have `x-on:click` containing `play-track` at the row level
   - Note: play buttons inside `<td>` won't exist either for unavailable tracks, so look for the absence of `cursor-pointer` class being applied

3. **library index: available track row has x-on:click with play-track dispatch**
   - Create user, available track
   - Use `Livewire::actingAs($user)->test('pages::library.index')->html()`
   - Assert HTML contains row-level `x-on:click` with `play-track`
   - Assert `cursor-pointer` is present

4. **library index: unavailable track row does NOT have cursor-pointer**
   - Create user, track with `is_available => false`
   - Livewire test
   - Assert HTML does NOT contain `cursor-pointer` for unavailable track context (unavailable tracks get `opacity-50` instead)

5. **playlist show: available track row has x-on:click with play-track dispatch**
   - Create user, playlist, attach an available track
   - GET route('playlists.show', $playlist)
   - Assert response contains `cursor-pointer` and row-level `play-track`

6. **playlist show: action buttons have mobile visibility class**
   - Create user, playlist, attach an available track
   - GET route
   - Assert response HTML contains `max-sm:opacity-100` (the mobile visibility class on action buttons)

Use existing model factories. Follow fake() convention from existing tests. Reference test patterns in AlbumShowTest.php and LibraryUnavailableTrackTest.php.

Run `vendor/bin/pint --dirty` after creating the test file.
  </action>
  <verify>
    <automated>cd /Users/defou/Herd/rekodo && php artisan test --compact tests/Feature/MobileTouchPlaybackTest.php</automated>
  </verify>
  <done>All 6 tests pass, confirming: available track rows render with x-on:click play-track handlers and cursor-pointer class; unavailable track rows do not; action buttons have mobile visibility class.</done>
</task>

</tasks>

<verification>
1. `grep -c "x-on:click.*play-track" resources/views/pages/library/album-show.blade.php` shows 2 (1 on tr + 1 on existing play button)
2. `grep -c "x-on:click.*play-track" resources/views/playlists/show.blade.php` shows 2 (1 on tr + 1 on existing play button)
3. `grep -c "x-on:click.*play-track" resources/views/pages/library/index.blade.php` shows 4 (2 on tr + 2 on existing play buttons)
4. `grep -c "cursor-pointer" resources/views/pages/library/album-show.blade.php resources/views/playlists/show.blade.php resources/views/pages/library/index.blade.php` — each file has cursor-pointer on available track rows
5. `grep -c "max-sm:opacity-100" resources/views/pages/library/album-show.blade.php resources/views/playlists/show.blade.php resources/views/pages/library/index.blade.php` — action buttons in all files have mobile visibility
6. `php artisan test --compact tests/Feature/MobileTouchPlaybackTest.php` — all tests pass
7. Existing tests still pass: `php artisan test --compact tests/Feature/AlbumShowTest.php tests/Feature/Library/LibraryUnavailableTrackTest.php`
</verification>

<success_criteria>
- Available track rows in all 3 views dispatch play-track on click/tap
- Action buttons (add-to-playlist, relink, remove) clicks do NOT trigger row playback (guarded by closest check)
- Unavailable track rows do NOT have the click handler
- Available track rows show cursor-pointer for visual affordance
- Action buttons are visible on mobile (max-sm:opacity-100)
- All new and existing tests pass
</success_criteria>

<output>
After completion, create `.planning/quick/4-copy-local-tracks-to-app-storage-for-rem/4-SUMMARY.md`
</output>
