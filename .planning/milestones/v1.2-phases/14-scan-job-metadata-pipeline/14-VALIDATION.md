---
phase: 14
slug: scan-job-metadata-pipeline
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-18
---

# Phase 14 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=ScanLinkedFolder` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=ScanLinkedFolder`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 14-01-01 | 01 | 1 | FOLD-02 | Feature | `php artisan test --compact --filter=ScanLinkedFolderTest` | ❌ W0 | ⬜ pending |
| 14-01-02 | 01 | 1 | FOLD-02 | Feature | `php artisan test --compact --filter=ScanLinkedFolderTest` | ❌ W0 | ⬜ pending |
| 14-01-03 | 01 | 1 | FOLD-04 | Feature | `php artisan test --compact --filter=ScanLinkedFolderTest` | ❌ W0 | ⬜ pending |
| 14-02-01 | 02 | 1 | LIBR-04 | Feature | `php artisan test --compact --filter=ExtractAudioMetadataTest` | ✅ partial | ⬜ pending |
| 14-02-02 | 02 | 1 | LIBR-04 | Feature | `php artisan test --compact --filter=ExtractAudioMetadataTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/ScanLinkedFolderTest.php` — stubs for FOLD-02, FOLD-04 (scan discovery, deduplication)
- [ ] `tests/Feature/ExtractAudioMetadataTest.php` — extend for LIBR-04 (cover art, album artist, folder fallback)

*Existing infrastructure covers test framework requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| UI not blocked during scan | FOLD-02 | Queue behavior in dev requires manual observation | Dispatch scan, verify page remains responsive |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
