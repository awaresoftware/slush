---
phase: 16
slug: missing-file-detection-polish
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-19
---

# Phase 16 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest v4 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=MissingFile` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=<task-relevant filter>`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 16-01-01 | 01 | 1 | LIBR-05 | Feature | `php artisan test --compact --filter=ScanLinkedFolder` | ✅ (extend) | ⬜ pending |
| 16-01-02 | 01 | 1 | LIBR-05 | Feature | `php artisan test --compact --filter=ScanLinkedFolder` | ✅ (extend) | ⬜ pending |
| 16-01-03 | 01 | 1 | LIBR-05 | Feature/Livewire | `php artisan test --compact --filter=LibraryUnavailableTrack` | ❌ W0 | ⬜ pending |
| 16-01-04 | 01 | 1 | LIBR-05 | Feature/Livewire | `php artisan test --compact --filter=LibrarySourceFilter` | ✅ (extend) | ⬜ pending |
| 16-01-05 | 01 | 1 | LIBR-05 | Feature/Livewire | `php artisan test --compact --filter=LibraryUnavailableTrack` | ❌ W0 | ⬜ pending |
| 16-01-06 | 01 | 1 | LIBR-05 | Feature | `php artisan test --compact --filter=AudioPlayer` | ❌ W0 | ⬜ pending |
| 16-02-01 | 02 | 1 | LIBR-06 | Feature/Livewire | `php artisan test --compact --filter=RelinkTrackModal` | ❌ W0 | ⬜ pending |
| 16-02-02 | 02 | 1 | LIBR-06 | Feature/Livewire | `php artisan test --compact --filter=RelinkTrackModal` | ❌ W0 | ⬜ pending |
| 16-02-03 | 02 | 1 | LIBR-06 | Feature | `php artisan test --compact --filter=LinkedFoldersSettings` | ✅ (extend) | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Library/LibraryUnavailableTrackTest.php` — stubs for LIBR-05 UI (dimming, badge, missing play button)
- [ ] `tests/Feature/Livewire/RelinkTrackModalTest.php` — stubs for LIBR-06 re-link modal
- [ ] `tests/Feature/AudioPlayerQueueTest.php` — stubs for unavailable track exclusion from loadQueue

*Extend existing: `ScanLinkedFolderTest.php`, `LibrarySourceFilterTest.php`, `LinkedFoldersSettingsTest.php`*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Visual dimming at ~50% opacity | LIBR-05 | CSS visual appearance | Inspect track row with `is_available=false`, verify `opacity-50` class |
| Alpine audio error skip + toast | LIBR-05 | Client-side JS behavior | Delete a file, attempt play, verify auto-skip and toast |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
