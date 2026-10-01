---
phase: 02-user-profiles
plan: 01
subsystem: database
tags: [eloquent, migration, storage, user-model, factory]

# Dependency graph
requires:
  - phase: 01-authentication-system
    provides: User model with is_admin, Fortify auth, UserFactory baseline
provides:
  - avatar_path and bio columns on users table
  - User::avatarUrl() as single source of truth for avatar URL
  - UserFactory null defaults for both new profile fields
affects: [02-02, 02-03, profile-edit, public-profile, audio-player]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Storage::url() used in model method avatarUrl() rather than in views"
    - "Nullable profile columns with no force-casts — null stays null"

key-files:
  created:
    - database/migrations/2026_03_04_121855_add_profile_columns_to_users_table.php
  modified:
    - app/Models/User.php
    - database/factories/UserFactory.php

key-decisions:
  - "avatar_path and bio left without extra casts — nullable string, null stays null"
  - "avatarUrl() on the User model is the single URL source of truth; views must not call Storage::url() directly"
  - "UserFactory includes explicit null defaults to avoid missing-column surprises in tests"

patterns-established:
  - "Profile field access via User model methods, not direct attribute access in views"

requirements-completed: [PROF-01, PROF-02, PROF-03]

# Metrics
duration: 8min
completed: 2026-03-04
---

# Phase 2 Plan 01: User Profiles Database Foundation Summary

**avatar_path and bio columns added to users table via migration, User::avatarUrl() method using Storage::url(), UserFactory null defaults — all 11 Settings tests green**

## Performance

- **Duration:** 8 min
- **Started:** 2026-03-04T12:17:18Z
- **Completed:** 2026-03-04T12:25:00Z
- **Tasks:** 1
- **Files modified:** 3

## Accomplishments
- Migration adds nullable `avatar_path` (string) and `bio` (string 160) columns to users table after `is_admin`
- `User::avatarUrl()` returns `Storage::url($this->avatar_path)` or `null` — single source of truth for avatar URLs across all views
- `UserFactory::definition()` includes `avatar_path => null` and `bio => null` as explicit defaults

## Task Commits

Each task was committed atomically:

1. **Task 1: Migration and User model update** - `722560f` (feat)

**Plan metadata:** TBD (docs: complete plan)

## Files Created/Modified
- `database/migrations/2026_03_04_121855_add_profile_columns_to_users_table.php` - Adds avatar_path and bio columns, drops them in down()
- `app/Models/User.php` - Added Storage import, avatar_path/bio to $fillable, avatarUrl() method
- `database/factories/UserFactory.php` - Added avatar_path => null and bio => null to definition()

## Decisions Made
- `avatar_path` and `bio` receive no extra casts — nullable string, null remains null without forced casting
- `User::avatarUrl()` is the single source of truth for avatar URLs; views in Phase 2 plans 02 and 03 must call this method rather than calling `Storage::url()` directly
- UserFactory explicit null defaults ensure factories produce predictable state without needing a database round-trip

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Users table has the profile columns needed for Phase 2, Plan 02 (profile edit UI)
- `User::avatarUrl()` is ready for use in profile views and navigation components
- All existing Settings tests continue to pass — no regressions

---
*Phase: 02-user-profiles*
*Completed: 2026-03-04*
