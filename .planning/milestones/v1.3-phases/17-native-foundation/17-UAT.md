---
status: complete
phase: 17-native-foundation
source: [17-01-SUMMARY.md, 17-02-SUMMARY.md]
started: 2026-03-27T00:00:00Z
updated: 2026-03-28T00:00:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Idempotent Setup Script
expected: Run `composer run setup` on your already-configured project. All 8 steps complete without errors. Your existing APP_KEY is preserved (not regenerated). No migration failures. Storage link created without issues.
result: issue
reported: "Parse error on APP_KEY guard step — shell expands $e variable before PHP receives the code, causing syntax error: unexpected token '=', expecting end of file"
severity: blocker

### 2. Makefile Removed
expected: No `Makefile` exists in the project root. The canonical install command is `composer run setup`.
result: pass

### 3. Dynamic Dev Server with Port Detection
expected: Run `composer run dev`. The terminal prints a resolved URL (bold, with detected port) before launching servers. Web server starts on port 8000 (or next available if occupied). Vite dev server starts on port 5173 (or next available).
result: pass

### 4. All Phase Tests Pass
expected: Run `php artisan test --compact --filter=NativeFoundation` and `php artisan test --compact --filter=DetectPort`. All tests pass (7 + 3 = 10 total).
result: pass

## Summary

total: 4
passed: 3
issues: 1
pending: 0
skipped: 0
blocked: 0

## Gaps

- truth: "composer run setup completes all 8 steps without errors, preserving existing APP_KEY"
  status: failed
  reason: "User reported: Parse error on APP_KEY guard step — shell expands $e variable before PHP receives the code, causing syntax error: unexpected token '=', expecting end of file"
  severity: blocker
  test: 1
  root_cause: "Shell variable expansion — $e in double-quoted composer script is expanded by shell before PHP receives it"
  artifacts:
    - path: "composer.json"
      issue: "Inline PHP in setup script uses $e which shell expands"
  missing:
    - "Escape $ signs in composer.json inline PHP (use \\$e) so shell passes literal $e to PHP"
  debug_session: ""
