# Phase 22: README Rewrite - Context

**Gathered:** 2026-03-27
**Status:** Ready for planning

<domain>
## Phase Boundary

Rewrite the project README.md with a clear project introduction and per-OS installation guides (macOS, Linux, Windows, Docker). Also create a CONTRIBUTING.md with development setup and PR guidelines. The README should get any user on any supported OS from clone to running app.

</domain>

<decisions>
## Implementation Decisions

### Document Structure
- **D-01:** Single README.md file — everything in one place, visible immediately on GitHub. No docs/ folder or wiki.
- **D-02:** Quick start first — brief project intro (1-2 sentences) then jump straight to installation. Features and details come after.
- **D-03:** Separate H2 sections for each install path — macOS, Linux, Windows, and Docker each get their own `##` section with full steps. Easy to link directly to your platform.

### Project Presentation
- **D-04:** Top of README includes: one-liner description, feature highlights bullet list, and tech stack badges (shields.io for Laravel, Livewire, PHP, license).
- **D-05:** No screenshot for now — text-only intro. Can be added later.

### Install Guide Depth
- **D-06:** Script-first with manual fallback for all native platforms. Lead with `./install.sh` (macOS/Linux) or `install.bat` (Windows) as the primary path. Include collapsed `<details>` sections with manual steps for users who prefer doing it themselves.
- **D-07:** Consistent pattern across macOS, Linux, and Windows — same structure (script command, what it does, manual fallback).
- **D-08:** Docker container path caveat uses GitHub `> [!IMPORTANT]` admonition block with example showing host path vs container path (`/music`). Hard to miss per DOCS-02.

### Content Beyond Install
- **D-09:** After install guides, include: Usage/Getting Started section (create admin, invite users, upload music, link folders), Configuration section (notable .env vars, theme, queue), Contributing section (links to CONTRIBUTING.md), and License section (MIT).
- **D-10:** Create a separate CONTRIBUTING.md with coding standards, PR guidelines, and development setup instructions.
- **D-11:** License is MIT.

### Carried Forward (locked from prior phases)
- **D-12:** `composer run setup` is the canonical native install command (Phase 17)
- **D-13:** `composer run dev` starts web server + Vite + queue worker (Phase 17)
- **D-14:** `install.sh` auto-detects and installs PHP 8.2+, Composer, Node.js, npm on macOS/Linux (Phase 19)
- **D-15:** `install.bat` wraps `install.ps1` with UAC auto-elevation; detects Herd PHP, falls back to Chocolatey (Phase 20)
- **D-16:** Docker uses `.env.docker.example` as standalone env file; `sail up -d` to start (Phase 18)
- **D-17:** `MUSIC_LIBRARY_PATH` env var drives Docker bind mount at `/music` — container paths only (Phase 18)
- **D-18:** `/healthz` endpoint verifies app + DB connectivity post-install (Phase 21)

### Claude's Discretion
- Exact wording and tone of documentation
- Badge selection and styling (shields.io variants)
- Feature highlights ordering and selection
- Manual steps detail level within `<details>` blocks
- Configuration section: which .env vars to highlight
- CONTRIBUTING.md structure and coding standards content

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Install Scripts (read for accurate documentation of what each does)
- `install.sh` — Bash install script for macOS/Linux; document its behavior and what it auto-installs
- `install.ps1` — PowerShell install script for Windows; document Herd PHP detection and Chocolatey flow
- `install.bat` — Windows wrapper with UAC auto-elevation; this is what users run

### Docker Configuration
- `compose.yaml` — Docker Compose config with services, healthchecks, music volume mount
- `.env.docker.example` — Standalone Docker env file; document how to use it
- `docker/start.sh` — Docker entrypoint script; reference for what happens on first run

### Native Setup
- `composer.json` (scripts section) — `setup` and `dev` script definitions
- `bin/detect-port.php` — Port conflict detection script

### Health Check
- `app/Http/Controllers/HealthCheckController.php` — `/healthz` endpoint implementation

### Requirements
- `.planning/REQUIREMENTS.md` — DOCS-01, DOCS-02 requirements for this phase

### Prior Phase Context
- `.planning/phases/17-native-foundation/17-CONTEXT.md` — Native install decisions
- `.planning/phases/18-docker-configuration/18-CONTEXT.md` — Docker setup decisions
- `.planning/phases/19-bash-install-script/19-CONTEXT.md` — Bash script decisions
- `.planning/phases/20-powershell-install-script/20-CONTEXT.md` — PowerShell script decisions
- `.planning/phases/21-health-check-endpoint/21-CONTEXT.md` — Health check decisions

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `install.sh`: Complete macOS/Linux install script with auto-dependency detection — README documents this as primary install path
- `install.ps1`/`install.bat`: Complete Windows install script with Herd PHP detection and Chocolatey — README documents install.bat as primary Windows path
- `compose.yaml`: Docker Compose with MySQL, Redis, queue worker, optional music mount — README documents `sail up -d` flow
- `.env.docker.example`: Standalone Docker env template — README references this for Docker setup
- `.env.example`: Native env template — manual steps reference this

### Established Patterns
- `composer run setup` is the canonical install command (idempotent, handles everything)
- `composer run dev` starts multi-process dev server (web + Vite + queue worker)
- Two-environment split: SQLite + database queue (native) vs MySQL + Redis (Docker)

### Integration Points
- README is the entry point for all installation paths built in Phases 17-21
- CONTRIBUTING.md will reference `composer run dev` for development setup
- License section requires LICENSE file (MIT) if not already present

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

*Phase: 22-readme-rewrite*
*Context gathered: 2026-03-27*
