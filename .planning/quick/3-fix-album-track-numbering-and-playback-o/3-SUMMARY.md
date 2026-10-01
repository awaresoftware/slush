---
phase: quick-3
plan: 01
subsystem: library-ui
tags: [bug-fix, livewire, blade, sorting, playback]
dependency_graph:
  requires: []
  provides: [correct-album-track-ordering, correct-playback-index]
  affects: [resources/views/pages/library/album-show.blade.php]
tech_stack:
  added: []
  patterns: [collection-custom-sort, values-key-reset]
key_files:
  created: []
  modified:
    - resources/views/pages/library/album-show.blade.php
    - tests/Feature/AlbumShowTest.php
decisions:
  - "Custom sortBy comparator over simple sortBy('track_number') to handle null values correctly"
  - "->values() required after sortBy to reset collection keys to 0-based sequential integers"
  - "Track # display uses $track->track_number ?? $index + 1 — shows metadata value, falls back to position"
metrics:
  duration: 54s
  completed_date: "2026-03-19"
  tasks_completed: 1
  files_modified: 2
---

# Quick Task 3: Fix Album Track Numbering and Playback Order Summary

**One-liner:** Fixed collection key mismatch by applying `->values()` after custom `sortBy` comparator that puts null `track_number` tracks last (sorted by title), and updated `#` column to display actual `track_number` metadata.

## What Was Done

### Task 1: Fix sorting, numbering, and playback index in album-show blade

**Problem:** `sortBy('track_number')` preserves original collection keys (e.g., tracks inserted in positions 0, 1, 2 remain at those keys after sort). This caused:
1. `$index` in `@foreach` was the original insertion key, not sequential position
2. The play-track dispatch used this stale `$index` as the playback position, causing wrong track to play
3. `{{ $index + 1 }}` showed the wrong number
4. Null `track_number` tracks were mixed in with numbered tracks (null sorts before numbers in PHP comparisons)

**Fix in `album-show.blade.php`:**
- Replaced inline `sortBy('track_number')` with a multi-key `sortBy` using a closure comparator: non-null track_numbers first (ascending), then null-track_number tracks sorted by title
- Chained `->values()` to reset keys to clean 0-based sequential integers
- Stored result in `$sortedTracks` variable (reused for both `$albumTrackIds` and the `@foreach`)
- Changed `{{ $index + 1 }}` to `{{ $track->track_number ?? $index + 1 }}` in both available and unavailable track branches

**New tests in `AlbumShowTest.php`:**
- `album show renders null track_number tracks after numbered tracks sorted by title` — verifies null tracks appear after numbered tracks and are sorted alphabetically
- `album show displays actual track_number metadata in number column` — verifies track_number value (e.g., 5) is rendered, not the collection position

## Test Results

```
Tests:    3 passed (6 assertions)
Duration: 0.48s
```

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

- `/Users/defou/Herd/rekodo/resources/views/pages/library/album-show.blade.php` — FOUND (modified)
- `/Users/defou/Herd/rekodo/tests/Feature/AlbumShowTest.php` — FOUND (modified, 3 tests)
- Commit `078322f` — FOUND
