---
phase: 07-css-foundation-app-shell
plan: 03
subsystem: ui
tags: [mary-ui, daisyui, livewire, blade, layout, sidebar, navigation]

# Dependency graph
requires:
  - phase: 07-01
    provides: Clean app.css with Mary UI @source directive and daisyUI configured
provides:
  - Mary UI layout shell (x-mary-main with drawer sidebar) replacing Flux sidebar/main
  - Mobile bottom nav bar with route-based active states
  - Plain Blade app-logo component (no Flux dependency)
  - Mary UI dropdown user menu (x-mary-dropdown)
  - AppShellTest with 7 tests verifying layout migration
affects:
  - 08-settings-profile (will render inside this layout)
  - 09-library (will render inside this layout)
  - 10-auth (will render inside this layout)
  - 11-flux-removal (header.blade.php still uses Flux; sidebar fully migrated)

# Tech tracking
tech-stack:
  added:
    - "config/mary.php published with prefix: 'mary-' for x-mary-* component resolution"
  patterns:
    - "x-mary-main with x-slot:sidebar drawer='main-drawer' for collapsible sidebar layout"
    - "x-mary-nav for mobile sticky top bar with hamburger toggle"
    - "x-mary-menu activate-by-route for automatic active link detection"
    - "x-mary-dropdown with x-slot:trigger for user menu"
    - "data-test attributes on bottom nav and logout button for test targeting"

key-files:
  created:
    - tests/Feature/AppShellTest.php
    - config/mary.php
  modified:
    - resources/views/layouts/app/sidebar.blade.php
    - resources/views/layouts/app.blade.php
    - resources/views/components/app-logo.blade.php
    - resources/views/components/desktop-user-menu.blade.php

key-decisions:
  - "Published config/mary.php and set prefix to 'mary-' — components x-mary-nav, x-mary-main, x-mary-dropdown only resolved with explicit prefix (not in MaryServiceProvider hardcoded list)"
  - "User menu placed at top of sidebar (below logo) per user decision from CONTEXT.md"
  - "No 'Platform' group heading in nav — items listed directly per user decision"
  - "@fluxScripts kept in sidebar.blade.php until Phase 11 per user decision"
  - "@persist('audio-player') preserved exactly as-is for cross-navigation audio continuity"
  - "Test 5 (icon rendering) checks for SVG presence + nav labels rather than heroicon-o-* names (icons render as inline SVGs without name attribute)"

patterns-established:
  - "Layout shell tests: actingAs(user) + get(route) + assertSee/assertDontSee for component presence"
  - "Bottom nav uses request()->routeIs() with text-primary / text-base-content/60 for active state"
  - "Mary UI component prefix must match config/mary.php prefix setting"

requirements-completed: [LAYOUT-01, LAYOUT-02, LAYOUT-03, LAYOUT-04]

# Metrics
duration: 25min
completed: 2026-03-13
---

# Phase 7 Plan 03: App Shell Migration Summary

**Flux sidebar/main/header replaced with x-mary-main drawer layout, mobile bottom nav added, plain Blade app-logo and x-mary-dropdown user menu — all 212 tests pass**

## Performance

- **Duration:** 25 min
- **Started:** 2026-03-13T16:58:09Z
- **Completed:** 2026-03-15T00:00:00Z
- **Tasks:** 2 of 2 (Task 2 checkpoint approved by user)
- **Files modified:** 6

## Accomplishments
- Migrated `sidebar.blade.php` from `flux:sidebar`/`flux:main` to `x-mary-main` with drawer sidebar and mobile top nav
- Added fixed mobile bottom nav bar (`data-test="bottom-nav"`) with route-based active highlighting
- Rewrote `app-logo.blade.php` as plain Blade anchor (no Flux dependency)
- Rewrote `desktop-user-menu.blade.php` using `x-mary-dropdown` with profile, settings, and logout
- Stripped `app.blade.php` of all Flux wrappers (`flux:main`, `flux:header`, mobile dropdown)
- Published `config/mary.php` with `prefix: 'mary-'` enabling `x-mary-nav`, `x-mary-main`, `x-mary-dropdown` resolution
- Created `AppShellTest.php` with 7 tests; full suite 212 passed, 4 skipped

