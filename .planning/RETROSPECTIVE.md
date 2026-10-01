# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v1.3 — Easy Installation

**Shipped:** 2026-03-29
**Phases:** 6 | **Plans:** 9

### What Was Built
- One-command native install (`composer run setup`) with idempotent 8-step sequence and Makefile removal
- Dynamic port conflict detection (`bin/detect-port.php`) with auto-resolving web (8000+) and Vite (5173+) ports
- Docker Compose with 4 services (web, MySQL, Redis, queue worker), health-gated startup, and music volume mounting
- Bash install script for macOS (Homebrew) and Linux (apt/dnf/pacman) with PHP 8.2+ enforcement
- PowerShell install script for Windows with Herd PHP probing, Chocolatey auto-install, and UAC elevation wrapper
- `/healthz` health check endpoint with 3-second DB timeout for install verification
- README rewrite with per-OS guides, CONTRIBUTING.md, and MIT LICENSE

### What Worked
- Two-environment split (SQLite native vs MySQL Docker) kept both paths simple — no single .env file trying to serve two masters
- Standalone `.env.docker.example` as a complete file eliminated confusion about which values to copy from `.env.example`
- Health-gated Docker services (`depends_on: condition: service_healthy`) solved first-boot race conditions cleanly
- Pest tests asserting file content (install.sh, install.ps1, compose.yaml) caught regressions in non-PHP files effectively
- Cross-platform patterns (PHP constants for version checks instead of `sort -V`, `maybe_sudo` helper, `passthru()` over `pcntl_exec`) avoided platform-specific failures

### What Was Inefficient
- No milestone audit was run before completion — skipped formal gap verification
- PowerShell Windows detection (Herd PHP vs PATH PHP, Chocolatey PATH refresh) has MEDIUM confidence — not tested on real Windows
- Phase plan checkboxes in archived ROADMAP.md were inconsistent — some marked `[x]`, some `[ ]` despite all being complete
- `detect-port.php` ended up owning the full launch sequence because Composer scripts can't pipe between entries — this wasn't obvious until implementation

### Patterns Established
- `detect-port.php` single-orchestrator pattern for Composer dev scripts
- `argv` guard (realpath check) allowing CLI scripts to be `require`'d by tests without executing
- `maybe_sudo` wrapper for all package manager calls in install scripts
- `Find-HerdPhp` descending version probe for Windows PHP discovery
- `ini_set` + `finally` block pattern for temporary PHP config overrides (DB timeout)
- GitHub `[!IMPORTANT]` admonition for container path caveats in README

### Key Lessons
1. Standalone `.env.example` files per environment are cleaner than a single file with inline comments about which values to change
2. PHP is an effective cross-platform scripting language for launch orchestration — avoids shell compatibility issues entirely
3. Install scripts should use deferred hints (print instructions) rather than live verification (curl) for services not yet running
4. `start_period` in Docker healthchecks is critical for services that have slow first-boot initialization (MySQL, npm build)

### Cost Observations
- Model mix: Balanced profile (opus for planning, sonnet for execution)
- Timeline: 4 days from first commit to shipped milestone (fastest milestone yet)
- Notable: 9 plans across 6 phases — average 1.5 plans per phase, leanest density yet

---

## Milestone: v1.2 — Local Library Linking

**Shipped:** 2026-03-26
**Phases:** 5 | **Plans:** 13

### What Was Built
- Data foundation with SourceType/FolderStatus enums, LinkedFolder model, and dual-semantic Track paths
- Authenticated streaming endpoint with Range/206, ETag, and path traversal protection
- Queued recursive folder scanning with md5 duplicate prevention and batch processing
- Folder linking settings UI with source badges, source filter, and server-side directory browser
- Missing file detection with unavailable states, re-link modal, and bulk folder path updates
- 9 quick tasks: sidebar fix, wire:navigate audit, album numbering, mobile touch, infinite scroll, artists tab, batch scanning, job timeout, directory browser

### What Worked
- Phased approach (data → streaming → scanning → UI → polish) kept dependencies clean
- Reusing existing ExtractAudioMetadata job with SourceType branching avoided duplicate code
- Quick task workflow proved effective for post-milestone polish (9 tasks shipped rapidly)
- Batch scanning design (quick task 7) solved real production OOM before it became a blocker
- Alpine trackIds-per-table-wrapper pattern solved 20k+ track rendering OOM elegantly

