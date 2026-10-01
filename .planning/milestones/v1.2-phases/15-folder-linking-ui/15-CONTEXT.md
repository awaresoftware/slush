# Phase 15: Folder Linking UI - Context

**Gathered:** 2026-03-18
**Status:** Ready for planning

<domain>
## Phase Boundary

User-facing Livewire components for linking folders, viewing linked tracks in the library, and identifying track sources at a glance. Users can link multiple folders, see scan progress, manage linked folders, and see source badges on tracks. No missing file detection (Phase 16), no file browser/picker (out of scope).

</domain>

<decisions>
## Implementation Decisions

### Folder Management Page
- Settings subpage — new tab in existing settings layout alongside Profile, Password, Appearance, etc.
- Text input for folder path with a "Link Folder" validate-and-submit button (server validates path is readable)
- Folder list shows: name, status badge, track count (minimal — no path or last-scanned timestamp in list)
- Actions per folder: Rescan, Remove, Edit name
- Auto-scan on link — dispatching scan job immediately after folder is validated and created

### Source Badges & Library Integration
- Small text badge ("Local") next to track title using x-mary-badge with badge-ghost class
- Only local tracks show a badge — uploaded tracks have no badge (they're the default)
- Badge shown consistently everywhere: library, album detail, playlist detail, search results
- Source filter as dropdown/select next to existing search bar (All / Uploaded / Local)

### Scan Feedback & Status
- Livewire polling every few seconds while any folder has "scanning" status
- Folder row shows "Scanning..." badge with loading spinner during scan
- Toast notification on scan completion: "Folder scanned: X tracks found"
- Error badge (red) + toast with error message on scan failure
- Individual file errors (permissions, unreadable) logged but don't block scan — Phase 14 already handles this

### Folder Removal Flow
- Custom Mary UI modal (not browser confirm) showing folder name, path, track count
- Modal includes affected playlist count: "X tracks are in Y playlists. They will be removed from those playlists too."
- Remove button disabled while folder status is "scanning" — user must wait for scan to complete
- Success toast after removal: "Folder removed. X tracks removed from library."
- Folder row disappears from list after removal

### Claude's Discretion
- Exact polling interval (2-5 seconds range)
- Settings page tab ordering/placement
- Folder name edit UX (inline edit vs modal)
- Badge color/styling details within Mary UI conventions
- Loading states and transitions

</decisions>

<specifics>
## Specific Ideas

- Folder management follows the same list pattern as the admin users page in settings (input at top, list below with actions)
- Source filter mirrors Spotify's approach of subtle filtering without changing page structure
- Removal modal should give full picture of impact (track count + playlist count) before user commits

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- Settings layout (`resources/views/pages/settings/layout.blade.php`): Two-column layout with sidebar menu, ready for new tab
- Admin users page (`resources/views/pages/settings/⚡users.blade.php`): List management pattern with input form + action buttons — direct template for folder list
- `AddToPlaylistModal` component: Modal pattern with `wire:model="showModal"`, Toast trait, computed properties
- `x-mary-badge`: Already used in upload summary for status display
- `<x-empty-state>` component: Reusable for "no folders linked" state
- `LinkedFolder` model: Complete with status enum, track_count, path normalisation, user/tracks relationships
- `FolderStatus` enum: Active/Scanning/Error cases ready
- `SourceType` enum: Uploaded/Local cases ready
- `ScanLinkedFolder` job: Dispatches scan, manages status transitions

### Established Patterns
- Livewire full-page components as settings subpages (⚡ prefix convention)
- `wire:confirm` for simple destructive actions, Mary UI modal for complex confirmations
- `wire:click` + `wire:loading.attr="disabled"` for action buttons
- Toast notifications via `Mary\Traits\Toast` (`$this->success()`, `$this->error()`)
- `#[Computed]` properties for database queries
- `wire:navigate` for SPA-style navigation between pages
- Alpine.js tabs for library view (tracks/albums)

### Integration Points
- Settings sidebar menu: Add "Linked Folders" menu item
- Library index page: Add source badge to track rows + source filter dropdown
- Album show page: Add source badge to track listings
- Playlist show page: Add source badge to track listings
- Routes: New settings/linked-folders route within auth middleware group
- Sidebar nav: No change needed (folder management lives in settings)

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 15-folder-linking-ui*
*Context gathered: 2026-03-18*
