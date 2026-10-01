# Quick Task 2: Fix audio player shutdown on album detail page - Context

**Gathered:** 2026-03-17
**Status:** Ready for planning

<domain>
## Task Boundary

Bottom audio player works fine when switching pages but shuts down when entering an album detail page. Root cause: album links in library/index.blade.php are missing `wire:navigate`, causing full page reloads that destroy the `@persist`ed player.

</domain>

<decisions>
## Implementation Decisions

### Root cause scope
- Full audit: scan ALL `<a href>` tags across the app and add `wire:navigate` where missing (not just album links)

### Play button behavior
- When clicking a track on album-show while audio is already playing, show a modal dialog asking "Replace current queue with this album?" before switching
- If nothing is playing, play immediately without confirmation

### Claude's Discretion
- Modal implementation details (component choice, wording, styling)

</decisions>

<specifics>
## Specific Ideas

- Album links at library/index.blade.php lines ~205 and ~324 are the known broken links
- All internal navigation links should use `wire:navigate` to preserve `@persist` blocks
- Confirmation modal should only appear when audio is actively playing

</specifics>
