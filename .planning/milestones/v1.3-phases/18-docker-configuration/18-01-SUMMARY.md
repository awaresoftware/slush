---
phase: 18-docker-configuration
plan: "01"
subsystem: infra
tags: [docker, sail, compose, mysql, redis, supervisord, healthcheck]

# Dependency graph
requires:
  - phase: 17-native-foundation
    provides: composer run setup pattern and start.sh conventions adapted for Docker entrypoint
provides:
  - compose.yaml with laravel.test, mysql, redis, worker services and proper healthcheck/depends_on
  - docker/start.sh entrypoint handling migrate, conditional npm build, storage:link, supervisord handoff
  - DockerConfigurationTest.php with test stubs for all 4 DOCK requirements (DOCK-01/04 passing)
affects: [18-02-env-file, 18-03-music-override]

# Tech tracking
tech-stack:
  added: [docker-compose, mysql:8.4, redis:alpine, sail-8.4]
  patterns: [healthcheck-depends_on, conditional-asset-build, exec-supervisord-handoff, __DIR__-relative-test-paths-for-worktrees]

key-files:
  created:
    - compose.yaml
    - docker/start.sh
    - tests/Feature/DockerConfigurationTest.php
  modified: []

key-decisions:
  - "No version: header in compose.yaml — Docker Compose v2 deprecated this field"
  - "No VITE_PORT mapping — assets are pre-built inside the container, no Vite dev server needed"
  - "Worker does NOT call docker/start.sh — runs queue:work directly to prevent double migration"
  - "Worker depends_on laravel.test (service_started) so web container runs migrations first"
  - "MySQL healthcheck has start_period: 30s to prevent race condition on first boot"
  - "Use __DIR__-relative paths in tests instead of base_path() — git worktrees with symlinked vendor resolve base_path() to the parent project root"
  - "dockerTestProjectRoot() helper function computes project root via dirname(__DIR__, 2) from tests/Feature/"

patterns-established:
  - "Healthcheck pattern: retries + timeout + interval + start_period on stateful services"
  - "depends_on with condition: service_healthy ensures dependents wait for health gates"
  - "Entrypoint script: set -e, cd to app, key:generate guard, migrate, conditional build, storage:link, exec supervisord"
  - "Test helper function for __DIR__-relative project root resolution in worktree environments"

requirements-completed: [DOCK-01, DOCK-04]

# Metrics
duration: 7min
completed: 2026-03-26
---

# Phase 18 Plan 01: Docker Core Services Summary

**Docker Compose config with 4 services (web/worker/mysql/redis), healthchecks with start_period guard, and entrypoint script handling first-run migrations and conditional npm build**

## Performance

- **Duration:** 7 min
- **Started:** 2026-03-26T22:20:30Z
- **Completed:** 2026-03-26T22:27:46Z
- **Tasks:** 2 (TDD: RED + GREEN)
- **Files modified:** 3

## Accomplishments

- compose.yaml defines all 4 services with proper dependency ordering and health gates
- MySQL healthcheck has start_period: 30s preventing first-boot race conditions (Pitfall 1)
- docker/start.sh handles key generation, migrations, conditional npm build, and supervisord handoff
- DockerConfigurationTest covers DOCK-01 through DOCK-04 with 20/28 tests passing (DOCK-02/03 intentionally fail — files created by Plans 02/03)

## Task Commits

Each task was committed atomically:

1. **Task 1: Create DockerConfigurationTest with all DOCK requirement stubs** - `a6ac8f2` (test)
2. **Task 2: Create compose.yaml and docker/start.sh** - `b5e79f6` (feat)

_Note: TDD — Task 1 is the RED commit (failing tests), Task 2 is the GREEN commit (passing DOCK-01/04)_

## Files Created/Modified

- `compose.yaml` — Docker Compose service definitions for laravel.test, worker, mysql, redis
- `docker/start.sh` — Entrypoint script: key:generate guard, migrate --force, conditional npm build, storage:link --force, exec supervisord
- `tests/Feature/DockerConfigurationTest.php` — Pest tests for all 4 DOCK requirements using __DIR__-relative paths

## Decisions Made

- `version:` header omitted — Docker Compose v2 deprecated this field; omitting is the modern approach
- No `VITE_PORT` port mapping — assets are pre-built inside the container on first run via `npm run build`, no Vite dev server in Docker
- Worker uses `queue:work` directly, NOT docker/start.sh — prevents double migration and double npm build
- Worker `depends_on laravel.test: condition: service_started` — web container runs migrations first, worker starts after
- MySQL `start_period: 30s` — prevents the health check from failing immediately during MySQL's first-boot initialization
- Tests use `dockerTestProjectRoot()` helper (via `dirname(__DIR__, 2)`) instead of `base_path()` — in git worktrees with symlinked vendor, `Application::inferBasePath()` resolves to the parent project via Composer's ClassLoader, making `base_path()` return the wrong directory

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Use __DIR__-relative paths in tests instead of base_path()**
- **Found during:** Task 1 (creating DockerConfigurationTest)
- **Issue:** `base_path()` calls failed with "Call to undefined method Illuminate\Container\Container::basePath()" — in git worktrees with symlinked vendor, `Application::inferBasePath()` uses Composer's ClassLoader which resolves to the parent project's vendor directory, making `basePath()` point to the wrong project
- **Fix:** Created `dockerTestProjectRoot()` helper using `dirname(__DIR__, 2)` which always resolves relative to the test file's location, regardless of how vendor is symlinked
- **Files modified:** tests/Feature/DockerConfigurationTest.php
- **Verification:** Tests now correctly fail due to missing files (proper RED state), not due to runtime errors
- **Committed in:** a6ac8f2 (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug in test infrastructure for worktree environment)
**Impact on plan:** Fix was necessary for correct test execution in the worktree environment. Tests now behave exactly as specified (DOCK-01/04 RED then GREEN, DOCK-02/03 remain RED for Plans 02/03).

## Issues Encountered

- Git worktree with symlinked vendor directory causes `Application::inferBasePath()` to resolve the parent project path via Composer's ClassLoader. Resolved by using `__DIR__`-relative paths in test helpers instead of `base_path()`.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- compose.yaml ready for Plan 02 (.env.docker.example) and Plan 03 (compose.music.yaml) to build on
- DockerConfigurationTest stubs for DOCK-02 and DOCK-03 are in place (failing) and will go GREEN when Plans 02/03 complete their files
- `service_healthy` health gates mean `sail up -d` will properly sequence service startup
