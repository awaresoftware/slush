---
phase: 20
slug: powershell-install-script
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-27
---

# Phase 20 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4.x |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=PowerShellInstallScriptTest` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=PowerShellInstallScriptTest`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 5 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 20-01-01 | 01 | 1 | INST-02 | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ W0 | ⬜ pending |
| 20-01-02 | 01 | 1 | INST-02 | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ W0 | ⬜ pending |
| 20-01-03 | 01 | 1 | INST-02 | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/PowerShellInstallScriptTest.php` — stubs for INST-02 (file content assertions following `BashInstallScriptTest` pattern)

*Existing test infrastructure covers framework needs — Pest 4.x already installed.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| `install.bat` launches PowerShell without execution policy errors | INST-02 | Requires Windows OS runtime | Double-click `install.bat` on Windows, verify PS1 runs |
| UAC elevation prompt appears when needed | INST-02 | Requires Windows UAC interaction | Run `install.bat` as non-admin, verify elevation prompt |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 5s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
