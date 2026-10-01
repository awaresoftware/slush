---
phase: quick-06
plan: 01
subsystem: library-ui
tags: [livewire, infinite-scroll, albums, artists, oom-fix, lazy-tabs]
dependency_graph:
  requires: []
  provides: [lazy-tabs, albums-pagination, artists-tab]
  affects: [library-index]
tech_stack:
  added: []
  patterns: [wire:intersect infinite scroll, template x-if lazy tabs, array-based pagination]
key_files:
  created:
    - tests/Feature/Library/LibraryAlbumsPaginationTest.php
    - tests/Feature/Library/LibraryArtistsTabTest.php
  modified:
    - resources/views/pages/library/index.blade.php
    - tests/Feature/Library/LibrarySearchTest.php
decisions:
  - loadedAlbums/loadedArtists use same array-accumulation pattern as loadedTracks for consistency
  - template x-if used (not x-show) so hidden tab content has zero DOM elements and zero image requests
  - Albums removed as Computed property; replaced by loadedAlbums public array with 20/page pagination
  - Artists tab queries Track model for distinct artists with COUNT, then looks up albums per artist
  - Tab order set to Tracks | Artists | Albums per plan spec
metrics:
  duration_seconds: 201
  completed_date: "2026-03-19"
  tasks_completed: 2
  tasks_total: 3
  files_changed: 4
---

# Quick Task 6: Paginate Albums Grid with Infinite Scroll — Summary

**One-liner:** Lazy x-if tabs with 20/page infinite scroll for albums and new Artists tab, eliminating Herd Expose OOM from hidden album cover img tags.

## What Was Built

### Task 1: Lazy tabs (x-if) + Albums/Artists pagination PHP logic

- Replaced all `x-show="activeTab === '...'"` with `<template x-if="activeTab === '...'"><div>...</div></template>` — hidden tab content now produces zero DOM elements and zero image requests, eliminating the WebSocket OOM crash on Herd Expose.
- Added album pagination: `$albumPage`, `$loadedAlbums`, `$hasMoreAlbums`, `$albumsPerPage = 20` properties with `loadAlbums()`, `loadMoreAlbums()`, `resetAlbumsState()` methods.
- Added artist pagination: `$artistPage`, `$loadedArtists`, `$hasMoreArtists`, `$artistsPerPage = 20` properties with `loadArtists()`, `loadMoreArtists()`, `resetArtistsState()` methods.
- New Artists tab shows distinct artists with track counts and album thumbnails (horizontal scroll strip).
- Both albums and artists have `wire:intersect` sentinels for infinite scroll.
- Removed `albums()` computed property; `userAlbums()` computed preserved for upload picker.
- Updated `updatedSearch()` and `updatedSourceFilter()` to also reset albums and artists state.
- Added `loading="lazy"` to all album cover `<img>` tags.
- Tab order: Tracks | Artists | Albums.
- Search results albums section updated to use `$this->loadedAlbums` with pagination.

### Task 2: Test updates and new test files

- Updated `LibrarySearchTest.php`: replaced 5 `albums()` computed property tests with `loadedAlbums` array-based equivalents using `collect($loadedAlbums)->pluck('id')`.
- Created `LibraryAlbumsPaginationTest.php` with 5 tests: batch loading (20/mount), loadMore appends, hasMoreAlbums flag, search reset, data shape.
- Created `LibraryArtistsTabTest.php` with 7 tests: artist loading with counts, null/empty exclusion, album thumbnails, pagination at 20/page, loadMore appends, search reset, tab button visibility.
- All 24 tests pass.

### Task 3: Visual verification (checkpoint:human-verify)

Skipped per instructions — user will verify in browser manually.

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 | 3db702e | feat(quick-06): lazy tabs (x-if) + albums/artists pagination PHP logic |
| 2 | dd119e5 | feat(quick-06): update search tests and add albums pagination + artists tab tests |

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

- [x] `resources/views/pages/library/index.blade.php` — modified, syntax valid
- [x] `tests/Feature/Library/LibrarySearchTest.php` — updated
- [x] `tests/Feature/Library/LibraryAlbumsPaginationTest.php` — created, all tests pass
- [x] `tests/Feature/Library/LibraryArtistsTabTest.php` — created, all tests pass
- [x] Commits 3db702e and dd119e5 exist in git log
- [x] `vendor/bin/pint --dirty` clean
- [x] 24 new/updated tests pass

## Self-Check: PASSED
