---
phase: 15-folder-linking-ui
plan: "01"
subsystem: settings-ui
tags: [livewire, settings, folders, scan, crud]
dependency_graph:
  requires: [app/Models/LinkedFolder.php, app/Enums/FolderStatus.php, app/Jobs/ScanLinkedFolder.php]
  provides: [settings/linked-folders route, linked-folders UI page]
  affects: [settings nav layout]
tech_stack:
  added: []
  patterns: [single-file Livewire component, Computed properties with unset cache busting, wire:poll polling]
key_files:
  created:
    - resources/views/pages/settings/⚡linked-folders.blade.php
    - tests/Feature/Settings/LinkedFoldersSettingsTest.php
  modified:
    - routes/settings.php
    - resources/views/pages/settings/layout.blade.php
decisions:
  - Used :disabled="$folder->status === \App\Enums\FolderStatus::Scanning" (bound PHP bool) instead of {{ }} ternary or @if inside x-component attributes to avoid Blade compiler parse errors
  - Used plain HTML div for empty state since x-mary-empty-state component does not exist in this Mary UI version
  - Used unset($this->folders) after mutations to invalidate Computed property cache
metrics:
  duration: ~25 minutes
  completed: 2026-03-18
  tasks_completed: 1
  files_changed: 4
---

# Phase 15 Plan 01: Linked Folders Settings Page Summary

Linked folders settings page (SFC) with full CRUD: link/validate paths, scan dispatch, rescan, rename, remove-with-impact-modal, polling during scans, and 10 feature tests.

## Tasks Completed

| # | Task | Commit | Status |
|---|------|--------|--------|
| 1 | Create linked folders settings page with route, menu item, and tests | 55eca68 | Done |

## What Was Built

**Route:** `settings/linked-folders` added to `routes/settings.php` under `auth` middleware group with name `settings.linked-folders`.

**Menu item:** "Linked Folders" added to settings sidebar layout after Appearance, visible to all authenticated users.

**Component (`⚡linked-folders.blade.php`):**
- `linkFolder()` — validates path, checks `is_dir` + `is_readable`, creates `LinkedFolder`, dispatches `ScanLinkedFolder`, resets inputs
- `rescan(int $folderId)` — dispatches `ScanLinkedFolder` for existing user folder
- `editName(int $folderId, string $newName)` — inline rename
- `confirmRemove(int $folderId)` — opens modal
- `removeFolder()` — checks not Scanning, deletes folder (cascade removes tracks + playlist_tracks), toast with track count
- `#[Computed] folders()` — `withCount('tracks')` ordered by name, cache-busted via `unset()`
- `#[Computed] folderBeingRemoved()` — scoped to auth user with tracks count
- `#[Computed] affectedPlaylistCount()` — distinct playlist count via `PlaylistTrack` query
- `wire:poll.3s` conditional on any folder having `Scanning` status
- Status badges: Scanning (warning + spinner), Error (error), Active (success)
- Removal modal with track count and affected playlist count impact summary

**Tests (10, all passing):** page loads, auth redirect, linkFolder creates + dispatches job, invalid path validation, multiple folders rendered, scanning badge visible, rescan dispatches, remove deletes + toast, scanning blocks remove, modal shows impact counts.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Blade compiler error with `{{ }}` ternary as component attribute**
- **Found during:** Task 1 (GREEN phase)
- **Issue:** Using `{{ $expr ? 'disabled' : '' }}` as an unquoted attribute on `x-mary-button` caused a Blade compilation syntax error (`unexpected token "endif"`)
- **Fix:** Replaced with `:disabled="$folder->status === \App\Enums\FolderStatus::Scanning"` (bound PHP boolean expression)
- **Files modified:** `resources/views/pages/settings/⚡linked-folders.blade.php`
- **Commit:** 55eca68

**2. [Rule 1 - Bug] x-mary-empty-state component does not exist**
- **Found during:** Task 1 (GREEN phase)
- **Issue:** `x-mary-empty-state` is not available in this version of Mary UI
- **Fix:** Replaced with a plain HTML div showing empty state text
- **Files modified:** `resources/views/pages/settings/⚡linked-folders.blade.php`
- **Commit:** 55eca68

## Self-Check: PASSED

- FOUND: `resources/views/pages/settings/⚡linked-folders.blade.php`
- FOUND: `tests/Feature/Settings/LinkedFoldersSettingsTest.php`
- FOUND: commit 55eca68
