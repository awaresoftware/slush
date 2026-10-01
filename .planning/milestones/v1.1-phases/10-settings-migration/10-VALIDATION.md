---
phase: 10
slug: settings-migration
status: draft
nyquist_compliant: true
wave_0_complete: false
created: 2026-03-17
---

# Phase 10 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (PHP) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=Settings` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=Settings`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 10-01-01 | 01 | 1 | SETTINGS-01 | feature | `php artisan test --compact --filter=SettingsNavigation` | W0 (10-00) | ⬜ pending |
| 10-01-02 | 01 | 1 | SETTINGS-02 | feature | `php artisan test --compact --filter=ProfileUpdateTest` | ✅ exists | ⬜ pending |
| 10-01-03 | 01 | 1 | SETTINGS-03 | feature | `php artisan test --compact --filter=PasswordUpdateTest` | ✅ exists | ⬜ pending |
| 10-02-01 | 02 | 1 | SETTINGS-04 | feature | `php artisan test --compact --filter=AppearanceSettings` | W0 (10-00) | ⬜ pending |
| 10-02-02 | 02 | 1 | SETTINGS-05 | feature | `php artisan test --compact --filter=TwoFactorAuthentication` | ✅ exists | ⬜ pending |
| 10-03-01 | 03 | 1 | SETTINGS-06 | feature | `php artisan test --compact --filter=UsersSettings` | W0 (10-00) | ⬜ pending |
| 10-03-02 | 03 | 1 | SETTINGS-07 | feature | `php artisan test --compact --filter=ProfileUpdateTest` | ✅ exists | ⬜ pending |
| 10-03-03 | 03 | 1 | SETTINGS-08 | feature | `php artisan test --compact --filter=AuthPages` | W0 (10-00) | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Wave 0 is handled by **Plan 10-00-PLAN.md**, which creates all four test stub files:

- [ ] `tests/Feature/Settings/SettingsNavigationTest.php` — stubs for SETTINGS-01
- [ ] `tests/Feature/Settings/AppearanceSettingsTest.php` — stubs for SETTINGS-04
- [ ] `tests/Feature/Settings/UsersSettingsTest.php` — stubs for SETTINGS-06
- [ ] `tests/Feature/Settings/AuthPagesTest.php` — stubs for SETTINGS-08

*Existing test infrastructure (Pest 4) covers framework needs. Only test files needed.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Theme toggle applies `data-theme` to `<html>` | SETTINGS-04 | Browser DOM manipulation | Toggle theme, inspect `<html>` element for `data-theme` attribute change |
| QR code renders on white background | SETTINGS-05 | Visual verification | Enable 2FA, verify QR code background is white in both themes |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references (via Plan 10-00)
- [x] No watch-mode flags
- [x] Feedback latency < 15s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
