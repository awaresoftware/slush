# Phase 12: Data Foundation - Context

**Gathered:** 2026-03-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Schema, model changes, and path normalisation that every downstream component depends on. The database and Track model correctly support both uploaded and local-linked tracks, with path normalisation and source discrimination in place. No UI, no streaming route, no scanning — purely data layer.

</domain>

<decisions>
## Implementation Decisions

### Source Type Storage
- String enum column `source_type` on tracks table (varchar, not native DB enum)
- Standalone PHP backed enum at `app/Enums/SourceType.php` with cases `Uploaded` and `Local`
- Column default `'uploaded'` — existing tracks automatically get the value, no backfill needed
- Query scopes on Track: `scopeUploaded()` and `scopeLocal()` for downstream filtering
- Cast `source_type` to `SourceType` enum in Track model casts

### Path Normalisation
- Mutator on `file_path` attribute — normalise backslashes to forward slashes on write
- Applies to ALL tracks (uploaded + local), not conditional on source type
- Single `file_path` column reused — relative paths for uploads, absolute paths for local
- `audioUrl()` checks `source_type` enum: uploaded → `Storage::disk('public')->url()`, local → streaming route URL

### LinkedFolder Model
- Rich metadata columns: `path` (string, normalised), `user_id` (FK), `name` (display label, defaults to folder basename), `last_scanned_at` (nullable timestamp), `track_count` (integer, cached counter), `status` (string enum: active/scanning/error)
- `linked_folder_id` nullable FK on tracks table — null for uploaded tracks, set for local tracks
- Cascade delete: deleting a linked folder cascade-deletes all its tracks (matches FOLD-06 requirement)
- LinkedFolder factory created with model for test support

### Migration Strategy
- Two separate migrations: (1) add `source_type` + `linked_folder_id` to tracks, (2) create `linked_folders` table
- `linked_folders` migration runs first (FK dependency)
- Cascade delete on `linked_folder_id` FK
- Track factory local state deferred to later phase — not needed until Phase 14+

### ID3 Metadata Job
- Modify existing ID3 extraction job to accept and process absolute file paths (not just Storage-relative)
- Job determines read strategy based on track's `source_type`: uploaded → Storage disk, local → direct filesystem read

### Claude's Discretion
- Exact LinkedFolder status enum values and transitions
- Internal structure of path normalisation mutator
- Migration ordering details beyond the two-migration split
- Factory definition details for LinkedFolder

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `Track` model (`app/Models/Track.php`): Has `file_path`, `audioUrl()`, factory, album/user relationships
- `TrackFactory` (`database/factories/TrackFactory.php`): Exists, will need `source_type` default added
- getid3 package: Already used for ID3 metadata extraction in queued jobs

### Established Patterns
- Model casts via `casts()` method (not `$casts` property) — Track already uses this
- `nullOnDelete` for album FK on tracks — local folder FK uses `cascadeOnDelete` instead (different intent)
- Mass assignable via `$fillable` array
- SQLite database engine

### Integration Points
- `Track::audioUrl()` — must be updated to branch on source_type (uploaded vs local streaming route)
- ID3 metadata extraction job — must accept absolute paths for local tracks
- Track queries throughout app — existing code unaffected since source_type defaults to uploaded

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches. User consistently chose recommended options aligned with clean relational design and Laravel conventions.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 12-data-foundation*
*Context gathered: 2026-03-18*
