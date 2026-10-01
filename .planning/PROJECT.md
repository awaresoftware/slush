# Spotify Clone — Personal Music Streaming

## What This Is

A personal audio streaming application where users can upload, organize, and stream their music collection. Features admin-controlled access with invite-based registration, user profiles, a full music library with ID3 metadata extraction, audio playback with queue management, and playlist creation. The UI is built entirely with Mary UI (daisyUI-based) components and Livewire 4.

## Core Value

Users can upload, organize, and stream their personal music collection.

## Current State

Shipped v1.3 Easy Installation (2026-03-29). 4 milestones complete, 22 phases, 52 plans.

The app is now installable and runnable with a single command on macOS, Linux, Windows, and Docker. Cross-platform install scripts detect missing dependencies, Docker Compose provides a batteries-included path with MySQL/Redis, and the README has per-OS guides.

## Requirements

### Validated

- AUTH-01: User can sign up with email and password — v1.0
- AUTH-02: User receives email verification after signup — v1.0
- AUTH-03: User can reset password via email link — v1.0
- AUTH-04: User session persists across browser refresh — v1.0
- PROF-01: User can create profile with display name — v1.0
- PROF-02: User can upload avatar image — v1.0
- PROF-03: User can write bio (max 500 chars) — v1.0
- PROF-04: User can view other users' profiles — v1.0
- MUSC-01: User can upload audio files (MP3, WAV, FLAC, M4A, AAC, OGG) — v1.0
- MUSC-02: User can organize music into albums — v1.0
- MUSC-03: User can view their uploaded music library — v1.0
- MUSC-04: User can search their own music collection — v1.0
- PLAY-01: User can play audio tracks — v1.0
- PLAY-02: User can pause/resume playback — v1.0
- PLAY-03: User can skip to next/previous track — v1.0
- PLAY-04: User can adjust volume — v1.0
- PLAY-05: User can see current playback position/time — v1.0
- PLST-01: User can create custom playlists — v1.0
- PLST-02: User can add/remove tracks from playlists — v1.0
- PLST-03: User can view their playlists — v1.0
- INFRA-01: Flux CSS replaced with Mary UI/daisyUI CSS system — v1.1
- INFRA-02: Flux directives removed from layouts — v1.1
- INFRA-03: Mary UI @source directive for Tailwind v4 scanning — v1.1
- LAYOUT-01: App layout shell uses Mary UI x-mary-main — v1.1
- LAYOUT-02: Sidebar navigation uses Mary UI menu items — v1.1
- LAYOUT-03: Header/navbar migrated to Mary UI — v1.1
- LAYOUT-04: User menu dropdown uses Mary UI — v1.1
- LAYOUT-05: Auth layout uses daisyUI card styling — v1.1
- PLAYER-01: Audio player icons use Mary UI x-mary-icon — v1.1
- PLAYER-02: Audio sliders use daisyUI range classes — v1.1
- PAGE-01: Library index page uses Mary UI components — v1.1
- PAGE-02: Album show page uses Mary UI components — v1.1
- PAGE-03: Upload summary page uses Mary UI components — v1.1
- PAGE-04: Playlist pages use Mary UI components — v1.1
- PAGE-05: Profile show page uses Mary UI components — v1.1
- MODAL-01: Modals use Livewire wire:model boolean pattern — v1.1
- MODAL-02: Add-to-playlist modal is single-instance with selectedTrackId — v1.1
- SETTINGS-01: Settings layout uses Mary UI navigation — v1.1
- SETTINGS-02: Settings profile page uses Mary UI — v1.1
- SETTINGS-03: Settings password page uses Mary UI — v1.1
- SETTINGS-04: Appearance theme toggle rebuilt without Flux — v1.1
- SETTINGS-05: 2FA and recovery codes pages use Mary UI — v1.1
- SETTINGS-06: Admin users page uses Mary UI — v1.1
- SETTINGS-07: Delete account page uses Mary UI — v1.1
- SETTINGS-08: Auth pages (confirm-password, 2FA challenge, verify-email) use Mary UI — v1.1
- CLEAN-01: livewire/flux package removed — v1.1
- CLEAN-02: Published Flux views deleted — v1.1
- CLEAN-03: All Flux-specific CSS/classes removed from views — v1.1
- CLEAN-04: All blade files verified Flux-free — v1.1
- FOLD-01: User can add a local folder path to link to their library — v1.2
- FOLD-02: App recursively scans linked folder discovering all audio files (MP3, WAV, FLAC, M4A, AAC, OGG) — v1.2
- FOLD-03: User can link multiple folders from different locations — v1.2
- FOLD-04: User can trigger a rescan on a specific linked folder — v1.2
- FOLD-05: User can see scan progress while folder is being processed — v1.2
- FOLD-06: User can remove a linked folder (tracks from that folder removed from library) — v1.2
- STRM-01: App serves linked audio files from absolute paths via authenticated route — v1.2
- STRM-02: Streaming supports HTTP Range requests for seeking — v1.2
- STRM-03: Streaming validates file ownership and path is within a registered linked folder — v1.2
- LIBR-01: Linked tracks appear in existing library alongside uploaded tracks — v1.2
- LIBR-02: Tracks display source badge indicating local-linked vs uploaded — v1.2
- LIBR-03: Linked tracks work with existing search, playlists, and queue — v1.2
- LIBR-04: Albums auto-created from folder structure or ID3 album tags — v1.2
- LIBR-05: Missing files detected and marked as unavailable with visual indicator — v1.2
- LIBR-06: User can re-link a moved file by updating its path — v1.2
- DATA-01: Track model supports source type (uploaded vs local-linked) — v1.2
- DATA-02: Absolute file paths stored and normalized (Windows backslash handling) — v1.2
- DATA-03: ID3 metadata extracted for linked files using existing pipeline — v1.2
- NATV-01: One-command native install via `composer run setup` — v1.3
- NATV-02: Port conflict detection with alternative suggestion — v1.3
- NATV-03: Queue worker auto-starts with `composer run dev` — v1.3
- INST-01: Bash install script for macOS/Linux with dependency detection — v1.3
- INST-02: PowerShell install script for Windows with Herd PHP probing — v1.3
- INST-03: Homebrew auto-install on macOS — v1.3
- INST-04: `.gitattributes` LF enforcement for shell scripts — v1.3
- DOCK-01: Docker Compose with MySQL, Redis, queue worker via Laravel Sail — v1.3
- DOCK-02: Standalone `.env.docker.example` — v1.3
- DOCK-03: Host music folder mounting via `MUSIC_LIBRARY_PATH` — v1.3
- DOCK-04: Health-gated service startup preventing race conditions — v1.3
- HLTH-01: `/healthz` endpoint with DB connectivity check — v1.3
- DOCS-01: Per-OS installation guides in README — v1.3
- DOCS-02: Docker container path caveat documentation — v1.3

