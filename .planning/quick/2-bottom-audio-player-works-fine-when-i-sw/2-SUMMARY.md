---
phase: quick-2
plan: 01
subsystem: audio-player
tags: [wire-navigate, spa-navigation, audio-player, livewire]
dependency-graph:
  requires: []
  provides: [persistent-audio-player, replace-queue-modal]
  affects: [library-index, playlists-show, upload-modal, audio-player]
tech-stack:
  added: []
  patterns: [wire-navigate-spa, alpine-confirmation-modal]
key-files:
  created: []
  modified:
    - resources/views/pages/library/index.blade.php
    - resources/views/playlists/show.blade.php
    - resources/views/livewire/upload-modal.blade.php
    - resources/views/livewire/audio-player.blade.php
decisions:
  - Gate replace-queue modal behind isPlaying state rather than always showing
  - Use Alpine x-show modal within audioPlayer scope instead of separate Livewire component
metrics:
  duration: ~5m
  completed: 2026-03-17
---

# Quick Task 2 Plan 01: Fix Audio Player Persistence on Navigation

Added wire:navigate to 4 internal links missing it and built an Alpine confirmation modal that gates queue replacement when audio is already playing.

## Completed Tasks

| Task | Description | Commit | Key Files |
|------|-------------|--------|-----------|
| 1 | Add wire:navigate to all missing internal app-layout links | 244142f | index.blade.php, show.blade.php, upload-modal.blade.php |
| 2 | Add replace-queue confirmation modal to audio player | f468124 | audio-player.blade.php |
| 3 | Human verification checkpoint | -- | Approved by user |

## What Changed

### wire:navigate Additions (Task 1)
- **Library index** (search results + albums tab): 2 album card `<a>` tags now use `wire:navigate`
- **Playlist show**: Breadcrumb back-link now uses `wire:navigate`
- **Upload modal**: "View Uploads" link now uses `wire:navigate`

### Replace-Queue Confirmation Modal (Task 2)
- `onPlayTrack()` checks `isPlaying` before loading new tracks
- If playing, stores pending detail and shows modal
- `confirmReplace()` loads the new queue; `cancelReplace()` dismisses
- When not playing, tracks start immediately with no modal

## Deviations from Plan

None - plan executed exactly as written.

## Decisions Made

1. **Modal scope**: Kept the confirmation modal inside the `audioPlayer` Alpine component scope rather than creating a separate component, since it needs direct access to `isPlaying` and the pending detail state.
2. **Gating logic**: Only gate on `isPlaying` (not `hasTrack`), so paused tracks get replaced without confirmation.
