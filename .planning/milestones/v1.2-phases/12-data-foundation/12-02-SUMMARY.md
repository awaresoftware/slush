---
phase: 12-data-foundation
plan: 02
subsystem: testing
tags: [pest, getid3, source-type, path-normalisation, linked-folder]

requires:
  - phase: 12-01
    provides: SourceType enum, Track model with scopes/mutator/audioUrl, LinkedFolder model and factory

provides:
  - ExtractAudioMetadata job branches path resolution on source_type
  - Feature tests covering DATA-01 (enum, scopes, audioUrl)
  - Feature tests covering DATA-02 (file_path normalisation mutator)
  - Feature tests covering DATA-03 (job path branching on source_type)
  - Feature tests covering LinkedFolder factory, relationships, cascade delete

affects: [13-streaming, 14-scan-job]

tech-stack:
  added: []
  patterns:
    - "Job path resolution: branch on SourceType enum rather than hardcoded disk"
    - "Pest tests use closure assertions for collection item properties"

key-files:
  created:
    - tests/Feature/TrackSourceTypeTest.php
    - tests/Feature/TrackPathNormalisationTest.php
    - tests/Feature/ExtractAudioMetadataTest.php
    - tests/Feature/LinkedFolderTest.php
  modified:
    - app/Jobs/ExtractAudioMetadata.php

key-decisions:
  - "ExtractAudioMetadata uses file_path directly for local tracks, Storage::disk('public')->path() for uploaded — no new abstraction needed"
  - "Pest collection assertions use closure pattern (->each(fn)) not chained property access (->each->prop)"

patterns-established:
  - "Job branching: compare source_type === SourceType::Local before path resolution"
  - "Test closures: $collection->each(fn ($item) => expect($item->prop)->toBe(...))"

requirements-completed: [DATA-03]

duration: 12min
completed: 2026-03-18
---

# Phase 12 Plan 02: Test Coverage Summary

**ExtractAudioMetadata job branching on SourceType enum, with 15 passing tests covering all three DATA requirements and LinkedFolder model**

## Performance

- **Duration:** 12 min
- **Started:** 2026-03-18T18:00:00Z
- **Completed:** 2026-03-18T18:12:00Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments

- Modified ExtractAudioMetadata job to use absolute path directly for local tracks and Storage::disk path for uploaded tracks
- 15 feature tests covering DATA-01 (SourceType enum, scopes, audioUrl), DATA-02 (path normalisation mutator), DATA-03 (job path branching), and LinkedFolder relationships
- No regressions — pre-existing failures unchanged (LibraryViewTest, AuthPagesTest, ProfileUpdateTest)

## Task Commits

1. **Task 1: Modify ExtractAudioMetadata job for local track support** - `9a00710` (feat)
2. **Task 2: Write feature tests for all Phase 12 requirements** - `e6b0e48` (test)

**Plan metadata:** _(this commit)_ (docs: complete plan)

## Files Created/Modified

- `app/Jobs/ExtractAudioMetadata.php` - Added SourceType import and conditional path resolution
- `tests/Feature/TrackSourceTypeTest.php` - 5 tests: default source_type, enum instance, uploaded/local scopes, audioUrl
- `tests/Feature/TrackPathNormalisationTest.php` - 3 tests: backslashes, forward slashes, mixed separators
- `tests/Feature/ExtractAudioMetadataTest.php` - 2 tests: uploaded resolves via Storage, local uses file_path directly
- `tests/Feature/LinkedFolderTest.php` - 4 tests: factory creates valid record, belongs to user, has many tracks, cascade delete

## Decisions Made

- ExtractAudioMetadata needs no abstraction layer — a simple ternary on source_type is sufficient and matches existing style
- Pest collection assertions use the closure pattern `->each(fn ($item) => ...)` since chained property access `->each->prop` resolves to method calls and fails for non-method properties

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed Pest collection assertion syntax**
- **Found during:** Task 2 (writing TrackSourceTypeTest)
- **Issue:** `->each->source_type->toBe(...)` calls `source_type()` as a method, throwing BadMethodCallException
- **Fix:** Changed to `$collection->each(fn ($track) => expect($track->source_type)->toBe(...))`
- **Files modified:** tests/Feature/TrackSourceTypeTest.php
- **Verification:** Tests pass
- **Committed in:** e6b0e48 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug in test assertion syntax)
**Impact on plan:** Minor fix to Pest assertion pattern. No scope creep.

## Issues Encountered

None beyond the assertion syntax fix above.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All Phase 12 requirements validated by passing tests
- ExtractAudioMetadata job ready for local tracks
- Phase 13 (streaming) can rely on DATA-01/02/03 being complete and tested
- Phase 13 must create the `stream.track` named route referenced in `Track::audioUrl()` for local tracks

---
*Phase: 12-data-foundation*
*Completed: 2026-03-18*
