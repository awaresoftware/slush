---
phase: 16-missing-file-detection-polish
plan: 03
subsystem: livewire
tags: [livewire, modal, relink, file-path, bulk-update, settings]

# Dependency graph
requires:
  - phase: 16-01
    provides: is_available column on tracks, unavailable_count on linked_folders
provides:
  - RelinkTrackModal Livewire component for individual track re-linking
  - Bulk folder path update in linked folders settings
  - Rescan completion toast with unavailable count
  - Unavailable count badge on folder rows in settings
affects:
  - users recovering from moved or renamed audio files

# Tech tracking
tech-stack:
  added: []
  patterns:
    - RelinkTrackModal follows AddToPlaylistModal pattern (On event, modal show/hide, Toast trait)
    - Hash mismatch warning requires explicit confirm step before proceeding
    - Bulk path update uses substr with old prefix length for reliable replacement
    - pollFolders + scanningFolderIds tracks scan completion for toast notification
    - setScanning method enables testability of poll-based scan completion detection

key-files:
  created:
    - app/Livewire/RelinkTrackModal.php
    - resources/views/livewire/relink-track-modal.blade.php
    - tests/Feature/Livewire/RelinkTrackModalTest.php
  modified:
    - resources/views/layouts/app/sidebar.blade.php
    - resources/views/pages/settings/⚡linked-folders.blade.php
    - tests/Feature/Settings/LinkedFoldersSettingsTest.php

key-decisions:
  - "openForTrack silently returns (no modal open) when track ownership fails — no error leaked to other users"
  - "confirmMismatch sets confirmedMismatch=true then calls relink() directly — avoids duplicate validation"
  - "pollFolders driven by scanningFolderIds array — allows testing toast without real background jobs"
  - "setScanning public method added to support testing poll completion; not harmful as it only sets state"
  - "updateFolderPath dispatches ScanLinkedFolder after path update — rescans to refresh is_available from new paths"

# Metrics
duration: 35min
completed: 2026-03-19
---

# Phase 16 Plan 03: RelinkTrackModal + Bulk Path Update Summary

**RelinkTrackModal component with hash mismatch warning, bulk folder path update in settings, rescan completion toast with unavailable count**

## Performance

- **Duration:** 35 min
- **Started:** 2026-03-19
- **Completed:** 2026-03-19
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments

- RelinkTrackModal Livewire component: opens via `open-relink-modal` event, validates file existence, hash mismatch warning with confirm step, updates track file_path + file_hash + is_available on success
- Modal view with old path display, new path input, hash warning UI, Re-link Anyway button
- RelinkTrackModal included in app sidebar layout alongside AddToPlaylistModal
- Bulk folder path update: openUpdatePathModal/updateFolderPath methods with dir validation, prefix replacement on all tracks, is_available set based on file_exists at new path
- Rescan completion toast: pollFolders method detects when scanning folders become active, dispatches toast with "Folder scanned: X tracks found, Y unavailable" format
- Unavailable count badge shown on folder rows when unavailable_count > 0
- Update Path modal in settings with old path display, new path input, validation
- 22 tests pass (6 for RelinkTrackModal, 16 for LinkedFoldersSettings including 6 new)

## Task Commits

1. **Task 1 RED: Failing tests for RelinkTrackModal** — `fe26cea` (test)
2. **Task 1 GREEN: RelinkTrackModal component + view + layout** — `07da699` (feat)
3. **Task 2 RED: Failing tests for bulk path update and rescan toast** — `f6d32f0` (test)
4. **Task 2 GREEN: Bulk path update + pollFolders + unavailable count UI** — `08cb0bb` (feat)

## Files Created/Modified

- `app/Livewire/RelinkTrackModal.php` — Component with open/relink/confirmMismatch/closeModal methods
- `resources/views/livewire/relink-track-modal.blade.php` — Modal UI with hash warning
- `resources/views/layouts/app/sidebar.blade.php` — Added relink-track-modal inclusion
- `resources/views/pages/settings/⚡linked-folders.blade.php` — Bulk update path modal, unavailable badge, pollFolders, rescan toast
- `tests/Feature/Livewire/RelinkTrackModalTest.php` — 6 tests for RelinkTrackModal
- `tests/Feature/Settings/LinkedFoldersSettingsTest.php` — 6 new tests added

## Decisions Made

- openForTrack silently returns when ownership check fails — prevents modal from opening for unauthorized tracks without leaking error info
- confirmMismatch calls relink() directly after setting confirmedMismatch=true — clean path without code duplication
- pollFolders method tracks IDs in scanningFolderIds array for testable scan completion detection without relying on real background jobs
- setScanning public method enables the rescan completion toast test to simulate "folder was previously scanning"
- updateFolderPath dispatches a fresh ScanLinkedFolder after bulk path replacement to update metadata and is_available from actual filesystem state

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED
