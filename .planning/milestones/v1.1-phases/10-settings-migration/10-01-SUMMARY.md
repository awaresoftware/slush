---
phase: 10-settings-migration
plan: 01
subsystem: ui
tags: [mary-ui, flux-migration, livewire, blade, settings]

# Dependency graph
requires:
  - phase: 10-00
    provides: research, test stubs, and phase scaffolding for settings migration

provides:
  - Settings sub-layout using x-mary-menu with activate-by-route navigation
  - Settings heading partial using x-mary-header with separator
  - Profile settings form with x-mary-input, x-mary-textarea, x-mary-button
  - Password settings form with x-mary-input, x-mary-button
  - Delete account using wire:model boolean + x-mary-modal pattern

affects: [10-02, 10-03, 10-04, 10-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "x-mary-menu activate-by-route for settings sidebar navigation (no wire:navigate needed - auto-added by MenuItem.php)"
    - "x-mary-modal wire:model boolean pattern for delete confirmation modals"
    - "closeModal() method resets password + showDeleteModal + resetErrorBag()"

key-files:
  created:
    - tests/Feature/Settings/SettingsNavigationTest.php
  modified:
    - resources/views/pages/settings/layout.blade.php
    - resources/views/partials/settings-heading.blade.php
    - resources/views/pages/settings/⚡profile.blade.php
    - resources/views/pages/settings/⚡password.blade.php
    - resources/views/pages/settings/⚡delete-user-form.blade.php

key-decisions:
  - "SettingsNavigationTest checks <flux: tag absence in rendered HTML (not flux. which appears in Flux JS bundle URLs from @fluxScripts still in sidebar layout)"
  - "Delete account uses showDeleteModal bool property + closeModal() method — same Phase 8 pattern as add-to-playlist-modal"
  - "Layout.blade.php uses x-mary-header for per-page headings (consistent with settings-heading partial approach)"

patterns-established:
  - "Pattern: x-mary-menu activate-by-route — no icons, no wire:navigate attributes needed"
  - "Pattern: closeModal() method = reset('password', 'showDeleteModal') + resetErrorBag()"

requirements-completed: [SETTINGS-01, SETTINGS-02, SETTINGS-03, SETTINGS-07]

# Metrics
duration: 45min
completed: 2026-03-17
---

# Phase 10 Plan 01: Settings Layout, Profile, Password, Delete Account Migration Summary

**Settings layout, heading partial, profile form, password form, and delete account modal migrated from Flux UI to Mary UI with zero flux: tags remaining in all five files**

## Performance

- **Duration:** ~45 min
- **Started:** 2026-03-17T00:00:00Z
- **Completed:** 2026-03-17
- **Tasks:** 2
- **Files modified:** 5 (+ 1 test created)

## Accomplishments
- Settings sub-layout migrated: flux:navlist replaced with x-mary-menu activate-by-route, flex layout and 220px sidebar preserved
- Settings heading partial migrated: three Flux components (heading, subheading, separator) replaced with single x-mary-header separator
- Profile form fully migrated: all flux:input, flux:textarea, flux:button, flux:text, flux:link, flux:label replaced with Mary UI / plain HTML equivalents
- Password form fully migrated: all flux:input and flux:button replaced
- Delete account modal fully migrated from Flux named modal pattern to wire:model boolean + x-mary-modal pattern; added showDeleteModal property and closeModal() method to PHP class
- SettingsNavigationTest created covering page load, menu item visibility, no-flux verification, and admin nav item

## Task Commits

Each task was committed atomically:

1. **Task 1: Migrate settings layout, heading partial, and navigation** - `432e1c2` (feat)
2. **Task 2: Migrate profile, password, and delete account pages** - `6dc7f1b` (feat)
3. **Fix: SettingsNavigationTest flux check** - `b867483` (fix)

**Plan metadata:** (to be created below)

## Files Created/Modified
- `resources/views/pages/settings/layout.blade.php` - Replaced flux:navlist with x-mary-menu activate-by-route; flux:heading/subheading with x-mary-header; flux:separator with hr
- `resources/views/partials/settings-heading.blade.php` - Replaced flux:heading + flux:subheading + flux:separator with x-mary-header title subtitle separator
- `resources/views/pages/settings/⚡profile.blade.php` - All Flux components replaced; sr-only heading, form inputs, textarea, buttons, links, labels all migrated
- `resources/views/pages/settings/⚡password.blade.php` - All flux:input and flux:button replaced with x-mary-input and x-mary-button
- `resources/views/pages/settings/⚡delete-user-form.blade.php` - Added showDeleteModal bool + closeModal() to PHP class; replaced Flux named modal pattern with wire:model boolean pattern
- `tests/Feature/Settings/SettingsNavigationTest.php` - Created 5 tests covering layout load, menu items, flux-free verification, admin nav

## Decisions Made
- `SettingsNavigationTest` checks `<flux:` in rendered HTML rather than `flux.` — the `@fluxScripts` directive in the sidebar layout still renders Flux JS bundle URLs containing `flux.` and this is out of scope for Plan 01
- `layout.blade.php` uses `x-mary-header` for per-page section headings (title/subtitle slots) — consistent with the heading partial approach and provides structured heading display

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- Linter auto-formatted the SettingsNavigationTest to include `expect($html)->not->toContain('flux.')` which falsely failed because `@fluxScripts` still emits Flux JS bundle URLs. Fixed by changing to `<flux:` which tests for Flux component tags specifically.
- AuthPagesTest in the Settings suite has 2 pre-existing failures (`verification.notice` route not defined) from Phase 10-00 stubs — these are out of scope for Plan 01 and were not introduced by these changes.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Settings layout shell is now fully migrated and ready for Plans 02-05 to depend on
- The wire:model boolean modal pattern is established for Plan 05 (2FA setup modal)
- Profile, password, and delete account pages are production-ready with Mary UI

---
*Phase: 10-settings-migration*
*Completed: 2026-03-17*
