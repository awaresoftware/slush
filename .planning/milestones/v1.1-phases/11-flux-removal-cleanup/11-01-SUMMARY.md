---
phase: 11-flux-removal-cleanup
plan: 01
subsystem: ui
tags: [flux, livewire, mary-ui, blade, css, composer]

# Dependency graph
requires:
  - phase: 10-settings-migration
    provides: All Flux component tags replaced by Mary UI equivalents across settings pages
  - phase: 07-css-foundation-app-shell
    provides: App shell rewritten with Mary UI; flux directives retained as deferred cleanup
provides:
  - Flux-free blade templates (zero flux: tags, @fluxScripts, @fluxAppearance)
  - livewire/flux package uninstalled from composer
  - resources/views/flux/ directory removed
  - Dead header.blade.php layout removed
affects:
  - 11-02 (any remaining cleanup tasks in phase 11)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "auth-header uses x-mary-header with title/subtitle props; external API preserves title/description naming"
    - "Theme appearance handled by data-theme attribute on html element; @fluxAppearance directive not needed"
    - "Livewire handles its own script injection; @fluxScripts directive not needed"

key-files:
  created: []
  modified:
    - resources/views/components/auth-header.blade.php
    - resources/views/layouts/app/sidebar.blade.php
    - resources/views/partials/head.blade.php
    - resources/css/app.css
    - composer.json
    - composer.lock

key-decisions:
  - "auth-header.blade.php maps external description prop to x-mary-header subtitle prop internally"
  - "livewire/flux v2.12.1 removed; vendor/livewire/flux directory deleted by composer"
  - "5 published Flux view overrides in resources/views/flux/ deleted; all were dead files"
  - "Dead layouts/app/header.blade.php deleted; replaced by sidebar.blade.php in Phase 7"

patterns-established:
  - "Mary UI: x-mary-header handles auth page headings with :title and :subtitle props"

requirements-completed: [CLEAN-01, CLEAN-02, CLEAN-03]

# Metrics
duration: 15min
completed: 2026-03-18
---

# Phase 11 Plan 01: Flux Removal and Cleanup Summary

**livewire/flux package fully removed — zero flux: tags, @fluxScripts, @fluxAppearance, or flux.css imports remain; app boots and builds cleanly**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-18T00:00:00Z
- **Completed:** 2026-03-18T00:15:00Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments

- Rewrote auth-header.blade.php to use x-mary-header (maps description prop to subtitle)
- Removed @fluxScripts from sidebar.blade.php and @fluxAppearance from head.blade.php
- Deleted dead header.blade.php layout and resources/views/flux/ directory (5 files)
- Removed flux.css @import from app.css; npm build succeeds
- Ran composer remove livewire/flux — v2.12.1 uninstalled, removed from composer.json and vendor/

## Task Commits

Each task was committed atomically:

1. **Task 1: Remove Flux references from blade templates and delete dead files** - `0b38a7d` (feat)
2. **Task 2: Remove Flux CSS import and uninstall composer package** - `f1eaefa` (feat)

## Files Created/Modified

- `resources/views/components/auth-header.blade.php` - Rewritten to use x-mary-header instead of flux:heading/flux:subheading
- `resources/views/layouts/app/sidebar.blade.php` - @fluxScripts removed (line 76)
- `resources/views/partials/head.blade.php` - @fluxAppearance removed (line 14)
- `resources/views/layouts/app/header.blade.php` - Deleted (dead file, replaced by sidebar in Phase 7)
- `resources/views/flux/` - Deleted (icon/ and navlist/ subdirectories, 5 published Flux view overrides)
- `resources/css/app.css` - flux.css @import line removed
- `composer.json` - livewire/flux removed from require
- `composer.lock` - livewire/flux v2.12.1 lock entry removed

## Decisions Made

- auth-header.blade.php keeps external API with `description` prop but maps it to `subtitle` internally for x-mary-header compatibility. No callers need updating.
- @fluxAppearance was the Flux appearance init script; replaced by data-theme attribute on html element (already in place since Phase 7).
- @fluxScripts was Flux's JavaScript injection; Livewire handles its own scripts natively — no replacement needed.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Flux is completely eliminated from the codebase
- Phase 11 cleanup is complete — the codebase is purely Mary UI / DaisyUI
- All 29 v1.1 requirements addressed across phases 7-11

---
*Phase: 11-flux-removal-cleanup*
*Completed: 2026-03-18*
