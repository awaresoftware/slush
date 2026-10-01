---
phase: 13
slug: authenticated-streaming
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-18
---

# Phase 13 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 / PHPUnit 12 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=StreamController` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=StreamController`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 10 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 13-01-01 | 01 | 1 | STRM-01 | feature | `php artisan test --compact --filter=StreamControllerTest` | ❌ W0 | ⬜ pending |
| 13-01-02 | 01 | 1 | STRM-02 | feature | `php artisan test --compact --filter=StreamControllerTest` | ❌ W0 | ⬜ pending |
| 13-01-03 | 01 | 1 | STRM-03 | feature | `php artisan test --compact --filter=StreamControllerTest` | ❌ W0 | ⬜ pending |
| 13-01-04 | 01 | 1 | STRM-03 | feature | `php artisan test --compact --filter=StreamControllerTest` | ❌ W0 | ⬜ pending |
| 13-02-01 | 02 | 1 | N/A | feature | `php artisan test --compact --filter=TrackSourceTypeTest` | ✅ (update) | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/StreamControllerTest.php` — stubs for STRM-01, STRM-02, STRM-03
- [ ] `app/Policies/TrackPolicy.php` — prerequisite for streaming tests
- [ ] `app/Http/Controllers/StreamController.php` — prerequisite for route tests

*Existing `tests/Feature/TrackSourceTypeTest.php` needs update — assert stream route URL instead of Storage URL.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| HTML5 audio seeking in Chrome/Edge/Safari | STRM-02 | Browser-specific Range behavior | Play a track, seek to middle, verify playback resumes |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 10s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
