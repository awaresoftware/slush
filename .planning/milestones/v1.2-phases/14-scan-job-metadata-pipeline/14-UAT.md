---
status: complete
phase: 14-scan-job-metadata-pipeline
source: 14-01-SUMMARY.md, 14-02-SUMMARY.md
started: 2026-03-19T00:00:00Z
updated: 2026-03-19T00:10:00Z
---

## Current Test

[testing complete]

## Tests

### 1. ScanLinkedFolder discovers audio files and creates tracks
expected: Dispatch ScanLinkedFolder on a linked folder. Tracks are created in the database for discovered audio files. LinkedFolder status becomes "active".
result: pass

### 2. Duplicate prevention on rescan
expected: Running ScanLinkedFolder a second time on the same folder does NOT create duplicate tracks. Existing tracks are updated, not duplicated. Track count stays the same.
result: pass

### 3. ExtractAudioMetadata extracts title, artist, and album
expected: After scan completes and metadata jobs run, tracks have correct title, artist, and album populated from ID3 tags.
result: pass

### 4. Cover art extraction
expected: For tracks with embedded cover art, the album's cover_art_path is populated and the image file exists on disk (storage/app/public/covers/).
result: pass (fixed — added comments.picture fallback for M4A/MP4 files, commit c05f898)

### 5. Track number extraction
expected: Tracks with ID3 track number tags have the track_number field correctly populated (handles "N/total" format like "3/12" → 3).
result: pass

## Summary

total: 5
passed: 5
issues: 0
pending: 0
skipped: 0

## Gaps

[none]
