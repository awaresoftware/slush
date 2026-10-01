# Quick Task 3: Fix album track numbering and playback order mismatch - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Task Boundary

Fix album track numbering and playback order mismatch. Tracks display in some order but show wrong track numbers, and clicking a track plays the wrong audio because the playback index doesn't match the displayed position.

Root cause: `sortBy('track_number')` preserves original collection keys, so `$index + 1` shows the original key instead of sequential position, and `index: {{ $index }}` passes the wrong index to the audio player.

</domain>

<decisions>
## Implementation Decisions

### Track number display
- Show the actual `track_number` from file metadata in the # column (like Spotify/Apple Music)
- If track_number metadata is null, fall back to sequential position
- If track title contains leading numbers, consider extracting those as a secondary fallback (future enhancement — not required for this fix)

### Null track number handling
- Tracks without track_number metadata appear at the end of the list
- Sort null-track-number tracks by title within their group

### Claude's Discretion
- Playback index fix approach (use `->values()` to reset keys after sorting)

</decisions>

<specifics>
## Specific Ideas

- Use `$track->track_number ?? ($index + 1)` for display
- Use `->sortBy(...)->values()` to fix key preservation issue
- Custom sort: track_number ascending (nulls last), then by title for null-track-number tracks

</specifics>
