---
phase: 20-powershell-install-script
verified: 2026-03-27T08:00:00Z
status: passed
score: 6/6 must-haves verified
re_verification: false
---

# Phase 20: PowerShell Install Script Verification Report

**Phase Goal:** Windows users can run a single script that detects missing dependencies and guides them through a complete install without PowerShell execution policy errors
**Verified:** 2026-03-27T08:00:00Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| #  | Truth                                                                                             | Status     | Evidence                                                                                   |
|----|---------------------------------------------------------------------------------------------------|------------|--------------------------------------------------------------------------------------------|
| 1  | Running install.bat invokes install.ps1 with -ExecutionPolicy Bypass without requiring manual policy change | VERIFIED | `install.bat` lines 10, 17: both the UAC re-launch and the direct invocation use `-ExecutionPolicy Bypass -File "%~dp0install.ps1"` |
| 2  | install.ps1 detects missing PHP, Composer, Node.js, and npm and prints specific guidance for each | VERIFIED   | `Check-Php`, `Check-Composer`, `Check-Node`, `Check-Npm` functions all call `Test-CommandExists` and branch on absence; failure paths call `Write-Fail` with specific messages |
| 3  | install.ps1 detects Laravel Herd PHP installation even if not on PATH                            | VERIFIED   | `Find-HerdPhp` probes `$env:USERPROFILE\.config\herd\bin\php84/83/82\php.exe` before any PATH check (lines 47–61) |
| 4  | install.ps1 auto-installs missing dependencies via Chocolatey                                    | VERIFIED   | 4 `choco install` calls: `php`, `composer`, `nodejs` (line 129, 153, 163); `Ensure-Chocolatey` bootstraps Chocolatey itself if absent |
| 5  | install.bat auto-elevates to admin via UAC if not already elevated                               | VERIFIED   | `net session >nul 2>&1` admin check at line 5; `-Verb RunAs` re-launch at line 11 when not elevated; direct execution via `:ELEVATED` label when already admin |
| 6  | .gitattributes enforces CRLF line endings for *.ps1 and *.bat files                              | VERIFIED   | Lines 3–4 of `.gitattributes`: `*.ps1 text eol=crlf` and `*.bat text eol=crlf` confirmed; `grep -c 'eol=crlf' .gitattributes` returns 2 |

**Score:** 6/6 truths verified

### Required Artifacts

| Artifact                                      | Expected                                            | Status     | Details                                                                                   |
|-----------------------------------------------|-----------------------------------------------------|------------|-------------------------------------------------------------------------------------------|
| `install.ps1`                                 | PowerShell install script with dependency detection and auto-install | VERIFIED | 201 lines; contains `$ErrorActionPreference`, `Set-StrictMode -Version 2`, all dependency check functions, Herd probing, Chocolatey install, `composer run setup`, `composer run dev` |
| `install.bat`                                 | Entry point wrapper with UAC elevation and ExecutionPolicy Bypass | VERIFIED | 19 lines; contains `-ExecutionPolicy Bypass`, `net session`, `-Verb RunAs`, `-File install.ps1` |
| `tests/Feature/PowerShellInstallScriptTest.php` | File content assertion tests for INST-02         | VERIFIED   | 19 Pest tests across 3 describe blocks (ps1 content, bat wrapper, line endings); all 19 pass |
| `.gitattributes`                              | CRLF enforcement for Windows scripts                | VERIFIED   | Contains `*.ps1 text eol=crlf` and `*.bat text eol=crlf`                                 |

### Key Link Verification

| From          | To                | Via                                               | Status     | Details                                                                     |
|---------------|-------------------|---------------------------------------------------|------------|-----------------------------------------------------------------------------|
| `install.bat` | `install.ps1`     | `powershell -NoProfile -ExecutionPolicy Bypass -File install.ps1` | VERIFIED | Lines 11 and 17 both reference `-ExecutionPolicy Bypass -File "%~dp0install.ps1"` |
| `install.ps1` | `composer run setup` | `& composer run setup` invocation              | VERIFIED   | Line 193: `& composer run setup`                                            |
| `install.ps1` | Chocolatey        | `choco install` for missing deps                  | VERIFIED   | Lines 129, 153, 163: `choco install php/composer/nodejs -y --no-progress`  |

### Requirements Coverage

| Requirement | Source Plan  | Description                                                                                   | Status    | Evidence                                                                                      |
|-------------|--------------|-----------------------------------------------------------------------------------------------|-----------|-----------------------------------------------------------------------------------------------|
| INST-02     | 20-01-PLAN.md | User can run `install.ps1` via `install.bat` wrapper on Windows to detect missing dependencies and get guided installation prompts | SATISFIED | All 19 PowerShellInstallScriptTest assertions pass; install.bat + install.ps1 fully implement the Windows install path |

### Anti-Patterns Found

None detected. Scanned `install.ps1`, `install.bat`, and `tests/Feature/PowerShellInstallScriptTest.php` for TODO/FIXME/XXX/HACK/PLACEHOLDER patterns — no matches.

### Human Verification Required

#### 1. Live Windows Execution

**Test:** On a Windows machine without PHP/Composer/Node installed, double-click `install.bat`
**Expected:** UAC prompt appears, PowerShell installs Chocolatey (if absent), then installs PHP/Composer/Node via `choco install`, then runs `composer run setup` successfully
**Why human:** Cannot simulate UAC elevation, real Chocolatey install, or PATH refresh behavior on a non-Windows host

#### 2. Herd PHP Detection

**Test:** On a Windows machine with Laravel Herd installed (but PHP not on PATH), run `install.bat`
**Expected:** Script detects `php.exe` under `%USERPROFILE%\.config\herd\bin\php84\`, adds parent directory to user PATH, and proceeds without Chocolatey installation
**Why human:** Herd installation and PATH state cannot be simulated programmatically in this environment

#### 3. PHP Version Gate

**Test:** On Windows with PHP 8.1 installed, run `install.bat`
**Expected:** Script prints `[FAIL] PHP 8.1 is below the minimum required version.` followed by `Minimum required: PHP 8.2` in red and exits with code 1
**Why human:** Requires a real PHP 8.1 Windows environment

### Gaps Summary

No gaps. All six must-have truths are verified, all four artifacts are substantive and wired, all three key links are confirmed present, and INST-02 is fully satisfied. The 19-test Pest suite passes cleanly with no regressions in BashInstallScriptTest (19/19 still passing).

The only items flagged are live-Windows behavioral tests that cannot be verified programmatically — these are expected for a Windows-only install script and do not block phase completion.

---

_Verified: 2026-03-27T08:00:00Z_
_Verifier: Claude (gsd-verifier)_
