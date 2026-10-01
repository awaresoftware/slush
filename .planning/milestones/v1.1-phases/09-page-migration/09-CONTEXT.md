# Phase 9: Page Migration - Context

**Gathered:** 2026-03-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Migrate all five authenticated content pages to Mary UI — no Flux components remain in library index, album show, upload summary, playlist pages (index/show/create/edit), or profile show views. The upload modal is also migrated from inline Flux modal to a Livewire component following the Phase 8 modal pattern.

</domain>

<decisions>
## Implementation Decisions

### Upload Modal
- Create a dedicated Livewire UploadModal component (like AddToPlaylistModal from Phase 8)
- Place single instance in app.blade.php layout — global, triggerable from anywhere
- Use Livewire boolean property + `wire:model` for open/close (Phase 8 pattern)
- Show a real progress bar during file upload using Livewire's file upload progress events
- After upload completes: show success state inside the modal (not auto-close) with link to upload summary page
- User dismisses manually after reviewing the success state
- Album selection uses searchable `x-mary-select` (search enabled for larger libraries)
- Delete the inline Flux upload modal from library/index.blade.php

### Alert & Notification Strategy
- Success messages (`session('success')`) → toast notifications via `x-mary-toast` (already in app layout from Phase 8)
- Replace inline `flux:callout variant="success"` blocks on playlist pages with toast-based flash messages
- Warning callouts (duplicate detection) → inline daisyUI `alert alert-warning` — contextual warnings stay visible inline, not as toasts
- Badges (e.g., "Processing..." on upload summary) → Mary UI badge component if available, fallback to daisyUI badge classes

### Form Fields
- Use Mary UI's built-in `:label` prop on `x-mary-input` and `x-mary-textarea` — cleaner markup, built-in error display
- Playlist create/edit forms use `x-mary-input label="Name"` pattern (no separate label elements)
- Keep native browser `confirm()` dialog for playlist delete — no need for a styled confirmation modal
- Upload modal album picker uses `x-mary-select` with search feature enabled

### Empty State Component
- Create a shared `x-empty-state` Blade component with: title, description, and optional action slot
- Icon is NOT part of the component — added separately outside/above the component per page
- Action slot is optional — album show's empty state uses the component without a button
- Used on: library index (empty library), playlist index (no playlists), playlist show (no tracks), album show (no tracks)

### Component Mapping (General)
- `flux:heading` → plain HTML heading tags (`h1`, `h2`, etc.) with Tailwind classes, or `x-mary-header` where appropriate
- `flux:text` → plain `<p>` or `<span>` tags with Tailwind classes
- `flux:button` → `x-mary-button`
- `flux:icon.*` → `x-mary-icon name="*"`
- `flux:link` → `<a>` tag or `x-mary-button` with link prop
- `flux:input` → `x-mary-input`
- `flux:textarea` → `x-mary-textarea`
- `flux:select` → `x-mary-select`
- `flux:callout` → daisyUI alert div or toast (context-dependent)

### Claude's Discretion
- Exact progress bar styling for upload modal
- Upload modal success state layout details
- How to wire flash messages to Mary UI toast component
- daisyUI alert styling details (border, icon, padding)
- Empty state component internal markup and spacing
- Profile page stat layout (cards, grid, inline — whatever fits)

</decisions>

<specifics>
## Specific Ideas

- Upload modal should show success state inside the modal, not auto-close — user wants to see confirmation before dismissing
- Album picker in upload modal should be searchable for users with many albums
- Success messages across the app should use toasts consistently (Phase 8 established this with AddToPlaylistModal)
- Empty state component keeps it simple: title + description + optional action — icons are page-specific

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `AddToPlaylistModal` Livewire component: Pattern template for the new UploadModal (boolean property, wire:model, event dispatch, closeModal method)
- `x-mary-toast`: Already in app.blade.php from Phase 8 — can be used for flash message toasts
- `x-mary-button`, `x-mary-icon`, `x-mary-input`, `x-mary-select`, `x-mary-textarea`: Mary UI components available via `x-mary-` prefix
- `spotify-dark` daisyUI theme: Active across the app

### Established Patterns
- Modal pattern: Livewire boolean + `wire:model` + `$dispatch` for triggering + `closeModal()` for cleanup (Phase 8)
- `@persist('audio-player')` for cross-page component persistence — upload modal in layout needs `wire:navigate` compatibility
- `data-test` attributes on interactive elements — preserve in all migrated pages
- `wire:navigate` on all internal navigation links

### Integration Points
- `resources/views/layouts/app.blade.php` — where UploadModal Livewire component will be placed (alongside AddToPlaylistModal)
- `resources/views/pages/library/index.blade.php` — largest migration (search, songs list, albums grid, upload trigger, empty state)
- `resources/views/pages/library/album-show.blade.php` — album header, track list, play actions
- `resources/views/pages/library/upload-summary.blade.php` — upload results, callouts, badges
- `resources/views/playlists/index.blade.php` — playlist list, empty state
- `resources/views/playlists/show.blade.php` — playlist detail, track list, remove track action
- `resources/views/playlists/create.blade.php` — form with name + description
- `resources/views/playlists/edit.blade.php` — form + danger zone delete
- `resources/views/pages/profile/show.blade.php` — avatar, bio, stats

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 09-page-migration*
*Context gathered: 2026-03-16*
