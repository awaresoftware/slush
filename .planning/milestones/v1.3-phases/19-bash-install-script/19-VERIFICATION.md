---
phase: 19-bash-install-script
verified: 2026-03-27T00:00:00Z
status: passed
score: 8/8 must-haves verified
re_verification: false
---

# Phase 19: Bash Install Script Verification Report

**Phase Goal:** macOS and Linux users can run a single script that detects missing dependencies and guides them through a complete install
**Verified:** 2026-03-27
**Status:** PASSED
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| #  | Truth                                                                                    | Status     | Evidence                                                                       |
|----|------------------------------------------------------------------------------------------|------------|--------------------------------------------------------------------------------|
| 1  | install.sh exists at project root and is executable                                      | VERIFIED   | File at `/install.sh`, permissions `-rwxr-xr-x`; test confirms `is_executable` |
| 2  | install.sh detects missing PHP, Composer, Node, and npm and auto-installs them           | VERIFIED   | `command -v php/composer/node/npm` checks at lines 162, 187, 201, 215         |
| 3  | install.sh checks PHP version is at least 8.2 and fails on older versions                | VERIFIED   | `PHP_MAJOR_VERSION >= 8 && PHP_MINOR_VERSION >= 2` gate at line 174; exits 1 on fail |
| 4  | install.sh prompts user to install Homebrew on macOS if missing (does not auto-install)  | VERIFIED   | `check_homebrew()` prints explanation + install URL, exits 1 without auto-installing (lines 96-107) |
| 5  | install.sh auto-installs dependencies via apt, dnf, or pacman on Linux                   | VERIFIED   | `install_php_linux`, `install_composer_linux`, `install_node_linux` switch on `$PKG_MGR` (lines 122-154) |
| 6  | install.sh runs `composer run setup` after all dependencies are satisfied                 | VERIFIED   | `composer run setup` called unconditionally at line 248                        |
| 7  | install.sh asks to start dev server after setup completes                                 | VERIFIED   | `read -rp "Start dev server? [Y/n]"` at line 252; runs `exec composer run dev` on confirmation |
| 8  | Shell scripts use LF line endings on all platforms via .gitattributes                     | VERIFIED   | `.gitattributes` line 2: `*.sh text eol=lf` (explicit rule in addition to wildcard) |

**Score:** 8/8 truths verified

### Required Artifacts

| Artifact                               | Expected                                          | Status     | Details                                                                  |
|----------------------------------------|---------------------------------------------------|------------|--------------------------------------------------------------------------|
| `install.sh`                           | Bash script with dependency detection             | VERIFIED   | 258 lines; shebang `#!/usr/bin/env bash`; `set -euo pipefail`; executable |
| `tests/Feature/BashInstallScriptTest.php` | File content assertion tests (17+ tests)        | VERIFIED   | 19 tests in 3 `describe` blocks; all 19 pass (0.66s runtime)            |
| `.gitattributes`                       | LF enforcement for shell scripts                  | VERIFIED   | Contains `*.sh text eol=lf` on line 2; preserves all existing rules     |

### Key Link Verification

| From           | To                 | Via                                  | Status   | Details                                                             |
|----------------|--------------------|--------------------------------------|----------|---------------------------------------------------------------------|
| `install.sh`   | `composer run setup` | Direct subprocess call at line 248  | WIRED    | Unconditional call after all dependency checks pass                 |
| `install.sh`   | `composer run dev`   | Conditional `exec` at line 256      | WIRED    | `exec composer run dev` inside `[[ $answer =~ ^[Yy]$ ]]` block     |
| `.gitattributes` | `install.sh`      | `*.sh text eol=lf` pattern at line 2 | WIRED    | Explicit rule present; verified by INST-04 test and direct file read |

### Requirements Coverage

| Requirement | Source Plan | Description                                                                   | Status    | Evidence                                                                                      |
|-------------|-------------|-------------------------------------------------------------------------------|-----------|-----------------------------------------------------------------------------------------------|
| INST-01     | 19-01-PLAN  | User can run `install.sh` on macOS/Linux to detect missing deps and get guided prompts | SATISFIED | install.sh detects PHP/Composer/Node/npm via `command -v`; auto-installs or exits with guidance |
| INST-03     | 19-01-PLAN  | Install script offers to install Homebrew on macOS if missing, with explanation | SATISFIED | `check_homebrew()` prints explanation including "package manager", install URL, and re-run hint |
| INST-04     | 19-01-PLAN  | `.gitattributes` enforces LF line endings for shell scripts                   | SATISFIED | `*.sh text eol=lf` present at line 2 of `.gitattributes`                                     |

No orphaned requirements: REQUIREMENTS.md traceability table maps only INST-01, INST-03, INST-04 to Phase 19, and all three are claimed and verified.

### Anti-Patterns Found

No anti-patterns detected.

- No TODO/FIXME/HACK/PLACEHOLDER comments in `install.sh` or `BashInstallScriptTest.php`
- No empty return stubs in `install.sh`
- `composer run setup` is a live unconditional call, not a placeholder
- `composer run dev` is a live conditional call, not a placeholder

### Human Verification Required

The following items require a human tester running on actual hardware — they cannot be verified by static file inspection:

#### 1. macOS end-to-end with missing Homebrew

**Test:** On a clean macOS machine with no Homebrew, run `./install.sh`
**Expected:** Script prints Homebrew explanation, install URL, exits with code 1 and does not attempt to install Homebrew automatically
**Why human:** Requires a macOS environment without Homebrew

#### 2. macOS end-to-end with Homebrew present and missing PHP/Composer/Node

**Test:** On macOS with Homebrew but without PHP/Composer/Node, run `./install.sh`
**Expected:** Each missing tool is installed via `brew install`, `composer run setup` runs to completion, and the dev server prompt appears
**Why human:** Requires a macOS environment with Homebrew but without the target binaries

#### 3. Linux end-to-end with apt

**Test:** On Ubuntu/Debian with missing PHP/Composer/Node, run `./install.sh`
**Expected:** NodeSource LTS script runs, packages installed via `apt-get`, setup completes
**Why human:** Requires a Debian/Ubuntu environment

#### 4. PHP version gate rejection

**Test:** On a machine with PHP 7.x or PHP 8.0/8.1 installed, run `./install.sh`
**Expected:** Script prints the installed PHP version, prints "Minimum required: PHP 8.2", and exits with code 1
**Why human:** Requires a machine with a deliberately outdated PHP version

#### 5. LF line endings on Windows checkout

**Test:** Clone the repository on Windows, inspect `install.sh` line endings in a hex editor or `file` command
**Expected:** File contains LF (`\n`) only, not CRLF (`\r\n`), confirming `.gitattributes` is applied
**Why human:** Requires a Windows environment with git configured to apply `.gitattributes`

### Gaps Summary

No gaps. All 8 must-have truths are verified, all 3 artifacts are substantive and wired, all 3 key links are confirmed present in the actual file content, and all 3 requirements are satisfied with implementation evidence. The test suite reports 19/19 passing assertions with no regressions.

The five items in Human Verification Required are operational validations that require live OS environments and cannot be verified through static analysis. They do not block phase completion — the code is structurally complete and correct.

---

_Verified: 2026-03-27_
_Verifier: Claude (gsd-verifier)_
