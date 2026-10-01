---
status: resolved
trigger: "tracks are not sorted by track number, and if played system plays different track than selected"
created: 2026-03-17T00:00:00Z
updated: 2026-03-17T00:00:00Z
---

## Current Focus

hypothesis: confirmed - two bugs found in album-show.blade.php
test: direct code inspection
expecting: fix applied to sort and index alignment
next_action: done

## Symptoms

expected: Tracks displayed in track_number order; pressing play on a track plays that track
actual: Tracks displayed sorted by title; pressing play dispatches an index into a title-sorted array, mismatching the track ID array built with a different sort
errors: none (silent logic bug)
reproduction: Visit any album show page; track order is alphabetical not by track number; play a track and a different one plays
started: introduced when sortBy('title') was used instead of sortBy('track_number')

## Eliminated

- hypothesis: bug is in the Track model relationship
  evidence: $album->load('tracks') loads all tracks fine; ordering is applied in the view not the model
  timestamp: 2026-03-17T00:00:00Z

## Evidence

- timestamp: 2026-03-17T00:00:00Z
  checked: resources/views/pages/library/album-show.blade.php line 46
  found: $albumTrackIds = $album->tracks->sortBy('title')->pluck('id')->all()
  implication: The play-track trackIds array is built by sorting tracks by title

- timestamp: 2026-03-17T00:00:00Z
  checked: resources/views/pages/library/album-show.blade.php line 58
  found: @foreach ($album->tracks->sortBy('title') as $index => $track)
  implication: Tracks are displayed sorted by title, not track_number - this is bug 1

- timestamp: 2026-03-17T00:00:00Z
  checked: resources/views/pages/library/album-show.blade.php line 64
  found: $dispatch('play-track', { trackIds: @js($albumTrackIds), index: {{ $index }} })
  implication: $index is the foreach key from sortBy('title'), and $albumTrackIds is also from sortBy('title') - they DO match each other, BUT the visual display order (row number shown as $index + 1) corresponds to title-sort position, not track_number. Bug 2 is that both sort by title instead of track_number.

- timestamp: 2026-03-17T00:00:00Z
  checked: Track model for track_number field
  found: Field is track_number on the tracks table
  implication: Should use sortBy('track_number') in both places

## Resolution

root_cause: Both the $albumTrackIds array and the @foreach loop use sortBy('title') instead of sortBy('track_number'). This causes: (1) tracks displayed alphabetically by title rather than by track number, and (2) the play index dispatched matches the title-sorted position, not the track number position — so the track that plays is whichever falls at that index in title order, not what the user sees as position N by track number.
fix: Change both sortBy('title') calls on lines 46 and 58 to sortBy('track_number')
verification: pending
files_changed:
  - resources/views/pages/library/album-show.blade.php
