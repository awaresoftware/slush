# Phase 16: Missing File Detection & Polish - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Detect when linked files have been moved or deleted, mark them as unavailable with a visual indicator in the library, and provide a way for users to re-link moved files. No new scanning infrastructure — detection piggybacks on existing rescan flow. No filesystem watching (out of scope).

</domain>

<decisions>
## Implementation Decisions

### Unavailable Indicator
- Dimmed row (reduced opacity ~50%) plus red/warning "Unavailable" badge
- Play button hidden entirely on unavailable tracks (not disabled — removed)
- Badge shown consistently everywhere: library, album detail, playlist detail
- "Unavailable" option added to existing source filter dropdown (All / Uploaded / Local / Unavailable)

### Detection Trigger
- Missing file detection runs during folder rescan only — no separate scheduled job
- ScanLinkedFolder job checks file existence for all known tracks in the folder during rescan
- If a previously-missing file reappears at its original path, auto-restore to available state
- Rescan toast includes unavailable count: "Folder scanned: X tracks found, Y unavailable"

### Re-link Flow
- Modal with path input: click "Re-link" button on unavailable track → modal shows old path, text input for new path, validate & save
- Hash match warning: compute hash of new file — if it doesn't match original, show warning but still allow re-link
- Re-link button available from track row everywhere (library, album, playlist) — not just settings
- Bulk re-link: "Update folder path" option on linked folder that updates all track paths by replacing old prefix with new prefix — handles folder moves elegantly
- Individual re-link also available for single track path changes

### Playback Behavior
- Queue auto-skips unavailable tracks with brief toast: "Skipped [track] — file unavailable"
- "Play All" / "Add all to queue" actions exclude unavailable tracks silently
- StreamController returns 404 when file doesn't exist on disk (Alpine player handles error)
- Unavailable tracks cannot be added to playlists (blocked in UI)

### Claude's Discretion
- Exact badge color/styling within Mary UI conventions
- Opacity value for dimmed rows
- Re-link modal layout details
- How bulk path update is validated (prefix replacement logic)
- Error handling for invalid re-link paths

</decisions>

<specifics>
## Specific Ideas

No specific requirements — user consistently chose recommended approaches. Key principles: detection should be non-intrusive (rescan-only), unavailable state should be clearly visible but not overwhelming (dimmed + badge), and re-link should work wherever the user encounters the problem.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `Track` model: Has `file_path`, `file_hash` (MD5), `source_type` cast, `linked_folder_id` FK
- `ScanLinkedFolder` job: Manages folder scan lifecycle, status transitions, already dispatches per-track metadata extraction — extend for missing file detection
- `LinkedFolder` model: Has `status` enum (Active/Scanning/Error), `track_count`, user/tracks relationships
- Source filter dropdown (Phase 15): Livewire `sourceFilter` property with `->when()` scopes — extend with "unavailable" option
- `x-mary-badge` with `badge-ghost` class: Used for "Local" source badge — reuse pattern for "Unavailable" badge
- `AddToPlaylistModal`: Modal pattern with `wire:model="showModal"` — template for re-link modal
- Alpine.js audio player: Client-side playback with queue management — needs error handling for 404 responses

### Established Patterns
- Livewire full-page components with `#[Computed]` properties
- Toast notifications via `Mary\Traits\Toast` (`$this->success()`, `$this->error()`)
- `wire:loading.attr="disabled"` for action buttons
- Path normalisation via attribute mutator (backslash to forward slash)
- Queued jobs with ShouldQueue interface

### Integration Points
- `Track` model: Add `is_available` boolean column (or equivalent) for availability state
- `ScanLinkedFolder` job: Add file existence check loop during rescan
- `StreamController`: Add file existence check before serving response
- Library/album/playlist views: Add dimming + badge for unavailable tracks, hide play button
- Source filter: Add "Unavailable" option to existing dropdown
- Alpine player: Handle 404 from stream route — auto-skip to next track
- `AddToPlaylistModal`: Filter out unavailable tracks or prevent selection
- Linked folders settings page: Add "Update folder path" action for bulk re-link

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 16-missing-file-detection-polish*
*Context gathered: 2026-03-19*
