---
phase: 15-folder-linking-ui
plan: "03"
subsystem: settings/linked-folders
tags: [bug-fix, album-cleanup, livewire]
dependency_graph:
  requires: ["15-01", "15-02"]
  provides: ["orphaned-album-cleanup"]
  affects: ["Album", "LinkedFolder", "removeFolder"]
tech_stack:
  added: []
  patterns: ["whereDoesntHave for orphan cleanup"]
key_files:
  modified:
    - resources/views/pages/settings/⚡linked-folders.blade.php
    - tests/Feature/Settings/LinkedFoldersSettingsTest.php
decisions:
  - "Clean up orphaned albums in removeFolder() using Album::whereDoesntHave('tracks')->delete() scoped to current user"
metrics:
  duration: "5 minutes"
  completed: "2026-03-19"
  tasks_completed: 1
  files_modified: 2
---

# Phase 15 Plan 03: Orphaned Album Cleanup Summary

**One-liner:** Adds post-deletion cleanup in `removeFolder()` to delete albums that have zero tracks remaining after a linked folder is removed.

## What Was Built

After `$folder->delete()` in the `removeFolder()` method, the component now runs:

```php
Album::where('user_id', auth()->id())
    ->whereDoesntHave('tracks')
    ->delete();
```

This ensures albums whose only tracks came from the removed folder are purged. Albums with tracks from other sources (uploaded or other linked folders) survive.

## Tests Added

Two new tests in `tests/Feature/Settings/LinkedFoldersSettingsTest.php`:

- **removing folder deletes orphaned empty albums** — folder with one local track in an album; after removal, album is gone.
- **removing folder preserves albums that still have tracks from other sources** — folder track + uploaded track in same album; after removal, album survives.

All 18 LinkedFolders tests pass.

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

- `resources/views/pages/settings/⚡linked-folders.blade.php` — modified, contains `whereDoesntHave`
- `tests/Feature/Settings/LinkedFoldersSettingsTest.php` — modified, contains "empty albums" test
- Commit `12e471b` exists
