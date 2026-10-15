# Project Research Summary

**Project:** foonk — v1.3 Easy Installation
**Domain:** Cross-platform installer and Docker/Sail setup for a self-hosted Laravel music streaming app
**Researched:** 2026-03-26
**Confidence:** HIGH

## Executive Summary

Foonk v1.3 adds zero new Composer or npm dependencies. Everything needed — Laravel Sail (already in require-dev), concurrently (already in package.json), and the existing `composer run dev` script — is already present. The milestone is purely about configuration files, install scripts, and documentation: generating a `compose.yaml` via `sail:install`, writing `install.sh`/`install.ps1`, adding Makefile Docker targets, and rewriting the README with per-OS setup guides. The existing app code (StreamController, ScanLinkedFolder, ExtractAudioMetadata, BrowseDirectoriesController) requires no changes for Docker — it works correctly against `/music` container paths as long as the volume mount is configured correctly.

The most architecturally significant decision is the two-environment split: SQLite + database queue for native installs, MySQL + Redis queue for Docker installs. This is driven by a concrete risk: two containers (web + worker) writing concurrently to the same bind-mounted SQLite file causes SQLITE_BUSY errors. The split is handled entirely through `.env` files — `.env.example` for native (SQLite defaults already present), `.env.docker.example` for Docker (MySQL + Redis). The Docker queue worker runs as a dedicated second service in `compose.yaml`, reusing the same `sail-8.4/app` image — not supervisord inside the main container.

The single highest-risk area is the volume mount for local library linking (v1.2 feature). The database stores absolute paths from wherever the app was linked; in Docker, those host paths do not exist inside the container. The correct solution — a `MUSIC_LIBRARY_PATH` env var driving a bind mount at `/music`, with container-side paths used throughout — is well-understood and fully specified in the research. However, it requires clear user education: Docker users must enter `/music/subfolder` not their host path when linking folders. Three compounding pitfalls (container paths vs host paths, UID/GID mismatch on Linux, WSL2 path translation on Windows) all converge on this single feature, making it the most likely source of "it doesn't work" reports post-launch.

## Key Findings

### Recommended Stack

No new dependencies are required. The milestone is entirely about consuming existing dependencies in new configurations. Laravel Sail 1.41 (already installed) ships MySQL 8.4 and Redis alpine stubs with healthchecks pre-configured. The `concurrently` package (already in package.json) already powers `composer run dev` with three concurrent processes.

New files to create: `compose.yaml` (generated via `sail:install`, then customized), `install.sh`, `install.ps1`, `.env.docker.example`. Modified files: `Makefile` (add Docker targets), `.env.example` (add port override vars), `.gitattributes` (enforce LF line endings for shell scripts).

**Core technologies:**
- `laravel/sail ^1.41`: Docker Compose environment — already installed, activated via `sail:install`
- `concurrently ^9.0.1`: Native multi-process dev runner — already in use via `composer run dev`
- MySQL 8.4 (Sail stub): Docker-only database — replaces SQLite for the Docker install path
- Redis alpine (Sail stub): Docker-only queue driver — replaces database queue for Docker
- Bash + PowerShell (OS built-ins): Install script runtimes — no installation required

### Expected Features

**Must have (table stakes):**
- `composer run setup` one-liner — exists, but missing `storage:link`; needs audit and fix
- `composer run dev` starts web + queue + Vite — already complete, needs documenting
- `install.sh` for macOS/Linux with dependency detection and helpful error messages — must create
- `install.ps1` for Windows with dependency detection — must create; ship `install.bat` wrapper for execution policy
- Docker install via Sail with MySQL + Redis — must generate `compose.yaml` and customize
- Queue worker runs automatically in Docker — separate `worker` service in `compose.yaml`
- Host folder volume mount for local library (`MUSIC_LIBRARY_PATH`) — most complex feature
- Port conflict detection before `sail up` — must check ports 80, 3306, 6379, 5173
- Health check after install — must hit a DB-exercising endpoint, not just `/`
- README with per-OS installation sections — write last, after all paths are validated

**Should have (differentiators):**
- `.env.docker.example` as a complete separate file (not a diff from `.env.example`) — eliminates user confusion
- Post-install first-run instructions printed to terminal — eliminates "now what?" confusion
- `MUSIC_LIBRARY_PATH` env-driven volume mount — one variable, no YAML editing required
- Smart port fallback suggestion with auto-write to `.env` — reduces Herd conflict friction

