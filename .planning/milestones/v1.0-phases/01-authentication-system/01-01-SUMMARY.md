---
phase: 01-authentication-system
plan: 01
subsystem: auth
tags: [fortify, middleware, migration, is_admin, registration-gate]

# Dependency graph
requires: []
provides:
  - is_admin boolean column on users table (migration + model cast)
  - First registered user auto-promoted to admin via CreateNewUser action
  - RequireNoUsers middleware blocking registration 404 once users exist
  - Email verification disabled (accounts immediately active)
affects: [02-user-profiles, 03-music-library, 04-audio-player, 05-playlists]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Registration gate via middleware on web group, scoped by route name check
    - First-user admin promotion via User::count() === 0 in Fortify CreateNewUser action

key-files:
  created:
    - database/migrations/2026_03_04_110630_add_is_admin_to_users_table.php
    - app/Http/Middleware/RequireNoUsers.php
    - tests/Feature/Auth/IsAdminTest.php
    - tests/Feature/Auth/RegistrationGateTest.php
  modified:
    - app/Models/User.php
    - app/Actions/Fortify/CreateNewUser.php
    - bootstrap/app.php
    - config/fortify.php
    - database/factories/UserFactory.php
    - tests/Feature/Auth/EmailVerificationTest.php

key-decisions:
  - "RequireNoUsers middleware appended to web group and checks route name (register/register.store) rather than being route-specific, avoiding vendor route modification"
  - "User factory includes is_admin => false explicitly so created models reflect the boolean default without a DB round-trip"
  - "EmailVerificationTest tests skip via markTestSkipped when Features::emailVerification() is disabled"

patterns-established:
  - "Fortify route gating: append middleware to web group, guard by route name inside handle()"
  - "TDD flow: failing test first, migrate/implement, green tests, pint format, commit"

requirements-completed: [AUTH-01, AUTH-04]

# Metrics
duration: 14min
completed: 2026-03-04
---

# Phase 01 Plan 01: Auth Backend Foundation Summary

**Fortify-based single-admin registration system: is_admin column, first-user auto-promotion, and 404 gate blocking all subsequent registrations**

## Performance

- **Duration:** 14 min
- **Started:** 2026-03-04T11:05:05Z
- **Completed:** 2026-03-04T11:19:29Z
- **Tasks:** 2
- **Files modified:** 10

## Accomplishments
- Added `is_admin` boolean column to users table with migration, model fillable, and boolean cast
- First registered user automatically receives `is_admin = true` via `CreateNewUser` action using `User::count() === 0`
- `RequireNoUsers` middleware blocks GET and POST `/register` with 404 whenever any user exists
- Removed `Features::emailVerification()` from Fortify config — accounts are immediately active

## Task Commits

Each task was committed atomically:

1. **Task 1: Add is_admin column and update User model** - `1b7f85e` (feat)
2. **Task 2: Auto-admin first user + registration gate middleware** - `ac2f7fb` (feat)

**Plan metadata:** _(docs commit hash — see below)_

_Note: TDD tasks followed RED → GREEN → format → commit flow_

## Files Created/Modified
- `database/migrations/2026_03_04_110630_add_is_admin_to_users_table.php` - Adds is_admin boolean column, default false
- `app/Models/User.php` - Added is_admin to fillable and boolean cast in casts()
- `app/Actions/Fortify/CreateNewUser.php` - Sets is_admin=true for first user (count === 0)
- `app/Http/Middleware/RequireNoUsers.php` - Aborts 404 on register/register.store routes when users exist
- `bootstrap/app.php` - Registers require.no-users alias and appends RequireNoUsers to web group
- `config/fortify.php` - Removed Features::emailVerification() from features array
- `database/factories/UserFactory.php` - Added is_admin => false to definition()
- `tests/Feature/Auth/IsAdminTest.php` - Tests default false and boolean cast
- `tests/Feature/Auth/RegistrationGateTest.php` - Tests gate 404, admin promotion, and registration flow
- `tests/Feature/Auth/EmailVerificationTest.php` - Added skip guards for disabled email verification

## Decisions Made
- Used `appendToGroup('web', [RequireNoUsers::class])` in `bootstrap/app.php` with route-name guard inside the middleware, rather than trying to attach middleware directly to Fortify's auto-registered routes (which is not supported cleanly without modifying vendor files).
- Added `is_admin => false` to UserFactory so model instances have the correct PHP boolean `false` immediately after `create()` without needing a `fresh()` reload.
- Email verification tests updated to skip with `markTestSkipped` rather than deleted, preserving test coverage intent.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Added is_admin => false to UserFactory definition**
- **Found during:** Task 1 (IsAdminTest RED phase — test for default false returned null)
- **Issue:** UserFactory didn't include `is_admin`, so `User::factory()->create()` returned a model with `is_admin = null` (not cast yet, column value not in attributes array)
- **Fix:** Added `'is_admin' => false` to `UserFactory::definition()` so the value is set from PHP side and cast runs correctly
- **Files modified:** `database/factories/UserFactory.php`
- **Verification:** IsAdminTest passes with 2/2 assertions
- **Committed in:** `1b7f85e` (Task 1 commit)

**2. [Rule 1 - Bug] Fixed booted() route middleware approach — replaced with web group middleware**
- **Found during:** Task 2 (RegistrationGateTest — gate tests returned 200 instead of 404)
- **Issue:** Initial approach used `$this->app->booted()` in FortifyServiceProvider to call `$route->middleware()` on Fortify's auto-registered routes. Route objects don't accept new middleware after registration via this path.
- **Fix:** Changed to appending `RequireNoUsers` to the `web` middleware group in `bootstrap/app.php`, with the middleware checking `in_array($routeName, ['register', 'register.store'])` before aborting
- **Files modified:** `bootstrap/app.php`, `app/Http/Middleware/RequireNoUsers.php`, `app/Providers/FortifyServiceProvider.php`
- **Verification:** RegistrationGateTest passes 6/6 tests
- **Committed in:** `ac2f7fb` (Task 2 commit)

**3. [Rule 1 - Bug] Updated EmailVerificationTest to skip when feature is disabled**
- **Found during:** Task 2 verification (full auth suite — 4 EmailVerificationTest failures due to undefined routes)
- **Issue:** Removing `Features::emailVerification()` from Fortify config removed the `verification.notice` and `verification.verify` routes, causing the existing tests to throw `RouteNotFoundException`
- **Fix:** Added `if (! Features::enabled(Features::emailVerification())) { $this->markTestSkipped(...); }` guards to all 4 tests
- **Files modified:** `tests/Feature/Auth/EmailVerificationTest.php`
- **Verification:** Full auth suite: 22 passed, 4 skipped (correct)
- **Committed in:** `ac2f7fb` (Task 2 commit)

---

**Total deviations:** 3 auto-fixed (1 missing critical, 2 bugs)
**Impact on plan:** All fixes necessary for correct test behavior and middleware wiring. No scope creep.

## Issues Encountered
- Vite manifest missing in test environment caused initial "GET /register returns 200" test failures. Resolved by running `npm run build` to generate the manifest before running those tests.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `is_admin` column exists on users table, cast as boolean, ready for authorization checks
- Registration gate is live — first visit to `/register` creates the admin, subsequent visits return 404
- All auth tests pass (22 passed, 4 skipped appropriately)
- Ready for Phase 01 Plan 02 (login UI and frontend)

## Self-Check: PASSED

All created files confirmed present. Both task commits (1b7f85e, ac2f7fb) verified in git log.

---
*Phase: 01-authentication-system*
*Completed: 2026-03-04*
