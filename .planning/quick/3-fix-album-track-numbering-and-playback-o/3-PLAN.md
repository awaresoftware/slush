---
phase: quick-3
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/pages/library/album-show.blade.php
  - tests/Feature/AlbumShowTest.php
autonomous: true
requirements: [fix-track-numbering, fix-playback-index, null-track-numbers-last]
must_haves:
  truths:
    - "Track # column shows actual track_number metadata (1, 2, 3...) not original collection keys"
    - "Tracks with null track_number appear at end of list, sorted by title"
    - "Clicking a track plays that exact track (playback index matches display position)"
    - "Null-track-number tracks show sequential fallback position in # column"
  artifacts:
    - path: "resources/views/pages/library/album-show.blade.php"
      provides: "Fixed album track list with correct numbering and playback"
      contains: "->values()"
    - path: "tests/Feature/AlbumShowTest.php"
      provides: "Tests for track ordering including null track_number edge case"
  key_links:
    - from: "album-show.blade.php line 46"
      to: "play-track dispatch on line 65"
      via: "$albumTrackIds array and $index must be in sync"
      pattern: "index.*\\$index"
---

<objective>
Fix album track numbering display and playback order mismatch in album-show.blade.php.

Purpose: Tracks currently show wrong numbers and clicking a track plays a different track because `sortBy()` preserves original collection keys. Also implement null-track-number-last sorting per user decision.
Output: Corrected blade template and expanded tests.
</objective>

<execution_context>
@./.claude/get-shit-done/workflows/execute-plan.md
@./.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/pages/library/album-show.blade.php
@tests/Feature/AlbumShowTest.php
@database/factories/TrackFactory.php

<interfaces>
<!-- From resources/views/pages/library/album-show.blade.php -->
<!-- Key variables in play: -->
<!-- Line 46: $albumTrackIds — sorted track IDs array passed to JS play-track dispatch -->
<!-- Line 58: @foreach with $index => $track — $index used for display number AND playback index -->
<!-- Line 62: {{ $index + 1 }} — track number display -->
<!-- Line 65: index: {{ $index }} — playback index sent to audio player -->

<!-- Track model has: track_number (nullable unsignedSmallInteger), title, is_available -->
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Fix sorting, numbering, and playback index in album-show blade</name>
  <files>resources/views/pages/library/album-show.blade.php, tests/Feature/AlbumShowTest.php</files>
  <behavior>
    - Test: tracks with track_numbers render in track_number order (existing test, should still pass)
    - Test: tracks with null track_number appear after tracks with track_numbers
    - Test: null-track-number tracks are sorted by title among themselves
    - Test: track number column shows actual track_number metadata value
    - Test: playback index is sequential (0-based) matching display order
  </behavior>
  <action>
**Step 1 — Create a custom sort in the PHP section (lines 1-17):**

In the `mount()` method or as a computed property, create a sorted tracks collection that:
1. Sorts tracks with non-null `track_number` first (ascending by track_number)
2. Then tracks with null `track_number` (sorted by title ascending)
3. Chains `->values()` to reset to 0-based sequential keys

The cleanest approach: add a method or use an inline `@php` block. Since this is a Livewire full-page component, add a public method or computed property that returns the sorted collection. Alternatively, do the sort once in the `@php` block near line 46 and reuse the variable.

Replace the `@php` block on line 46 with:
```php
@php
    $sortedTracks = $album->tracks
        ->sortBy([
            fn ($a, $b) => match (true) {
                is_null($a->track_number) && is_null($b->track_number) => strcmp($a->title, $b->title),
                is_null($a->track_number) => 1,
                is_null($b->track_number) => -1,
                default => $a->track_number <=> $b->track_number,
            },
        ])
        ->values();
    $albumTrackIds = $sortedTracks->pluck('id')->all();
@endphp
```

**Step 2 — Update the foreach on line 58:**

Change `@foreach ($album->tracks->sortBy('track_number') as $index => $track)` to:
```blade
@foreach ($sortedTracks as $index => $track)
```

Now `$index` is a clean 0-based sequential integer.

**Step 3 — Fix track number display on line 62:**

Change `{{ $index + 1 }}` to `{{ $track->track_number ?? $index + 1 }}` — shows actual metadata track_number, falls back to sequential position for null.

Apply the same change to the unavailable track display (line 72): change `{{ $index + 1 }}` to `{{ $track->track_number ?? $index + 1 }}`.

**Step 4 — Playback index (line 65) is already correct** once `->values()` is applied, since `$index` will be 0-based sequential. No change needed on that line.

**Step 5 — Write new test cases in tests/Feature/AlbumShowTest.php:**

Add these tests after the existing test:

1. `test('album show renders null track_number tracks after numbered tracks sorted by title')` — Create 4 tracks: track_number=2, track_number=1, track_number=null title="Zebra", track_number=null title="Alpha". Assert page shows in order: track with #1, track with #2, "Alpha", "Zebra". Use `assertSeeInOrder()`.

2. `test('album show displays actual track_number metadata in number column')` — Create a track with track_number=5. Assert the page `assertSee` the value "5" in the rendered output. Use `assertSeeInOrder` with the track title to confirm association.

**Step 6 — Run pint:**
Run `vendor/bin/pint --dirty` to fix formatting.

**Step 7 — Run tests:**
Run `php artisan test --compact tests/Feature/AlbumShowTest.php` to verify all tests pass.
  </action>
  <verify>
    <automated>cd /Users/defou/Herd/foonk && php artisan test --compact tests/Feature/AlbumShowTest.php</automated>
  </verify>
  <done>
    - All existing album show tests still pass
    - New test confirms null track_numbers sort to end, ordered by title
    - New test confirms track_number metadata value is displayed
    - Playback index is 0-based sequential after ->values() (verified by $index usage in foreach)
    - Pint formatting clean
  </done>
</task>

</tasks>

<verification>
1. `php artisan test --compact tests/Feature/AlbumShowTest.php` — all tests pass
2. `vendor/bin/pint --test --dirty` — no formatting issues
3. Manual spot-check: view an album page in browser to confirm track numbers display correctly and clicking plays the right track
</verification>

<success_criteria>
- Track # column shows actual track_number from metadata (e.g., 1, 2, 3) not stale collection keys
- Null track_number tracks appear at end of list, sorted alphabetically by title
- Null track_number tracks show fallback sequential position number
- Clicking any track row plays that specific track (index matches display position)
- All AlbumShowTest tests pass including new edge case coverage
</success_criteria>

<output>
After completion, create `.planning/quick/3-fix-album-track-numbering-and-playback-o/3-SUMMARY.md`
</output>
