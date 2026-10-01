---
phase: 03-music-library
plan: 04
subsystem: ui
tags: [laravel, livewire, alpinejs, fluxui, pest, library, search, tabs]

# Dependency graph
requires:
  - phase: 03-music-library
    plan: 02
    provides: Library index SFC shell, upload pipeline, Track and Album models
  - phase: 03-music-library
    plan: 03
    provides: Album show page, upload summary page, duration format pattern
provides:
  - "Fully functional /library page: Tracks tab table + Albums tab card grid"
  - "Real-time search (debounced 300ms) filtering tracks by title/artist/album, albums by title/artist"
  - "Empty state with Upload Music CTA when library has no content"
  - "tracks() and albums() #[Computed] properties with cross-user isolation and eager loading"
affects: [04-audio-player, 05-playlists]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Alpine.js x-data tabs with x-show for tab switching (flux:tab.group is Pro-only in Flux free)"
    - "@entangle('activeTab') to sync Alpine tab state with Livewire $activeTab property"
    - "wire:model.live.debounce.300ms for real-time search with 300ms debounce"
    - "#[Computed] properties with ->when($this->search) for conditional query filtering"
    - "orWhereHas for cross-relationship search (tracks searching by album.title)"
    - "Livewire test ->get('albums') to access computed property collection directly (avoiding modal dropdown false-positives in assertDontSee)"

key-files:
  created:
    - tests/Feature/Library/LibrarySearchTest.php
  modified:
    - resources/views/pages/library/index.blade.php

key-decisions:
  - "flux:tab.group is Pro-only — used Alpine.js x-data tabs with x-show and @entangle instead"
  - "Album filter tests use ->get('albums') on Livewire component to verify computed property directly, not assertDontSee — the upload modal album picker (userAlbums) always renders ALL user albums causing false assertDontSee failures"
  - "search results view renders when strlen($search) > 0, showing separate Songs/Albums sections; tabs view renders when search is empty"

patterns-established:
  - "Computed property filtering: ->when($this->search, fn($q) => ...) on Eloquent query builder"
  - "Alpine.js tab pattern for free Flux edition: x-data + x-show + @entangle"
  - "Cross-relationship search: orWhereHas('album', fn($q) => $q->where('title', 'like', ...))"

requirements-completed: [MUSC-03, MUSC-04]

# Metrics
duration: 12min
completed: 2026-03-04
---

# Phase 03 Plan 04: Library Index Tabs, Search, and Empty State Summary

**Tabbed library view with Alpine.js tabs, real-time debounced search splitting Songs/Albums results, empty state CTA — 12 new LibrarySearch tests, 111 total tests green**

## Performance

- **Duration:** 12 min
- **Started:** 2026-03-04T18:48:28Z
- **Completed:** 2026-03-04T19:00:30Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- tracks() and albums() #[Computed] properties with ->when(search) filtering, cross-user isolation, and with('album') eager loading
- Full library UI: tabbed view (Tracks table + Albums card grid) via Alpine.js x-data/x-show/@entangle replacing flux:tab.group (Pro-only)
- Real-time search with wire:model.live.debounce.300ms showing Songs/Albums split sections when active
- Empty state with flux:icon.musical-note + "Your library is empty" heading + Upload Music modal trigger
- 12 LibrarySearch tests (cross-user isolation, title/artist/album filtering, case-insensitivity, order)

## Task Commits

Each task was committed atomically:

1. **Task 1 RED: Failing LibrarySearch tests** - `73a126a` (test)
2. **Task 1 GREEN + Task 2: Computed properties and full library UI** - `e43cd2d` (feat)

_Note: Task 1 and Task 2 share a GREEN commit because the UI is required for the computed property tests to assert rendered HTML_

## Files Created/Modified

- `resources/views/pages/library/index.blade.php` - Added tracks()/albums() computed properties; replaced placeholder with tabbed UI, search bar, search results view, empty state, album card grid
- `tests/Feature/Library/LibrarySearchTest.php` - 12 tests covering computed property filtering, ordering, cross-user isolation, case-insensitivity

## Decisions Made

- **flux:tab.group is Pro-only:** Same issue as 03-03 with flux:table. Used Alpine.js x-data with x-show and @entangle instead. The @entangle directive syncs the Alpine activeTab variable with the Livewire $activeTab property so server-side state is preserved.
- **Album filter tests use ->get('albums'):** The upload modal's album picker (userAlbums computed property) renders ALL user albums regardless of search state. Using assertDontSee('Goodbye Blues') would always fail because that album appears in the modal's select dropdown. Solution: access the computed property directly via $component->get('albums') and assert on the ID collection.
- **Search view vs tabs view:** When $search is non-empty, render a flat Songs + Albums section (search results). When $search is empty, render the tabbed interface. This matches the plan spec for search results splitting into sections.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] flux:tab.group is Pro-only in Flux free edition**
- **Found during:** Task 2 (tabs UI implementation)
- **Issue:** Plan specified `<flux:tab.group>` with `<flux:tabs>`, `<flux:tab>`, and `<flux:tab.panel>` — these components throw `InvalidArgumentException: Unable to locate a class or view for component [flux::tab.group]` in the free edition
- **Fix:** Replaced with Alpine.js `x-data="{ activeTab: @entangle('activeTab') }"` + `x-show` approach. Tab buttons use `x-on:click` and `:class` binding for active state styling. Identical UX — no functionality lost.
- **Files modified:** `resources/views/pages/library/index.blade.php`
- **Verification:** All 60 Library tests pass; 111 total tests pass
- **Committed in:** `e43cd2d`

**2. [Rule 1 - Bug] assertDontSee false positives for albums due to upload modal dropdown**
- **Found during:** Task 1 GREEN (running LibrarySearch tests)
- **Issue:** Test `albums computed property filters by title when search is set` used `assertDontSee('Goodbye Blues')` — the album appeared in the upload modal's album picker (userAlbums), causing the assertion to fail even though the albums() computed property correctly excluded it
- **Fix:** Updated two album filter tests to use `$component->get('albums')` to access the computed property collection directly and assert on ID membership, bypassing the modal dropdown context
- **Files modified:** `tests/Feature/Library/LibrarySearchTest.php`
- **Verification:** All 12 LibrarySearch tests pass
- **Committed in:** `e43cd2d`

---

**Total deviations:** 2 auto-fixed (2 bugs — Pro-only component, test false-positive)
**Impact on plan:** Both fixes necessary for correctness. No scope creep. Identical functionality delivered.

## Issues Encountered

- `flux::tab.group` 500 error on first test run confirmed another Pro-only component. Fixed by switching to Alpine.js tabs — same pattern as the 03-03 flux:table substitution.
- Album `assertDontSee` tests failed because the upload modal's `userAlbums` dropdown renders all albums. Fixed by testing computed property values directly.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Library index is fully functional: search, tabs, empty state, album cards, track table
- MUSC-03 (library view) and MUSC-04 (search) are complete
- Phase 03 is fully complete — all 4 plans delivered
- Ready for Phase 04 (audio player)
- No blockers

## Self-Check: PASSED

All key files verified:
- `resources/views/pages/library/index.blade.php` - FOUND (modified)
- `tests/Feature/Library/LibrarySearchTest.php` - FOUND (created)

All task commits verified:
- `73a126a` test(03-04): add failing LibrarySearch tests - FOUND
- `e43cd2d` feat(03-04): implement computed properties and full library UI - FOUND

---
*Phase: 03-music-library*
*Completed: 2026-03-04*
