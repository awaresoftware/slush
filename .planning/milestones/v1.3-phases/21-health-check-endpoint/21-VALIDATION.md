---
phase: 21
slug: health-check-endpoint
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-27
---

# Phase 21 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 / PHPUnit 12 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=HealthCheck` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=HealthCheck`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 5 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 21-01-01 | 01 | 1 | HLTH-01 | feature | `php artisan test --compact --filter=HealthCheck` | ❌ W0 | ⬜ pending |
| 21-01-02 | 01 | 1 | HLTH-01 | feature | `php artisan test --compact --filter=HealthCheck` | ❌ W0 | ⬜ pending |
| 21-01-03 | 01 | 1 | HLTH-01 | feature | `php artisan test --compact --filter=HealthCheck` | ❌ W0 | ⬜ pending |
| 21-01-04 | 01 | 1 | HLTH-01 | feature | `php artisan test --compact --filter=HealthCheck` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/HealthCheckTest.php` — stubs for HLTH-01 (200 success, JSON structure, 503 failure, no-auth access)

*Existing `DockerConfigurationTest.php` and `BashInstallScriptTest.php` will be extended in-place, not created from scratch.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Docker healthcheck detects unhealthy web container | HLTH-01 | Requires running Docker environment | 1. `sail up -d` 2. `docker compose ps` — verify `laravel.test` shows `healthy` 3. Stop MySQL 4. Verify `laravel.test` transitions to `unhealthy` |
| Install script prints health verification hint | HLTH-01 | Requires clean install environment | 1. Run `./install.sh` on clean clone 2. Verify health check hint is printed after setup |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 5s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
