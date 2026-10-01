---
status: resolved
trigger: "Investigate why a re-linked track (via RelinkTrackModal) shows as 'local' source type and gets skipped during playback when the new file path is outside any linked folder."
created: 2026-03-19T00:00:00Z
updated: 2026-03-19T00:00:00Z
---

## Current Focus

hypothesis: StreamController aborts 403 when re-linked track's file_path is outside its linkedFolder path
test: confirmed via code reading
expecting: n/a - root cause confirmed
next_action: return diagnosis

## Symptoms

expected: Re-linked track plays normally after relinking to a new file path
actual: Track shows as available but gets skipped during playback
errors: StreamController returns 403 when file_path is outside linked folder
reproduction: Re-link a "local" source_type track to a path outside any linked folder
started: Always existed - design gap in RelinkTrackModal

## Eliminated

(none needed - root cause found on first hypothesis)

## Evidence

- timestamp: 2026-03-19T00:00:00Z
  checked: RelinkTrackModal::relink()
  found: Only updates file_path, file_hash, is_available. Does NOT update source_type or linked_folder_id.
  implication: A track with source_type=local retains its linked_folder_id after relink, even if new path is outside that folder.

- timestamp: 2026-03-19T00:00:00Z
  checked: StreamController::__invoke()
  found: For non-uploaded tracks (source_type=local), line 26 checks `str_starts_with($track->file_path, $track->linkedFolder->path)`. If file_path is outside the linked folder, it aborts 403.
  implication: This is the direct cause of playback failure. The stream endpoint refuses to serve the file.

- timestamp: 2026-03-19T00:00:00Z
  checked: AudioPlayer::loadQueue()
  found: Only filters on user_id and is_available. No source_type or linked folder filtering. Track IS included in the queue.
  implication: loadQueue is NOT the problem. The track enters the queue fine but fails at stream time.

## Resolution

root_cause: |
  StreamController (line 26) validates that local-source tracks have a file_path within their linkedFolder's path.
  RelinkTrackModal only updates file_path/file_hash/is_available but never updates linked_folder_id or source_type.
  When a track is re-linked to a path outside its original linked folder, the StreamController security check fails with 403.

  Two sub-issues:
  1. RelinkTrackModal should determine if the new path falls within an existing linked folder and update linked_folder_id accordingly.
  2. If the new path is NOT within any linked folder, the track needs a strategy: either reject the relink, or allow "orphaned local" files by relaxing the StreamController check for re-linked tracks.

fix: (not yet applied)
verification: (not yet done)
files_changed: []
