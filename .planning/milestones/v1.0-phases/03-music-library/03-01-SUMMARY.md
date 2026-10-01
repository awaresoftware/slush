---
phase: 03-music-library
plan: 01
subsystem: database
tags: [laravel, eloquent, migrations, getid3, livewire, factories, pest]

# Dependency graph
requires:
  - phase: 02-user-profiles
    provides: User model with avatarUrl() convention and ProfileValidationRules pattern
provides:
  - Album Eloquent model with user/tracks relationships and coverArtUrl() helper
  - Track Eloquent model with user/album relationships and audioUrl() helper
  - albums and tracks database tables with correct FKs, indexes, and cascade behaviour
  - AlbumFactory and TrackFactory with withAlbum() state
  - AudioValidationRules trait for reusable audio file validation
  - james-heinrich/getid3 package installed
  - Livewire temporary upload limit raised to 100 MB
affects: [03-02, 03-03, 03-04, 04-audio-player, 05-playlists]

# Tech tracking
tech-stack:
  added: [james-heinrich/getid3 v1.9.24]
  patterns:
    - "Model URL helpers: audioUrl() and coverArtUrl() follow avatarUrl() single-source-of-truth convention"
    - "Nullable FK with nullOnDelete: album_id on tracks nullifies (not cascade-deletes) when album is removed"
    - "Validation traits: AudioValidationRules follows ProfileValidationRules pattern"
    - "casts() method used (not $casts property) per project convention"
    - "Factories use User::factory() as default for user_id, Album::factory() in withAlbum() state"

key-files:
  created:
    - app/Models/Album.php
    - app/Models/Track.php
    - database/factories/AlbumFactory.php
    - database/factories/TrackFactory.php
    - database/migrations/2026_03_04_175915_create_albums_table.php
    - database/migrations/2026_03_04_175951_create_tracks_table.php
    - app/Concerns/AudioValidationRules.php
    - config/livewire.php
    - tests/Feature/Library/MusicLibraryFoundationTest.php
  modified:
    - composer.json
    - composer.lock

key-decisions:
  - "nullOnDelete used for album_id FK on tracks: deleting an album detaches its tracks rather than deleting them, preserving user audio files"
  - "audioUrl() uses Storage::disk('public') explicitly, coverArtUrl() uses Storage::url() following avatarUrl() pattern"
  - "Livewire temporary_file_upload rules set to string format (not array) matching plan spec: file|max:102400"
  - "year column on tracks kept as string type to allow metadata values like '2024' from getid3 without integer conversion"

patterns-established:
  - "URL helper convention: model has XxxUrl() method returning Storage URL or null — never call Storage::url() in views"
  - "AudioValidationRules trait: use audioFileRules() in Livewire upload components"
  - "TrackFactory::withAlbum() state ensures album user_id matches track user_id"

requirements-completed: [MUSC-01, MUSC-02]

# Metrics
duration: 12min
completed: 2026-03-04
---

# Phase 03 Plan 01: Music Library Foundation Summary

**Album and Track Eloquent models with migrations, getid3 package, AlbumFactory/TrackFactory, AudioValidationRules trait, and Livewire 100 MB upload limit — all verified by 14 passing Pest tests**

## Performance

- **Duration:** 12 min
- **Started:** 2026-03-04T17:55:36Z
- **Completed:** 2026-03-04T18:07:00Z
- **Tasks:** 2
- **Files modified:** 12

## Accomplishments

- Installed james-heinrich/getid3 v1.9.24 for audio metadata extraction
- Created albums and tracks tables with correct FKs, cascade/nullify behaviors, and compound indexes
- Album and Track models with relationships, URL helpers, casts(), and fillable arrays following project conventions
- AlbumFactory and TrackFactory with withAlbum() state ensuring user consistency
- AudioValidationRules trait (mp3/wav/flac/m4a/aac/ogg, max 100 MB)
- Published and configured Livewire config with 100 MB temporary upload limit

## Task Commits

Each task was committed atomically:

1. **Task 1 RED: Failing tests** - `4ccc681` (test)
2. **Task 1 GREEN: getid3, migrations, models, factories** - `8a13725` (feat)
3. **Task 2: AudioValidationRules, Livewire config** - `8dd305f` (feat)

_Note: TDD tasks have multiple commits (test RED → feat GREEN)_

## Files Created/Modified

- `app/Models/Album.php` - Album model with user/tracks BelongsTo/HasMany, coverArtUrl()
- `app/Models/Track.php` - Track model with user/album BelongsTo relationships, audioUrl()
- `database/factories/AlbumFactory.php` - Generates albums with genre/artist/year
- `database/factories/TrackFactory.php` - Generates tracks; withAlbum() state
- `database/migrations/2026_03_04_175915_create_albums_table.php` - albums table with user_id cascade, compound index
- `database/migrations/2026_03_04_175951_create_tracks_table.php` - tracks table with album_id nullOnDelete, file_hash index
- `app/Concerns/AudioValidationRules.php` - audioFileRules() trait for Livewire upload components
- `config/livewire.php` - Published Livewire config; temporary_file_upload rules=file|max:102400
- `tests/Feature/Library/MusicLibraryFoundationTest.php` - 14 tests covering relationships, URL helpers, cascade behavior
- `composer.json` / `composer.lock` - getid3 dependency added

## Decisions Made

- **nullOnDelete for album_id:** Deleting an album detaches tracks (sets album_id = null) rather than cascade-deleting them. Preserves user audio files even when albums are removed.
- **audioUrl() uses disk('public') explicitly:** Matches the plan spec and makes disk selection unambiguous for future disk changes.
- **year on tracks kept as string:** Allows raw metadata values from getid3 (e.g., "2024", "circa 1980") without integer coercion; albums.year is unsignedSmallInteger (known-clean data).
- **Livewire rules as string:** Used `'file|max:102400'` (not array) to match the plan's specified format.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required. Queue connection note: QUEUE_CONNECTION should be set to `database` in .env for async metadata extraction in later plans. Current plan does not require this.

## Next Phase Readiness

- Album and Track models, migrations, and factories are complete and tested — ready for 03-02 (upload flow)
- AudioValidationRules trait ready for use in Livewire upload component
- getid3 installed and available for audio metadata extraction in 03-03
- No blockers

---
*Phase: 03-music-library*
*Completed: 2026-03-04*
