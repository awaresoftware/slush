---
phase: 14-scan-job-metadata-pipeline
plan: "02"
subsystem: metadata-pipeline
tags: [jobs, getid3, cover-art, albums, metadata]
dependency_graph:
  requires: []
  provides: [album-artist-extraction, cover-art-extraction, folder-name-fallback, track-number-extraction]
  affects: [ExtractAudioMetadata, Album]
tech_stack:
  added: []
  patterns: [TDD, ID3v2-binary-frame-construction, first-track-wins-cover-art]
key_files:
  created: []
  modified:
    - app/Jobs/ExtractAudioMetadata.php
    - tests/Feature/ExtractAudioMetadataTest.php
decisions:
  - "Access $info['id3v2']['APIC'] directly for cover art — CopyTagsToComments does not reliably copy binary APIC data"
  - "album_artist resolution order: album_artist > band (TPE2) > artist — covers all major tagger conventions"
  - "Track artist fallback preserves existing value instead of setting null — prevents overwriting 'Unknown Artist'"
  - "Cover art filename: covers/{album_id}.{ext} — album-scoped, overwrites only if no existing art"
  - "Test helpers build real ID3v2.3 binary frames to exercise actual getid3 parsing path"
metrics:
  duration: 15m
  completed_date: "2026-03-18"
  tasks_completed: 1
  files_changed: 2
---

# Phase 14 Plan 02: Extended Metadata Extraction Summary

Extended `ExtractAudioMetadata` job with album artist from ID3 TPE2/band tags, embedded cover art extraction via APIC frame, folder-name album fallback for local tracks, and track number parsing.

## What Was Built

`app/Jobs/ExtractAudioMetadata.php` extended with:

1. **Eager-load linkedFolder** at start of `handle()` to avoid lazy load in queued context
2. **Track number extraction** — parses ID3 `track_number` tag, handles "N/total" format
3. **Album artist resolution** — checks `album_artist`, then `band` (ID3v2 TPE2), then `artist`
4. **Folder-name album fallback** — when no ID3 album tag and `linked_folder_id` is set, uses `linkedFolder->name` as album title (local tracks only)
5. **Cover art extraction** — reads `$info['id3v2']['APIC']` directly (not via CopyTagsToComments), saves to `covers/{album_id}.{ext}` on public disk
6. **First-track-wins** — skips cover art if `album->cover_art_path` is already set
7. **Artist null-safety** — `$tags['artist'][0] ?? $this->track->artist` preserves "Unknown Artist"

`tests/Feature/ExtractAudioMetadataTest.php` extended with 9 new tests using real ID3v2.3 binary frame construction (no mocking needed — exercises full getid3 parsing path).

## Deviations from Plan

None — plan executed exactly as written.

## Tests

All 11 `ExtractAudioMetadata` tests pass. Pre-existing `ScanLinkedFolderTest` failure (from plan 14-01) is out of scope.

```
php artisan test --compact --filter=ExtractAudioMetadata
Tests: 1 failed (ScanLinkedFolder — pre-existing), 11 passed
```

## Self-Check: PASSED

- `app/Jobs/ExtractAudioMetadata.php` — exists, modified
- `tests/Feature/ExtractAudioMetadataTest.php` — exists, 11 tests
- Commit `80fbc77` — verified
