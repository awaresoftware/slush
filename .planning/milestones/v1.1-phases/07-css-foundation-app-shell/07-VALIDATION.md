---
phase: 7
slug: css-foundation-app-shell
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-13
---

# Phase 7 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest v4 + PHPUnit v12 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=AudioPlayer` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=AudioPlayer`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 07-01-01 | 01 | 1 | INFRA-01 | unit | `php artisan test --compact --filter=CssFoundation` | ❌ W0 | ⬜ pending |
| 07-01-02 | 01 | 1 | INFRA-03 | unit | `php artisan test --compact --filter=CssFoundation` | ❌ W0 | ⬜ pending |
| 07-02-01 | 02 | 1 | LAYOUT-01 | feature | `php artisan test --compact --filter=DashboardTest` | ✅ | ⬜ pending |
| 07-02-02 | 02 | 1 | LAYOUT-02 | feature | `php artisan test --compact --filter=DashboardTest` | ✅ partial | ⬜ pending |
| 07-02-03 | 02 | 1 | LAYOUT-03 | feature | `php artisan test --compact --filter=AppShellTest` | ❌ W0 | ⬜ pending |
| 07-02-04 | 02 | 1 | LAYOUT-04 | feature | `php artisan test --compact --filter=AppShellTest` | ❌ W0 | ⬜ pending |
| 07-03-01 | 03 | 2 | PLAYER-01 | feature | `php artisan test --compact --filter=AudioPlayer` | ✅ | ⬜ pending |
| 07-03-02 | 03 | 2 | PLAYER-02 | feature | `php artisan test --compact --filter=AudioPlayer` | ✅ partial | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/CssFoundationTest.php` — stubs for INFRA-01, INFRA-03 (assert app.css contains/not-contains specific strings)
- [ ] `tests/Feature/AppShellTest.php` — stubs for LAYOUT-03, LAYOUT-04 (bottom nav renders, user menu has logout button)

*Existing infrastructure covers LAYOUT-01, LAYOUT-02, PLAYER-01, PLAYER-02 partially.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Pages load without visual breakage from CSS conflicts | INFRA-01 | Visual rendering check | Load dashboard, library, playlists pages; verify no broken backgrounds, text colors, or layout jumps |
| Bottom nav positioned correctly relative to audio player | LAYOUT-03 | Z-stacking and layout geometry | On mobile viewport, play a track; verify bottom nav and player are both visible without overlap |
| Volume/seek sliders are functional and styled | PLAYER-02 | Interactive behavior + visual | Play a track; drag seek bar and volume slider; verify they respond and use daisyUI theme colors |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
