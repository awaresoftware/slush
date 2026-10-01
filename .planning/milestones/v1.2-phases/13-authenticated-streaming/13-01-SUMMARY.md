---
phase: 13-authenticated-streaming
plan: 01
subsystem: api
tags: [streaming, authorization, policy, binary-file-response, range-requests]

requires:
  - phase: 12-data-foundation
    provides: Track model with source_type, file_path, linkedFolder relationship

provides:
  - GET /stream/{track} route with owner-only auth via TrackPolicy
  - StreamController serving audio with BinaryFileResponse, ETag, Range support
  - Path traversal protection for local tracks via linkedFolder boundary check

affects:
  - 14-local-library-scan (uses stream.track route for local track playback)
  - Audio player components (audioUrl() now resolves for local tracks)

tech-stack:
  added: []
  patterns:
    - Gate::authorize() instead of $this->authorize() (base Controller has no AuthorizesRequests trait)
    - BinaryFileResponse with setAutoEtag() for range/caching support
    - Storage::disk('public')->put() in tests for non-empty fake files (UploadedFile::fake()->create() produces 0-byte files)

key-files:
  created:
    - app/Http/Controllers/StreamController.php
    - app/Policies/TrackPolicy.php
    - tests/Feature/StreamControllerTest.php
  modified:
    - routes/web.php

key-decisions:
  - "Use Gate::authorize() not $this->authorize() - base Controller class has no AuthorizesRequests trait"
  - "Use Storage::disk('public')->put() in tests needing real file content - UploadedFile::fake()->create() produces 0-byte files"
  - "Content-Disposition header set to inline (Symfony appends filename automatically)"

patterns-established:
  - "Path safety check: str_starts_with($track->file_path, $track->linkedFolder->path) guards local tracks"
  - "Missing file returns 404 JSON not exception"

requirements-completed: [STRM-01, STRM-02, STRM-03]

duration: 4min
completed: 2026-03-18
---

# Phase 13 Plan 01: Authenticated Streaming Summary

**Owner-only streaming endpoint at GET /stream/{track} using BinaryFileResponse with ETag, Range/206 support, and path traversal protection for local tracks**

## Performance

- **Duration:** 4 min
- **Started:** 2026-03-18T19:27:26Z
- **Completed:** 2026-03-18T19:31:48Z
- **Tasks:** 1
- **Files modified:** 4

## Accomplishments

- TrackPolicy with `stream` method authorizing owner-only access
- StreamController resolving absolute paths for uploaded/local tracks, serving via BinaryFileResponse with ETag and Range support
- Path traversal protection: local tracks validated against their linkedFolder path boundary
- 9 comprehensive tests covering all authentication, authorization, path safety, missing file, and Range scenarios

## Task Commits

1. **Task 1: StreamController, TrackPolicy, route, and tests (TDD)** - `9398e1f` (feat)

**Plan metadata:** _(see final commit below)_

## Files Created/Modified

- `app/Http/Controllers/StreamController.php` - Invokable controller; resolves path, validates safety, returns BinaryFileResponse
- `app/Policies/TrackPolicy.php` - Owner-only stream authorization
- `routes/web.php` - Added `Route::get('stream/{track}', StreamController::class)->name('stream.track')`
- `tests/Feature/StreamControllerTest.php` - 9 tests: owner streams uploaded/local, non-owner 403, unauthenticated 302, path traversal 403, no linkedFolder 403, missing file 404 JSON, Range 206, ETag present

## Decisions Made

- Used `Gate::authorize()` instead of `$this->authorize()` since the base `Controller` class does not include `AuthorizesRequests` trait
- Tests needing actual file content use `Storage::disk('public')->put($path, $content)` because `UploadedFile::fake()->create()` produces 0-byte files (causes 416 on Range requests)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Gate::authorize() used instead of $this->authorize()**
- **Found during:** Task 1 (GREEN phase - first test run)
- **Issue:** `Call to undefined method StreamController::authorize()` — base Controller has no AuthorizesRequests trait
- **Fix:** Replaced `$this->authorize()` with `Gate::authorize()` in StreamController
- **Files modified:** app/Http/Controllers/StreamController.php
- **Verification:** All 9 tests pass
- **Committed in:** 9398e1f (Task 1 commit)

**2. [Rule 1 - Bug] Fixed Range test using UploadedFile::fake() producing 0-byte files**
- **Found during:** Task 1 (Range test returning 416 instead of 206)
- **Issue:** `UploadedFile::fake()->create('test.mp3', 200)` stores a 0-byte file; Range request on empty file returns 416
- **Fix:** Changed Range test to use `Storage::disk('public')->put($path, str_repeat('A', 2048))` for real content
- **Files modified:** tests/Feature/StreamControllerTest.php
- **Verification:** Range test now passes with 206
- **Committed in:** 9398e1f (Task 1 commit)

---

**Total deviations:** 2 auto-fixed (2 Rule 1 bugs)
**Impact on plan:** Both necessary for correctness. No scope creep.

## Issues Encountered

None beyond the auto-fixed deviations above.

## Next Phase Readiness

- `stream.track` route is live — `Track::audioUrl()` for Local tracks resolves correctly
- Ready for Phase 14 (local library scanning) which will create Local tracks pointing to filesystem paths
- No blockers

---
*Phase: 13-authenticated-streaming*
*Completed: 2026-03-18*
