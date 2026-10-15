---
phase: quick-7
plan: "01"
subsystem: jobs
tags: [scanning, batching, progress, memory, queue]
dependency_graph:
  requires: []
  provides: [scan-progress-tracking, batch-scanning]
  affects: [linked-folders-settings-ui, scan-job]
tech_stack:
  added: [config/foonk.php]
  patterns: [array_chunk batch processing, progressive progress updates]
key_files:
  created:
    - config/foonk.php
    - database/migrations/2026_03_19_222546_add_scan_progress_to_linked_folders_table.php
  modified:
    - app/Jobs/ScanLinkedFolder.php
    - app/Models/LinkedFolder.php
    - database/factories/LinkedFolderFactory.php
    - resources/views/pages/settings/⚡linked-folders.blade.php
    - tests/Feature/ScanLinkedFolderTest.php
    - tests/Feature/Settings/LinkedFoldersSettingsTest.php
    - .env.example
decisions:
  - "Collect file paths (not SplFileInfo objects) into array before chunking to avoid holding file handles"
  - "Store progress as scanned_count/total_count columns on LinkedFolder; update after each batch"
  - "No Bus::batch() or job chains — chunking inside single ScanLinkedFolder job per user decision"
  - "UI shows 'Scanning...' until total_count is known (file discovery phase), then 'Scanning: X / Y'"
metrics:
  duration: ~10 minutes
  completed: 2026-03-19
  tasks_completed: 2
  files_modified: 8
---

# Quick Task 7: Batch Folder Scanning Summary

**One-liner:** Chunked ScanLinkedFolder job with scanned_count/total_count progress tracking and UI "Scanning: 2,000 / 6,000" badge using configurable batch size (default 200 via SCAN_BATCH_SIZE env).

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Add progress columns, config, and refactor ScanLinkedFolder to batch dispatching | 3040a7e | Migration, config/foonk.php, ScanLinkedFolder.php, LinkedFolder model+factory |
| 2 | UI progress display and tests for batching + progress | 629e8cd | linked-folders.blade.php, ScanLinkedFolderTest.php, LinkedFoldersSettingsTest.php |

## Changes Made

### Migration
Added `scanned_count unsignedInteger default 0` and `total_count unsignedInteger default 0` columns to `linked_folders` after `unavailable_count`.

### Config
Created `config/foonk.php` with `scan_batch_size` driven by `SCAN_BATCH_SIZE` env var (default 200). Added env var to `.env.example`.

### ScanLinkedFolder Job
Refactored `handle()` into three phases:
1. **Discovery:** Iterates all files, collecting `{path, extension}` arrays (not SplFileInfo objects, avoids holding file handles). Sets `total_count` and `scanned_count = 0` on the folder before processing.
2. **Chunked processing:** `array_chunk($files, config('foonk.scan_batch_size', 200))` — per-chunk loop runs existing path/hash dedup logic and dispatches ExtractAudioMetadata. After each chunk: updates `scanned_count`, unsets chunk variable for GC. After all chunks: unsets `$files`.
3. **Availability check + completion:** Unchanged availability loop, then final update sets `status = Active`, `scanned_count = total_count`, and `total_count`.

### UI Badge
Scanning badge now shows `Scanning: 2,000 / 6,000` (via `number_format`) when `total_count > 0`, falling back to `Scanning...` during the initial file discovery phase before the folder record is updated.

### Tests
- 3 new tests in `ScanLinkedFolderTest.php`: batch size config, progressive `scanned_count`, `total_count` set before processing
- 1 new test in `LinkedFoldersSettingsTest.php`: progress displayed with formatted numbers (2,000 / 6,000)
- All 37 tests pass (15 original + 3 new scan tests + 14 original settings tests + 1 new progress test)

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check

- [x] `config/foonk.php` exists
- [x] Migration `2026_03_19_222546_add_scan_progress_to_linked_folders_table` applied (status: Ran)
- [x] Commits 3040a7e and 629e8cd exist in git log
- [x] 37 tests pass with no regressions

## Self-Check: PASSED