**Defer (v2+):**
- Production-optimized multi-stage Docker image — overkill for personal app
- Full WSL2 setup automation in `install.ps1` — requires admin elevation, reboot; document instead
- Multiple music directory support beyond manual compose editing — document the workaround for now
- `laravel/horizon` — overkill for personal app queue needs
- Pre-building Vite assets inside the Docker container at startup — run once at install, not every restart

### Architecture Approach

The architecture is additive: new configuration files and scripts layer on top of an unchanged application codebase. The core pattern is a two-path install (native: SQLite + database queue; Docker: MySQL + Redis) controlled entirely through `.env` files. In Docker, the `compose.yaml` defines four services: `laravel.test` (web), `mysql`, `redis`, and `worker` (queue processor reusing the same image). Both `laravel.test` and `worker` mount the same `MUSIC_LIBRARY_PATH` volume at `/music` so that ScanLinkedFolder and StreamController see identical container paths. Build order is dictated by dependencies: `.env.docker.example` first (referenced by compose), `compose.yaml` second, Makefile Docker targets third, install scripts fourth, README last.

**Major components:**
1. `compose.yaml` — Docker service topology (web + mysql + redis + worker), volume mounts, healthchecks, `depends_on: condition: service_healthy`
2. `install.sh` / `install.ps1` — dependency detection, port checks, install sequence, user guidance (wrappers around existing Makefile commands)
3. `.env.docker.example` — complete Docker-specific env file with MySQL/Redis connection strings pre-filled
4. Makefile Docker targets — `sail-install`, `sail-up`, `sail-down`, `sail-fresh` using `vendor/bin/sail`
5. `/healthz` route — new lightweight endpoint that exercises the DB, used by install health check

### Critical Pitfalls

1. **Host paths stored in DB break inside Docker** — `LinkedFolder.path` and `tracks.file_path` store absolute host paths (e.g. `/Users/alice/Music`) that do not exist in the container. Prevention: bind-mount `MUSIC_LIBRARY_PATH` at `/music`; instruct Docker users to enter `/music/subfolder` in the UI; document prominently in README.

2. **MySQL container not ready when Laravel boots** — `sail up -d` starts containers simultaneously; `php artisan migrate` runs before MySQL accepts connections. Prevention: add `condition: service_healthy` to `depends_on` in `compose.yaml`; poll `mysqladmin ping` in the install script before `migrate`.

3. **CRLF line endings in `install.sh` break bash execution** — Git on Windows converts LF to CRLF; `#!/bin/bash\r` is an invalid interpreter path. Prevention: commit `.gitattributes` with `*.sh eol=lf` in the same phase as `install.sh`.

4. **PowerShell execution policy blocks `install.ps1`** — Windows defaults to `Restricted` policy; script cannot run. Prevention: ship `install.bat` wrapper invoking PowerShell with `-ExecutionPolicy Bypass`; document `Set-ExecutionPolicy RemoteSigned` in README.

5. **SQLite-to-MySQL type coercion differences break runtime queries** — migrations succeed but queries fail (strict mode, GROUP BY, JSON column differences). Prevention: run the full Pest suite against MySQL in Docker as a gate before shipping the Docker path.

## Implications for Roadmap

Based on research, suggested phase structure:

### Phase 1: Composer Setup Script Fixes and Native Foundation
**Rationale:** The native path (SQLite, `composer run setup`, `composer run dev`) is the primary install path for most users. Auditing and fixing the existing `composer run setup` script (add `storage:link`, verify all steps) establishes a working baseline and provides the command sequence that `install.sh` will wrap. Lowest risk, highest reward — unblocks all subsequent phases.
**Delivers:** A fully working one-command native install that can be documented and tested
**Addresses:** `composer run setup` missing `storage:link`, `composer run dev` documentation gap
**Avoids:** Pitfall 8 (queue worker not started), Pitfall 13 (`key:generate` running unconditionally), Pitfall 15 (`config:cache` locking in wrong DB)

### Phase 2: Docker Compose Configuration (compose.yaml + .env.docker.example)
**Rationale:** All Docker work depends on a correct `compose.yaml`. This is the most structurally complex phase. Must be completed before Makefile Docker targets, before install scripts that call `sail up`, and before any Docker testing. The volume mount for local library is implemented here.
**Delivers:** Working `sail up -d` with MySQL, Redis, worker service, healthchecks, and music volume mount
**Implements:** `compose.yaml` (generated + customized), `.env.docker.example`, WWWUSER/WWWGROUP detection in `.env`
**Avoids:** Pitfall 1 (broken container paths), Pitfall 2 (MySQL not ready), Pitfall 6 (connection refused), Pitfall 7 (port conflicts), Pitfall 11 (WSL2 path issues)

