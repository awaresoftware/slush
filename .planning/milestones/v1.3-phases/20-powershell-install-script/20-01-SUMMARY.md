---
phase: 20-powershell-install-script
plan: 01
subsystem: infra
tags: [powershell, windows, chocolatey, herd, bat, gitattributes, install-script]

requires:
  - phase: 19-bash-install-script
    provides: "install.sh structure and BashInstallScriptTest pattern to mirror in PowerShell"

provides:
  - "install.ps1: PowerShell 5.1 installer with Herd PHP detection, Chocolatey auto-install, PATH refresh, PHP 8.2+ gate, composer run setup delegation"
  - "install.bat: UAC auto-elevation wrapper with -ExecutionPolicy Bypass"
  - "PowerShellInstallScriptTest.php: 19 file-content assertion tests for INST-02"
  - ".gitattributes: CRLF enforcement for *.ps1 and *.bat"

affects: [readme, documentation, windows-users]

tech-stack:
  added: []
  patterns:
    - "psTestProjectRoot() helper mirrors bashTestProjectRoot() pattern — __DIR__-relative to avoid base_path() worktree issues"
    - "Find-HerdPhp probes .config\\herd\\bin\\phpXX for php84/83/82 — Herd before Chocolatey fallback"
    - "Refresh-EnvPath: chocolateyProfile.psm1 Update-SessionEnvironment first, registry merge fallback"
    - "install.bat: net session admin check + -Verb RunAs UAC elevation before running install.ps1"

key-files:
  created:
    - install.ps1
    - install.bat
    - tests/Feature/PowerShellInstallScriptTest.php
  modified:
    - .gitattributes

key-decisions:
  - "*.sh text eol=lf added to worktree .gitattributes to carry over phase-19 change (worktree branched before that commit)"
  - "Refresh-EnvPath uses chocolateyProfile.psm1 Update-SessionEnvironment as primary, registry PATH merge as fallback — covers both Chocolatey-managed and non-standard installs"
  - "Find-HerdPhp probes php84/php83/php82 in descending order — favors newest Herd PHP version"

patterns-established:
  - "PowerShell test files use psTestProjectRoot() helper at file top for __DIR__-relative root resolution"
  - "install.bat delegates entirely to install.ps1 — all logic stays in PowerShell, bat is elevation-only"

requirements-completed: [INST-02]

duration: 3min
completed: 2026-03-27
---

# Phase 20 Plan 01: PowerShell Install Script Summary

**Windows install experience with UAC-auto-elevating install.bat, Chocolatey-backed dependency detection, Herd PHP path probing, and 19 Pest assertion tests covering install.ps1/install.bat content and .gitattributes CRLF rules**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-27T07:21:34Z
- **Completed:** 2026-03-27T07:24:56Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- Created `install.ps1` with strict mode, Herd PHP probing at `.config\herd\bin`, Chocolatey auto-install for PHP/Composer/Node, PATH refresh, PHP 8.2 version gate, and `composer run setup`/`composer run dev` delegation
- Created `install.bat` with `net session` admin check and `-Verb RunAs` UAC auto-elevation wrapping install.ps1 with `-ExecutionPolicy Bypass`
- Created `PowerShellInstallScriptTest.php` with 19 test cases (TDD: RED then GREEN) covering ps1 content, bat content, and .gitattributes CRLF rules
- Updated `.gitattributes` with `*.ps1 text eol=crlf` and `*.bat text eol=crlf` (plus `*.sh text eol=lf` carry-over from phase 19)

## Task Commits

Each task was committed atomically:

1. **Task 1: Create PowerShellInstallScriptTest with file content assertions** - `27d6b99` (test)
2. **Task 2: Create install.ps1, install.bat, and update .gitattributes** - `533632f` (feat)

## Files Created/Modified

- `tests/Feature/PowerShellInstallScriptTest.php` - 19 Pest tests: ps1 content, bat content, gitattributes CRLF
- `install.ps1` - PowerShell 5.1 installer: output helpers, Test-CommandExists, Find-HerdPhp, Add-ToUserPath, Refresh-EnvPath, Install-Chocolatey, Check-Php/Composer/Node/Npm, main flow
- `install.bat` - UAC elevation wrapper: net session check, -Verb RunAs re-launch, -ExecutionPolicy Bypass
- `.gitattributes` - Added `*.sh text eol=lf`, `*.ps1 text eol=crlf`, `*.bat text eol=crlf`

## Decisions Made

- Added `*.sh text eol=lf` to this worktree's `.gitattributes` because the worktree branched from main before phase-19's gitattributes commit — ensures BashInstallScriptTest line-endings test will pass when merged
- `Refresh-EnvPath` tries `chocolateyProfile.psm1 Update-SessionEnvironment` first (Chocolatey-managed sessions), then falls back to registry PATH merge — covers both standard and non-standard installs
- `Find-HerdPhp` probes `php84`, `php83`, `php82` in descending order — always picks the newest Herd-managed PHP version

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

- Worktree had no `vendor/` directory — created a symlink to the main project's vendor to allow `php artisan test` to work
- Worktree `.gitattributes` was missing `*.sh text eol=lf` (worktree branched before phase-19 commit) — added it alongside the CRLF rules to prevent regression when merged

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Windows install path (`install.bat` → `install.ps1`) is complete and tested
- Phase 20 (PowerShell install script) is the final deliverable for the Windows install experience
- Ready for README rewrite (per-OS installation guides) in the next phase

---
*Phase: 20-powershell-install-script*
*Completed: 2026-03-27*
