---
status: complete
phase: 16-missing-file-detection-polish
source: 16-01-SUMMARY.md, 16-02-SUMMARY.md, 16-03-SUMMARY.md, 16-04-SUMMARY.md
started: 2026-03-19T19:00:00Z
updated: 2026-03-19T19:15:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Unavailable Track Indicators in Library
expected: In your library, any track whose source file has been moved/deleted should appear dimmed (lower opacity) with an "Unavailable" badge. Play button and add-to-playlist button should be hidden. A re-link icon button appears in the actions column.
result: pass

### 2. Unavailable Track Indicators in Album Detail
expected: Open an album containing an unavailable track. The unavailable track row should be dimmed with an "Unavailable" badge, no play button, and a re-link button.
result: pass

### 3. Unavailable Track Indicators in Playlist Detail
expected: Open a playlist containing an unavailable track. The unavailable track row should be dimmed with "Unavailable" badge, no play button, and a re-link button replacing the remove button.
result: pass

### 4. Source Filter — Unavailable Option
expected: In the library, open the source filter dropdown. An "Unavailable" option should be present. Selecting it filters to show only unavailable tracks.
result: pass

### 5. Queue Excludes Unavailable Tracks
expected: Play an album or playlist containing a mix of available and unavailable tracks. The queue should skip unavailable tracks entirely — they should not appear in the play queue.
result: pass

### 6. Re-link Track Modal
expected: Click the re-link button on an unavailable track. A modal opens showing the old file path and an input for the new file path. Enter a valid path (must be inside a linked folder) and submit. The track should become available again (no longer dimmed, play button returns).
result: pass

### 7. Re-link Hash Mismatch Warning
expected: In the re-link modal, provide a path to a different audio file (not the original). A warning about hash mismatch should appear with a "Re-link Anyway" confirmation button before proceeding.
result: pass

### 8. Re-link Rejects Path Outside Linked Folders
expected: In the re-link modal, enter a path to a file that exists but is NOT inside any linked folder. The modal should show a validation error rejecting the relink.
result: pass

### 9. Bulk Folder Path Update
expected: In Settings > Linked Folders, click to update a folder's path. A modal shows the old path and input for new path. Enter the new location. All tracks under that folder should have their paths updated and availability re-checked.
result: pass

### 10. Unavailable Count Badge in Settings
expected: In Settings > Linked Folders, any folder with unavailable tracks should show a count badge indicating how many tracks are unavailable.
result: pass

### 11. Rescan Completion Toast
expected: Trigger a rescan of a linked folder (from settings). When the scan completes, a toast notification should appear showing the number of tracks found and how many are unavailable.
result: pass

## Summary

total: 11
passed: 11
issues: 0
pending: 0
skipped: 0

## Gaps

[none]
