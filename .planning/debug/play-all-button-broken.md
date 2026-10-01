---
status: awaiting_human_verify
trigger: "Play All button on playlist show page does nothing when clicked"
created: 2026-03-12T00:00:00Z
updated: 2026-03-12T00:00:00Z
---

## Current Focus

hypothesis: Play All button uses x-on:click and $dispatch without any x-data Alpine scope, so Alpine never binds the handler
test: Compare with working album-show page (which is a Livewire component providing Alpine scope)
expecting: Adding x-data to a parent element will make the button functional
next_action: Add x-data to a wrapper div around the Play All button area

## Symptoms

expected: Clicking "Play All" dispatches play-track event and starts playback of all playlist tracks
actual: Nothing happens when clicking the button
errors: None visible
reproduction: Navigate to any playlist show page with tracks, click Play All
started: Likely since playlist show was implemented as a plain Blade view

## Eliminated

(none)

## Evidence

- timestamp: 2026-03-12T00:00:00Z
  checked: playlist show.blade.php structure
  found: Uses <x-layouts::app> - a plain Blade component view, NOT a Livewire component
  implication: No automatic Alpine scope from Livewire

- timestamp: 2026-03-12T00:00:00Z
  checked: album-show.blade.php structure
  found: Is a Livewire component (class extends Component) - Livewire provides Alpine x-data automatically on root div
  implication: This is why $dispatch works in album-show but not playlist show

- timestamp: 2026-03-12T00:00:00Z
  checked: Play All button markup (line 24 of show.blade.php)
  found: Uses x-on:click="$dispatch('play-track', ...)" but no parent element has x-data
  implication: Alpine never initializes on this element, so the click handler is never bound

- timestamp: 2026-03-12T00:00:00Z
  checked: Per-track play buttons (line 73 of show.blade.php)
  found: Same issue - also use x-on:click without x-data scope
  implication: Individual track play buttons are also broken for the same reason

## Resolution

root_cause: The playlist show page (resources/views/playlists/show.blade.php) is a plain Blade view, not a Livewire component. Alpine directives (x-on:click, $dispatch) require an Alpine component scope (x-data) to function. No element in the view has x-data, so Alpine never binds the click handlers on the Play All button or the per-track play buttons.
fix: Add x-data to a parent element wrapping the buttons that use Alpine directives
verification: (pending)
files_changed:
  - resources/views/playlists/show.blade.php
