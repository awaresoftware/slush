# Phase 22: README Rewrite - Research

**Researched:** 2026-03-27
**Domain:** Technical documentation — README.md and CONTRIBUTING.md authoring
**Confidence:** HIGH

## Summary

This phase is pure documentation authoring. No new code is written. The planner's single task is to produce two files: `README.md` (the project root entry point) and `CONTRIBUTING.md` (developer guide). All technical facts to document already exist in the completed install infrastructure (Phases 17–21). Research involves reading every canonical source file to extract accurate, verified commands and then specifying the structure, tone, and content with enough precision that an implementer writes it correctly first time.

The `README.md` does not yet exist in this repository. `CONTRIBUTING.md` does not exist either. The root `LICENSE` file does not exist — creating it (MIT text) is a prerequisite for the License section and must be part of this plan. The `composer.json` lists `"license": "MIT"` confirming intent.

**Primary recommendation:** One plan (22-01) writes README.md, CONTRIBUTING.md, and LICENSE in a single pass using the verified command inventory below. No new code, no test suite.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01:** Single README.md file — everything in one place, visible immediately on GitHub. No docs/ folder or wiki.
- **D-02:** Quick start first — brief project intro (1-2 sentences) then jump straight to installation. Features and details come after.
- **D-03:** Separate H2 sections for each install path — macOS, Linux, Windows, and Docker each get their own `##` section with full steps. Easy to link directly to your platform.
- **D-04:** Top of README includes: one-liner description, feature highlights bullet list, and tech stack badges (shields.io for Laravel, Livewire, PHP, license).
- **D-05:** No screenshot for now — text-only intro. Can be added later.
- **D-06:** Script-first with manual fallback for all native platforms. Lead with `./install.sh` (macOS/Linux) or `install.bat` (Windows) as the primary path. Include collapsed `<details>` sections with manual steps for users who prefer doing it themselves.
- **D-07:** Consistent pattern across macOS, Linux, and Windows — same structure (script command, what it does, manual fallback).
- **D-08:** Docker container path caveat uses GitHub `> [!IMPORTANT]` admonition block with example showing host path vs container path (`/music`). Hard to miss per DOCS-02.
- **D-09:** After install guides, include: Usage/Getting Started section (create admin, invite users, upload music, link folders), Configuration section (notable .env vars, theme, queue), Contributing section (links to CONTRIBUTING.md), and License section (MIT).
- **D-10:** Create a separate CONTRIBUTING.md with coding standards, PR guidelines, and development setup instructions.
- **D-11:** License is MIT.
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

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DOCS-01 | README has separate installation sections for macOS, Linux, Windows, and Docker with step-by-step guides | Verified exact commands for each platform from canonical source files |
| DOCS-02 | Docker documentation prominently explains that linked folder paths must use container paths (e.g. `/music`) not host paths | D-08 mandates GitHub `> [!IMPORTANT]` admonition; `compose.music.yaml` confirms container mount at `/music` |
</phase_requirements>

---

## Verified Command Inventory

These are the exact commands and facts to document. All verified from source files.

### macOS / Linux (install.sh)

**Confidence: HIGH** — read directly from `install.sh`

| Fact | Value |
|------|-------|
| Primary install command | `./install.sh` |
| What it does | Detects OS, checks Homebrew (macOS) or apt/dnf/pacman (Linux), installs PHP 8.2+/Composer/Node/npm if missing, then runs `composer run setup` |
| macOS dependency manager | Homebrew — prompted to install if missing; not auto-installed |
| Linux package managers | apt (Debian/Ubuntu), dnf (Fedora/RHEL), pacman (Arch) |
| PHP minimum | 8.2 |
| On completion | Offers to run `composer run dev` |
| Health check hint | `curl http://localhost:8000/healthz` shown post-setup |

**Manual fallback steps (for `<details>` block):**
```bash
# Prerequisites: PHP 8.2+, Composer, Node.js
git clone <repo-url> foonk
cd foonk
composer run setup
composer run dev
```

### Windows (install.bat / install.ps1)

**Confidence: HIGH** — read directly from `install.bat` and `install.ps1`

