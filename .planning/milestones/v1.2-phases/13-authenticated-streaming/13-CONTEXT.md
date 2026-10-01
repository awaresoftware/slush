# Phase 13: Authenticated Streaming - Context

**Gathered:** 2026-03-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Secure authenticated route to serve audio files (both uploaded and local-linked) with full HTTP Range/seeking support. No UI changes beyond player error handling. No scanning, no folder management.

</domain>

<decisions>
## Implementation Decisions

### Access Control
- Owner-only streaming — no admin exception, even admin cannot stream another user's tracks
- TrackPolicy class with `stream` ability for authorization — reusable and testable
- **Unified stream route for ALL tracks** — both uploaded and local-linked tracks served via `/stream/{track}`
- `audioUrl()` updated to always return the stream route URL regardless of source_type
- Uploaded tracks no longer have public Storage URLs — all audio goes through authenticated route

### Missing File Response
- Return 404 with JSON error body when file doesn't exist on disk
- Stream route is read-only — no DB side effects (no marking track unavailable)
- Phase 16 background job handles all missing file state detection
- Player auto-skips to next track on 404 — seamless, no error toast

### Caching & Headers
- ETag based on file modification time — browser caches, revalidates on replay
- Content-Disposition: inline — browser plays via Audio API
- No CORS headers — same-origin requests only

### Path Security
- Path traversal validation: local-linked tracks must have file_path within a registered linked folder
- Uploaded tracks served from Storage disk — no path traversal concern
- Reject with 403 if path is outside registered linked folders

### Claude's Discretion
- Exact BinaryFileResponse vs StreamedResponse implementation
- Range header parsing approach (Symfony vs manual)
- ETag generation strategy details
- Controller structure (single action vs resource)

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `Track` model with `audioUrl()` method — needs update to always return stream route
- `SourceType` enum (`app/Enums/SourceType.php`) — used to branch file serving logic
- `LinkedFolder` model — used to validate local track paths are within registered folders
- Alpine.js audio player (`app/Livewire/AudioPlayer.php`) — needs 404 auto-skip handling

### Established Patterns
- Model casts via `casts()` method
- Routes in `routes/web.php` within auth middleware group
- Named routes used throughout (`route('stream.track', $track)` already referenced in audioUrl)
- No existing Policy classes — this will be the first one

### Integration Points
- `Track::audioUrl()` — must change to always return stream route URL (breaking change for uploaded tracks)
- `routes/web.php` — new route registration within auth group
- Alpine.js player — needs error handling for 404 responses (auto-skip)
- Existing tests referencing `audioUrl()` — will need updating for new URL format

</code_context>

<specifics>
## Specific Ideas

- Unified streaming mirrors Spotify's approach — all audio goes through one authenticated endpoint regardless of source
- Auto-skip on missing file mimics Spotify's unavailable track behavior — seamless, non-disruptive

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 13-authenticated-streaming*
*Context gathered: 2026-03-18*
