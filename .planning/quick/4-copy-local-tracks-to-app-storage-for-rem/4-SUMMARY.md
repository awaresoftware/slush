---
phase: quick-4
plan: 01
subsystem: ui
tags: [alpine, livewire, tailwindcss, mobile, touch, playback]

requires: []
provides:
  - Row-level x-on:click play-track handlers on all three track listing views (album-show, playlist show, library index)
  - Mobile action button visibility via max-sm:opacity-100 on all action buttons
  - Feature tests verifying mobile touch playback attributes render correctly
affects: []

tech-stack:
  added: []
  patterns:
    - "Row-level Alpine x-on:click with closest() guard pattern for mobile touch playback"
    - "max-sm:opacity-100 alongside opacity-0 group-hover:opacity-100 for mobile-visible action buttons"

key-files:
  created:
    - tests/Feature/MobileTouchPlaybackTest.php
  modified:
    - resources/views/pages/library/album-show.blade.php
    - resources/views/playlists/show.blade.php
    - resources/views/pages/library/index.blade.php

key-decisions:
  - "Use Js::from() not @js() inside x-on:click attribute strings on tr elements — @js() is a Blade directive best used standalone"
  - "Guard action button clicks with event.target.closest('button, a, form') return pattern on tr click handler"
  - "Test cursor-pointer presence via play aria-label absence for unavailable tracks — layout sidebar also emits cursor-pointer so raw string check fails"

patterns-established:
  - "Mobile touch: x-on:click on tr rows with @if($track->is_available) guard for conditional Alpine attributes"
  - "Action button mobile visibility: add max-sm:opacity-100 alongside opacity-0 group-hover:opacity-100"

requirements-completed: [QUICK-4]

duration: 3min
completed: 2026-03-19
---

# Quick Task 4: Mobile Touch Playback Summary

**Alpine x-on:click row handlers with closest() guard added to all three track listing views so touch devices can play tracks without visible hover states**

## Performance

- **Duration:** ~3 min
- **Started:** 2026-03-19T22:02:19Z
- **Completed:** 2026-03-19T22:05:44Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- All four track `<tr>` elements (1 in album-show, 1 in playlist-show, 2 in library-index) now dispatch `play-track` on click/tap for available tracks
- Action buttons (`add-to-playlist`, `relink`, `remove`) protected by `event.target.closest('button, a, form')` guard — no double-fire
- Action buttons have `max-sm:opacity-100` so they are tappable on mobile without hover
- 6 feature tests covering all three views for both available and unavailable track behavior — all pass
- 9 existing AlbumShowTest and LibraryUnavailableTrackTest tests still pass

## Task Commits

1. **Task 1: Add x-on:click row handlers to all track listing views** - `63e8768` (feat)
2. **Task 2: Create feature tests for mobile touch playback attributes** - `8b368b5` (feat)

**Plan metadata:** TBD (docs)

## Files Created/Modified

- `resources/views/pages/library/album-show.blade.php` - x-on:click on tr, cursor-pointer on available rows, max-sm:opacity-100 on action buttons
- `resources/views/playlists/show.blade.php` - x-on:click on tr, cursor-pointer on available rows, max-sm:opacity-100 on relink + remove buttons
- `resources/views/pages/library/index.blade.php` - x-on:click on two tr sections (search results + tab tracks), max-sm:opacity-100 on all action buttons
- `tests/Feature/MobileTouchPlaybackTest.php` - 6 tests verifying mobile touch playback attributes

## Decisions Made

- Used `Js::from()` instead of `@js()` inside x-on:click attribute strings on `<tr>` elements. `@js()` is a Blade directive that works best in standalone expression contexts; `Js::from()` is the safe choice inside HTML attribute strings.
- Tests for "unavailable track has no click handler" check for absence of `aria-label="Play [track name]"` rather than absence of `cursor-pointer` — the layout sidebar independently emits `cursor-pointer` so a raw string check would always fail.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

- Test for unavailable track row initially failed because the page layout sidebar contains `cursor-pointer` independently of the track row. Fixed by asserting absence of `aria-label="Play [title]"` instead, which is more meaningful and specific to the track playback guard.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Mobile touch playback is ready across all track listing views
- Users on touch/mobile devices can now tap any available track row to start playback
- No further work required for this quick task

---
*Phase: quick-4*
*Completed: 2026-03-19*
