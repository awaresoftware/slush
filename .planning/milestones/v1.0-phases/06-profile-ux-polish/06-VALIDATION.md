---
phase: 6
slug: profile-ux-polish
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-12
---

# Phase 6 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 / PHPUnit 12 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=PublicProfile` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=PublicProfile`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 10 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 06-01-01 | 01 | 1 | PROF-04 | feature | `php artisan test --compact --filter=PublicProfile` | ✅ existing | ⬜ pending |
| 06-01-02 | 01 | 1 | PROF-04 | feature | `php artisan test --compact --filter=PublicProfile` | ❌ new test | ⬜ pending |
| 06-01-03 | 01 | 1 | PROF-04 | feature | `php artisan test --compact --filter=PublicProfile` | ❌ new test | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] Add nav link tests to `tests/Feature/Profile/PublicProfileTest.php` — covers PROF-04-nav
- [ ] Add accurate stat count tests to `tests/Feature/Profile/PublicProfileTest.php` — covers PROF-04-trackcount
- No new test files needed — extend existing `PublicProfileTest.php`
- No framework install needed — Pest already configured

*Existing infrastructure covers framework requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Visual layout of 3-column stat grid | PROF-04 | CSS layout verification | Inspect profile page on desktop (3 cols) and mobile (stacked) |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 10s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
