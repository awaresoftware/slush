---
phase: 08
slug: shared-components-modal-pattern
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-15
---

# Phase 08 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (PHP) |
| **Config file** | phpunit.xml |
| **Quick run command** | `php artisan test --compact --filter={test}` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter={test}`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 08-01-01 | 01 | 1 | LAYOUT-05 | feature | `php artisan test --compact --filter=AuthLayout` | ❌ W0 | ⬜ pending |
| 08-02-01 | 02 | 1 | MODAL-01 | feature | `php artisan test --compact --filter=AddToPlaylistModal` | ❌ W0 | ⬜ pending |
| 08-02-02 | 02 | 1 | MODAL-02 | feature | `php artisan test --compact --filter=AddToPlaylistModal` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/AuthLayoutTest.php` — stubs for LAYOUT-05
- [ ] `tests/Feature/AddToPlaylistModalTest.php` — stubs for MODAL-01, MODAL-02

*Existing Pest infrastructure covers framework needs.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Auth pages visually match spotify-dark theme | LAYOUT-05 | Visual design check | Load /login, /register, /forgot-password — verify daisyUI card styling, consistent theme |
| Modal opens/closes smoothly | MODAL-01 | UX behavior | Click add-to-playlist on any track, verify modal opens, close it, verify clean state |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
