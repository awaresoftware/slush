# Phase 7: CSS Foundation & App Shell - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Add Mary UI infrastructure and migrate the app layout shell (sidebar, header, user menu) and audio player to Mary UI components. Flux CSS import (`flux.css`) stays until Phase 11, but Flux-specific CSS rules and @source lines are removed in this phase. `@fluxScripts` stays until Phase 11 so unmigrated Flux components keep working.

**Updated goal (differs from original roadmap):** Phase 7 does NOT remove Flux CSS/JS entirely. It adds Mary UI alongside Flux, migrates the layout and player, and cleans up Flux-specific overrides. Full Flux removal is Phase 11.

</domain>

<decisions>
## Implementation Decisions

### Migration Strategy
- Keep Flux CSS (`flux.css` import) and `@fluxScripts` until Phase 11 — all Flux components keep working throughout migration
- Mary UI components win on CSS conflicts — if daisyUI and Flux custom properties clash, resolve in favor of Mary UI; Flux components may look slightly off during transition
- Remove Flux-specific CSS rules from app.css in Phase 7 (`[data-flux-field]`, `[data-flux-label]`, `input:focus[data-flux-control]` selectors)
- Remove Flux `@source` directives from app.css in Phase 7 (`flux-pro/stubs`, `flux/stubs`)
- Add Mary UI `@source` directive for Tailwind v4 scanning

### Sidebar Navigation
- Keep same 3 nav items: Dashboard, Library, Playlists
- Drop the "Platform" group heading — cleaner look without section labels
- Same icons: home, musical-note, queue-list
- Move user menu to top of sidebar (below logo), not bottom

### Mobile Navigation
- Replace hamburger + slide-in drawer with a fixed bottom nav bar
- Bottom nav bar shows icons for Dashboard, Library, Playlists
- Bottom nav positioned below the audio player (nav at very bottom, player above it)
- Both visible simultaneously when a track is playing

### Audio Player
- 1:1 icon swap: `flux:icon.name` → `x-icon name="name"` (same Heroicon names)
- Use daisyUI range classes for seek and volume sliders (`range range-xs range-primary` or similar)
- Keep white circle play/pause button (bg-white text-zinc-900), not primary green
- Simplify mobile layout: hide volume control on mobile (mute only via icon tap), show only track info + play controls + seek bar
- Keep overall desktop layout: [track info] [controls + seek] [volume]

### Theme & CSS Cleanup
- Remove Flux-specific overrides from `@theme` block (accent-content, accent-foreground, zinc palette) — keep only font-sans
- Remove the `.dark` layer overrides that target Flux accent variables
- Remove the `@layer base` border-color reset (Flux-specific)
- Keep daisyUI spotify-dark theme colors as-is (minor tweaks at Claude's discretion)

### Claude's Discretion
- Minor color tweaks to spotify-dark theme if needed for better Mary UI component rendering
- How to handle the `@custom-variant dark` directive (keep if needed, remove if daisyUI handles dark mode)
- Mary UI sidebar component configuration details
- Bottom nav bar icon styling and active state indicator

</decisions>

<specifics>
## Specific Ideas

- Bottom nav bar on mobile is a deliberate choice for an "app-like feel" — not just a drawer replacement
- User menu at top of sidebar signals importance — profile/settings/logout should be prominent
- Audio player should feel minimal on mobile — volume control hidden, just the essentials

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `audioPlayer()` Alpine.js function: Full playback logic (play/pause, seek, volume, queue navigation) — keep entirely, only change template markup
- `desktop-user-menu.blade.php`: Standalone component, will be rewritten with Mary UI dropdown
- `spotify-dark` daisyUI theme: Already defined in app.css with correct color values

### Established Patterns
- Audio player uses `@persist('audio-player')` for cross-page persistence — must preserve this pattern
- Sidebar layout wraps `flux:main` with `pb-28` for player clearance — will need equivalent bottom padding for player + bottom nav
- `wire:navigate` on all nav links — must preserve for SPA-like navigation
- `data-test` attributes on interactive elements (e.g., `data-test="logout-button"`) — preserve for testing

### Integration Points
- `resources/views/layouts/app/sidebar.blade.php` — main layout, wraps content in sidebar shell
- `resources/views/layouts/app.blade.php` — outer shell with sidebar, player, and `@fluxScripts`
- `resources/views/layouts/app/header.blade.php` — header variant (not actively used for sidebar layout but exists)
- `resources/views/components/desktop-user-menu.blade.php` — user dropdown component
- `resources/views/livewire/audio-player.blade.php` — audio player Livewire component
- `resources/css/app.css` — CSS entry point with theme and Flux imports

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 07-css-foundation-app-shell*
*Context gathered: 2026-03-13*