| Fact | Value |
|------|-------|
| Primary install command | `install.bat` (double-click or run from Command Prompt) |
| UAC elevation | `install.bat` auto-requests admin via `net session` check + `-Verb RunAs` |
| PHP detection order | 1. Probes `%USERPROFILE%\.config\herd\bin\php84/83/82\php.exe` (Laravel Herd), 2. Falls back to Chocolatey |
| Chocolatey | Auto-installed if needed |
| PATH refresh | `chocolateyProfile.psm1 Update-SessionEnvironment` or registry merge fallback |
| On completion | Offers to run `composer run dev` |
| Health check hint | `curl http://localhost:8000/healthz` shown post-setup |

**Manual fallback steps (for `<details>` block):**
```powershell
# Prerequisites: PHP 8.2+, Composer, Node.js (via Chocolatey or Herd)
git clone <repo-url> foonk
cd foonk
composer run setup
composer run dev
```

### Docker

**Confidence: HIGH** — read directly from `compose.yaml`, `.env.docker.example`, `docker/start.sh`, and `compose.music.yaml`

| Fact | Value |
|------|-------|
| Env file | Copy `.env.docker.example` to `.env` (standalone — do NOT merge with `.env.example`) |
| Start command | `./vendor/bin/sail up -d` |
| Services | `laravel.test` (PHP 8.4 + supervisord), `mysql` (8.4), `redis` (alpine), `worker` (queue:work) |
| Default web port | 80 (configurable via `APP_PORT` in `.env`) |
| Startup sequence | `docker/start.sh` runs key:generate (if missing), migrate, npm install + build (first run only), storage:link, then supervisord |
| Health check | `curl http://localhost/healthz` (port 80 default) |
| Linux UID/GID | `WWWUSER=1000 WWWGROUP=1000` (change if different; Windows may need `0`) |

**Docker music mount (for `> [!IMPORTANT]` admonition block):**
```bash
# Step 1: Set MUSIC_LIBRARY_PATH in .env to your HOST folder
MUSIC_LIBRARY_PATH=/Users/you/Music

# Step 2: Copy the override file
cp compose.music.yaml compose.override.yaml

# Step 3: Restart Sail
./vendor/bin/sail up -d

# Step 4: In the app, link folders using CONTAINER paths only
# CORRECT:   /music/subfolder
# INCORRECT: /Users/you/Music/subfolder
```

The `compose.music.yaml` mounts `${MUSIC_LIBRARY_PATH}:/music:ro` into both `laravel.test` and `worker` services.

### `composer run setup` (what it does)

**Confidence: HIGH** — verified from `composer.json` scripts section

Steps in order:
1. `composer install`
2. Copy `.env.example` to `.env` if not present
3. Touch `database/database.sqlite` if not present
4. Generate `APP_KEY` if not set (guarded — skips if already `base64:`)
5. `php artisan migrate --force`
6. `npm install`
7. `npm run build`
8. `php artisan storage:link --force`

### `composer run dev` (what it does)

**Confidence: HIGH** — verified from `composer.json` and `bin/detect-port.php`

- Runs `bin/detect-port.php`
- Probes starting from port 8000, finds first free port
- Launches PHP built-in server + Vite (with `--port=N`) + queue worker concurrently via `passthru()`
- Prints the actual URL when a port conflict moves it (e.g., `http://127.0.0.1:8001`)

### Health Check

**Confidence: HIGH** — verified from `HealthCheckController.php`

`GET /healthz` returns JSON:
```json
{
  "status": "ok",
  "version": null,
  "php": "8.4.x",
  "database": { "status": "ok", "latency_ms": 2 }
}
```
HTTP 200 on success, 503 on DB failure. 3-second DB timeout. No authentication required.

---

## Architecture Patterns

### README Document Structure