## Task Commits

Each task was committed atomically:

1. **Task 1: Create app shell tests and migrate layout files** - `3ac9eb7` (feat)

2. **Task 2: Visual verification of complete layout and player migration** - Checkpoint approved by user (2026-03-15)

## Files Created/Modified
- `tests/Feature/AppShellTest.php` - 7 Pest tests verifying layout shell migration
- `config/mary.php` - Published Mary UI config with prefix set to 'mary-'
- `resources/views/layouts/app/sidebar.blade.php` - Fully rewritten with Mary UI shell
- `resources/views/layouts/app.blade.php` - Stripped to simple sidebar layout wrapper
- `resources/views/components/app-logo.blade.php` - Plain Blade anchor, no Flux dependency
- `resources/views/components/desktop-user-menu.blade.php` - Mary UI dropdown user menu

## Decisions Made
- Published `config/mary.php` and set `prefix: 'mary-'` — `x-mary-nav`, `x-mary-main`, `x-mary-dropdown` are NOT in MaryServiceProvider's hardcoded `mary-*` aliases; they only resolve via the configurable prefix
- Test 5 checks for `<svg` presence and nav labels rather than raw `heroicon-o-*` strings because Mary icons render as inline SVGs without the name in the HTML output

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Published config/mary.php and set mary- prefix**
- **Found during:** Task 1 (GREEN phase — tests returned 500)
- **Issue:** `x-mary-nav`, `x-mary-main`, `x-mary-dropdown` could not be resolved; MaryServiceProvider only hardcodes a subset of components with `mary-` prefix; the configurable `$prefix` defaulted to empty string `''`
- **Fix:** Published vendor config via `php artisan vendor:publish --provider="Mary\MaryServiceProvider"`, set `prefix => 'mary-'` in `config/mary.php`, cleared view and config caches
- **Files modified:** `config/mary.php` (new)
- **Verification:** All 7 AppShellTest tests pass; 212 suite tests pass
- **Committed in:** `3ac9eb7` (Task 1 commit)

**2. [Rule 1 - Bug] Corrected icon test assertion**
- **Found during:** Task 1 (test 5 failing after prefix fix)
- **Issue:** Test checked `assertSee('o-home')` expecting heroicon name in HTML; Mary `x-mary-icon` renders as inline SVG paths without the icon name attribute in output
- **Fix:** Updated assertion to check for `<svg` presence and menu labels (Dashboard, Library, Playlists) which confirm icon-bearing elements render correctly
- **Files modified:** `tests/Feature/AppShellTest.php`
- **Verification:** Test 5 passes; icon SVGs confirmed present in rendered HTML
- **Committed in:** `3ac9eb7` (Task 1 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 bug)
**Impact on plan:** Both fixes necessary for the layout to function. No scope creep.

## Issues Encountered
- Mary UI component prefix configuration was undocumented in the plan — the RESEARCH noted the provider "hardcodes mary-* aliases unconditionally" but only a subset of components have hardcoded aliases; layout-critical components (nav, main, dropdown) require the configurable prefix.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Layout shell fully migrated; authenticated pages render in Mary UI shell
- Visual confirmation received (Task 2 checkpoint approved 2026-03-15)
- `resources/views/layouts/app/header.blade.php` still uses Flux — this is an alternate layout not in active use; deferred to Phase 11
- Phase 07 complete; ready to proceed to Phase 08 (Library UI) or next phase

## Self-Check: PASSED

- FOUND: tests/Feature/AppShellTest.php
- FOUND: config/mary.php
- FOUND: resources/views/layouts/app/sidebar.blade.php
- FOUND: .planning/phases/07-css-foundation-app-shell/07-03-SUMMARY.md
- FOUND commit: 3ac9eb7 feat(07-03): migrate app shell from Flux to Mary UI layout

---
*Phase: 07-css-foundation-app-shell*
*Completed: 2026-03-13*
