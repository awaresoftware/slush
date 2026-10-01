---
phase: 9
slug: page-migration
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-16
---

# Phase 9 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest v4 (PHPUnit v12 backend) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=<relevant>` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=<relevant test file>`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 09-00-01 | 00 | 0 | PAGE-01 | feature | `php artisan test --compact tests/Feature/UploadModalTest.php` | ❌ W0 | ⬜ pending |
| 09-01-xx | 01 | 1 | PAGE-01 | feature | `php artisan test --compact tests/Feature/Library/TrackUploadTest.php` | ✅ | ⬜ pending |
| 09-01-xx | 01 | 1 | PAGE-01 | feature | `php artisan test --compact tests/Feature/Library/LibrarySearchTest.php` | ✅ | ⬜ pending |
| 09-01-xx | 01 | 1 | PAGE-02 | feature | `php artisan test --compact tests/Feature/Library/LibraryViewTest.php` | ✅ | ⬜ pending |
| 09-01-xx | 01 | 1 | PAGE-03 | feature | `php artisan test --compact tests/Feature/Library/LibraryViewTest.php` | ✅ | ⬜ pending |
| 09-02-xx | 02 | 1 | PAGE-04 | feature | `php artisan test --compact tests/Feature/PlaylistControllerTest.php` | ✅ | ⬜ pending |
| 09-03-xx | 03 | 1 | PAGE-05 | feature | `php artisan test --compact tests/Feature/Profile/PublicProfileTest.php` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/UploadModalTest.php` — stubs for UploadModal component: opens via event, shows form, shows progress state, shows success state after save, duplicate warnings inline, toast dispatched. Pattern: mirrors `AddToPlaylistModalTest.php` structure.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Upload progress bar visual | PAGE-01 | CSS/visual rendering | Upload a file, verify progress bar animates |
| Toast notification display | PAGE-04 | JS event dispatching | Create playlist, verify toast appears |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
