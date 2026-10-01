---
phase: 06-profile-ux-polish
plan: 01
subsystem: ui
tags: [laravel, livewire, blade, eloquent, hasMany, withCount, profile]

# Dependency graph
requires:
  - phase: 02-user-profiles
    provides: User model with avatar_path/bio, profile.show and profile.edit routes
  - phase: 03-music-library
    provides: Track and Album models with user_id foreign key
  - phase: 05-playlists
    provides: Playlist model with user_id foreign key
provides:
  - User.tracks(), User.albums(), User.playlists() HasMany relationship methods
  - Profile nav link in desktop user dropdown menu (above Settings)
  - Profile nav link in mobile user dropdown menu (above Settings)
  - Profile show page with 3 stat cards: Tracks, Albums, Playlists (live counts from DB)
affects: [any feature that renders user stats, profile-related UX enhancements]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - withCount() on Livewire mount() for aggregate stats without N+1 queries
    - HasMany relationships declared with explicit return type on User model

key-files:
  created: []
  modified:
    - app/Models/User.php
    - resources/views/components/desktop-user-menu.blade.php
    - resources/views/layouts/app/sidebar.blade.php
    - resources/views/pages/profile/⚡show.blade.php
    - tests/Feature/Profile/PublicProfileTest.php

key-decisions:
  - "withCount(['tracks', 'albums', 'playlists']) called in mount() via findOrFail — single query, no N+1"
  - "Profile nav link placed above Settings in both desktop and mobile dropdowns for consistent ordering"

patterns-established:
  - "User model uses HasMany with explicit return type HasMany — consistent with Laravel relationship conventions"
  - "Profile stat counts use withCount on Livewire mount() — avoids extra queries, exposes *_count attributes"

requirements-completed: [PROF-04]

# Metrics
duration: 9min
completed: 2026-03-12
---

# Phase 6 Plan 01: Profile UX Polish - Nav Links and Stat Counts Summary

**HasMany relationships on User model plus Profile nav link in both dropdown menus and 3 live-count stat cards (Tracks/Albums/Playlists) on the public profile page**

## Performance

- **Duration:** 9 min
- **Started:** 2026-03-12T07:56:52Z
- **Completed:** 2026-03-12T08:05:52Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- Added tracks(), albums(), playlists() HasMany relationships to User model with explicit return types
- Profile nav link now appears above Settings in both desktop and mobile user dropdown menus
- Profile show page replaced the single hardcoded "0" Tracks card with a 3-card grid (Tracks/Albums/Playlists) using withCount for live database counts
- Added 6 new tests covering nav link presence in both menus and accurate stat counts for each content type

## Task Commits

Each task was committed atomically:

1. **Task 1: Add User relationships, profile nav links, and 3-column stat grid** - `8547c4f` (feat)
2. **Task 2: Add tests for profile nav links and accurate stat counts** - `2a57819` (test)

## Files Created/Modified
- `app/Models/User.php` - Added tracks(), albums(), playlists() HasMany relationships with HasMany return type import
- `resources/views/components/desktop-user-menu.blade.php` - Added Profile menu item above Settings with user icon
- `resources/views/layouts/app/sidebar.blade.php` - Added Profile menu item above Settings in mobile dropdown
- `resources/views/pages/profile/⚡show.blade.php` - Updated mount() with withCount, replaced 1-card grid with 3-card sm:grid-cols-3 grid
- `tests/Feature/Profile/PublicProfileTest.php` - Added 6 new tests; renamed zero-count test to cover all 3 labels

## Decisions Made
- withCount() called in mount() via `User::withCount(['tracks', 'albums', 'playlists'])->findOrFail($user->id)` — single DB query approach that exposes tracks_count, albums_count, playlists_count attributes without N+1
- Profile link placed above Settings in both dropdowns following natural navigation hierarchy (profile comes before settings)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- PROF-04 requirement fully satisfied
- Profile page now shows accurate stats and is discoverable via nav
- Ready for additional Phase 6 polish plans if any remain

---
*Phase: 06-profile-ux-polish*
*Completed: 2026-03-12*
