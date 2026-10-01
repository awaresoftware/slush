---
phase: quick-9
plan: 01
subsystem: settings/linked-folders
tags: [directory-browser, alpine, livewire, api, json]
dependency_graph:
  requires: []
  provides: [browse-directories-api, folder-picker-ui]
  affects: [settings/linked-folders]
tech_stack:
  added: []
  patterns: [single-action-invokable-controller, alpine-x-data-modal, fetch-api]
key_files:
  created:
    - app/Http/Controllers/BrowseDirectoriesController.php
    - tests/Feature/BrowseDirectoriesTest.php
  modified:
    - routes/web.php
    - resources/views/pages/settings/⚡linked-folders.blade.php
decisions:
  - Use single-action invokable controller pattern matching StreamController
  - Return 422 (not 404) for invalid paths so Alpine fetch() can surface the message
  - Alpine x-data at outer container level so modal works within Livewire component
  - selectFolder() uses $wire.set(browserTarget, currentPath) for polymorphic targeting
  - Exclude hidden directories (dot-prefix) server-side, not client-side
metrics:
  duration: "~3 minutes"
  completed_date: "2026-03-20"
  tasks_completed: 2
  tasks_total: 3
  files_created: 2
  files_modified: 2
---

# Quick Task 9: Add Native Folder Picker to Linked Folders — Summary

**One-liner:** Server-side directory listing JSON API with Alpine.js modal browser for navigating and selecting filesystem folders.

## What Was Built

A server-side directory browser replacing manual path typing on the Linked Folders settings page. Users can now click "Browse" to open a modal that navigates their filesystem and populates the path input.

## Tasks Completed

### Task 1: BrowseDirectoriesController + Route + Tests (TDD)

**Commit:** `89a1fa7`

- Created `BrowseDirectoriesController` as a single-action invokable controller
- Returns JSON: `{ current_path, parent_path, directories: [{name, path}] }`
- Defaults to user home directory (`$_SERVER['HOME']` → `posix_getpwuid()` → `/`)
- Excludes hidden directories (dot-prefix names) and non-directories (files)
- Sorts alphabetically case-insensitive via `usort` + `strcasecmp`
- Returns 422 for non-existent or unreadable paths
- Route registered as `GET api/browse-directories` inside the `auth` middleware group, named `browse-directories`
- 11 feature tests: auth guard, JSON structure, subdirectory listing, file exclusion, hidden dir exclusion, alphabetic sort, name/path keys, parent_path, root parent_path=null, 422 errors

### Task 2: Alpine.js Folder Browser Modal

**Commit:** `ce23cab`

- Wrapped entire settings layout in `x-data` Alpine component with browser state
- "Browse" button added next to both path inputs:
  - Add Folder form — populates `$wire.path`
  - Update Path modal — populates `$wire.newFolderPath`
- `openBrowser(target)` sets `browserTarget` to control which Livewire property is updated
- `fetchDirectories(path)` calls the API via `fetch()` with JSON Accept header
- `navigateTo(path)` drills into a subdirectory; `goUp()` navigates to parent
- `selectFolder()` calls `$wire.set(browserTarget, currentPath)` then closes modal
- Modal features: loading spinner, error message, scrollable directory list with folder icons, Up button (disabled at root), backdrop click + Escape key to close, `x-transition` animations
- Route URL generated server-side via `{{ route('browse-directories') }}` stored in Alpine data property

### Task 3: Human Verification (checkpoint — awaiting)

Human verification of the end-to-end UI flow is pending.

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

- FOUND: app/Http/Controllers/BrowseDirectoriesController.php
- FOUND: tests/Feature/BrowseDirectoriesTest.php
- FOUND: resources/views/pages/settings/⚡linked-folders.blade.php
- FOUND: commit 89a1fa7 (Task 1)
- FOUND: commit ce23cab (Task 2)