### Phase 3: Makefile Docker Targets and MySQL Compatibility Validation
**Rationale:** With `compose.yaml` in place, wrap the Docker workflow in Makefile targets and validate that the entire existing test suite passes against MySQL (not SQLite). This is the MySQL compatibility gate — must pass before shipping Docker support.
**Delivers:** `sail-install`, `sail-up`, `sail-down`, `sail-fresh` Makefile targets; confirmed MySQL compatibility
**Avoids:** Pitfall 5 (SQLite/MySQL type coercion differences), Pitfall 14 (node_modules native binary mismatch)

### Phase 4: Bash Install Script (macOS/Linux)
**Rationale:** `install.sh` is a thin wrapper around the commands already validated in Phase 1. The complexity is in dependency detection, port checking, and user-friendly error messages — not in the install logic itself. Implement after native foundation is solid.
**Delivers:** `install.sh` with dependency checks, port conflict detection, guided install flow, first-run message
**Implements:** PHP/Composer/Node detection, Homebrew/apt/dnf suggestions, `lsof` port checking, `storage:link` call
**Avoids:** Pitfall 3 (CRLF — requires `.gitattributes` committed in this phase), Pitfall 9 (PATH not updated after Homebrew install)

### Phase 5: PowerShell Install Script (Windows)
**Rationale:** Parallel to Phase 4 but Windows-specific. Implement after `install.sh` is validated — the logic is the same, the tooling differs. PowerShell nuances (execution policy, `netstat` vs `lsof`, `Get-Command`) require careful testing.
**Delivers:** `install.ps1` + `install.bat` wrapper for execution policy bypass
**Avoids:** Pitfall 4 (execution policy blocks script), Pitfall 9 (Chocolatey PATH in same session)

### Phase 6: Health Check Endpoint and Install Verification
**Rationale:** Both install scripts need a reliable health check that exercises the database. The `/healthz` route is a prerequisite for trustworthy install verification. Implement as its own phase to keep concerns separate and ensure the route is tested.
**Delivers:** `GET /healthz` route returning JSON `{"status":"ok","db":"connected"}`, used by both install scripts
**Avoids:** Pitfall 10 (false positive health check on static web server response)

### Phase 7: README Rewrite
**Rationale:** The README is the first-contact install UX. Must be written last — after all three paths (native macOS/Linux, native Windows, Docker) are confirmed working. Writing documentation before scripts are validated causes them to diverge.
**Delivers:** Per-OS installation sections (macOS native, Linux native, Windows native, Docker), quick-start badges, local library Docker caveat prominently documented
**Avoids:** Documentation describing a flow that does not actually work

### Phase Ordering Rationale

