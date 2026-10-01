# Phase 17: Native Foundation - Context

**Gathered:** 2026-03-26
**Status:** Ready for planning

<domain>
## Phase Boundary

One-command native install (`composer run setup`) and dev runner (`composer run dev`) with automatic port conflict detection. This phase makes the native (non-Docker) path work reliably on a fresh clone.

</domain>

<decisions>
## Implementation Decisions

### Port Conflict Detection
- **D-01:** Auto-pick next free port and start automatically. No user prompt or manual re-run needed — print a notice showing the resolved port.
- **D-02:** Check both web server (8000) and Vite (5173) ports for conflicts.
- **D-03:** Implement detection as a PHP script (e.g. `bin/detect-port.php`) using `fsockopen`. Cross-platform, no OS-specific tools like lsof/netstat.
- **D-04:** Port detection runs as part of `composer run dev` before launching concurrently. Resolved ports are passed to `artisan serve --port` and Vite `--port` flags.
- **D-05:** On startup, print the resolved web URL (e.g. "App running at http://localhost:8001") before launching concurrently, so the user knows where to browse when the port shifted.

### Setup Script Scope
- **D-06:** `composer run setup` must be fully idempotent — safe to run on both fresh clones and re-runs. Skip steps that already completed (don't fail on existing storage symlink, don't regenerate key if .env exists with a key).
- **D-07:** Remove the Makefile. Consolidate into `composer run setup` as the single canonical install command.
- **D-08:** Add `touch database/database.sqlite` to setup script — auto-create the SQLite database file if it doesn't exist, preventing migration failure on fresh clone.
- **D-09:** Add `php artisan storage:link` to setup script — required for public disk access (uploads, avatars, cover art).
- **D-10:** No success message or URL output at end of setup — silent beyond what each command prints natively.

### Dev Runner
- **D-11:** Keep `queue:listen --tries=1` for the queue worker. Auto-restarts on code changes during development.
- **D-12:** Keep existing concurrently setup with colored output and named processes (server, queue, vite).

### Claude's Discretion
- Exact implementation of the PHP port detection script (argument parsing, port range scanning logic)
- How to handle edge case where no ports are available in a reasonable range
- Whether to use `@php` prefix in composer scripts for the detection step

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Existing Scripts
- `composer.json` (lines 44-56) — Current `setup` and `dev` script definitions
- `Makefile` — To be removed; reference for the complete install sequence with storage:link and sqlite touch

### Research
- `.planning/research/FEATURES.md` — Analysis of existing setup script gaps
- `.planning/research/PITFALLS.md` — Pitfall 12: storage:link fails on re-run (idempotency concern)
- `.planning/research/SUMMARY.md` — Phase 1 native setup recommendations and rationale
- `.planning/research/ARCHITECTURE.md` — Native install flow documentation

### Requirements
- `.planning/REQUIREMENTS.md` — NATV-01, NATV-02, NATV-03 requirements for this phase

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `composer.json` scripts section: Already has `setup` and `dev` scripts — need augmentation, not creation from scratch
- `concurrently` npm package: Already installed and configured for multi-process dev runner
- `.env.example`: Standard Laravel env template with SQLite defaults

### Established Patterns
- Composer scripts use `@php` prefix for artisan commands (e.g. `@php artisan key:generate`)
- `dev` script uses `Composer\Config::disableProcessTimeout` for long-running processes
- concurrently uses `-c` for colors and `--names` for process labels

### Integration Points
- `composer run setup` is the entry point called by future install scripts (Phase 19, 20)
- `composer run dev` is the primary development command referenced in future README (Phase 22)
- Port detection output may be consumed by health check verification (Phase 21)

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

*Phase: 17-native-foundation*
*Context gathered: 2026-03-26*
