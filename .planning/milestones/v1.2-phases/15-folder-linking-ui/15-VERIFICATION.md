---
phase: 15-folder-linking-ui
verified: 2026-03-19T00:30:00Z
status: passed
score: 11/11 must-haves verified
re_verification:
  previous_status: passed
  previous_score: 10/10
  gaps_closed:
    - "Removing a linked folder also deletes albums that become empty"
    - "Albums with tracks from other sources are NOT deleted when a folder is removed"
  gaps_remaining: []
  regressions: []
human_verification:
  - test: "Link a real local folder, scan it, remove it, then navigate to Albums view."
    expected: "Albums whose only tracks came from the removed folder no longer appear in the list."
    why_human: "UAT test 5 (severity: major) caught this failure; the fix is now in code and 18 tests pass, but a human pass formally closes the UAT issue."
---

# Phase 15: Folder Linking UI Verification Report

**Phase Goal:** User-facing Livewire components for linking folders, library integration, and source badges
**Verified:** 2026-03-19
**Status:** passed
**Re-verification:** Yes — after UAT gap closure (Plan 15-03)

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|---------|
| 1 | User can enter a folder path and link it to their library | VERIFIED | `linkFolder()` in `linked-folders.blade.php` validates path, creates `LinkedFolder`, dispatches `ScanLinkedFolder` |
| 2 | User can see multiple linked folders in a managed list | VERIFIED | `#[Computed] folders()` returns all user folders; `@forelse` renders each with `wire:key` |
| 3 | Scanning folder shows a Scanning badge with polling | VERIFIED | `badge-warning` + spinner shown when `FolderStatus::Scanning`; `wire:poll.3s` div conditionally rendered |
| 4 | User can remove a folder via modal showing impact summary | VERIFIED | `confirmRemove()` + `removeFolder()` wired to `x-mary-modal`; shows track count and `affectedPlaylistCount` |
| 5 | User can rescan and edit folder name | VERIFIED | `rescan()` dispatches `ScanLinkedFolder`; `editName()` updates name field |
| 6 | Local tracks appear in library alongside uploaded tracks | VERIFIED | `tracks()` computed method in `index.blade.php` queries all user tracks regardless of source; `sourceFilter = 'all'` default |
| 7 | Local tracks show a 'Local' badge; uploaded tracks show no badge | VERIFIED | `@if ($track->source_type === \App\Enums\SourceType::Local)` conditional `x-mary-badge` in all three views |
| 8 | Source badge appears on library, album detail, and playlist detail pages | VERIFIED | Badge present in `index.blade.php`, `album-show.blade.php`, `playlists/show.blade.php` |
| 9 | Source filter dropdown filters tracks by All/Uploaded/Local | VERIFIED | `sourceFilter` property with `->when()` scopes calling `->uploaded()` / `->local()` on `tracks()` query |
| 10 | Contextual empty state shown when filter yields no results | VERIFIED | Three-branch `@if` on `sourceFilter` in empty-library and filtered-tab-empty cases |
| 11 | Removing a linked folder also deletes albums that become empty | VERIFIED | `Album::where('user_id', auth()->id())->whereDoesntHave('tracks')->delete()` runs after `$folder->delete()` at lines 156-158; albums with remaining tracks from other sources are preserved |

**Score:** 11/11 truths verified

---

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `resources/views/pages/settings/⚡linked-folders.blade.php` | Linked folders settings page with orphaned album cleanup | VERIFIED | Contains `whereDoesntHave('tracks')->delete()` after `$folder->delete()` at lines 156-158 |
| `tests/Feature/Settings/LinkedFoldersSettingsTest.php` | Feature tests including orphaned album cleanup | VERIFIED | 18 tests pass; tests at lines 290-337 cover deletion and preservation cases |
| `resources/views/pages/library/index.blade.php` | Source badges + source filter dropdown | VERIFIED | Contains `badge-ghost`, `sourceFilter`, `SourceType::Local` conditional |
| `tests/Feature/Library/LibrarySourceBadgeTest.php` | Tests for badge display | VERIFIED | 6 tests across library/album/playlist |
| `tests/Feature/Library/LibrarySourceFilterTest.php` | Tests for source filtering | VERIFIED | 6 tests including contextual empty states |
| `resources/views/pages/library/album-show.blade.php` | Source badge in album track listing | VERIFIED | Badge present |
| `resources/views/playlists/show.blade.php` | Source badge in playlist track listing | VERIFIED | Badge present |
| `routes/settings.php` | Route for linked-folders settings page | VERIFIED | `Route::livewire('settings/linked-folders', ...)` under `auth` middleware |
| `resources/views/pages/settings/layout.blade.php` | "Linked Folders" menu item in settings nav | VERIFIED | `x-mary-menu-item` with `route('settings.linked-folders')` |

