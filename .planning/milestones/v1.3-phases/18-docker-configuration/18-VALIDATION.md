---
phase: 18
slug: docker-configuration
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-26
---

# Phase 18 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 + PHPUnit 12 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=DockerConfiguration` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=DockerConfiguration`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 18-01-01 | 01 | 1 | DOCK-01 | unit (file content) | `php artisan test --compact --filter=DockerConfigurationTest` | ❌ W0 | ⬜ pending |
| 18-02-01 | 02 | 1 | DOCK-02 | unit (file content) | `php artisan test --compact --filter=DockerConfigurationTest` | ❌ W0 | ⬜ pending |
| 18-03-01 | 03 | 2 | DOCK-03 | unit (file content) | `php artisan test --compact --filter=DockerConfigurationTest` | ❌ W0 | ⬜ pending |
| 18-01-02 | 01 | 1 | DOCK-04 | unit (file content) | `php artisan test --compact --filter=DockerConfigurationTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/DockerConfigurationTest.php` — stubs for DOCK-01, DOCK-02, DOCK-03, DOCK-04
  - Tests parse `compose.yaml` as YAML and assert service keys, healthcheck config, `depends_on` conditions
  - Tests assert `.env.docker.example` contains `DB_CONNECTION=mysql`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `WWWUSER`, `WWWGROUP`
  - Tests assert `compose.music.yaml` contains the music volume mount line

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| `sail up -d` starts all services | DOCK-01 | Requires Docker runtime | Run `sail up -d`, verify `docker compose ps` shows all services healthy |
| Music folder mounts at `/music` | DOCK-03 | Requires Docker runtime + host folder | Set `MUSIC_LIBRARY_PATH=/tmp/test-music`, run `sail up -d`, verify `sail exec laravel.test ls /music` |
| Services wait for dependencies | DOCK-04 | Requires Docker runtime | Run `sail up -d` on fresh DB, verify migrations complete without race condition |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
