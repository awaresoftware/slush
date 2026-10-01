# Phase 14: Scan Job & Metadata Pipeline - Context

**Gathered:** 2026-03-18
**Status:** Ready for planning

<domain>
## Phase Boundary

A queued job recursively discovers all audio files in a linked folder, extracts ID3 metadata, and auto-creates albums — all without blocking the UI. No folder linking UI, no library display changes, no missing file detection. Purely backend pipeline.

</domain>

<decisions>
## Implementation Decisions

### Duplicate Prevention
- Match by file_path first; if no match, fall back to MD5 file_hash to catch moved/renamed files
- On hash match (file moved within folder), auto-update the existing track's file_path to new location — preserves playlist/queue associations
- File hashing (MD5) happens during the scan job, not deferred to metadata extraction
- MD5 chosen for speed; column is varchar(64), MD5 produces 32 hex chars

### Album Auto-Creation
- ID3 album tag takes priority — each unique album tag creates its own album, even for tracks in the same folder
- When no ID3 album tag exists, use parent folder name as album title
- Album artist from ID3 `album_artist` tag when present, fall back to track artist
- Extract embedded cover art from ID3 tags — save as album cover_art_path; first track with art wins for the album
- Existing ExtractAudioMetadata job already has album auto-creation logic — extend it for cover art and album artist

### Metadata Fallbacks
- Title: filename without extension when no ID3 title tag (no parsing of track numbers or separators)
- Artist: "Unknown Artist" when no ID3 artist tag
- Track number: extract from ID3 tag when available, null otherwise
- Duration: from getid3 audio analysis
- If getid3 cannot analyze a file at all (corrupt/unsupported), still create the track with filename-based title, "Unknown Artist", and null duration

### Job Architecture
- Single `ScanLinkedFolder` job per folder: discovers files via RecursiveDirectoryIterator, computes MD5 hashes, creates Track records, then dispatches `ExtractAudioMetadata` per track
- ScanLinkedFolder manages LinkedFolder status transitions: set to `scanning` at start, `active` on completion, `error` on exception; update `last_scanned_at` and `track_count`
- On individual file error (permission denied, unreadable), log and skip — continue scanning rest of folder
- No file count limit — personal music app, folders are reasonable size; queue timeout handles edge cases
- Supported formats: MP3, WAV, FLAC, M4A, AAC, OGG (matches existing upload support)

### Claude's Discretion
- Exact RecursiveDirectoryIterator filter implementation
- How to store extracted cover art (file naming, directory structure)
- Error logging approach (Laravel Log vs custom)
- Queue connection/timeout configuration
- Exact hash computation strategy (full file vs partial)

</decisions>

<specifics>
## Specific Ideas

No specific requirements — user consistently chose recommended approaches aligned with clean, predictable behavior. Key principle: always create the track record even if metadata extraction fails — the file exists, the user should see it.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `ExtractAudioMetadata` job (`app/Jobs/ExtractAudioMetadata.php`): Already handles local files via `source_type` branching, creates albums from ID3 tags. Needs extension for cover art extraction and album artist.
- `Track` model: Has `file_hash` column (varchar 64, indexed), `source_type` cast to `SourceType` enum, `linked_folder_id` FK
- `LinkedFolder` model: Has `status` (FolderStatus enum: active/scanning/error), `track_count`, `last_scanned_at`, path normalisation mutator
- `SourceType` enum: `Uploaded` and `Local` cases
- `FolderStatus` enum: `Active`, `Scanning`, `Error` cases
- getid3 library: Already integrated for ID3 metadata extraction

### Established Patterns
- Queued jobs use `ShouldQueue` interface with constructor Track injection
- Model casts via `casts()` method
- Path normalisation via attribute mutators (backslash to forward slash)
- Mass assignable via `$fillable` array
- SQLite database engine

### Integration Points
- `ExtractAudioMetadata::dispatch($track)` — existing dispatch pattern from UploadModal
- `LinkedFolder->tracks()` HasMany relationship — ready for track_count updates
- `Track::scopeLocal()` — query scope for filtering local tracks
- Album model with `cover_art_path` field — ready for cover art storage

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 14-scan-job-metadata-pipeline*
*Context gathered: 2026-03-18*