### What Was Inefficient
- Some SUMMARY.md one-liners were truncated or malformed ("Route:", "One-liner:") — extraction quality degraded
- Phase 16 had 4 plans including 2 gap-closure plans — better upfront design of Phase 15 could have avoided rework
- Quick tasks 5-8 were all performance/stability fixes for issues that could have been caught with load testing during Phase 14-15
- v1.2 MILESTONES.md entry was initially created with "(none recorded)" for accomplishments — required manual completion

### Patterns Established
- Dual-semantic `file_path` column (relative for uploads, absolute for local) with model mutator normalisation
- `Gate::authorize()` pattern (no AuthorizesRequests trait on base Controller)
- `pollFolders` + `scanningFolderIds` array for testable async scan completion detection
- `forPage()` + Alpine `tracks-loaded` browser event for infinite scroll without full Livewire re-render
- `extendQueue` deduplication via `array_diff` before appending to queue
- Server-side directory browser with Alpine.js modal for folder path selection

### Key Lessons
1. Performance testing with realistic data volumes (20k+ tracks) should happen during implementation, not as post-milestone quick tasks
2. Quick tasks are excellent for rapid iteration on shipped features — they bypass heavy planning overhead for small changes
3. Batch processing patterns should be the default for any job that processes unbounded collections (folders, tracks)
4. The dual-semantic column approach works well when the two semantics share the same data type and context is always clear

### Cost Observations
- Model mix: Balanced profile (opus for planning, sonnet for execution)
- Timeline: 3 days from first commit to shipped milestone (fastest yet)
- Notable: 13 plans across 5 phases plus 9 quick tasks — quick tasks nearly doubled the effective plan count

---

## Milestone: v1.1 — Mary UI Migration

**Shipped:** 2026-03-18
**Phases:** 5 | **Plans:** 15

### What Was Built
- Replaced entire Flux UI Free component library with Mary UI (daisyUI) across all views
- Migrated app shell layout (sidebar, nav, user menu, mobile bottom nav) to Mary UI components
- Rebuilt audio player icons and sliders with Mary UI/daisyUI equivalents
- Created Livewire wire:model boolean modal pattern, applied to 4 modals (add-to-playlist, upload, delete account, 2FA setup)
- Migrated all 5 content pages and 8 settings pages including DB-backed theme toggle
- Removed livewire/flux package entirely with Symfony Finder blade audit as regression guard

### What Worked
- Phased migration approach (CSS first, then layout, then pages, then cleanup) prevented cascading breakage
- Intentionally deferring Flux directive removal to final phase kept intermediate states stable
- Wave 0 RED test stubs (Phase 10) validated test infrastructure before implementation began
- UAT sessions caught real issues: missing upload button in sidebar, album track sort order broken
- Integration checker caught stale LibraryViewTest assertion that was a direct consequence of Phase 9 changes

### What Was Inefficient
- INFRA-01 and INFRA-02 were marked complete in REQUIREMENTS.md during Phase 7 but actual completion spanned to Phase 11 — requirements labeling was premature
- Phase 9 had 4 plans (09-01 through 09-04) — 09-04 was a gap-closure plan that could have been avoided if 09-02 had included the sidebar upload button and track_number sort
- 4 of 5 phases have incomplete Nyquist validation — test coverage backfill was deprioritized during execution
- AuthPagesTest tests for verify-email route were written but Fortify emailVerification() is intentionally disabled — test should have been skipped from the start

### Patterns Established
- `x-mary-` prefix convention for all Mary UI components (via config/mary.php)
- Livewire `wire:model` boolean + `closeModal()` pattern for all modals
- `#[On('event-name')]` + `$dispatch('event-name')` pattern for cross-component communication
- Alpine `x-effect` for live DOM mutations (theme toggle data-theme attribute)
- `x-mary-pin` + hidden input `x-model` bridge for OTP POST forms
- Symfony Finder recursive blade audit in Pest tests for package-free verification
- `updatedPropertyName()` Livewire lifecycle hook for immediate DB persistence

