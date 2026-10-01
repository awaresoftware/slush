---
phase: quick-5
plan: "01"
subsystem: library-performance
tags: [infinite-scroll, pagination, shuffle, queue-extension, livewire, alpine]
dependency_graph:
  requires: []
  provides:
    - Library infinite scroll with 50-track batch loading
    - Alpine-managed trackIds array (not per-row JSON)
    - AudioPlayer extendQueue and toggleShuffle methods
    - Album detail simplePaginate(50)
  affects:
    - resources/views/pages/library/index.blade.php
    - app/Livewire/AudioPlayer.php
    - resources/views/livewire/audio-player.blade.php
    - resources/views/pages/library/album-show.blade.php
tech_stack:
  added: []
  patterns:
    - wire:intersect for infinite scroll sentinel
    - Alpine x-data trackIds array shared across table rows
    - tracks-loaded browser event to append new IDs to Alpine state
    - queue-running-low browser event dispatched by server and client
    - WithPagination + simplePaginate(50) for album tracks
    - Fisher-Yates shuffle in PHP for toggleShuffle
key_files:
  created:
    - tests/Feature/Library/InfiniteScrollTest.php
  modified:
    - resources/views/pages/library/index.blade.php
    - app/Livewire/AudioPlayer.php
    - resources/views/livewire/audio-player.blade.php
    - resources/views/pages/library/album-show.blade.php
    - tests/Feature/AudioPlayer/AudioPlayerTest.php
decisions:
  - Use forPage() not paginate() — library does not need page links, infinite scroll handles it
  - Dispatch tracks-loaded browser event with newIds slice so Alpine appends without full Livewire re-render
  - queue-running-low dual-dispatch: server side in nextTrack() AND client side in onEnded() for reliability
  - extendQueue uses array_diff to avoid duplicate IDs
  - toggleShuffle keeps originalQueue to allow lossless restore; loadQueue() resets it
metrics:
  duration: "~6 minutes"
  completed_date: "2026-03-19"
  tasks_completed: 3
  tasks_total: 3
  files_modified: 6
---

# Quick Task 5: Optimize Track Loading with Livewire Lazy/Infinite Scroll — Summary

**One-liner:** Infinite scroll library with Alpine-managed trackIds, AudioPlayer shuffle/extendQueue, and album simplePaginate(50) replacing OOM-causing ->get() calls.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Library index infinite scroll with Alpine-managed track IDs | bf8d221 | index.blade.php, InfiniteScrollTest.php |
| 2 | AudioPlayer shuffle mode and auto-extend queue | 93b77c0 | AudioPlayer.php, audio-player.blade.php, AudioPlayerTest.php |
| 3 | Album detail pagination and final test verification | c57dbb0 | album-show.blade.php |

## What Was Built

### Task 1: Library Infinite Scroll

The library index `tracks()` computed property called `->get()` loading ALL tracks into memory. With 20k+ tracks this caused OOM crashes.

**Replaced with:**
- `loadTracks()` private method using `->forPage($this->page, $this->perPage)->get()` (50 tracks per batch)
- `loadedTracks` array property (accumulated track data arrays)
- `trackIds` array property (accumulated IDs for playback queue)
- `hasMoreTracks` bool, `page` int, `perPage = 50` properties
- `mount()` calls `loadTracks()` for the initial batch
- `loadMore()` public method — increments page, loads next batch, dispatches `tracks-loaded` browser event with new IDs
- `updatedSearch()` and `updatedSourceFilter()` reset and reload first batch
- Both track tables (default view + search view) wrapped in Alpine `x-data="{ trackIds: @js($this->trackIds) }"` with `@tracks-loaded.window` listener appending new IDs
- `wire:intersect="loadMore"` sentinel `<div>` after each table body
- `@queue-running-low.window` listener on track table wrappers calls `$wire.loadMore()` if more tracks exist

**Key decision:** Track IDs now live in a single Alpine `trackIds` variable per table wrapper — each `<tr>` references `trackIds` and its `$index`, not a per-row JSON blob. This eliminates the 20k-integer JSON duplication.

### Task 2: AudioPlayer Shuffle and Queue Extension

- `extendQueue(array $newTrackIds)` — filters to owned+available, deduplicates against current queue, appends (or if shuffled, Fisher-Yates shuffles new tracks and splices them after current position)
- `toggleShuffle()` — Fisher-Yates shuffles queue with current track at position 0; toggling off restores `$originalQueue`; `loadQueue()` resets shuffle state
- `nextTrack()` now dispatches browser event `queue-running-low` when current index is within last 3 tracks
- Shuffle button added to audio player controls: `[shuffle] [prev] [play/pause] [next]`
- `onEnded()` in Alpine also dispatches `queue-running-low` (belt-and-suspenders alongside server dispatch)

### Task 3: Album Detail Pagination

- Added `WithPagination` trait to `album-show` component
- Replaced `$album->load('tracks')` eager load with `mount()` storing just `$this->album = $album`
- Added `tracks()` computed property with `simplePaginate(50)` and DB-level sorting:
  - `CASE WHEN track_number IS NULL THEN 1 ELSE 0 END` puts numbered tracks first
  - Then `orderBy('track_number')`, then `orderBy('title')`
- Removed PHP collection `sortBy` block from blade template
- Added `{{ $this->tracks->links() }}` after table

## Test Results

79 tests pass across all affected files:
- `tests/Feature/Library/InfiniteScrollTest.php` — 7 new tests
- `tests/Feature/Library/LibrarySearchTest.php` — 12 existing tests (no changes needed)
- `tests/Feature/Library/LibrarySourceFilterTest.php` — 6 existing tests
- `tests/Feature/Library/LibraryViewTest.php` — 16 existing tests
- `tests/Feature/AudioPlayer/AudioPlayerTest.php` — 9 new + 22 existing = 31 tests
- `tests/Feature/AlbumShowTest.php` — 3 existing tests

**Pre-existing failure (not caused by this work):** `MusicLibraryFoundationTest > it track audio URL returns the storage URL` was already failing before this task.

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

### Files exist:
- resources/views/pages/library/index.blade.php: FOUND
- app/Livewire/AudioPlayer.php: FOUND
- resources/views/livewire/audio-player.blade.php: FOUND
- resources/views/pages/library/album-show.blade.php: FOUND
- tests/Feature/Library/InfiniteScrollTest.php: FOUND
- tests/Feature/AudioPlayer/AudioPlayerTest.php: FOUND

### Commits exist:
- bf8d221: FOUND (Task 1)
- 93b77c0: FOUND (Task 2)
- c57dbb0: FOUND (Task 3)

## Self-Check: PASSED
