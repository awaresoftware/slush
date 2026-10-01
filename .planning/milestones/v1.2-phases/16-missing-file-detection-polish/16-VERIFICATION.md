---
phase: 16-missing-file-detection-polish
verified: 2026-03-19T18:00:00Z
status: passed
score: 12/12 must-haves verified
re_verification: false
---

# Phase 16: Missing File Detection Polish — Verification Report

**Phase Goal:** Missing file detection polish — detect unavailable tracks, show UI indicators, allow re-linking
**Verified:** 2026-03-19T18:00:00Z
**Status:** PASSED
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Tracks whose files no longer exist on disk are marked is_available=false after a rescan | VERIFIED | `ScanLinkedFolder.php` lines 106–110: availability loop calls `file_exists()` and sets `is_available=false` |
| 2 | Tracks whose files reappear at original path are auto-restored to is_available=true | VERIFIED | `ScanLinkedFolder.php` lines 111–113: `elseif (!$track->is_available)` branch restores to true |
| 3 | LinkedFolder stores unavailable_count after each scan | VERIFIED | `ScanLinkedFolder.php` line 119: `'unavailable_count' => $unavailableCount` in final `folder->update()` |
| 4 | StreamController returns 404 when file does not exist on disk | VERIFIED | `StreamController.php` lines 33–36: `file_exists()` check, marks `is_available=false`, returns JSON 404 |
| 5 | Unavailable tracks display dimmed row with Unavailable badge in library, album detail, and playlist detail | VERIFIED | `opacity-50` class and `<x-mary-badge value="Unavailable" class="badge-error badge-sm" />` found in all three views |
| 6 | Play button is hidden (not disabled) for unavailable tracks | VERIFIED | `@if ($track->is_available)` wraps play button in all three views |
| 7 | Source filter dropdown includes Unavailable option and filters correctly | VERIFIED | `<option value="unavailable">Unavailable</option>` in index.blade.php; `->when($this->sourceFilter === 'unavailable', fn ($q) => $q->unavailable())` in Volt component |
| 8 | loadQueue excludes unavailable tracks | VERIFIED | `AudioPlayer.php` line 53: `->where('is_available', true)` in owned-IDs query |
| 9 | Alpine player shows skip toast and advances on 404 error | VERIFIED | `audio-player.blade.php` lines 314–331: `onAudioError` dispatches `mary-toast` then calls `nextTrack()` |
| 10 | Re-link button appears on unavailable tracks in library, album detail, and playlist detail views | VERIFIED | `data-test="relink-btn"` with `$dispatch('open-relink-modal', { trackId: ... })` found in all three views |
| 11 | User can re-link a single unavailable track by providing a new file path | VERIFIED | `RelinkTrackModal.php`: `relink()` validates path, checks file existence, updates `file_path`, `file_hash`, `is_available=true` |
| 12 | Re-link modal shows hash mismatch warning when new file differs from original | VERIFIED | `RelinkTrackModal.php` lines 72–76: hash comparison sets `$hashMismatch=true`; view shows warning alert with "Re-link Anyway" button |
| 13 | User can bulk-update all track paths in a folder by changing the folder path prefix | VERIFIED | `linked-folders.blade.php` line 198: `$normalisedNew.substr($track->file_path, $oldPathLength)` prefix replacement loop |
| 14 | Re-linked track is restored to is_available=true | VERIFIED | `RelinkTrackModal.php` line 81: `'is_available' => true` in update call |
| 15 | Rescan toast shows unavailable count in format: Folder scanned: X tracks found, Y unavailable | VERIFIED | `linked-folders.blade.php` line 244: `'Folder scanned: :count tracks found, :unavailable unavailable'` |

**Score:** 15/15 truths verified (12 declared as must-haves across the three plans; 15 derived from plan truths)

---

### Required Artifacts

