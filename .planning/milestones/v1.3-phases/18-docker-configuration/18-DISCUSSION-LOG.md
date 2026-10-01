# Phase 18: Docker Configuration - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-03-26
**Phase:** 18-docker-configuration
**Areas discussed:** First-run workflow, Music mount when unset, Extra services, Port mapping

---

## First-run Workflow

### Q1: Auto vs manual setup after `sail up -d`

| Option | Description | Selected |
|--------|-------------|----------|
| Auto via entrypoint (Recommended) | Docker entrypoint script detects first run and runs migrations + npm build + storage:link automatically | ✓ |
| Manual sail commands | User runs `sail artisan migrate`, `sail npm run build`, etc. after `sail up -d` | |
| Separate setup command | Add `sail artisan app:install` or `composer run docker-setup` that user runs once | |

**User's choice:** Auto via entrypoint
**Notes:** None

### Q2: Re-run migrations on subsequent boots

| Option | Description | Selected |
|--------|-------------|----------|
| Always run migrate (Recommended) | Run `artisan migrate --force` every boot. Idempotent, picks up schema changes after `git pull` | ✓ |
| First boot only | Only migrate when DB is empty. Manual migrate after pulling updates | |

**User's choice:** Always run migrate
**Notes:** None

### Q3: NPM build on every boot

| Option | Description | Selected |
|--------|-------------|----------|
| Only when needed (Recommended) | Check if `public/build/manifest.json` exists — skip if assets compiled. Faster restarts | ✓ |
| Every boot | Always run `npm install && npm run build`. Guarantees fresh assets but adds 30-60s | |

**User's choice:** Only when needed
**Notes:** None

---

## Music Mount When Unset

### Q1: Behavior when MUSIC_LIBRARY_PATH is not set

| Option | Description | Selected |
|--------|-------------|----------|
| Skip mount, app works without it (Recommended) | No volume mount created. Upload-based library works fine. Comment explains how to enable | ✓ |
| Mount empty directory | Always mount a volume with default empty dir | |
| Fail with clear error | Entrypoint refuses to start if path doesn't exist on host | |

**User's choice:** Skip mount, app works without it
**Notes:** None

### Q2: Conditional vs always-present volume line

| Option | Description | Selected |
|--------|-------------|----------|
| Conditional in compose.yaml (Recommended) | Use profiles or override file so mount only activates when var is set | ✓ |
| Always in compose.yaml with default | Volume line always present with `${MUSIC_LIBRARY_PATH:-.}:/music` fallback | |

**User's choice:** Conditional in compose.yaml
**Notes:** None

---

## Extra Services

### Q1: Additional services beyond MySQL + Redis

| Option | Description | Selected |
|--------|-------------|----------|
| Minimal — MySQL + Redis only (Recommended) | Keep lean. Mail uses `log` driver. Users add extras later if needed | ✓ |
| Add Mailpit for email | Include Mailpit for email debugging at localhost:8025 | |
| Mailpit + Meilisearch | Full dev experience with email + search | |

**User's choice:** Minimal — MySQL + Redis only
**Notes:** None

---

## Port Mapping

### Q1: Host port mapping

| Option | Description | Selected |
|--------|-------------|----------|
| 80 (standard) | Sail's default. Clean URLs, no port number needed | |
| 8080 | Avoids port 80 conflicts. Less clean URL | |
| Configurable via APP_PORT | Default to 80, let users override. Sail supports this pattern | ✓ |

**User's choice:** Configurable via APP_PORT
**Notes:** None

### Q2: Default APP_PORT value

| Option | Description | Selected |
|--------|-------------|----------|
| 80 (Recommended) | Sail's standard default. Users change if conflicts | ✓ |
| 8080 | Safer default avoiding local web server conflicts | |

**User's choice:** 80
**Notes:** None

---

## Claude's Discretion

- Entrypoint script implementation (bash vs PHP, conditional checks)
- Conditional music volume mount approach (profiles vs override file)
- MySQL/Redis/PHP version selection
- Healthcheck implementation details

## Deferred Ideas

None — discussion stayed within phase scope
