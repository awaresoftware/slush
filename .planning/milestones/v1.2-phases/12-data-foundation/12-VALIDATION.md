---
phase: 12
slug: data-foundation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-18
---

# Phase 12 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest v4 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=DataFoundation` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=<relevant test>`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 10 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 12-01-01 | 01 | 1 | DATA-01 | feature | `php artisan test --compact --filter=TrackSourceTypeTest` | ❌ W0 | ⬜ pending |
| 12-01-02 | 01 | 1 | DATA-01 | feature | `php artisan test --compact --filter=TrackSourceTypeTest` | ❌ W0 | ⬜ pending |
| 12-01-03 | 01 | 1 | DATA-02 | feature | `php artisan test --compact --filter=TrackPathNormalisationTest` | ❌ W0 | ⬜ pending |
| 12-01-04 | 01 | 1 | DATA-03 | feature | `php artisan test --compact --filter=ExtractAudioMetadataTest` | ❌ W0 | ⬜ pending |
| 12-01-05 | 01 | 1 | — | feature | `php artisan test --compact --filter=LinkedFolderTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/TrackSourceTypeTest.php` — stubs for DATA-01 (enum, scopes, audioUrl uploaded branch)
- [ ] `tests/Feature/TrackPathNormalisationTest.php` — stubs for DATA-02 (mutator)
- [ ] `tests/Feature/ExtractAudioMetadataTest.php` — stubs for DATA-03 (job path branching)
- [ ] `tests/Feature/LinkedFolderTest.php` — LinkedFolder factory and model

*Existing infrastructure covers test framework setup.*

---

## Manual-Only Verifications

*All phase behaviors have automated verification.*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 10s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
