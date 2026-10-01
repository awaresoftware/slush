# Phase 8: Shared Components & Modal Pattern - Context

**Gathered:** 2026-03-15
**Status:** Ready for planning

<domain>
## Phase Boundary

Migrate the auth layout to Mary UI styling and establish the Mary UI modal pattern (Livewire boolean property + `wire:model`) in the codebase. The add-to-playlist modal is refactored from per-track Flux modals to a single Livewire component with `selectedTrackId`. Other modals (upload, delete-user, two-factor) are migrated in their respective page phases (9 and 10).

</domain>

<decisions>
## Implementation Decisions

### Auth Layout
- Restyle `simple.blade.php` with daisyUI card wrapper around the form area — gives form a defined boundary against the background
- Match the `spotify-dark` daisyUI theme (remove the manual dark gradient `bg-linear-to-b from-neutral-950 to-neutral-900`)
- Use Mary UI components for structure: `x-mary-header` for page headings, `x-mary-button` for submit buttons, `x-mary-input` for form fields
- Layout shell migration only — do NOT restyle the auth page contents (login, register, forgot-password are already Flux-free)
- Delete unused `split.blade.php` auth layout — it's dead code
- Keep `card.blade.php` if used, delete if unused (Claude to verify)

### Modal Trigger Pattern
- Trigger buttons use `wire:click` to set a Livewire boolean property (e.g., `wire:click="$set('showModal', true)"`)
- Modal uses `wire:model="showModal"` binding
- Every modal has an explicit `closeModal()` method that sets `showModal = false` AND resets related state (selectedTrackId, form inputs, etc.)
- Consistent modal structure across the app: title header, body content, footer with action buttons — width varies by content (sm/md/lg)

### Add-to-Playlist Refactor
- Create a dedicated Livewire component (e.g., `AddToPlaylistModal`) with `selectedTrackId` and `showModal` properties
- Place single instance in the app layout — accessible from any page
- Trigger via Livewire event dispatch: `$dispatch('open-add-to-playlist', { trackId: X })` — component listens and sets `selectedTrackId` + `showModal = true`
- Use Livewire action for adding tracks: `wire:click="addToPlaylist(playlistId)"` — no form POST, handles DB operation directly
- After adding: modal closes immediately + toast notification confirms "Added to [playlist name]"
- Delete old per-track `add-track-modal.blade.php` Blade component — it's replaced by the Livewire component
- Update all 3 pages with trigger buttons (library index, album show, playlist show) to use the new `$dispatch` pattern

### Migration Scope
- Phase 8 migrates only the add-to-playlist modal — establishes the pattern
- Upload modal stays in Phase 9 (library index page migration)
- Delete-user and two-factor modals stay in Phase 10 (settings page migration)

### Claude's Discretion
- daisyUI card style details (border, shadow, padding) for auth layout
- Toast notification implementation (Mary UI toast component or custom)
- Exact Livewire component structure and event naming
- Whether `card.blade.php` auth layout is used and should be kept

</decisions>

<specifics>
## Specific Ideas

- Auth layout should feel consistent with the authenticated app — same `spotify-dark` theme, not a separate visual identity
- Modal close should always be a clean reset — no stale data showing when reopening
- The single-instance modal pattern is key — eliminates N DOM elements for N tracks

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `simple.blade.php` auth layout: Minimal centered form layout — good base to restyle with daisyUI card
- `add-track-modal.blade.php`: Current per-track modal — will be replaced by Livewire component
- `spotify-dark` daisyUI theme: Already defined in app.css — auth pages should use it
- `x-mary-` prefix active via `config/mary.php`

### Established Patterns
- Audio player uses `@persist('audio-player')` for cross-page persistence — add-to-playlist modal in layout may need similar consideration with `wire:navigate`
- `data-test` attributes on interactive elements — preserve for testing
- Livewire event dispatch used elsewhere in the app — consistent approach

### Integration Points
- `resources/views/layouts/auth/simple.blade.php` — auth layout to restyle
- `resources/views/components/playlist/add-track-modal.blade.php` — old modal to delete
- `resources/views/pages/library/index.blade.php` — has add-to-playlist triggers (lines ~251, ~372)
- `resources/views/pages/library/album-show.blade.php` — has add-to-playlist triggers (line ~94)
- `resources/views/playlists/show.blade.php` — may have add-to-playlist triggers
- `resources/views/layouts/app.blade.php` — where new Livewire modal component will be placed

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 08-shared-components-modal-pattern*
*Context gathered: 2026-03-15*
