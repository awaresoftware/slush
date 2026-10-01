# Quick Task 5: Optimize track loading with Livewire lazy loading and pagination for 20k+ tracks - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Task Boundary

Fix OOM crash (256MB exhausted) when loading library with 20k+ tracks. Implement infinite scroll, auto-extending playback queue, and shuffle mode.

Current problem: `tracks()` computed property calls `->get()` loading all 20k tracks into memory. Each `<tr>` also embeds the full track IDs array as JSON (20k IDs × 20k rows).

</domain>

<decisions>
## Implementation Decisions

### Pagination UX — Infinite scroll
- Load 50 tracks initially, automatically load more as user scrolls down using `wire:intersect`
- Use Livewire 4's `@island` with `wire:island.append` for efficient DOM appending (avoids re-rendering all loaded tracks)
- Feels seamless like Spotify/Apple Music

### Playback queue scope — Loaded tracks only + auto-extend
- Queue only the currently loaded/visible tracks (no massive DB query)
- Do NOT embed full track IDs array in every `<tr>` — maintain track IDs in Alpine.js `x-data` and update when more tracks load
- **Auto-extend queue:** When playback reaches tracks near the end of queue (e.g., last 2-3 tracks), automatically load 50 more tracks and extend the queue. AudioPlayer should dispatch an event when nearing queue end; library page listens and loads more.

### Shuffle mode
- Add shuffle toggle to the AudioPlayer component
- When enabled, randomize the queue order (Fisher-Yates shuffle in JS/Alpine)
- Maintain original queue order so shuffle can be toggled off
- Shuffle applies to loaded tracks; as more tracks load and extend queue, newly loaded tracks get shuffled into remaining queue

### Album detail page
- Also add pagination to album detail page for consistency
- Most albums have <30 tracks so it'll show all on one page, but albums with 100+ tracks benefit

### Claude's Discretion
- Exact per-page count (50 suggested, adjust if needed)
- Whether to use cursor pagination or offset pagination under the hood
- Loading spinner/skeleton design for infinite scroll

</decisions>

<specifics>
## Specific Ideas

### Livewire 4 features to use:
- `wire:intersect` — trigger `loadMore()` when sentinel element enters viewport
- `@island` with `wire:island.append` — append new track batches without re-rendering existing ones
- `WithPagination` trait if using traditional pagination for album pages
- `#[Lazy]` — consider lazy loading the track list section on initial page load

### Architecture for library index:
- Replace computed `tracks()` with manual page tracking (`$page` property)
- `loadMore()` method: query next 50 tracks, dispatch event with new IDs to Alpine
- Alpine `x-data` holds `trackIds` array, updated on `tracks-loaded` event
- Each row click handler references Alpine's `trackIds` and its own index

### Auto-extend queue:
- AudioPlayer `nextTrack()` checks if current position is within last 3 tracks of queue
- Dispatches `queue-running-low` event
- Library page (or any page with track list) listens and calls `loadMore()`, then dispatches updated queue to AudioPlayer via `queue-extended` event

### Search behavior with infinite scroll:
- When search input changes, reset loaded tracks and page counter
- Load fresh first batch matching the search query

</specifics>
