---
phase: 16-missing-file-detection-polish
plan: 02
subsystem: ui
tags: [livewire, blade, alpine, audio-player, unavailable-tracks]

# Dependency graph
requires:
  - phase: 16-missing-file-detection-polish
    plan: 01
    provides: is_available boolean column on tracks, scopeUnavailable/scopeAvailable query scopes
provides:
  - Unavailable track visual indicators (dimmed row, Unavailable badge) in library, album, playlist views
  - Hidden play button and add-to-playlist for unavailable tracks in all views
  - Re-link icon button dispatching open-relink-modal for unavailable tracks
  - Source filter dropdown includes Unavailable option filtering via scopeUnavailable
  - loadQueue excludes unavailable tracks (is_available=true filter)
  - Alpine onAudioError dispatches mary-toast skip notification
affects:
  - 16-03 (re-link modal must handle open-relink-modal event dispatched here)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "@if ($track->is_available) ... @else ... @endif pattern for conditionally rendering play/add-to-playlist vs re-link buttons"
    - "Unavailable badge uses badge-error badge-sm consistent with Local badge-ghost badge-sm convention"
    - "loadQueue is_available filter applied server-side at query time, not in PHP array filter"
    - "Alpine onAudioError uses $dispatch('mary-toast') for skip notification before advancing queue"

key-files:
  created:
    - tests/Feature/Library/LibraryUnavailableTrackTest.php
  modified:
    - resources/views/pages/library/index.blade.php
    - resources/views/pages/library/album-show.blade.php
    - resources/views/playlists/show.blade.php
    - app/Livewire/AudioPlayer.php
    - resources/views/livewire/audio-player.blade.php
    - tests/Feature/AudioPlayer/AudioPlayerTest.php

key-decisions:
  - "Re-link button replaces add-to-playlist (not shown alongside) — simpler UX, unavailable tracks can't be queued anyway"
  - "Playlist show: re-link replaces remove button for unavailable tracks — cannot remove from playlist while file missing would be confusing"
  - "is_available filter applied at DB query level in loadQueue (WHERE clause) not PHP array filter — single trip to DB"
  - "Toast uses mary-toast event dispatch to leverage existing MaryUI notification infrastructure"

patterns-established:
  - "Unavailable track row: opacity-50 class on <tr>, no play button, Unavailable badge, re-link button in actions column"
  - "Apply @if ($track->is_available) guard to all play buttons and action buttons across track listing views"

requirements-completed:
  - LIBR-05

# Metrics
duration: 20min
completed: 2026-03-19
---

# Phase 16 Plan 02: Unavailable Track UI Indicators Summary

**Dimmed rows with Unavailable badge and re-link buttons across library/album/playlist views, source filter extended with Unavailable option, queue excludes unavailable tracks, and Alpine toast on 404**

## Performance

- **Duration:** 20 min
- **Started:** 2026-03-19T16:45:00Z
- **Completed:** 2026-03-19T17:05:00Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments

- Library index, album detail, and playlist detail views all show dimmed (opacity-50) rows for unavailable tracks with Unavailable badge (badge-error), hidden play and add-to-playlist buttons, and a re-link icon button dispatching open-relink-modal
- Source filter dropdown extended with Unavailable option; Library Livewire component adds ->when() for scopeUnavailable
- AudioPlayer::loadQueue now filters where is_available=true in addition to user ownership check
- Alpine onAudioError dispatches mary-toast skip notification before advancing to next track in queue
- 8 new Pest tests: 6 in LibraryUnavailableTrackTest, 2 added to AudioPlayerTest

## Task Commits

Each task was committed atomically:

1. **Task 1: Unavailable track indicators + re-link button in all views + source filter** - `d15bf11` (feat)
2. **Task 2: Queue exclusion + Alpine skip toast** - `c1d9199` (feat)

## Files Created/Modified

- `tests/Feature/Library/LibraryUnavailableTrackTest.php` - 6 Pest tests covering dimmed rows, Unavailable badge, hidden play/add-to-playlist, re-link button, source filter
- `resources/views/pages/library/index.blade.php` - Unavailable indicators in both search results and tab track tables; source filter dropdown; unavailable scope in Volt component
- `resources/views/pages/library/album-show.blade.php` - Unavailable indicators in album track list
- `resources/views/playlists/show.blade.php` - Unavailable indicators in playlist track list
- `app/Livewire/AudioPlayer.php` - loadQueue adds is_available=true filter to owned track query
- `resources/views/livewire/audio-player.blade.php` - onAudioError dispatches mary-toast skip notification
- `tests/Feature/AudioPlayer/AudioPlayerTest.php` - 2 new tests: loadQueue excludes unavailable, adjusts startIndex

## Decisions Made

- Re-link button replaces add-to-playlist for unavailable tracks rather than showing both — unavailable tracks cannot be queued, so offering to queue them would be misleading
- In playlist show, re-link replaces the remove button for unavailable tracks — file must be re-linked before it makes sense to manage its playlist membership
- is_available filter applied at DB query level in loadQueue (additional WHERE clause), not in PHP array filter, to keep it a single DB trip
- Toast uses existing mary-toast event dispatch to leverage MaryUI notification infrastructure already present in the layout

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## Next Phase Readiness

- All unavailable track UI indicators complete across all track listing views
- open-relink-modal event is being dispatched but no modal handler exists yet — plan 16-03 must implement the re-link modal
- loadQueue exclusion and Alpine toast are in place for graceful playback degradation

---
*Phase: 16-missing-file-detection-polish*
*Completed: 2026-03-19*
