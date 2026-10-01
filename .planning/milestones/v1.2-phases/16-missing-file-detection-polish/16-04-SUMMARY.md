---
phase: 16-missing-file-detection-polish
plan: "04"
subsystem: linked-folders
tags: [gap-closure, relink, toast, livewire, mary-ui]
dependency_graph:
  requires: []
  provides: [linked_folder_id-on-relink, mary-toast-linked-folders]
  affects: [RelinkTrackModal, linked-folders-settings]
tech_stack:
  added: []
  patterns: [Mary Toast trait, linked_folder_id lookup on relink]
key_files:
  created: []
  modified:
    - app/Livewire/RelinkTrackModal.php
    - resources/views/pages/settings/⚡linked-folders.blade.php
    - tests/Feature/Livewire/RelinkTrackModalTest.php
    - tests/Feature/Settings/LinkedFoldersSettingsTest.php
decisions:
  - RelinkTrackModal now validates new path is inside a linked folder before updating
  - Mary Toast trait replaces all raw dispatch('toast') calls in linked-folders component
  - Test for rescan completion toast uses effects['xjs'] JS effect assertion matching AddToPlaylistModalTest pattern
metrics:
  duration: ~10 minutes
  completed: "2026-03-19"
  tasks_completed: 2
  files_modified: 4
---

# Phase 16 Plan 04: UAT Gap Closure — RelinkTrackModal linked_folder_id + Mary Toast Summary

One-liner: RelinkTrackModal now updates linked_folder_id when relinking to a new folder location, and all linked-folders toast calls use the Mary Toast trait.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Fix RelinkTrackModal to update linked_folder_id | 6d0412e | app/Livewire/RelinkTrackModal.php, tests/Feature/Livewire/RelinkTrackModalTest.php |
| 2 | Fix linked-folders toast dispatches to use Mary Toast trait | 6a8e515 | resources/views/pages/settings/⚡linked-folders.blade.php, tests/Feature/Settings/LinkedFoldersSettingsTest.php |

## What Was Built

**Task 1 — RelinkTrackModal linked_folder_id update:**
- Added `use App\Models\LinkedFolder;` import
- After path normalisation and before the track update, queries all user LinkedFolders to find which one contains the new path using `str_starts_with`
- Rejects relink with validation error if new path is outside all linked folders
- Adds `linked_folder_id` to the `$track->update()` array
- Updated 5 existing tests to create real temp directories and linked folders (the old tests used flat temp files that had no parent folder matching a LinkedFolder)
- Added 2 new tests: relinking to a different folder updates linked_folder_id; relinking outside all folders returns validation error

**Task 2 — Mary Toast trait in linked-folders:**
- Added `use Mary\Traits\Toast;` import to the anonymous component class PHP block
- Added `use Toast;` trait declaration inside the class
- Replaced all 6 `$this->dispatch('toast', message: ...)` calls with appropriate Mary Toast methods: `$this->success()` for success actions, `$this->info()` for rescan queued
- Updated the rescan completion toast test to assert `effects['xjs']` JS effects (Mary Toast uses `$this->js('toast(...)')`) rather than Livewire event dispatch

## Verification

```
php artisan test --compact --filter="RelinkTrackModal|LinkedFolders"
26 passed (56 assertions)
```

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Existing RelinkTrackModal tests used flat temp files without linked folders**
- **Found during:** Task 1
- **Issue:** The existing tests created temp files at e.g. `/tmp/rekodo-testXXXX` but the new linked_folder_id lookup requires files to be inside a folder whose path matches a LinkedFolder record. All success-path tests were failing with the new validation.
- **Fix:** Rewrote tests to create temp directories, place files inside them, and create matching LinkedFolder factory records.
- **Files modified:** tests/Feature/Livewire/RelinkTrackModalTest.php
- **Commit:** 6d0412e

## Self-Check: PASSED

- app/Livewire/RelinkTrackModal.php — FOUND
- resources/views/pages/settings/⚡linked-folders.blade.php — FOUND
- Commit 6d0412e — FOUND
- Commit 6a8e515 — FOUND
