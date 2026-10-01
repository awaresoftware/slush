---
status: resolved
trigger: "rescan completion toast does not appear in linked folders settings page"
created: 2026-03-19T00:00:00Z
updated: 2026-03-19T00:00:00Z
---

## Current Focus

hypothesis: Component dispatches raw Livewire 'toast' event but nothing listens for it; should use MaryUI Toast trait methods instead
test: Compare toast dispatch pattern in linked-folders vs working components (UploadModal, RelinkTrackModal, AddToPlaylistModal)
expecting: Working components use `Mary\Traits\Toast` trait with `$this->success()` etc; linked-folders does not
next_action: Confirm root cause and document

## Symptoms

expected: After triggering a folder rescan, a toast notification should appear when scanning completes
actual: No toast appears after scan completion
errors: None (silent failure - event dispatched to nowhere)
reproduction: Click "Rescan" on any linked folder, wait for scan to complete
started: Likely since implementation - toast was never wired correctly

## Eliminated

## Evidence

- timestamp: 2026-03-19T00:01:00Z
  checked: linked-folders.blade.php toast dispatch calls
  found: All toast calls use `$this->dispatch('toast', message: ...)` - a raw Livewire event dispatch
  implication: This dispatches a browser event named 'toast' that nothing listens for

- timestamp: 2026-03-19T00:02:00Z
  checked: MaryUI Toast trait (vendor/robsontenorio/mary/src/Traits/Toast.php)
  found: MaryUI provides a `Toast` trait with `success()`, `warning()`, `error()`, `info()` methods that internally call `$this->js('toast(...)') `
  implication: The correct way to show MaryUI toasts from Livewire is to use the Toast trait, not raw dispatch

- timestamp: 2026-03-19T00:03:00Z
  checked: Working components in app/Livewire/
  found: UploadModal, RelinkTrackModal, AddToPlaylistModal all `use Mary\Traits\Toast` and call `$this->success(...)` etc.
  implication: Confirmed pattern - linked-folders is the outlier using wrong approach

- timestamp: 2026-03-19T00:04:00Z
  checked: Blade views using Alpine.js toasts (playlists/show.blade.php, playlists/index.blade.php)
  found: They dispatch `mary-toast` with `{ toast: { title: ..., css: ..., timeout: ... } }` object
  implication: Even client-side toast dispatch uses different event name and structure than what linked-folders dispatches

- timestamp: 2026-03-19T00:05:00Z
  checked: Whether linked-folders component uses Toast trait
  found: No `use Toast` in the component class
  implication: Component never imported the trait - all `$this->dispatch('toast')` calls are no-ops from toast perspective

## Resolution

root_cause: The linked-folders Volt component dispatches `$this->dispatch('toast', message: ...)` which is a raw Livewire event that nothing listens for. It should use MaryUI's `Toast` trait (`use Mary\Traits\Toast`) and call `$this->success($message)` instead. This affects ALL toast calls in the component (6 total), not just the rescan completion toast in `pollFolders()`.
fix:
verification:
files_changed: []
