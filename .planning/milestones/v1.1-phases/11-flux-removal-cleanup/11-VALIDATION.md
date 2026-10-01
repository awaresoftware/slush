---
phase: 11
slug: flux-removal-cleanup
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-18
---

# Phase 11 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4.4 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=CssFoundation` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=CssFoundation`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 11-01-01 | 01 | 1 | CLEAN-01 | unit | `php artisan test --compact --filter=CssFoundation` | ✅ (needs inversion) | ⬜ pending |
| 11-01-02 | 01 | 1 | CLEAN-02 | manual | Shell: `ls resources/views/flux/` returns error | N/A — file deletion | ⬜ pending |
| 11-01-03 | 01 | 1 | CLEAN-03 | integration | `php artisan test --compact --filter=AppShell` | ✅ existing | ⬜ pending |
| 11-01-04 | 01 | 1 | CLEAN-04 | smoke | `php artisan test --compact` | ✅ existing suite | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/CssFoundationTest.php:62` — Invert `toContain` → `not->toContain` for flux.css assertion

*Existing infrastructure covers all other phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| `resources/views/flux/` directory deleted | CLEAN-02 | File system deletion — shell check sufficient | Run `ls resources/views/flux/` — should return error |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