```
README.md
├── H1: Foonk
│   ├── Shields.io badges (Laravel, Livewire, PHP 8.2+, MIT License)
│   └── One-liner description
├── Feature highlights (bullet list)
├── ## Installation
│   └── Jump links: macOS | Linux | Windows | Docker
├── ## macOS
│   ├── Primary: ./install.sh
│   ├── What it installs (Homebrew + PHP/Composer/Node)
│   └── <details>Manual steps</details>
├── ## Linux
│   ├── Primary: ./install.sh
│   ├── Supported distros (Debian/Ubuntu/Fedora/Arch)
│   └── <details>Manual steps</details>
├── ## Windows
│   ├── Primary: install.bat (double-click)
│   ├── Herd PHP detection + Chocolatey fallback note
│   └── <details>Manual steps</details>
├── ## Docker
│   ├── Prerequisites (Docker Desktop)
│   ├── Steps: copy .env.docker.example → .env, sail up -d
│   ├── > [!IMPORTANT] container path caveat (DOCS-02)
│   └── Music library mounting sub-section
├── ## Getting Started
│   └── Create admin, invite users, upload music, link folders
├── ## Configuration
│   └── Notable .env vars: APP_DEBUG, APP_URL, MUSIC_LIBRARY_PATH, SCAN_BATCH_SIZE
├── ## Contributing
│   └── Link to CONTRIBUTING.md
└── ## License
    └── MIT
```

### CONTRIBUTING.md Document Structure

```
CONTRIBUTING.md
├── Development Setup
│   ├── Prerequisites
│   ├── composer run setup
│   └── composer run dev
├── Tech Stack overview
├── Running Tests (composer run test / php artisan test --compact)
├── Code Style (vendor/bin/pint --dirty)
├── PR Guidelines
└── Project conventions note
```

### Shields.io Badges

**Confidence: MEDIUM** — standard GitHub README pattern; shields.io badge URLs are stable

| Badge | Shield URL pattern |
|-------|--------------------|
| Laravel | `https://img.shields.io/badge/Laravel-12-FF2D20?style=flat&logo=laravel` |
| Livewire | `https://img.shields.io/badge/Livewire-4-FB70A9?style=flat&logo=livewire` |
| PHP | `https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php` |
| License | `https://img.shields.io/badge/License-MIT-blue?style=flat` |

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Docker path warning visibility | Custom HTML/CSS | GitHub `> [!IMPORTANT]` admonition | Renders natively on GitHub, no HTML needed |
| Manual step collapsing | Custom JS | `<details><summary>` HTML | Standard GitHub Markdown support |
| Tech stack badges | Custom images | shields.io | Industry standard, always up to date |

---

## Common Pitfalls

### Pitfall 1: Stale or invented commands
**What goes wrong:** README documents commands that differ from what the scripts actually do (e.g., `./install.sh --setup` instead of just `./install.sh`, or `sail artisan migrate` when Docker does this automatically).
**Why it happens:** Writing from memory rather than reading source files.
**How to avoid:** Every command in the README must be traced to a specific line in the canonical source files above. This research document contains the verified inventory.
**Warning signs:** Any command not found in the Verified Command Inventory section above.

### Pitfall 2: Mixing Docker and native .env instructions
**What goes wrong:** Telling Docker users to run `composer run setup`, or native users to copy `.env.docker.example`.
**Why it happens:** Two-environment split is not obvious.
**How to avoid:** Each install section is fully self-contained. Docker section explicitly says "copy `.env.docker.example` to `.env`" and NOT `.env.example`. Native section says `composer run setup` handles `.env` automatically.

### Pitfall 3: DOCS-02 container path caveat buried or weak
**What goes wrong:** The `/music` path caveat appears in a small note that users miss, then link host paths in the app and find nothing.
**Why it happens:** Easy to relegate important warnings to footnotes.
**How to avoid:** D-08 mandates `> [!IMPORTANT]` admonition in the Docker section. The admonition must include a before/after example (host path vs container path) as specified in CONTEXT.md.

### Pitfall 4: Windows instructions don't mention UAC
**What goes wrong:** User double-clicks `install.bat`, UAC prompt appears with no context, user cancels.
**Why it happens:** UAC prompt is unexpected.
**How to avoid:** macOS/Linux section should note that `./install.sh` may prompt for sudo (for package installs). Windows section should mention the UAC prompt is expected and required.