| Artifact | Status | Details |
|----------|--------|---------|
| `database/migrations/2026_03_19_162051_add_is_available_to_tracks_table.php` | VERIFIED | Exists, contains `is_available` column |
| `database/migrations/2026_03_19_162058_add_unavailable_count_to_linked_folders_table.php` | VERIFIED | Exists, contains `unavailable_count` column |
| `app/Models/Track.php` | VERIFIED | `is_available` in fillable + casts; `scopeUnavailable` and `scopeAvailable` defined |
| `app/Jobs/ScanLinkedFolder.php` | VERIFIED | Contains `is_available` availability loop after filesystem scan |
| `app/Http/Controllers/StreamController.php` | VERIFIED | `file_exists()` guard marks track unavailable, returns 404 |
| `resources/views/pages/library/index.blade.php` | VERIFIED | `is_available`, `opacity-50`, Unavailable badge, re-link button, Unavailable filter option all present |
| `resources/views/pages/library/album-show.blade.php` | VERIFIED | Same unavailable track indicators applied |
| `resources/views/playlists/show.blade.php` | VERIFIED | Same unavailable track indicators applied |
| `app/Livewire/AudioPlayer.php` | VERIFIED | `where('is_available', true)` filter in `loadQueue()` |
| `resources/views/livewire/audio-player.blade.php` | VERIFIED | `onAudioError` dispatches `mary-toast` and calls `nextTrack()` |
| `app/Livewire/RelinkTrackModal.php` | VERIFIED | 114 lines; open/relink/confirmMismatch/closeModal; `#[On('open-relink-modal')]` listener |
| `resources/views/livewire/relink-track-modal.blade.php` | VERIFIED | 45 lines; old path display, new path input, hash warning alert, Re-link Anyway button |
| `resources/views/layouts/app/sidebar.blade.php` | VERIFIED | `<livewire:relink-track-modal />` included alongside add-to-playlist-modal |
| `resources/views/pages/settings/⚡linked-folders.blade.php` | VERIFIED | `openUpdatePathModal`, `updateFolderPath`, `pollFolders`, `unavailable_count` badge, rescan toast with locked format |
| `tests/Feature/Library/LibraryUnavailableTrackTest.php` | VERIFIED | 106 lines — substantially above 30-line minimum |
| `tests/Feature/Livewire/RelinkTrackModalTest.php` | VERIFIED | 139 lines — substantially above 30-line minimum |
| `tests/Feature/Settings/LinkedFoldersSettingsTest.php` | VERIFIED | 308 lines — extended with bulk path update and toast tests |
| `tests/Feature/ScanLinkedFolderTest.php` | VERIFIED | Contains availability detection and restoration tests |

---

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `ScanLinkedFolder.php` | `Track.php` | `file_exists()` → `is_available` update | WIRED | Lines 106–113 use `file_exists($track->file_path)` and call `$track->update(['is_available' => false/true])` |
| `Library/Index (Volt)` | `Track.php` | `sourceFilter === 'unavailable'` → `scopeUnavailable` | WIRED | `->when($this->sourceFilter === 'unavailable', fn ($q) => $q->unavailable())` confirmed in index.blade.php |
| `AudioPlayer.php` | `Track.php` | `loadQueue` filters `where is_available=true` | WIRED | Line 53: `->where('is_available', true)` in owned-IDs query |
| `RelinkTrackModal.php` | `Track.php` | Updates `file_path`, `file_hash`, `is_available` on track | WIRED | Lines 78–82: `$track->update(['file_path' => ..., 'file_hash' => ..., 'is_available' => true])` |
| `LinkedFolders (Volt)` | `Track.php` | Bulk prefix replacement via `substr` | WIRED | Line 198: `$normalisedNew.substr($track->file_path, $oldPathLength)` inside track loop |

---

### Requirements Coverage

| Requirement | Source Plans | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| LIBR-05 | 16-01, 16-02 | Missing files detected and marked as unavailable with visual indicator | SATISFIED | Backend detection in ScanLinkedFolder + StreamController; visual indicators (opacity-50, badge-error) in all three track listing views |
| LIBR-06 | 16-03 | User can re-link a moved file by updating its path | SATISFIED | RelinkTrackModal (individual re-link with hash warning) + bulk folder path update in settings, both wired and tested |

Both requirements are marked complete in `REQUIREMENTS.md` (lines 76–77).

---

### Anti-Patterns Found

No blocker or warning anti-patterns detected in phase-modified files.

- No TODO/FIXME/PLACEHOLDER comments in new code
- No stub `return null` / `return []` implementations
- No empty handlers
- ScanLinkedFolder availability loop is substantive (file_exists + conditional update)
- RelinkTrackModal has full validation, hash check, confirm step, and model update

---

### Human Verification Required

#### 1. Alpine skip toast visibility

**Test:** Load a queue that includes a track whose file has been deleted. Begin playback. Observe when the player attempts to play the missing track.
**Expected:** A yellow warning toast appears with message `Skipped "Track Title" — file unavailable` and playback automatically advances to the next track.
**Why human:** The `onAudioError` handler fires on a real browser audio error event (HTTP 404 from StreamController); cannot be triggered in a Pest test.

#### 2. Re-link modal open-relink-modal event dispatch

**Test:** In the library view, find a track marked Unavailable. Click the re-link (chain) icon button.
**Expected:** The RelinkTrackModal opens, showing the current (broken) file path in read-only display and an empty "New file path" input.
**Why human:** Alpine `$dispatch('open-relink-modal', ...)` triggering a Livewire `#[On]` listener across component boundaries requires a real browser.

#### 3. Hash mismatch warning UI

**Test:** Open the re-link modal for an unavailable track. Enter a path to a valid file whose content differs from the original (different hash). Click "Re-link".
**Expected:** The warning alert `"File hash doesn't match original..."` appears with a "Re-link Anyway" button. The primary "Re-link" button disappears.
**Why human:** Requires a real filesystem with two different files at known paths.

---

## Gaps Summary

None. All must-haves across the three plans are verified. The phase goal — detect unavailable tracks, show UI indicators, allow re-linking — is fully achieved with substantive, wired implementations and test coverage across all three plans.

---

_Verified: 2026-03-19T18:00:00Z_
_Verifier: Claude (gsd-verifier)_
