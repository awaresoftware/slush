# Phase 3: Music Library - Context

**Gathered:** 2026-03-04
**Status:** Ready for planning

<domain>
## Phase Boundary

Implement core music management: audio file upload, album-based organization, a library view (Tracks + Albums tabs), and inline search within the user's own collection. Metadata editing, playlist management, and local file referencing are out of scope for this phase.

</domain>

<decisions>
## Implementation Decisions

### Organization model
- Albums only — no playlists in this phase (Phase 5 handles playlists)
- Album fields: title, artist, year, genre, cover art (all optional)
- Tracks can exist without an album — loose tracks are valid
- Top-level library navigation: two tabs — **Tracks** | **Albums**

### Upload experience
- Batch upload: multiple files selected/dropped at once
- Auto-extract ID3 tags (title, artist, album, year, genre, duration) on upload — no in-phase editing
- Supported formats: MP3, WAV, FLAC, M4A, AAC, OGG
- Max file size: 100 MB per file
- Entry point: "Upload" button on the library page that opens a modal
- Optional album picker in upload modal (select existing or create new album from dropdown)
- Processing: background queue job (file saved immediately, metadata extraction async)
- Progress feedback during upload: spinner/loading state (not per-file progress bars)
- After batch completes: redirect to a summary page listing all uploaded tracks with links
- Duplicate detection: hash-based check — warn the user ("this looks like a duplicate") but allow them to proceed

### Library view layout
- **Tracks tab**: table/list with columns — title, artist, album, duration
- **Albums tab**: card grid with cover art, album title, and artist below each card
- Clicking an album → dedicated album page at `/library/albums/{id}` with full track list
- Sort order: alphabetical by title (fixed — no sortable column headers)
- Empty state: illustration + "Upload your first track" CTA that opens the upload modal

### Search
- Location: search bar at top of library page, inline above the Tracks/Albums tabs
- Fields searched: title, artist, album
- Trigger: instant as-you-type with debounce (no submit button needed)
- Results: tracks and albums displayed in separate sections (e.g., "Songs" and "Albums")

### Claude's Discretion
- Illustration choice for empty state
- Exact debounce timing for search
- Album page layout details (track numbering, play button placement)
- Cover art fallback when no image is provided (placeholder color/icon)
- Queue driver configuration (use sync driver in tests)

</decisions>

<specifics>
## Specific Ideas

- Inspired by Spotify's library and iTunes' organization model
- "Link in place" upload (iTunes-style path referencing) was requested but deferred — see deferred section

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `resources/views/pages/settings/⚡profile.blade.php` — Livewire file upload pattern with `WithFileUploads`, `#[Rule]` attributes, and Intervention Image — reuse this approach for audio file uploads
- `app/Concerns/ProfileValidationRules.php` — validation concern pattern to follow for audio/album validation rules
- Auth-guarded routes already established in `routes/web.php` — library routes will follow same pattern
- Flux UI components (`flux:table`, `flux:input`, `flux:modal`, `flux:badge`) available for library views and upload modal
- `#[Layout('layouts.app')]` attribute pattern established in Phase 2 for full-page Livewire components

### Established Patterns
- Livewire full-page components with `#[Layout]` attribute (not layout wrapper in blade)
- TDD approach: write failing tests first, then implement
- Atomic commits per task with `feat(03-XX):` / `test(03-XX):` prefix
- Factories and seeders required for new models
- Form Requests for validation logic

### Integration Points
- `app/Models/User.php` — tracks and albums will have `user_id` foreign key (user-owned library)
- `config/filesystems.php` — `public` disk already used for avatars; audio files go on same disk under `music/{userId}/` path
- `routes/web.php` — auth-guarded route group for `/library/*` routes

</code_context>

<deferred>
## Deferred Ideas

- **Local file referencing** (iTunes-style): user points to a file path on disk rather than uploading a copy. Significant feature — different storage model, path resolution complexity, server vs client paths. Deserves its own phase.
- **Metadata editing**: edit track title, artist, album, year, genre after upload. Deferred — ID3 tags are read-only this phase.
- **Playlist creation in this phase**: roadmap scopes this to Phase 5 (Playlists). Albums are the only organization unit here.

</deferred>

---

*Phase: 03-music-library*
*Context gathered: 2026-03-04*
