---
status: resolved
trigger: "Removing a linked folder leaves behind empty albums"
created: 2026-03-19T00:00:00Z
updated: 2026-03-19T12:00:00Z
---

## Current Focus

hypothesis: Tracks are cascade-deleted by the DB when LinkedFolder is deleted, but nobody cleans up albums that become empty
test: Confirmed by reading migration and removeFolder() code
expecting: N/A - root cause confirmed
next_action: Implement cleanup of empty albums after folder deletion

## Symptoms

expected: Removing a linked folder should delete its tracks AND any albums left with zero tracks
actual: Folder and tracks are deleted, but empty albums remain in the database
errors: None (silent data leak)
reproduction: Link folder, scan (creates albums+tracks), remove folder, check albums table
started: Since linked folder feature was added

## Eliminated

(none needed - root cause found on first hypothesis)

## Evidence

- timestamp: 2026-03-19
  checked: removeFolder() in linked-folders.blade.php (line 144-160)
  found: Calls $folder->delete() with no album cleanup logic
  implication: The only deletion mechanism is the DB cascade

- timestamp: 2026-03-19
  checked: Migration 2026_03_18_174352 (linked_folder_id FK on tracks)
  found: `linked_folder_id` FK has `cascadeOnDelete()` - deleting a LinkedFolder cascade-deletes its tracks
  implication: Tracks ARE deleted correctly via DB cascade

- timestamp: 2026-03-19
  checked: Migration 2026_03_04_175951 (album_id FK on tracks)
  found: `album_id` FK has `nullOnDelete()` - deleting an album nulls the track's album_id
  implication: There is NO reverse cascade - deleting tracks does NOT delete albums

- timestamp: 2026-03-19
  checked: Album model and LinkedFolder model
  found: No model events (deleting/deleted) that clean up empty albums
  implication: Nothing in the codebase handles orphaned album cleanup

## Resolution

root_cause: When a LinkedFolder is deleted, the DB cascade deletes all its tracks. However, the tracks' `album_id` FK uses `nullOnDelete()` (not cascadeOnDelete), and crucially the cascade goes the WRONG direction here - it's the tracks being deleted, not the albums. The DB simply removes the track rows. The albums table has no FK pointing to tracks, so albums are never touched. No application-level cleanup exists either.

fix: After `$folder->delete()` in `removeFolder()`, delete all albums for the user that have zero tracks remaining. Something like:
```php
Album::where('user_id', auth()->id())
    ->whereDoesntHave('tracks')
    ->delete();
```

Alternatively, add a model `deleting` event on LinkedFolder that collects album IDs from its tracks before deletion, then a `deleted` event that removes any of those albums that are now empty.

verification: (empty - not yet applied)
files_changed: []