---

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `removeFolder()` | `Album::whereDoesntHave('tracks')->delete()` | Post-deletion cleanup at lines 156-158 | WIRED | Runs after `$folder->delete()` scoped to `auth()->id()`; two dedicated tests confirm behavior |
| `⚡linked-folders.blade.php` | `App\Jobs\ScanLinkedFolder` | `ScanLinkedFolder::dispatch()` in `linkFolder()` and `rescan()` | WIRED | Dispatched on both create and rescan |
| `routes/settings.php` | `⚡linked-folders.blade.php` | `Route::livewire('settings/linked-folders', ...)` | WIRED | Route registered, component name `pages::settings.linked-folders` |
| `index.blade.php` | `App\Enums\SourceType` | `$track->source_type === \App\Enums\SourceType::Local` | WIRED | Present in both track table locations |
| `index.blade.php` | Track model scopes | `->when($this->sourceFilter === 'uploaded', fn ($q) => $q->uploaded())` + local scope | WIRED | Both scopes applied in `tracks()` computed method |

---

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|---------|
| FOLD-01 | 15-01-PLAN | User can add a local folder path to link to their library | SATISFIED | `linkFolder()` validates and creates `LinkedFolder`; tests pass |
| FOLD-03 | 15-01-PLAN | User can link multiple folders from different locations | SATISFIED | No uniqueness constraint; test "user can link multiple folders" confirms both appear |
| FOLD-05 | 15-01-PLAN, 15-03-PLAN | User can see scan progress while folder is being processed | SATISFIED | `badge-warning` + `wire:poll.3s`; gap closure also added orphaned album cleanup under this requirement ID |
| FOLD-06 | 15-01-PLAN | User can remove a linked folder (tracks and empty albums removed from library) | SATISFIED | `removeFolder()` deletes folder (cascade removes tracks), then deletes orphaned albums; 18 tests pass |
| LIBR-01 | 15-02-PLAN | Linked tracks appear in existing library alongside uploaded tracks | SATISFIED | `tracks()` query returns all user tracks; default `sourceFilter = 'all'` shows both |
| LIBR-02 | 15-02-PLAN | Tracks display source badge indicating local-linked vs uploaded | SATISFIED | Conditional `x-mary-badge` on `SourceType::Local` in library, album, and playlist views |
| LIBR-03 | 15-02-PLAN | Linked tracks work with existing search, playlists, and queue | SATISFIED | Search filter applies to all tracks regardless of source; play dispatch present |

---

### Anti-Patterns Found

None. No TODO/FIXME/placeholder comments, no empty implementations, no stub handlers. All CRUD methods contain real database operations.

---

### Human Verification Required

#### 1. Final UAT recheck — remove folder, verify no empty albums

**Test:** Link a real local folder with audio files. After the scan completes, remove the folder. Navigate to the Albums view.
**Expected:** Albums that contained only tracks from the removed folder no longer appear in the list.
**Why human:** UAT test 5 (severity: major) caught this failure. The fix is implemented (`whereDoesntHave('tracks')->delete()`) and 18 automated tests now pass, but a final human visual pass closes UAT test 5 formally.

---

### Re-verification Summary

The initial VERIFICATION.md (2026-03-18) returned `passed` based on automated code inspection across 10 truths. UAT subsequently identified a major gap: removing a linked folder left behind empty albums in the library. Plan 15-03 was executed, adding `Album::whereDoesntHave('tracks')->delete()` to `removeFolder()` in `resources/views/pages/settings/⚡linked-folders.blade.php` (lines 156-158), along with two new tests in `tests/Feature/Settings/LinkedFoldersSettingsTest.php` covering both the deletion case and the preservation case (albums with tracks from other sources survive).

All 18 LinkedFolders tests now pass. The truth count grows from 10 to 11 to include the orphaned album cleanup behavior. The UAT gap is closed in code; one human verification item remains to formally close UAT test 5.

---

_Verified: 2026-03-19_
_Verifier: Claude (gsd-verifier)_
