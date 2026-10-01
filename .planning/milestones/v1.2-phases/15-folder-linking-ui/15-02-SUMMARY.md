---
phase: 15-folder-linking-ui
plan: "02"
subsystem: ui
tags: [livewire, blade, source-badge, source-filter, library, local-tracks]

# Dependency graph
requires:
  - phase: 14-scan-job
    provides: Track model with source_type enum, scopeUploaded/scopeLocal query scopes
  - phase: 12-data-foundation
    provides: SourceType enum (Uploaded/Local cases)

provides:
  - Source badges (badge-ghost badge-sm) on all track listings in library, album, and playlist views
  - Source filter dropdown on library index page filtering by All/Uploaded/Local
  - Contextual empty states when filter yields no results
  - 12 tests covering badge display and source filtering behavior

affects: [phase-16-missing-file-detection, future-library-features]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "x-mary-badge with badge-ghost badge-sm for source type indicators"
    - "Livewire sourceFilter property with ->when() scopes for reactive filtering"
    - "Contextual empty state pattern: @if sourceFilter === 'local'/'uploaded'/'all'"

key-files:
  created:
    - tests/Feature/Library/LibrarySourceBadgeTest.php
    - tests/Feature/Library/LibrarySourceFilterTest.php
  modified:
    - resources/views/pages/library/index.blade.php
    - resources/views/pages/library/album-show.blade.php
    - resources/views/playlists/show.blade.php

key-decisions:
  - "badge-ghost badge-sm used for Local badge — subtle styling that doesn't dominate the track title"
  - "Livewire component name for testing is pages::library.index (double-colon namespace separator)"
  - "sourceFilter property added to library Livewire component, not a URL query param — keeps implementation simple"
  - "assertSee('badge-ghost', false) used in tests since badge-ghost appears as a CSS class attribute value"

patterns-established:
  - "Source badge pattern: wrap title in flex span, conditional x-mary-badge for Local source_type"
  - "Livewire filter pattern: ->when($this->sourceFilter === 'x', fn ($q) => $q->scope())"

requirements-completed: [LIBR-01, LIBR-02, LIBR-03]

# Metrics
duration: 8min
completed: 2026-03-18
---

# Phase 15 Plan 02: Source Badges and Filter Summary

**Local/Uploaded source badges on all track listings and reactive source filter dropdown on library index using Livewire sourceFilter property with scopeUploaded/scopeLocal query scopes**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-03-18T22:32:39Z
- **Completed:** 2026-03-18T22:40:45Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments

- Added "Local" badge (badge-ghost badge-sm) next to local track titles in library index (both search and tab sections), album detail, and playlist detail views
- Added source filter dropdown (All Sources / Uploaded / Local) to library page search bar area with live Livewire reactive filtering
- Contextual empty state messages shown when filter yields no results ("No local tracks found. Link a folder in Settings > Linked Folders." / "No uploaded tracks found.")
- 12 tests total: 6 badge tests + 6 filter tests, all passing

## Task Commits

Each task was committed atomically:

1. **Task 1: Add source badges to track listings** - `2a5cd9a` (feat)
2. **Task 2: Add source filter dropdown to library page** - `ee9f5ed` (feat)

_Note: TDD tasks — tests written first (RED), then implementation (GREEN), committed together per task._

## Files Created/Modified

- `resources/views/pages/library/index.blade.php` - Added sourceFilter property, source filter scopes in tracks() computed method, filter dropdown in UI, contextual empty states, badges in both track table sections
- `resources/views/pages/library/album-show.blade.php` - Added Local badge to track title cell
- `resources/views/playlists/show.blade.php` - Added Local badge to track title cell
- `tests/Feature/Library/LibrarySourceBadgeTest.php` - 6 tests for badge presence/absence across library, album, playlist pages
- `tests/Feature/Library/LibrarySourceFilterTest.php` - 6 tests for source filter behavior including contextual empty states

## Decisions Made

- Used `badge-ghost badge-sm` class for the Local badge — matches the Mary UI daisyUI conventions used elsewhere in the project and keeps badge subtle
- Livewire component referenced in tests as `pages::library.index` (discovered from existing settings tests pattern)
- Used `assertSee('badge-ghost', false)` to assert the badge CSS class appears as an HTML attribute, not escaped text
- sourceFilter scoped only on `tracks()` computed method (albums tab not filtered by source since albums don't have direct source_type)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

- Initial TDD RED phase tests passed unexpectedly — resolved by using `assertSee('badge-ghost', false)` instead of `assertSee('Local')`, since "Local" text appears in the source filter dropdown option, making tests non-deterministic
- Livewire component name `pages.library.index` failed — correct format is `pages::library.index` (double-colon namespace); discovered from existing test patterns

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 16 (missing file detection) can proceed — library UI is now source-aware
- All 12 new tests pass; 1 pre-existing LibraryViewTest failure (album track ordering) is a known issue per STATE.md, not caused by this plan

---
*Phase: 15-folder-linking-ui*
*Completed: 2026-03-18*
