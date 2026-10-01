# Phase 18: Docker Configuration - Context

**Gathered:** 2026-03-26
**Status:** Ready for planning

<domain>
## Phase Boundary

Full Docker Compose setup via Laravel Sail with MySQL, Redis, queue worker, and optional host music folder mounting. Users can start the app with `sail up -d` and have it fully operational without additional manual steps.

</domain>

<decisions>
## Implementation Decisions

### First-run Workflow
- **D-01:** Auto-setup via Docker entrypoint script. Detects first run (empty DB / missing assets) and runs migrations, npm install/build, storage:link automatically. User just does `sail up -d` and waits.
- **D-02:** Run `artisan migrate --force` on every boot. Idempotent — no-ops if no pending migrations. Picks up schema changes after `git pull`.
- **D-03:** Only run `npm install && npm run build` when `public/build/manifest.json` is missing. Skip if assets already compiled — faster restarts on subsequent boots.

### Music Mount
- **D-04:** When `MUSIC_LIBRARY_PATH` is not set, skip the volume mount entirely. App works without it — local library linking is simply unavailable until the user configures it. Comment in `.env.docker.example` explains how to enable.
- **D-05:** Use conditional mount (Docker Compose profiles or separate override file) so the volume line only activates when `MUSIC_LIBRARY_PATH` is set. Cleaner for users who don't need local library linking.

### Services
- **D-06:** Minimal service set: MySQL + Redis + queue worker only. No Mailpit or Meilisearch. Mail driver stays `log` (viewable via `sail artisan pail`). Users can add extra services later if needed.

### Port Mapping
- **D-07:** Web port is configurable via `APP_PORT` env var. Sail already supports this pattern.
- **D-08:** Default `APP_PORT=80` in `.env.docker.example`. Users who hit conflicts can change to 8080 or any other port.

### Carried Forward (locked from prior phases)
- **D-09:** Two-environment split: SQLite + database queue for native installs; MySQL + Redis for Docker installs (STATE.md).
- **D-10:** `MUSIC_LIBRARY_PATH` env var drives Docker bind mount at `/music` — users must enter container paths (`/music/subfolder`) not host paths (STATE.md).
- **D-11:** Docker queue worker runs as a dedicated second service in compose.yaml, not supervisord inside main container (STATE.md).
- **D-12:** `.env.docker.example` is a complete standalone file — not a diff from `.env.example` (STATE.md).

### Claude's Discretion
- Exact entrypoint script implementation (bash vs PHP, specific conditional checks)
- How to implement the conditional music volume mount (profiles vs override file vs env interpolation)
- MySQL version selection (8.0 vs 8.4)
- Redis version selection
- PHP version in Sail Dockerfile (8.2 vs 8.4)
- Healthcheck implementation details for `depends_on` conditions

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Existing Configuration
- `composer.json` (lines 44-58) — Current `setup` and `dev` script definitions; Sail dependency at line 26
- `.env.example` — Current SQLite-based env template; Docker env must diverge from this
- `bin/detect-port.php` — Port detection script for native dev (reference for port handling patterns)

### Requirements
- `.planning/REQUIREMENTS.md` — DOCK-01, DOCK-02, DOCK-03, DOCK-04 requirements for this phase

### Prior Phase Context
- `.planning/phases/17-native-foundation/17-CONTEXT.md` — Native install decisions (setup script, dev runner, port detection)

### State Decisions
- `.planning/STATE.md` — Docker-specific decisions in "Decisions Affecting Current Work" section

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `laravel/sail ^1.41` is already a composer dev dependency — `sail:install` artisan command available
- `composer.json` scripts section has established patterns for setup/dev commands
- `.env.example` provides the base environment template to derive Docker values from

### Established Patterns
- `composer run setup` is the canonical install command — Docker entrypoint should mirror this sequence (migrate, npm build, storage:link)
- `concurrently` npm package used for multi-process dev runner — Docker uses separate services instead
- `@php` prefix in composer scripts for artisan commands

### Integration Points
- Install scripts (Phase 19, 20) will need Docker-specific paths alongside native paths
- Health check endpoint (Phase 21) will be used by Docker healthchecks
- README (Phase 22) needs Docker installation section with container path explanation

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 18-docker-configuration*
*Context gathered: 2026-03-26*
