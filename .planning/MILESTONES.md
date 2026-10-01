# Milestones

## v1.3 Easy Installation (Shipped: 2026-03-29)

**Phases completed:** 6 phases, 9 plans, 19 tasks

**Key accomplishments:**

- Idempotent `composer run setup` with 8-step sequence: SQLite creation, conditional APP_KEY guard, npm build, and storage:link — Makefile removed
- Dynamic port detection via fsockopen in bin/detect-port.php, replacing static concurrently command with auto-resolving web (8000+) and Vite (5173+) ports
- Docker Compose config with 4 services (web/worker/mysql/redis), healthchecks with start_period guard, and entrypoint script handling first-run migrations and conditional npm build
- .env.docker.example standalone file with MySQL/Redis Docker service config and .gitignore exclusion for compose.override.yaml
- compose.music.yaml override template ships in repo, mounting MUSIC_LIBRARY_PATH to /music:ro for laravel.test and worker; all 28 DockerConfigurationTest assertions pass across DOCK-01 through DOCK-04
- Bash install.sh for macOS (Homebrew) and Linux (apt/dnf/pacman) with PHP 8.2+ enforcement, auto-install of missing deps, and delegation to composer run setup
- Windows install experience with UAC-auto-elevating install.bat, Chocolatey-backed dependency detection, Herd PHP path probing, and 19 Pest assertion tests covering install.ps1/install.bat content and .gitattributes CRLF rules
- GET /healthz endpoint returning JSON health status with 3-second DB timeout (D-05), Docker compose web healthcheck, and install script verification hints
- README.md with per-OS installation guides (macOS/Linux/Windows/Docker), CONTRIBUTING.md with development setup, and MIT LICENSE — completing v1.3 Easy Installation documentation

---

## v1.2 Local Library Linking (Shipped: 2026-03-26)

**Phases completed:** 5 phases, 13 plans, 16 tasks

**Timeline:** 3 days (2026-03-18 to 2026-03-20)
**Files modified:** 143 | **Lines:** +15,226 / -906
**Git range:** `b813239..560ef50`

**Key accomplishments:**

- Data foundation with SourceType/FolderStatus enums, LinkedFolder model, and dual-semantic Track paths with normalisation
- Authenticated streaming endpoint at GET /stream/{track} with Range/206, ETag, and path traversal protection
- Queued ScanLinkedFolder job with recursive directory scanning, md5 duplicate prevention, and batch processing for large libraries
- Folder linking UI with source badges, source filter, linked folder management settings, and server-side directory browser
- Missing file detection with unavailable states, re-link modal, bulk folder path updates, and rescan toast notifications
- Performance optimizations: infinite scroll library, batch folder scanning, queue worker memory management (9 quick tasks)

**Delivered:** Users can link local filesystem folders to their library with recursive scanning, ID3 metadata extraction, authenticated streaming, missing file detection, and re-linking — alongside the existing upload flow.

---

## v1.1 Mary UI Migration (Shipped: 2026-03-18)

**Phases completed:** 5 phases, 15 plans, 0 tasks

**Timeline:** 6 days (2026-03-13 to 2026-03-18)
**Files modified:** 58 | **Lines:** +1,506 / -1,108
**Git range:** `ba00ef8..6165e9d`

**Key accomplishments:**

- Replaced Flux CSS system with Mary UI/daisyUI — clean app.css with @source directive
- Migrated app shell layout (sidebar, nav, user menu, mobile bottom nav) to Mary UI components
- Rebuilt audio player with Mary UI icons and daisyUI range sliders
- Created Livewire modal pattern (wire:model boolean) replacing Flux named events — applied to 4 modals
- Migrated all 5 content pages and 8 settings pages to Mary UI with zero Flux components remaining
- Removed livewire/flux package entirely — Symfony Finder blade audit confirms Flux-free state

**Delivered:** Complete UI migration from Flux UI Free to Mary UI (daisyUI). Every component, layout, modal, and form across all authenticated views now uses Mary UI exclusively.

---

## v1.0 MVP (Shipped: 2026-03-12)

**Phases completed:** 6 phases, 15 plans
**Timeline:** 8 days (2026-03-04 to 2026-03-12)
**Files modified:** 127 | **Lines of code:** 8,521 PHP/Blade
**Git range:** `1b7f85e..0efb0aa`

**Key accomplishments:**

- Admin-controlled auth system with invite-based registration and email verification
- User profiles with avatar upload (WebP conversion), bio, and public profile pages
- Music library with batch audio upload, async ID3 metadata extraction, album organization
- Full audio player with play/pause, skip, volume control, and position tracking
- Playlist creation and management with track add/remove and player integration
- Profile UX polish with nav links and live stat counts (tracks, albums, playlists)

**Delivered:** Users can upload, organize, and stream their personal music collection with full authentication, profiles, library management, audio playback, and playlists.

---
