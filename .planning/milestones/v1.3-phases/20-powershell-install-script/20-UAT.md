---
status: complete
phase: 20-powershell-install-script
source: 20-01-SUMMARY.md
started: 2026-03-29T12:00:00Z
updated: 2026-03-29T12:05:00Z
---

## Current Test

[testing complete]

## Tests

### 1. install.ps1 exists with key PowerShell functions
expected: `install.ps1` exists at project root. Contains: strict mode (`Set-StrictMode`), `Find-HerdPhp` function probing php84/php83/php82, `Install-Chocolatey` function, PHP 8.2 version gate, and `composer run setup` delegation.
result: pass

### 2. install.bat UAC elevation wrapper
expected: `install.bat` exists at project root. Contains `net session` admin check and `-Verb RunAs` UAC auto-elevation that launches `install.ps1` with `-ExecutionPolicy Bypass`.
result: pass

### 3. .gitattributes line-ending rules
expected: `.gitattributes` contains `*.ps1 text eol=crlf`, `*.bat text eol=crlf`, and `*.sh text eol=lf` entries for correct cross-platform line endings.
result: pass

### 4. All PowerShell install script tests pass
expected: Running `php artisan test --compact tests/Feature/PowerShellInstallScriptTest.php` passes all 19 tests with no failures.
result: pass

## Summary

total: 4
passed: 4
issues: 0
pending: 0
skipped: 0
blocked: 0

## Gaps

[none yet]
