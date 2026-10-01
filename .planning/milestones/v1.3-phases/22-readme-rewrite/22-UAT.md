---
status: complete
phase: 22-readme-rewrite
source: 22-01-SUMMARY.md
started: 2026-03-29T00:00:00Z
updated: 2026-03-29T00:05:00Z
---

## Current Test

[testing complete]

## Tests

### 1. README Exists with Project Overview
expected: Open README.md at the repository root. It should show the project name "Rekodo", badge icons (Laravel, Livewire, PHP, License), and a brief description of what the app does.
result: pass

### 2. Per-OS Installation Sections
expected: README.md contains four distinct installation sections: macOS, Linux, Windows, and Docker. Each native section leads with a script command (install.sh or install.bat/install.ps1) and includes a collapsible manual fallback inside a `<details>` block. Each section ends with a health check URL.
result: pass

### 3. Docker Container Path Caveat
expected: The Docker installation section contains a prominent `> [!IMPORTANT]` admonition warning about container vs host paths. It should include a comparison table showing host and container path differences.
result: pass

### 4. Health Check URLs Correct Per Platform
expected: Native installation sections (macOS, Linux, Windows) reference `http://localhost:8000/healthz` for verification. Docker section references `http://localhost/healthz` (port 80, no port number shown).
result: pass

### 5. CONTRIBUTING.md with Development Setup
expected: CONTRIBUTING.md exists at the repository root. It covers: development setup with `composer run setup` and `composer run dev`, tech stack overview, test commands (`php artisan test --compact`), code style (`vendor/bin/pint --dirty`), and PR guidelines.
result: pass

### 6. MIT LICENSE File
expected: LICENSE file exists at the repository root containing a standard MIT License with "2026 Rekodo Contributors" as the copyright holder.
result: pass

### 7. Cross-File Links
expected: README.md links to CONTRIBUTING.md in its Contributing section and references the LICENSE file in its License section. These are relative links that would work on GitHub.
result: pass

## Summary

total: 7
passed: 7
issues: 0
pending: 0
skipped: 0
blocked: 0

## Gaps

[none yet]
