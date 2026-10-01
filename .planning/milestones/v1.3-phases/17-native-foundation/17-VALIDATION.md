---
phase: 17
slug: native-foundation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-26
---

# Phase 17 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (pestphp/pest ^4.4) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=NativeFoundation` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=NativeFoundation`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 10 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 17-01-01 | 01 | 1 | NATV-01 | Feature | `php artisan test --compact --filter=NativeFoundationTest` | ❌ W0 | ⬜ pending |
| 17-01-02 | 01 | 1 | NATV-01 | Feature | `php artisan test --compact --filter=NativeFoundationTest` | ❌ W0 | ⬜ pending |
| 17-01-03 | 01 | 1 | NATV-01 | Feature | `php artisan test --compact --filter=NativeFoundationTest` | ❌ W0 | ⬜ pending |
| 17-02-01 | 02 | 1 | NATV-02 | Unit | `php artisan test --compact --filter=DetectPortTest` | ❌ W0 | ⬜ pending |
| 17-02-02 | 02 | 1 | NATV-02 | Unit | `php artisan test --compact --filter=DetectPortTest` | ❌ W0 | ⬜ pending |
| 17-02-03 | 02 | 1 | NATV-03 | Manual | — | N/A | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/NativeFoundationTest.php` — stubs for NATV-01 idempotency behaviors (storage:link, key:generate, SQLite touch)
- [ ] `tests/Unit/DetectPortTest.php` — stubs for NATV-02 port scanning logic
- [ ] `bin/detect-port.php` — main deliverable; must exist before DetectPortTest can load it

*Note: End-to-end `composer run setup` and `composer run dev` flows are shell-level integrations. Unit tests validate individual behaviors; full E2E verification via `/gsd:verify-work`.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| `composer run dev` spawns concurrently with resolved ports | NATV-03 | Shell-level process group; Pest cannot exercise | Run `composer run dev`, verify output shows resolved ports and all 3 processes start |
| Full `composer run setup` on fresh clone | NATV-01 | Requires clean environment state | Clone repo fresh, run `composer run setup`, verify all steps complete |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 10s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