### Active

(None — planning next milestone)

### Out of Scope

- Real-time chat — high complexity, not core to music streaming
- Video content — audio only
- Social media integration — keep simple
- Mobile app — web-first approach, PWA potential
- Offline mode — streaming is core value

## Context

Shipped v1.3 Easy Installation. 4 milestones, 22 phases, 52 plans, 83 files (+12,191/-113) in v1.3 alone.
Tech stack: Laravel 12, Livewire 4, Mary UI (daisyUI), Alpine.js, Tailwind CSS v4, Pest 4.
Audio processing: getid3 for ID3 metadata extraction via queued jobs.
Storage: Local disk with public URLs for audio, avatars (WebP), and cover art.
Theme: spotify-dark (daisyUI) default with light mode toggle (DB-backed per user).
Installation: Native (SQLite + database queue) or Docker (MySQL + Redis). Cross-platform install scripts for macOS, Linux, Windows.

Known tech debt: 3 pre-existing test failures (LibraryViewTest stale sort assertion, AuthPagesTest verify-email route disabled), 4 phases missing Nyquist validation.

## Constraints

- **Tech stack**: Laravel 12, PHP 8.4, Livewire 4, Mary UI (daisyUI)
- **Auth**: Admin-controlled — first user is admin, subsequent users via invite only
- **Storage**: Local filesystem (public disk)
- **Audio**: Client-side playback via HTML5 Audio API + Alpine.js

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Invite-based registration | Admin controls who can access | Good |
| Mary UI (daisyUI) | Full component library, better DX than Flux UI Free | Good — clean migration completed |
| x-mary- prefix | Mary UI config prefix avoids conflicts with other packages | Good |
| Livewire wire:model boolean modals | Replaces Flux named event pattern — cleaner, testable | Good — applied to all 4 modals |
| DB-backed theme toggle | User theme persists across sessions via users.theme column | Good |
| x-mary-pin + hidden input bridge | x-mary-pin doesn't render native form input; Alpine x-model bridges to hidden POST input | Good — solves 2FA challenge form |
| getid3 for metadata | PHP-native, no external deps | Good |
| Alpine.js audio player | Client-side playback, no server sync needed | Good — clean separation |
| WebP avatar conversion | Consistent format, smaller files | Good |
| withCount for stats | Single query, no N+1 | Good |
| nullOnDelete for album FK | Deleting album preserves tracks | Good |
| Queue jobs for metadata | Non-blocking uploads | Good |
| Dual-semantic file_path | Relative for uploads, absolute for local-linked | Good |
| Authenticated StreamController | GET /stream/{track} not symlinks — secure path validation | Good |
| cascadeOnDelete for linked_folder FK | Removing folder removes its tracks cleanly | Good |
| md5-based duplicate prevention | Prevents re-importing same file on rescan | Good |
| Batch folder scanning | Chunked job dispatch prevents queue worker OOM on large libraries | Good |
| Infinite scroll library | forPage() + Alpine trackIds eliminates pagination OOM on 20k+ tracks | Good |
| Two-environment split | SQLite + database queue native, MySQL + Redis Docker | Good |
| Standalone .env.docker.example | Complete file, not a diff from .env.example | Good |
| detect-port.php owns launch | Composer scripts can't pipe between entries; single PHP orchestrator | Good |
| install.bat UAC wrapper | Elevation-only bat, all logic in install.ps1 | Good |
| /healthz outside auth middleware | Exercises DB, not static response; 3s timeout | Good |
| README as single install doc source | No docs/ folder or wiki — one canonical location | Good |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd:transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd:complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-03-29 after v1.3 Easy Installation milestone*
