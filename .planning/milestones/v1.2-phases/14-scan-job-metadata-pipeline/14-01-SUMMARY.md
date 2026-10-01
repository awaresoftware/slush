---
phase: 14-scan-job-metadata-pipeline
plan: 01
subsystem: jobs
tags: [queued-jobs, file-scanning, audio, linked-folders, tracks]

requires:
  - phase: 12-data-foundation
    provides: LinkedFolder model, Track model, FolderStatus enum, SourceType enum
  - phase: 13-authenticated-streaming
    provides: ExtractAudioMetadata job

provides:
  - ScanLinkedFolder queued job for recursive audio file discovery
  - Duplicate prevention by file_path (rescan) and file_hash (moved files)
  - LinkedFolder status lifecycle management (scanning/active/error)
  - Per-track ExtractAudioMetadata dispatch

affects: [15-linked-folder-ui, 16-background-maintenance]

tech-stack:
  added: []
  patterns:
    - "RecursiveDirectoryIterator with SKIP_DOTS for filesystem traversal"
    - "Dual-key duplicate detection: path match first, then hash match for moved files"
    - "Outer try/catch re-throws with status=error; inner try/catch skips bad files"

key-files:
  created:
    - app/Jobs/ScanLinkedFolder.php
    - tests/Feature/ScanLinkedFolderTest.php
  modified: []

key-decisions:
  - "Use (new ScanLinkedFolder($folder))->handle() in tests instead of dispatchSync() — Queue::fake() intercepts dispatchSync, preventing the job from running"
  - "Each test file must have unique content to avoid md5 hash collisions causing false 'moved file' matches"
  - "track_count uses $this->folder->tracks()->count() after loop, not loop counter, for accuracy across rescans"

patterns-established:
  - "Job constructor injection: public function __construct(public LinkedFolder $folder)"
  - "Status set to Scanning before outer try; Active inside on success; Error in catch before re-throw"

requirements-completed: [FOLD-02, FOLD-04]

duration: 20min
completed: 2026-03-18
---

# Phase 14 Plan 01: ScanLinkedFolder Job Summary

**Queued ScanLinkedFolder job with RecursiveDirectoryIterator scanning 6 audio formats, md5-based duplicate prevention, status lifecycle, and ExtractAudioMetadata dispatch per track**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-03-18T20:10:00Z
- **Completed:** 2026-03-18T20:30:00Z
- **Tasks:** 1
- **Files modified:** 2

## Accomplishments
- ScanLinkedFolder job recursively discovers MP3, WAV, FLAC, M4A, AAC, OGG files
- Duplicate prevention: path match updates hash; hash match updates path (moved files); no match creates new track
- LinkedFolder status transitions: scanning -> active on success, error on thrown exception
- ExtractAudioMetadata dispatched for each track (new or existing)
- 12 Pest feature tests covering all behaviors including recursive discovery, status lifecycle, and edge cases

## Task Commits

1. **Task 1: Create ScanLinkedFolder job with tests** - `c0e6762` (feat)

## Files Created/Modified
- `app/Jobs/ScanLinkedFolder.php` - Queued job implementing recursive folder scan with duplicate prevention
- `tests/Feature/ScanLinkedFolderTest.php` - 12 Pest feature tests covering all scan behaviors

## Decisions Made
- Direct `->handle()` call in tests rather than `dispatchSync()`: `Queue::fake()` intercepts `dispatchSync`, preventing the job body from executing
- Unique file content per test: files with identical content hash to the same md5, triggering false "moved file" detection within a single scan
- Outer try/catch wraps all scan logic; inner per-file try/catch swallows individual file errors; outer catch sets status=Error and re-throws

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- `Queue::fake()` + `dispatchSync()`: discovered that `Queue::fake()` intercepts synchronous dispatches. Resolved by calling `->handle()` directly — this is the correct test pattern for jobs where we only want to fake the jobs dispatched *within* the job under test.
- Same-content files: multiple files with content `'audio'` all hash to the same md5. Resolved by using unique content per file (`uniqid()` suffix). Not a code bug — a test data issue.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- ScanLinkedFolder job is complete and tested; ready for Phase 14 Plan 02 (ExtractAudioMetadata enhancements) or Phase 15 (UI to trigger scanning)
- No blockers

---
*Phase: 14-scan-job-metadata-pipeline*
*Completed: 2026-03-18*
