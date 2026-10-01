---
phase: 12-data-foundation
plan: 01
subsystem: database
tags: [eloquent, enums, migrations, factory, linked-folders, source-type]

requires: []
provides:
  - SourceType enum (Uploaded/Local) for track source discrimination
  - FolderStatus enum (Active/Scanning/Error) for folder state tracking
  - linked_folders table with user FK and status/path/name columns
  - tracks table extended with source_type (default uploaded) and linked_folder_id FK
  - LinkedFolder model with FolderStatus cast, path normalisation mutator, user/tracks relationships
  - LinkedFolderFactory for testing
  - Track model updated with SourceType cast, filePath mutator, scopeUploaded/scopeLocal, linkedFolder relationship, branching audioUrl()
affects: [13-streaming, 14-scanning, 15-ui, 16-missing-file-detection]

tech-stack:
  added: []
  patterns:
    - "Backed string enums for domain state (SourceType, FolderStatus)"
    - "Attribute::make() mutator for path normalisation (backslash to forward slash)"
    - "match() expression in audioUrl() for source-based URL branching"
    - "Builder scopes using enum values for type-safe filtering"

key-files:
  created:
    - app/Enums/SourceType.php
    - app/Enums/FolderStatus.php
    - app/Models/LinkedFolder.php
    - database/factories/LinkedFolderFactory.php
    - database/migrations/2026_03_18_174351_create_linked_folders_table.php
    - database/migrations/2026_03_18_174352_add_source_type_to_tracks_table.php
  modified:
    - app/Models/Track.php
    - database/factories/TrackFactory.php

key-decisions:
  - "source_type defaults to 'uploaded' at DB level — existing tracks unaffected by migration"
  - "linked_folder_id FK uses cascadeOnDelete so removing a folder removes its tracks"
  - "filePath mutator normalises backslashes on write — handles Windows Herd development paths transparently"
  - "audioUrl() uses match() on SourceType enum; Local case routes to stream.track (Phase 13)"

patterns-established:
  - "Attribute::make(set:) mutator pattern for path normalisation on write"
  - "scopeUploaded/scopeLocal use enum values directly in where() clause"

requirements-completed: [DATA-01, DATA-02]

duration: 12min
completed: 2026-03-18
---

# Phase 12 Plan 01: Data Foundation — Schema and Model Layer Summary

**SourceType/FolderStatus enums, linked_folders migration, tracks extended with source_type FK, LinkedFolder model, and Track model updated with path normalisation, scopes, and branching audioUrl()**

## Performance

- **Duration:** 12 min
- **Started:** 2026-03-18T17:43:04Z
- **Completed:** 2026-03-18T17:55:00Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments
- Two enums created: SourceType (Uploaded/Local) and FolderStatus (Active/Scanning/Error)
- linked_folders table with user FK, path/name/status columns, track_count, last_scanned_at
- tracks table extended with source_type (default 'uploaded') and nullable linked_folder_id FK
- LinkedFolder model with enum cast, path normalisation mutator, user/tracks relationships, factory
- Track model updated: SourceType cast, filePath backslash normalisation, scopeUploaded/scopeLocal, linkedFolder BelongsTo, audioUrl() branching on source type

## Task Commits

Each task was committed atomically:

1. **Task 1: Create enums, migrations, and LinkedFolder model with factory** - `b813239` (feat)
2. **Task 2: Update Track model with source_type support, path normalisation, and scopes** - `12297d0` (feat)

## Files Created/Modified
- `app/Enums/SourceType.php` - Backed string enum: Uploaded='uploaded', Local='local'
- `app/Enums/FolderStatus.php` - Backed string enum: Active, Scanning, Error
- `app/Models/LinkedFolder.php` - Model with FolderStatus cast, path normalisation, user/tracks relationships
- `database/factories/LinkedFolderFactory.php` - Factory with user_id, path, name, status defaults
- `database/migrations/2026_03_18_174351_create_linked_folders_table.php` - Create linked_folders table
- `database/migrations/2026_03_18_174352_add_source_type_to_tracks_table.php` - Add source_type + linked_folder_id to tracks
- `app/Models/Track.php` - Added SourceType cast, filePath mutator, scopes, linkedFolder relationship, branching audioUrl()
- `database/factories/TrackFactory.php` - Added source_type default 'uploaded'

## Decisions Made
- source_type defaults to 'uploaded' at the DB level, existing tracks are unaffected
- linked_folder_id FK uses cascadeOnDelete — removing a folder cascades track removal
- filePath mutator normalises on write, making Windows paths transparent to the rest of the app
- audioUrl() Local branch routes to stream.track named route (to be created in Phase 13)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Schema and model layer complete; all downstream phases can depend on SourceType enum, LinkedFolder model, and Track scopes
- stream.track named route referenced in audioUrl() — must be created in Phase 13 before Local tracks can be played
- Pre-existing test failures (LibraryViewTest, AuthPagesTest) are known and out of scope for v1.2

---
*Phase: 12-data-foundation*
*Completed: 2026-03-18*
