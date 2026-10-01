---
phase: 15
slug: folder-linking-ui
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-18
---

# Phase 15 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 + PHPUnit 12 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=LinkedFolders` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=LinkedFolders`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 15-01-01 | 01 | 1 | FOLD-01 | Feature | `php artisan test --compact --filter=LinkedFoldersSettingsTest` | ❌ W0 | ⬜ pending |
| 15-01-02 | 01 | 1 | FOLD-01 | Feature | `php artisan test --compact --filter=LinkedFoldersSettingsTest` | ❌ W0 | ⬜ pending |
| 15-01-03 | 01 | 1 | FOLD-03 | Feature | `php artisan test --compact --filter=LinkedFoldersSettingsTest` | ❌ W0 | ⬜ pending |
| 15-01-04 | 01 | 1 | FOLD-05 | Feature | `php artisan test --compact --filter=LinkedFoldersSettingsTest` | ❌ W0 | ⬜ pending |
| 15-01-05 | 01 | 1 | FOLD-06 | Feature | `php artisan test --compact --filter=LinkedFoldersSettingsTest` | ❌ W0 | ⬜ pending |
| 15-02-01 | 02 | 1 | LIBR-01 | Feature | `php artisan test --compact --filter=LibraryViewTest` | ✅ | ⬜ pending |
| 15-02-02 | 02 | 1 | LIBR-02 | Feature | `php artisan test --compact --filter=LibraryViewTest` | ✅ | ⬜ pending |
| 15-03-01 | 03 | 1 | LIBR-03 | Feature | `php artisan test --compact --filter=LibrarySearchTest` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Settings/LinkedFoldersSettingsTest.php` — stubs for FOLD-01, FOLD-03, FOLD-05, FOLD-06
- [ ] Extend `tests/Feature/Library/LibraryViewTest.php` — stubs for LIBR-01, LIBR-02
- [ ] Extend `tests/Feature/Library/LibrarySearchTest.php` — stubs for LIBR-03

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Toast notification appears on scan completion | FOLD-05 | Toast rendering requires browser JS | Link a folder, wait for scan, verify toast appears |
| Loading spinner visible during scan | FOLD-05 | CSS animation not testable in feature tests | Link folder, observe spinner in folder row |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
