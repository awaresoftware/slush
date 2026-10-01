# Quick Task 4: Add mobile touch playback to all track lists - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Task Boundary

Make track playback work on mobile/touch devices. Currently, play buttons are hidden behind CSS `group-hover` which doesn't work on touch screens. On mobile, tapping a track row should trigger playback.

Original concern was that local tracks weren't playable remotely — investigation revealed the StreamController already handles local file streaming correctly. The actual issue is that play buttons are invisible on touch devices.

</domain>

<decisions>
## Implementation Decisions

### Mobile playback interaction
- Tapping anywhere on the track row starts playback (replaces/adds to queue with album/playlist context)
- Action buttons (add-to-playlist, relink, remove) remain as separate tap targets and should NOT trigger row playback
- Unavailable tracks should not trigger playback on tap

### Scope
- Apply to ALL track listing views:
  - `resources/views/pages/library/album-show.blade.php` (album detail)
  - `resources/views/playlists/show.blade.php` (playlist detail)
  - `resources/views/pages/library/index.blade.php` (library search results + tab tracks)

### Claude's Discretion
- Implementation approach (Alpine.js click handler on row with event.target check, or other)
- Whether to also make hover action buttons visible on mobile via Tailwind responsive utilities

</decisions>

<specifics>
## Specific Ideas

- Use Alpine.js `x-on:click` on the `<tr>` element to dispatch `play-track`
- Prevent the row click from firing when action buttons are clicked (use `event.target.closest('button')` check)
- Only trigger for available tracks (`$track->is_available`)
- Consider adding `cursor-pointer` on mobile for available track rows

</specifics>
