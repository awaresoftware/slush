# Quick Task 1: Fix sidebar layout - Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

<domain>
## Task Boundary

Fix sidebar layout - sidebar should be full height with content beside it, not stacked vertically. Currently the sidebar ends at mid-screen and content renders below it at full width instead of beside it.

</domain>

<decisions>
## Implementation Decisions

### Audio Player Padding
- Move pb-28 from the wrapper div onto the flux:main component directly
- Keeps padding scoped to content area only

### Mobile Header Placement
- Move the mobile header (flux:header lg:hidden) inside the flux:main block
- It should flow naturally with content on mobile while not breaking the desktop sidebar layout

### Claude's Discretion
- None — all gray areas resolved

</decisions>

<specifics>
## Specific Ideas

- The root cause is in `resources/views/layouts/app/sidebar.blade.php` — the `{{ $slot }}` (containing `<flux:main>`) is wrapped in a `<div class="pb-28">` which breaks Flux's expected sibling layout of `<flux:sidebar>` + `<flux:main>`
- Remove the wrapper div, let `<flux:main>` be a direct sibling of `<flux:sidebar>`
- Move `pb-28` onto flux:main
- Move the mobile `<flux:header>` inside flux:main

</specifics>
