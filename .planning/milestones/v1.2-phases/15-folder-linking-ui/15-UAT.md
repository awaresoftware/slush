---
status: resolved
phase: 15-folder-linking-ui
source: [15-01-SUMMARY.md, 15-02-SUMMARY.md]
started: 2026-03-19T00:00:00Z
updated: 2026-03-19T12:00:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Linked Folders Settings Page Access
expected: Navigate to Settings. "Linked Folders" appears in the sidebar after Appearance. Clicking it loads the linked folders page.
result: pass

### 2. Link a New Folder
expected: On the linked folders page, enter a valid folder path on your system and submit. The folder is added to the list and a scan begins (you should see a "Scanning" badge with a spinner).
result: pass

### 3. Invalid Folder Path Validation
expected: Enter a path that doesn't exist or isn't readable and submit. A validation error message appears — the folder is NOT added.
result: pass

### 4. Folder Status Badges
expected: Folders show status badges: "Scanning" (yellow/warning with spinner) while scanning, "Active" (green/success) when done, "Error" (red) if scan failed.
result: pass

### 5. Remove Folder with Impact Modal
expected: Click remove on a folder. A confirmation modal appears showing how many tracks and playlists will be affected. Confirming removes the folder and its tracks.
result: issue
reported: "albums still remain and they are empty. they should be removed also"
severity: major

### 6. Local Badge on Track Listings
expected: In the library, album detail, or playlist detail view, tracks from linked local folders show a small "Local" badge next to their title. Uploaded tracks do NOT show this badge.
result: pass

### 7. Source Filter on Library Page
expected: On the library page, a source filter dropdown appears (All Sources / Uploaded / Local). Selecting "Local" shows only local tracks. Selecting "Uploaded" shows only uploaded tracks. "All" shows everything.
result: pass

## Summary

total: 7
passed: 6
issues: 1
pending: 0
skipped: 0

## Gaps

- truth: "Removing a folder removes the folder, its tracks, and any albums left empty"
  status: resolved
  reason: "User reported: albums still remain and they are empty. they should be removed also"
  severity: major
  test: 5
  root_cause: "removeFolder() calls $folder->delete() which cascades to tracks via FK, but no cleanup of orphaned albums. Albums with zero tracks remain."
  artifacts:
    - path: "resources/views/pages/settings/⚡linked-folders.blade.php"
      issue: "removeFolder() has no post-deletion album cleanup"
  missing:
    - "After $folder->delete(), delete user's albums that have zero tracks"
  debug_session: ".planning/debug/empty-albums-after-removal.md"
