---
phase: 19
slug: bash-install-script
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-26
---

# Phase 19 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4.4 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=BashInstallScriptTest` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=BashInstallScriptTest`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 5 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 19-01-01 | 01 | 1 | INST-01 | unit | `php artisan test --compact --filter=BashInstallScriptTest` | ❌ W0 | ⬜ pending |
| 19-01-02 | 01 | 1 | INST-01 | unit | `php artisan test --compact --filter=BashInstallScriptTest` | ❌ W0 | ⬜ pending |
| 19-01-03 | 01 | 1 | INST-01 | unit | `php artisan test --compact --filter=BashInstallScriptTest` | ❌ W0 | ⬜ pending |
| 19-01-04 | 01 | 1 | INST-03 | unit | `php artisan test --compact --filter=BashInstallScriptTest` | ❌ W0 | ⬜ pending |
| 19-01-05 | 01 | 1 | INST-03 | unit | `php artisan test --compact --filter=BashInstallScriptTest` | ❌ W0 | ⬜ pending |
| 19-02-01 | 02 | 1 | INST-04 | unit | `php artisan test --compact --filter=BashInstallScriptTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/BashInstallScriptTest.php` — file content assertions for INST-01, INST-03, INST-04 (follows `DockerConfigurationTest.php` pattern)

*Existing Pest infrastructure covers all framework requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Script runs to completion on clean macOS | INST-01 | Requires clean OS environment | Run `./install.sh` on fresh macOS without PHP/Composer/Node |
| Script runs to completion on clean Linux | INST-01 | Requires clean OS environment | Run `./install.sh` on fresh Ubuntu 22.04/24.04 |
| Homebrew prompt appears on macOS without Homebrew | INST-03 | Requires macOS without Homebrew | Remove Homebrew, run `./install.sh`, verify prompt |
| `sort -V` fallback works on stock macOS | INST-01 | BSD sort lacks `-V` flag | Run version comparison on macOS without coreutils |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 5s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending