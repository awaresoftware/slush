---
phase: 09-page-migration
plan: 04
subsystem: ui
tags: [livewire, blade, mary-ui, tracks, album, upload, testing]

# Dependency graph
requires:
  - phase: 09-page-migration
    provides: migrated page views and Livewire components
provides:
  - Upload button in sidebar nav dispatching open-upload-modal event
  - Album tracks sorted by track_number in display and play-track trackIds
  - AlbumShowTest confirming track_number sort order
  - UploadModalTest sidebar trigger assertion
  - track_number column in tracks table with migration
affects: [phase-10, phase-11, testing]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Sidebar action items use x-on:click with $dispatch rather than link attribute for event-driven triggers"
    - "track_number used as canonical sort key for album track ordering"

key-files:
  created:
    - tests/Feature/AlbumShowTest.php
    - database/migrations/2026_03_17_083324_add_track_number_to_tracks_table.php
  modified:
    - resources/views/layouts/app/sidebar.blade.php
    - resources/views/pages/library/album-show.blade.php
    - app/Models/Track.php
    - tests/Feature/UploadModalTest.php

key-decisions:
  - "track_number column added as nullable unsignedSmallInteger — column was missing from schema entirely, required for correct album ordering"
  - "Upload sidebar item uses x-on:click dispatch with no link attribute — it triggers a modal, not navigation"

patterns-established:
  - "Action-trigger menu items: use x-on:click=$dispatch('event') without link attribute"
  - "Album track display and play-track trackIds array both use same sortBy('track_number') to guarantee consistency"

requirements-completed: [PAGE-01, PAGE-02]

# Metrics
duration: 15min
completed: 2026-03-17
---

# Phase 9 Plan 04: Gap Closure — Upload Button and Album Sort Summary

**Upload button added to sidebar nav via open-upload-modal dispatch, album tracks sorted by track_number with new DB column and passing tests**

## Performance

- **Duration:** 15 min
- **Started:** 2026-03-17T08:24:22Z
- **Completed:** 2026-03-17T08:39:00Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments
- Upload menu item added to sidebar with arrow-up-tray icon dispatching open-upload-modal to already-mounted UploadModal component
- Both sortBy('title') instances in album-show.blade.php changed to sortBy('track_number') fixing display and play order
- track_number column added to tracks table via migration (was missing from schema entirely)
- AlbumShowTest added confirming track_number ordering wins over title ordering
- UploadModalTest sidebar test added confirming open-upload-modal dispatch is present in layout

## Task Commits

Each task was committed atomically:

1. **Task 1: Add upload button to sidebar and fix album track sort order** - `a1b5aa9` (feat)
2. **Task 2: Add tests for sidebar upload button and album track sort order** - `3cf515b` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `resources/views/layouts/app/sidebar.blade.php` - Added Upload menu item with open-upload-modal dispatch
- `resources/views/pages/library/album-show.blade.php` - Changed sortBy('title') to sortBy('track_number') in both locations
- `app/Models/Track.php` - Added track_number to fillable array
- `database/migrations/2026_03_17_083324_add_track_number_to_tracks_table.php` - Adds nullable track_number column to tracks table
- `tests/Feature/AlbumShowTest.php` - New test: album renders tracks in track_number order not title order
- `tests/Feature/UploadModalTest.php` - New test: sidebar contains open-upload-modal dispatch trigger

## Decisions Made
- track_number column added as nullable unsignedSmallInteger — plan referenced it but column was absent from schema
- Upload sidebar item has no link attribute — it triggers a modal open event, not a navigation route

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Added missing track_number column to tracks table**
- **Found during:** Task 2 (TDD test setup)
- **Issue:** Plan called for sortBy('track_number') but the column had never been added to the tracks schema; test threw QueryException on insert
- **Fix:** Created migration adding nullable unsignedSmallInteger track_number column; added to Track model fillable array
- **Files modified:** database/migrations/2026_03_17_083324_add_track_number_to_tracks_table.php, app/Models/Track.php
- **Verification:** AlbumShowTest passes GREEN after migration runs
- **Committed in:** 3cf515b (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (missing column required by plan's own sort logic)
**Impact on plan:** Essential fix — without the column the sort behavior is undefined and tests cannot run.

## Issues Encountered
- track_number column referenced in plan but never existed in the tracks migration. Added via new migration as Rule 1 auto-fix.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Phase 09 UAT gaps are now closed: upload button is in sidebar, album tracks sort by track_number
- Phase 10 can proceed; track_number is available for track editing/upload flows
- No blockers

## Self-Check: PASSED

- FOUND: resources/views/layouts/app/sidebar.blade.php
- FOUND: resources/views/pages/library/album-show.blade.php
- FOUND: tests/Feature/AlbumShowTest.php
- FOUND: tests/Feature/UploadModalTest.php
- FOUND: database/migrations/2026_03_17_083324_add_track_number_to_tracks_table.php
- FOUND: .planning/phases/09-page-migration/09-04-SUMMARY.md
- FOUND commit a1b5aa9: feat(09-04): add upload button to sidebar and fix album track sort order
- FOUND commit 3cf515b: feat(09-04): add tests for upload button and album track sort; add track_number migration

---
*Phase: 09-page-migration*
*Completed: 2026-03-17*