- Native foundation first because Docker setup inherits its install command sequence; fixing `setup` once benefits both paths
- `compose.yaml` before Makefile targets because targets call `sail` commands that require the compose file
- MySQL compatibility validation in Phase 3 (not later) because it is a hard gate — if tests fail against MySQL, Docker support is not shippable
- `install.sh` before `install.ps1` because macOS/Linux is the primary developer platform; Windows can be validated with the confirmed logic
- README last because it must accurately describe what the scripts actually do

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 2 (Docker Compose):** The exact worker service configuration (`queue:work` command, `depends_on` with `condition: service_healthy` on both mysql and redis, volume sharing between web and worker) has several interaction points that should be verified with a working Docker environment before finalizing the compose spec
- **Phase 5 (PowerShell):** Windows-specific dependency detection (Laravel Herd's PHP vs PATH-registered PHP, Chocolatey PATH refresh behavior) needs Windows environment validation; the research flags this as MEDIUM confidence

Phases with standard patterns (skip additional research):
- **Phase 1 (Native setup):** Existing `composer.json` scripts are confirmed; `storage:link` addition is a one-line change
- **Phase 3 (Makefile targets):** All `vendor/bin/sail` commands are standard documented Sail usage
- **Phase 4 (Bash install script):** `lsof`, `command -v`, `brew shellenv` patterns are well-documented and confirmed
- **Phase 6 (Health check route):** Laravel 12 route + JSON response is a minimal implementation
- **Phase 7 (README):** No research needed; write from the confirmed install paths

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All dependencies confirmed by reading `composer.json`, `package.json`, and Sail stub files directly. Zero new packages required — highest possible confidence. |
| Features | HIGH | Feature list derived from direct codebase inspection + authoritative PROJECT.md. Comparable self-hosted projects (Navidrome, Immich) confirm the pattern. |
| Architecture | HIGH | Data flow confirmed by reading `StreamController`, `ScanLinkedFolder`, `LinkedFolder` source. SQLite/MySQL split and volume mount pattern are well-documented standards. PowerShell detection is MEDIUM. |
| Pitfalls | HIGH | 15 documented pitfalls with detection methods. All critical pitfalls (Docker paths, MySQL readiness, CRLF, PowerShell policy, SQLite/MySQL differences) are verified against official sources and codebase inspection. |

**Overall confidence:** HIGH

### Gaps to Address

- **Multiple music directories in Docker:** Research identifies the manual compose edit workaround but does not specify whether to build a wizard or just document the workaround. Phase 2 planning should decide this explicitly.
- **Windows Docker UID/GID:** On Windows with Docker Desktop + WSL2, UID/GID mismatch handling differs from Linux. The macOS/Linux mitigation (detect `id -u`, write `WWWUSER`) is clear, but the Windows equivalent was not fully validated. Flag for testing in Phase 5.
- **Health check endpoint placement:** The research recommends a `/healthz` route outside auth middleware. The exact route file, middleware exclusion, and response format need a brief implementation decision in Phase 6.
- **`sail:install` scaffolding output:** Running `sail:install --with=mysql,redis` generates a `compose.yaml` that does not include the worker service. Phase 2 should confirm the generated output before customizing it so the customization does not overwrite Sail's stubs incorrectly.

## Sources

### Primary (HIGH confidence)
- `composer.json` — confirms `laravel/sail ^1.41`, existing `setup` and `dev` scripts
- `package.json` — confirms `concurrently ^9.0.1`, already used by `composer run dev`
- `.env.example` — confirms SQLite defaults, existing Redis vars commented out
- `config/database.php` — confirms both sqlite and mysql connections pre-configured
- `app/Http/Controllers/StreamController.php` — confirms absolute path file serving pattern
- `app/Jobs/ScanLinkedFolder.php` — confirms `RecursiveDirectoryIterator($this->folder->path)` reads stored path directly
- `vendor/laravel/sail/stubs/mysql.stub` — MySQL 8.4 healthcheck confirmed
- `vendor/laravel/sail/stubs/redis.stub` — Redis alpine healthcheck confirmed
- `vendor/laravel/sail/runtimes/8.4/supervisord.conf` — single PHP process per container confirmed
- [Laravel Sail 12.x Documentation](https://laravel.com/docs/12.x/sail) — sail:install, volumes, services
- [Docker Bind Mounts Documentation](https://docs.docker.com/engine/storage/bind-mounts/) — bind mount syntax, env var interpolation
- PROJECT.md v1.3 requirements — authoritative project spec

### Secondary (MEDIUM confidence)
- [Docker Compose healthcheck guide — Last9](https://last9.io/blog/docker-compose-health-checks/) — `depends_on condition: service_healthy` pattern
- [Dockerizing Laravel queues — Sevalla](https://sevalla.com/blog/dockerizing-laravel-queues/) — dedicated worker container approach
- [GitHub laravel/sail issue #245](https://github.com/laravel/sail/issues/245) — queue worker container pattern discussion
- [Self Hosting Navidrome — NucuLabs 2025](https://nuculabs.dev/posts/2025/self-hosting-navidrome/) — comparable install UX reference
- [Immich Docker Compose install](https://docs.immich.app/install/docker-compose/) — `.env.docker.example` pattern reference
- [Adding Laravel Sail to Existing Projects — Vincent Schmalbach](https://www.vincentschmalbach.com/adding-laravel-sail-to-existing-projects-solving-the-database-driver-chicken-and-egg-problem/) — MySQL driver in Sail containers

### Tertiary (LOW confidence)
- [CRLF vs LF normalising line endings — aleksandrhovhannisyan.com](https://www.aleksandrhovhannisyan.com/blog/crlf-vs-lf-normalizing-line-endings-in-git/) — needs Windows environment validation
- [15 ways to bypass PowerShell execution policy — NetSPI](https://www.netspi.com/blog/technical-blog/network-pentesting/15-ways-to-bypass-the-powershell-execution-policy/) — policy bypass patterns for install.bat wrapper

---
*Research completed: 2026-03-26*
*Ready for roadmap: yes*