### Key Lessons
1. Migration milestones benefit from a "cleanup phase" at the end — Phase 11 caught residual references that individual phase verifications missed
2. UAT catches integration gaps that automated verification misses (upload button reachability, sort order affecting play behavior)
3. Wave 0 RED test stubs are effective for settings-heavy phases — they validate test infrastructure before code exists
4. Requirements should only be marked complete when their literal text is fully satisfied, not when the phase's scoped portion is done

### Cost Observations
- Model mix: Balanced profile (opus/inherit for planning, sonnet for execution/checking)
- Timeline: 6 days from first commit to shipped milestone
- Notable: 15 plans across 5 phases — average 3 plans per phase, slightly denser than v1.0

---

## Milestone: v1.0 — MVP

**Shipped:** 2026-03-12
**Phases:** 6 | **Plans:** 15

### What Was Built
- Admin-controlled auth system with invite-based registration and email verification
- User profiles with avatar upload (WebP), bio, and public profile pages
- Music library with batch upload, async ID3 extraction, album organization, tabbed view, search
- Full audio player with play/pause, skip, volume, position tracking, queue management
- Playlist CRUD with track management and player integration
- Profile UX polish with nav links and live stat counts

### What Worked
- Phase-based planning kept scope clear and commits atomic
- Flux UI Free provided solid components; Alpine.js filled gaps for Pro-only features (tabs, tables)
- withCount() pattern for aggregate stats avoided N+1 queries cleanly
- Queue jobs for metadata extraction kept uploads non-blocking
- UAT sessions caught real issues before milestone close (PROF-04 gaps)

### What Was Inefficient
- Phase 6 was created solely to close audit gaps (nav link + stat count) — these could have been caught during Phase 2 if profile completeness was validated earlier
- Roadmap table format got corrupted during execution (column misalignment) — tooling didn't validate format
- Some plans had overlapping file modifications requiring careful merge ordering

### Patterns Established
- `Model::url()` helper methods as single source of truth for asset URLs (avatarUrl, audioUrl, coverArtUrl)
- Alpine.js + Livewire event bridge for client-side interactivity (audio player, tabs)
- `abort_unless()` inline authorization pattern for simple ownership checks
- WebP conversion for uploaded images (consistent format, smaller files)
- Queue jobs for heavy processing (ID3 extraction)

### Key Lessons
1. Milestone audits are valuable — the v1.0 audit caught real gaps (no nav link to profiles, hardcoded counts) that would have shipped broken
2. Flux UI Free is sufficient for MVP but requires Alpine.js workarounds for Pro-only components (tabs, tables)
3. Early phases should validate UX completeness (reachability, counts) not just feature existence

### Cost Observations
- Model mix: Balanced profile (opus for planning, sonnet for execution/checking)
- Timeline: 8 days from first commit to shipped milestone
- Notable: 15 plans across 6 phases — average ~2.5 plans per phase

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Phases | Plans | Key Change |
|-----------|--------|-------|------------|
| v1.0 | 6 | 15 | Initial process — phase planning, audit, gap closure |
| v1.1 | 5 | 15 | Migration workflow — phased component swap, Wave 0 test stubs, blade audits |
| v1.2 | 5 | 13 | Feature development — quick tasks for rapid post-milestone polish (9 tasks) |
| v1.3 | 6 | 9 | DevOps/installation — cross-platform scripts, Docker, health checks, documentation |

### Cumulative Quality

| Milestone | LOC Delta | Files | Requirements |
|-----------|-----------|-------|-------------|
| v1.0 | +8,521 | 127 | 21/21 validated |
| v1.1 | +1,506/-1,108 | 58 | 29/29 validated |
| v1.2 | +15,226/-906 | 143 | 18/18 validated |
| v1.3 | +12,191/-113 | 83 | 14/14 validated |

### Top Lessons (Verified Across Milestones)

1. Milestone audits catch integration gaps that phase-level testing misses
2. Plan atomic commits per task — keeps git history clean and reversible
3. UAT sessions with real user testing catch reachability and interaction gaps that code inspection misses
4. Cleanup/removal phases at the end of migration milestones are worth the overhead
5. Performance testing with realistic data volumes should happen during implementation, not post-milestone
6. Quick tasks are effective for rapid iteration on shipped features without heavy planning overhead
7. Standalone env files per environment are cleaner than a single file with conditional comments
8. PHP is an effective cross-platform scripting language for launch orchestration — avoids shell compatibility issues
