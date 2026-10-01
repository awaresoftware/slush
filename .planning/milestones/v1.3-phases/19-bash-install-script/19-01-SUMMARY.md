---
phase: 19-bash-install-script
plan: 01
subsystem: infra
tags: [bash, install-script, homebrew, apt, dnf, pacman, gitattributes, pest]

# Dependency graph
requires:
  - phase: 17-native-foundation
    provides: composer run setup and composer run dev commands that install.sh delegates to
provides:
  - install.sh at project root — executable bash script with dependency detection and auto-install for macOS (Homebrew) and Linux (apt/dnf/pacman)
  - tests/Feature/BashInstallScriptTest.php — 19 file content assertion tests covering INST-01, INST-03, INST-04
  - .gitattributes updated with explicit *.sh text eol=lf rule
affects: [20-powershell-install-script, README-rewrite]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "PHP constants for version comparison (PHP_MAJOR_VERSION/PHP_MINOR_VERSION) instead of sort -V which is unavailable on macOS BSD sort"
    - "maybe_sudo helper to handle root vs non-root environments in bash scripts"
    - "command -v instead of which for POSIX-portable binary presence checks"
    - "bashTestProjectRoot() pattern following DockerConfigurationTest.php convention for worktree-safe file assertions"

key-files:
  created:
    - install.sh
    - tests/Feature/BashInstallScriptTest.php
  modified:
    - .gitattributes

key-decisions:
  - "Used PHP_MAJOR_VERSION/PHP_MINOR_VERSION constants via php -r for version check instead of sort -V — macOS ships BSD sort which does not support -V flag"
  - "Homebrew prompts user with package manager explanation and official install URL but does not auto-install (D-02 locked decision)"
  - "maybe_sudo helper wraps all package manager calls to handle both root and non-root environments (pitfall 6)"
  - "hash -r called after every binary install to clear bash command cache (pitfall 5)"
  - "*.sh text eol=lf added explicitly to .gitattributes despite wildcard * text=auto eol=lf — community best practice for executable scripts"

patterns-established:
  - "File content assertion test pattern: bashTestProjectRoot() with dirname(__DIR__, 2) following DockerConfigurationTest.php"
  - "Bash install script structure: shebang → set -euo pipefail → TTY/color → helpers → OS detection → dependency checks → setup delegation"

requirements-completed: [INST-01, INST-03, INST-04]

# Metrics
duration: 4min
completed: 2026-03-27
---

# Phase 19 Plan 01: Bash Install Script Summary

**Bash install.sh for macOS (Homebrew) and Linux (apt/dnf/pacman) with PHP 8.2+ enforcement, auto-install of missing deps, and delegation to composer run setup**

## Performance

- **Duration:** 4 min
- **Started:** 2026-03-27T00:46:07Z
- **Completed:** 2026-03-27T00:50:24Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments
- 19-test BashInstallScriptTest covering INST-01 (script structure/deps), INST-03 (Homebrew), and INST-04 (.gitattributes) using proven DockerConfigurationTest file-assertion pattern
- install.sh with full dependency detection and auto-install for PHP/Composer/Node/npm on macOS via Homebrew and Linux via apt/dnf/pacman
- PHP 8.2 minimum version gate using PHP constants (avoids macOS BSD sort -V limitation)
- .gitattributes explicit *.sh LF rule satisfying INST-04

## Task Commits

Each task was committed atomically:

1. **Task 1: Create BashInstallScriptTest with file content assertions** - `45919af` (test)
2. **Task 2: Create install.sh with dependency detection, auto-install, and setup delegation** - `7a24fc1` (feat)
3. **Task 3: Add explicit *.sh LF line ending rule to .gitattributes** - `b8f4829` (chore)

## Files Created/Modified
- `install.sh` - Bash install script: shebang, set -euo pipefail, TTY/color detection, maybe_sudo helper, OS detection, Homebrew check with Apple Silicon support, PHP/Composer/Node/npm auto-install, PHP 8.2 minimum enforcement, composer run setup delegation, optional dev server start
- `tests/Feature/BashInstallScriptTest.php` - 19 file content assertions: 14 INST-01 (script structure), 4 INST-03 (Homebrew), 1 INST-04 (.gitattributes rule)
- `.gitattributes` - Added `*.sh text eol=lf` rule for explicit LF enforcement on shell scripts

## Decisions Made
- Used `php -r 'exit(PHP_MAJOR_VERSION > 8 || (PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION >= 2) ? 0 : 1);'` for PHP version check instead of `sort -V` — macOS BSD sort does not support the -V flag, making the pure-PHP approach the only cross-platform solution that works before any package manager has run
- `maybe_sudo` helper added to handle root environments (CI, Docker containers without sudo installed) — pitfall 6 from RESEARCH.md

## Deviations from Plan

None - plan executed exactly as written. The PHP version comparison method using PHP constants (instead of sort -V) was pre-documented in the plan as the recommended approach due to the Open Question about macOS BSD sort.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- install.sh ready for manual testing on macOS and Linux
- All 19 BashInstallScriptTest tests pass
- Full test suite shows no regressions (5 failures are pre-existing carry-over from v1.2)
- Phase 20 (PowerShell install script) can proceed independently

## Self-Check: PASSED

- FOUND: install.sh (project root, executable -rwxr-xr-x)
- FOUND: tests/Feature/BashInstallScriptTest.php
- FOUND: 19-01-SUMMARY.md
- FOUND commit: 45919af (test: BashInstallScriptTest RED phase)
- FOUND commit: 7a24fc1 (feat: install.sh GREEN phase)
- FOUND commit: b8f4829 (chore: .gitattributes *.sh rule)

---
*Phase: 19-bash-install-script*
*Completed: 2026-03-27*
