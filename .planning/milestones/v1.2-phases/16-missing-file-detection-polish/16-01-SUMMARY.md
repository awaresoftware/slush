---
phase: 16-missing-file-detection-polish
plan: 01
subsystem: database
tags: [migration, eloquent, jobs, file-detection, is-available]

# Dependency graph
requires:
  - phase: 13-local-library-linking
    provides: Track model with file_path, LinkedFolder model, ScanLinkedFolder job, StreamController
provides:
  - is_available boolean column on tracks table (default true)
  - unavailable_count integer column on linked_folders table (default 0)
  - Track scopeUnavailable and scopeAvailable query scopes
  - ScanLinkedFolder availability detection loop with auto-restore
  - StreamController sets is_available=false as side-effect of 404 response
affects:
  - 16-02 (UI badge/indicator for unavailable tracks reads is_available)
  - any future scheduling or notification around missing files

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Availability check loop runs after filesystem iterator in ScanLinkedFolder — separate concerns
    - Use relationship query tracks()->get() not cached $folder->tracks to avoid stale data in long-running jobs
    - StreamController side-effect: updates is_available=false when serving 404, so DB stays consistent without needing a full rescan

key-files:
  created:
    - database/migrations/2026_03_19_162051_add_is_available_to_tracks_table.php
    - database/migrations/2026_03_19_162058_add_unavailable_count_to_linked_folders_table.php
  modified:
    - app/Models/Track.php
    - app/Models/LinkedFolder.php
    - app/Jobs/ScanLinkedFolder.php
    - app/Http/Controllers/StreamController.php
    - tests/Feature/ScanLinkedFolderTest.php

key-decisions:
  - "Use tracks()->get() not $folder->tracks in availability loop to prevent stale cached relationship data"
  - "StreamController marks is_available=false as side-effect when file missing — opportunistic consistency without full rescan"
  - "unavailable_count counts tracks whose file is missing at time of scan, not cumulative"

patterns-established:
  - "Availability loop: separate pass after filesystem discovery, not interleaved — cleaner responsibility separation"
  - "Auto-restore: if file_exists() returns true and is_available is false, restore without manual intervention"

requirements-completed:
  - LIBR-05

# Metrics
duration: 25min
completed: 2026-03-19
---

# Phase 16 Plan 01: is_available Infrastructure Summary

**is_available boolean + unavailable_count columns with ScanLinkedFolder detection loop, auto-restore, and StreamController 404 side-effect marking**

## Performance

- **Duration:** 25 min
- **Started:** 2026-03-19T16:20:00Z
- **Completed:** 2026-03-19T16:45:00Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments
- Two migrations applied: is_available on tracks (default true), unavailable_count on linked_folders (default 0)
- Track model gains scopeUnavailable/scopeAvailable; LinkedFolder model gains unavailable_count in fillable/casts
- ScanLinkedFolder post-scan availability loop marks missing files unavailable and auto-restores reappeared files
- StreamController marks track is_available=false as a side-effect when serving 404 for missing file
- 15 ScanLinkedFolder tests pass, 9 StreamController tests pass

## Task Commits

Each task was committed atomically:

1. **Task 1: Migration + Model changes for is_available** - `4e61309` (feat)
2. **Task 2 RED: Failing tests for availability detection** - `87096ed` (test)
3. **Task 2 GREEN: ScanLinkedFolder detection + StreamController side-effect** - `8a5727e` (feat)

## Files Created/Modified
- `database/migrations/2026_03_19_162051_add_is_available_to_tracks_table.php` - Adds is_available boolean default true after source_type
- `database/migrations/2026_03_19_162058_add_unavailable_count_to_linked_folders_table.php` - Adds unavailable_count unsignedInteger default 0 after track_count
- `app/Models/Track.php` - is_available in fillable/casts, scopeUnavailable, scopeAvailable
- `app/Models/LinkedFolder.php` - unavailable_count in fillable/casts
- `app/Jobs/ScanLinkedFolder.php` - Availability check loop after filesystem scan, unavailable_count in final folder update
- `app/Http/Controllers/StreamController.php` - Marks track is_available=false when file missing (404 side-effect)
- `tests/Feature/ScanLinkedFolderTest.php` - 4 new tests for availability detection, restoration, and count

## Decisions Made
- Used `tracks()->get()` instead of `$folder->tracks` (cached relation) in availability loop — prevents stale data when job re-uses the same folder instance across multiple handle() calls
- StreamController opportunistically marks is_available=false when serving 404 — keeps DB consistent without requiring a full rescan trigger
- unavailable_count reflects current state at scan time, not cumulative

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- "Restores track" test failed on first GREEN attempt because `$this->folder->tracks` was a cached Eloquent collection from the previous `handle()` call. Fixed by changing to `$this->folder->tracks()->get()` to always query fresh data.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- is_available infrastructure complete; Phase 16 Plan 02 (UI badge/indicator) can read is_available directly from Track model
- ScanLinkedFolder auto-restores tracks when files reappear — no manual intervention needed

---
*Phase: 16-missing-file-detection-polish*
*Completed: 2026-03-19*
