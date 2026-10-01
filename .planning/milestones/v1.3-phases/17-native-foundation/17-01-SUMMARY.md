---
phase: 17-native-foundation
plan: 01
subsystem: infra
tags: [composer, setup, sqlite, storage-link, idempotency, makefile]

# Dependency graph
requires: []
provides:
  - Idempotent composer run setup script covering all 8 install steps
  - Conditional APP_KEY generation (skips if already set)
  - SQLite file creation before migration
  - storage:link --force for idempotent symlink creation
  - NativeFoundationTest validating idempotency behaviors
  - Makefile removed (composer run setup is canonical install command)
affects: [17-02-PLAN, install-scripts, docker-setup, readme-rewrite]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Inline PHP in composer scripts for idempotent file/key operations"
    - "preg_match APP_KEY=base64: guard prevents re-generating an existing key"

key-files:
  created:
    - tests/Feature/NativeFoundationTest.php
  modified:
    - composer.json

key-decisions:
  - "Conditional key:generate via inline PHP preg_match guard — only runs when APP_KEY is absent or empty"
  - "SQLite touch added before migrate — prevents migration failure on fresh clone"
  - "storage:link --force makes symlink creation idempotent — safe on re-runs"
  - "Makefile deleted per D-07 — composer run setup is the single canonical install command"

patterns-established:
  - "Idempotent setup: every step in composer run setup is safe to run multiple times"
  - "Inline PHP -r guards for file/env checks in composer scripts"

requirements-completed: [NATV-01]

# Metrics
duration: 2min
completed: 2026-03-26
---

# Phase 17 Plan 01: Native Foundation — Idempotent Setup Summary

**Idempotent `composer run setup` with 8-step sequence: SQLite creation, conditional APP_KEY guard, npm build, and storage:link — Makefile removed**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-03-26T20:00:20Z
- **Completed:** 2026-03-26T20:02:13Z
- **Tasks:** 2
- **Files modified:** 3 (composer.json modified, Makefile deleted, NativeFoundationTest.php created)

## Accomplishments

- Updated composer.json setup script from 6 steps to 8 with full idempotency
- Added conditional APP_KEY generation — skips if `APP_KEY=base64:` already present in .env
- Added SQLite file touch before migration — prevents migration failure on fresh clone
- Added `storage:link --force` at the end — idempotent public storage symlink creation
- Deleted Makefile — `composer run setup` is now the single canonical install command
- Created NativeFoundationTest with 7 passing tests covering all idempotency behaviors

## Task Commits

Each task was committed atomically:

1. **Task 1: Create NativeFoundationTest with idempotency behavior tests** - `1c177c4` (test)
2. **Task 2: Update composer.json setup script and remove Makefile** - `43ba438` (feat)

_Note: Task 1 used TDD — tests were written and confirmed passing before the implementation step._

## Files Created/Modified

- `tests/Feature/NativeFoundationTest.php` — Pest tests for APP_KEY regex logic (3 scenarios), SQLite touch idempotency (2 scenarios), storage:link --force idempotency (2 scenarios)
- `composer.json` — Updated scripts.setup with 8 idempotent steps
- `Makefile` — Deleted per D-07

## Decisions Made

- Conditional key generation uses inline PHP preg_match instead of shell conditionals — works cross-platform without bash/PowerShell dependency
- `--no-interaction` flag added to key:generate to prevent any CLI prompts
- SQLite touch placed before migrate (not after) — ensures the file exists before Laravel tries to connect
- `storage:link --force` placed last — symlink is only needed after assets are built

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- `composer run setup` is now fully idempotent and covers the complete install sequence
- Ready for Phase 17 Plan 02 (port detection) which builds on the native foundation
- No blockers identified

---
*Phase: 17-native-foundation*
*Completed: 2026-03-26*
