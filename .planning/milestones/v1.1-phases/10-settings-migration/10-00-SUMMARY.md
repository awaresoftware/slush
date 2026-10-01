---
phase: 10-settings-migration
plan: "00"
subsystem: testing
tags: [pest, livewire, settings, flux-migration]

# Dependency graph
requires: []
provides:
  - "RED test stubs for settings nav, appearance, users, and auth page migrations"
  - "Behavioral coverage for SETTINGS-01, SETTINGS-04, SETTINGS-06, SETTINGS-08"
affects: [10-01, 10-02, 10-03]

# Tech tracking
tech-stack:
  added: []
  patterns: ["actingAs + get() for page render tests", "Livewire::test with assertHasNoErrors + assertSet for component state tests"]

key-files:
  created:
    - tests/Feature/Settings/SettingsNavigationTest.php
    - tests/Feature/Settings/AppearanceSettingsTest.php
    - tests/Feature/Settings/UsersSettingsTest.php
    - tests/Feature/Settings/AuthPagesTest.php
  modified: []

key-decisions:
  - "Tests written as RED stubs — they will fail until Plans 01-03 complete migrations, which is expected Wave 0 behavior"
  - "AppearanceSettingsTest uses Livewire::test with isDarkMode property and theme column assertions to verify DB persistence"
  - "AuthPagesTest uses assertRedirect for two-factor.login (not assertOk) since mid-auth challenge requires active 2FA session"

patterns-established:
  - "Settings test pattern: actingAs(User::factory()->create()) + get(route(name)) + assertSee/getContent for rendered HTML"
  - "Admin-gated test pattern: User::factory()->create(['is_admin' => true]) for admin-only pages"

requirements-completed: [SETTINGS-01, SETTINGS-04, SETTINGS-06, SETTINGS-08]

# Metrics
duration: 15min
completed: 2026-03-17
---

# Phase 10 Plan 00: Settings Migration Test Stubs Summary

**Four Pest RED test stubs covering settings nav rendering, appearance theme persistence, users admin page, and auth page flux-free verification**

## Performance

- **Duration:** 15 min
- **Started:** 2026-03-17T00:00:00Z
- **Completed:** 2026-03-17
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Created SettingsNavigationTest.php with 5 tests covering nav rendering, menu items, no flux tags, and admin nav item visibility
- Created AppearanceSettingsTest.php with 5 tests covering theme toggle DB persistence (dark/light), mount state, and no flux references
- Created UsersSettingsTest.php with 4 tests covering admin page load, flux absence, user list display, and invite form
- Created AuthPagesTest.php with 5 tests covering confirm-password, verify-email, and two-factor-challenge page loads without flux

## Task Commits

Each task was committed atomically:

1. **Task 1: Create test stubs for settings navigation and appearance** - (test)
2. **Task 2: Create test stubs for users settings and auth pages** - (test)

## Files Created/Modified
- `tests/Feature/Settings/SettingsNavigationTest.php` - Nav rendering, menu items, no flux tags, admin nav item (SETTINGS-01)
- `tests/Feature/Settings/AppearanceSettingsTest.php` - Theme toggle DB persistence, mount state, no flux (SETTINGS-04)
- `tests/Feature/Settings/UsersSettingsTest.php` - Admin page load, user list, invite form (SETTINGS-06)
- `tests/Feature/Settings/AuthPagesTest.php` - Confirm password, verify email, two-factor challenge (SETTINGS-08)

## Decisions Made
- Tests written as RED stubs that will fail until Plans 01-03 complete the Flux-to-Mary migrations — this is the intended Wave 0 behavior
- AppearanceSettingsTest uses `Livewire::test` with `isDarkMode` property wire binding and `users.theme` column assertions to verify DB persistence rather than just checking rendered HTML
- AuthPagesTest uses `assertRedirect()` for `two-factor.login` since the challenge page only loads mid-auth — not `assertOk()`

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- All four test stub files are in place and ready to turn GREEN as Plans 01, 02, and 03 execute
- Plan 01 targets auth pages migration (AuthPagesTest will pass)
- Plan 02 targets appearance settings migration (AppearanceSettingsTest will pass)
- Plan 03 targets users settings and nav migration (SettingsNavigationTest and UsersSettingsTest will pass)

---
*Phase: 10-settings-migration*
*Completed: 2026-03-17*