### Pitfall 5: Forgetting the LICENSE file
**What goes wrong:** License section in README references MIT but no `LICENSE` file exists in the repo root — shields.io license badge may be inaccurate, and GitHub won't show "MIT" on repo page.
**Why it happens:** LICENSE file does not currently exist (verified).
**How to avoid:** Plan 22-01 must create `LICENSE` with standard MIT text in addition to README.md and CONTRIBUTING.md.

### Pitfall 6: Health check URL differs between native and Docker
**What goes wrong:** Both sections use `http://localhost:8000/healthz` but Docker's default port is 80, not 8000.
**Why it happens:** Copy-paste from native section.
**How to avoid:** Native health check: `http://localhost:8000/healthz`. Docker health check: `http://localhost/healthz` (port 80, matching `APP_PORT=80` in `.env.docker.example`).

---

## Files Created / Modified

| File | Status | Action |
|------|--------|--------|
| `README.md` | Does not exist | Create |
| `CONTRIBUTING.md` | Does not exist | Create |
| `LICENSE` | Does not exist | Create (MIT text) |

No PHP, Blade, or test files are modified. No `composer run pint` needed (no PHP).

---

## Validation Architecture

No automated tests cover documentation content. This phase is documentation-only.

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| DOCS-01 | README has macOS/Linux/Windows/Docker sections | manual | N/A — visual review of rendered Markdown | N/A |
| DOCS-02 | Docker section has `> [!IMPORTANT]` container path block | manual | N/A — visual review of rendered Markdown | N/A |

### Manual Verification Checklist

- [ ] README.md contains `## macOS`, `## Linux`, `## Windows`, `## Docker` H2 headings
- [ ] Each platform section has the correct primary command
- [ ] Docker section contains `> [!IMPORTANT]` admonition with container path example
- [ ] `compose.music.yaml` copy step is documented in Docker section
- [ ] Health check URL is port 8000 for native, port 80 for Docker
- [ ] `LICENSE` file exists in repo root with MIT text
- [ ] CONTRIBUTING.md includes `composer run setup` and `composer run dev` instructions
- [ ] No commands appear that are not in the Verified Command Inventory

### Sampling Rate

- **Per task commit:** Manual spot-check: headings present, `> [!IMPORTANT]` admonition visible
- **Phase gate:** Full manual checklist above before `/gsd:verify-work`

### Wave 0 Gaps

None — no test infrastructure needed for documentation phase.

---

## Sources

### Primary (HIGH confidence)

- `install.sh` — Verified: macOS/Linux flow, Homebrew prompting, package managers, minimum PHP 8.2, `composer run setup` delegation, health check hint
- `install.ps1` — Verified: Herd PHP probing order, Chocolatey fallback, PATH refresh, `composer run dev` offer
- `install.bat` — Verified: UAC elevation pattern, no-admin re-launch via `-Verb RunAs`
- `compose.yaml` — Verified: services (laravel.test, mysql 8.4, redis, worker), ports (80, 3306, 6379), Docker Compose v2 no `version:` header
- `.env.docker.example` — Verified: standalone env, `APP_PORT=80`, `MUSIC_LIBRARY_PATH` instruction, `WWWUSER`/`WWWGROUP`
- `compose.music.yaml` — Verified: `${MUSIC_LIBRARY_PATH}:/music:ro` on both app and worker services, 3-step enable process
- `docker/start.sh` — Verified: key:generate guard, migrate --force, npm build (first run only), storage:link, supervisord handoff
- `composer.json` scripts section — Verified: full `setup` and `dev` sequences, `"license": "MIT"`
- `app/Http/Controllers/HealthCheckController.php` — Verified: JSON response shape, 200/503 HTTP codes, no auth required

### Secondary (MEDIUM confidence)

- shields.io badge URL patterns — standard community convention, stable service

### Tertiary (LOW confidence)

None.

---

## Metadata

**Confidence breakdown:**
- Verified command inventory: HIGH — all commands read directly from source files
- Document structure: HIGH — derived from locked decisions in CONTEXT.md
- Shields.io badges: MEDIUM — stable community convention, not verified against shields.io API
- Pitfalls: HIGH — derived from actual source file behaviors and known env split

**Research date:** 2026-03-27
**Valid until:** Stable — this phase depends only on existing source files that are locked (phases 17-21 complete). No expiry concern.
