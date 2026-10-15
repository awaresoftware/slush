---
phase: 17-native-foundation
plan: "02"
subsystem: infra
tags: [php, fsockopen, port-detection, concurrently, composer-scripts]

requires:
  - phase: 17-native-foundation-plan-01
    provides: Updated composer.json setup script (idempotent key generation, SQLite touch, storage:link)

provides:
  - bin/detect-port.php — PHP script that finds free ports for web (8000+) and Vite (5173+) via fsockopen, prints resolved URL, spawns concurrently
  - composer.json dev script using @php bin/detect-port.php (replaces static inline concurrently command)
  - tests/Unit/DetectPortTest.php — 3 Pest unit tests validating findFreePort logic

affects: [native-foundation, install-scripts, README]

tech-stack:
  added: []
  patterns:
    - "PHP port detection via fsockopen with 0.5s timeout — cross-platform, no OS-specific tools"
    - "Composer dev script delegates to PHP script that owns full launch sequence (detect + print + spawn)"
    - "argv guard (realpath check) allows PHP script to be required by tests without executing main block"
    - "passthru() for cross-platform subprocess spawning (not pcntl_exec which is unavailable on Windows)"

key-files:
  created:
    - bin/detect-port.php
    - tests/Unit/DetectPortTest.php
  modified:
    - composer.json

key-decisions:
  - "detect-port.php owns the full launch sequence — Composer scripts cannot pipe output between entries"
  - "argv guard (realpath($argv[0]) === realpath(__FILE__)) prevents launcher execution when file is required by tests"
  - "0.5s fsockopen timeout prevents slow startup when scanning multiple occupied ports"
  - "passthru() used over pcntl_exec for Windows PHP compatibility"
  - "npm run dev -- --port=N with -- separator forwards port flag through npm to Vite"

patterns-established:
  - "Pattern: PHP bin scripts with argv guard allow both direct execution and test require()"
  - "Pattern: fsockopen falsy check (not errno comparison) for cross-platform port availability detection"

requirements-completed: [NATV-02, NATV-03]

duration: 3min
completed: "2026-03-26"
---

# Phase 17 Plan 02: Port Detection and Dynamic Dev Runner Summary

**Dynamic port detection via fsockopen in bin/detect-port.php, replacing static concurrently command with auto-resolving web (8000+) and Vite (5173+) ports**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-26T20:00:23Z
- **Completed:** 2026-03-26T20:02:59Z
- **Tasks:** 2
- **Files modified:** 3 (bin/detect-port.php created, tests/Unit/DetectPortTest.php created, composer.json updated by Plan 01)

## Accomplishments

- Created bin/detect-port.php with findFreePort() using fsockopen for cross-platform port scanning
- Script prints resolved URL with bold ANSI before launching concurrently with dynamic port flags
- Added argv guard so the launcher block is skipped when the file is required by tests
- Added 3 Pest unit tests validating findFreePort behavior across happy path scenarios
- composer.json dev script already updated to @php bin/detect-port.php (completed by Plan 01 ahead of schedule)

## Task Commits

Each task was committed atomically:

1. **Task 1: Create bin/detect-port.php with findFreePort and unit tests** - `ee1c57e` (feat)
2. **Task 2: Update composer.json dev script** - No separate commit needed (Plan 01 already applied this change in `43ba438`)

## Files Created/Modified

- `/Users/defou/Herd/foonk/bin/detect-port.php` — Port detection script with findFreePort(), URL printer, concurrently spawner
- `/Users/defou/Herd/foonk/tests/Unit/DetectPortTest.php` — 3 Pest unit tests for findFreePort
- `/Users/defou/Herd/foonk/composer.json` — dev script updated (by Plan 01 commit 43ba438)

## Decisions Made

- Used `realpath($argv[0] ?? '') === realpath(__FILE__)` guard — allows test files to require detect-port.php without executing the launcher
- 0.5s fsockopen timeout chosen to prevent slow startup (connection refusal is near-instant on localhost, timeout only applies to SYN-ACK scenarios)
- Fallback port beyond maxAttempts range includes a secondary fsockopen check — if fallback is also occupied, exits with an error message to STDERR

## Deviations from Plan

### Noted Differences from Expected State

**1. [Informational] Task 2 was pre-completed by Plan 01**
- **Found during:** Task 2 (Update composer.json dev script)
- **Issue:** Plan 01 (commit 43ba438 `feat(17-01): update composer.json setup script and remove Makefile`) already replaced the inline concurrently command with `@php bin/detect-port.php` in the dev script
- **Resolution:** No action needed — acceptance criteria already met. Task 2 verified as complete, no duplicate commit created.
- **Verification:** `grep -A5 '"dev"' composer.json` confirmed `@php bin/detect-port.php` in place; `grep -c "npx concurrently" composer.json` returned 0

---

**Total deviations:** 1 informational (Task 2 pre-completed by Plan 01)
**Impact on plan:** All acceptance criteria met. No scope changes or rework needed.

## Issues Encountered

- Pest test runner displays fsockopen "connection refused" messages as warnings (3 warnings in output) even though `@` error suppression is used. The assertions all pass (6/6) — this is test runner behavior surfacing PHP E_WARNING notices in the test output, not a test failure. Tests pass correctly.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- bin/detect-port.php is ready for use via `composer run dev`
- Port conflict detection covers both web (8000) and Vite (5173) servers
- Queue worker (queue:listen --tries=1) is included in the concurrently launch
- Phase 17 is complete — both plans (setup idempotency + port detection) are delivered

---
*Phase: 17-native-foundation*
*Completed: 2026-03-26*

## Self-Check: PASSED

- FOUND: bin/detect-port.php
- FOUND: tests/Unit/DetectPortTest.php
- FOUND: 17-02-SUMMARY.md
- FOUND commit: ee1c57e (Task 1)
