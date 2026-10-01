---
phase: 13-authenticated-streaming
plan: 02
subsystem: api
tags: [streaming, audio-player, alpine, livewire, track-model]

requires:
  - phase: 13-01
    provides: StreamController with named route 'stream.track'

provides:
  - Track::audioUrl() unified to always return stream route URL for all source types
  - Audio player auto-skip on error via onAudioError() Alpine method

affects: [audio-player, track-model, streaming]

tech-stack:
  added: []
  patterns:
    - "All audio URLs routed through authenticated stream endpoint regardless of source_type"
    - "Audio error handling mirrors onEnded() pattern — silent skip, no user toast"

key-files:
  created: []
  modified:
    - app/Models/Track.php
    - resources/views/livewire/audio-player.blade.php
    - tests/Feature/TrackSourceTypeTest.php

key-decisions:
  - "audioUrl() no longer branches on source_type — always returns route('stream.track', $this) — breaking change for uploaded tracks now routed through auth endpoint"
  - "Audio error handler silently skips to next track matching Spotify-style unavailable track behavior — no error toast"

patterns-established:
  - "Track URL generation: single unified route, no conditional storage logic in model"
  - "Alpine audio error: set isPlaying=false then check hasNext before calling nextTrack"

requirements-completed: [STRM-01]

duration: 10min
completed: 2026-03-18
---

# Phase 13 Plan 02: Unified audioUrl and Player Error Handling Summary

**Track::audioUrl() unified to always return the authenticated stream route, with Alpine audio player silently auto-skipping to next track on any playback error (404/missing file)**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-03-18T19:35:00Z
- **Completed:** 2026-03-18T19:45:00Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- Removed `match` on `source_type` from `audioUrl()` — all tracks now routed through `stream.track`
- Removed unused `Storage` facade import from `Track` model
- Updated `TrackSourceTypeTest` with two audioUrl tests (uploaded and local) asserting stream route
- Added `x-on:error="onAudioError"` to `<audio>` element in player
- Added `onAudioError()` Alpine method that sets `isPlaying = false` and calls `nextTrack()` if `hasNext`

## Task Commits

1. **Task 1: Unify audioUrl() and update existing tests** - `1a16b3f` (feat)
2. **Task 2: Add audio player auto-skip on error** - `00b9403` (feat)

## Files Created/Modified

- `app/Models/Track.php` - Removed match/Storage, audioUrl() now always returns stream route
- `tests/Feature/TrackSourceTypeTest.php` - Updated audioUrl test + added local track audioUrl test
- `resources/views/livewire/audio-player.blade.php` - Added error event handler and onAudioError() method

## Decisions Made

- audioUrl() is now a breaking change for uploaded tracks — they now go through the authenticated stream endpoint instead of direct Storage URLs. This is intentional and matches the security goal of phase 13.
- onAudioError() mirrors onEnded() exactly (minus currentTime reset) for consistent behavior.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 13 complete — all audio for all track types flows through the authenticated StreamController
- Missing files are handled gracefully with silent auto-skip
- Ready for Phase 14

---
*Phase: 13-authenticated-streaming*
*Completed: 2026-03-18*

## Self-Check: PASSED
