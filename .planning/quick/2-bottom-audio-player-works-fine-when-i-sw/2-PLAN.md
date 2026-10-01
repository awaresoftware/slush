---
phase: quick-2
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/pages/library/index.blade.php
  - resources/views/playlists/show.blade.php
  - resources/views/livewire/upload-modal.blade.php
  - resources/views/livewire/audio-player.blade.php
autonomous: false
requirements: [fix-player-shutdown, audit-wire-navigate, replace-queue-modal]
must_haves:
  truths:
    - "Audio player persists when navigating to album detail page from library"
    - "Audio player persists when navigating back from playlist show page"
    - "All internal app-layout links use wire:navigate for SPA navigation"
    - "Clicking a track on album-show while audio is playing shows confirmation modal before replacing queue"
    - "Clicking a track on album-show while nothing is playing starts playback immediately"
  artifacts:
    - path: "resources/views/pages/library/index.blade.php"
      provides: "Album links with wire:navigate"
      contains: "wire:navigate"
    - path: "resources/views/livewire/audio-player.blade.php"
      provides: "Replace-queue confirmation modal and gated play-track handler"
  key_links:
    - from: "resources/views/pages/library/album-show.blade.php"
      to: "resources/views/livewire/audio-player.blade.php"
      via: "play-track event dispatched, intercepted by Alpine for confirmation"
      pattern: "play-track"
---

<objective>
Fix audio player shutdown when navigating to album detail pages, audit all links for missing wire:navigate, and add a confirmation modal when replacing the current queue.

Purpose: The @persist('audio-player') block is destroyed by full page reloads caused by missing wire:navigate attributes. Additionally, users should be warned before their current queue is replaced.
Output: All internal app-layout links use wire:navigate; album-show play buttons show confirmation when audio is already playing.
</objective>

<execution_context>
@./.claude/get-shit-done/workflows/execute-plan.md
@./.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/pages/library/index.blade.php
@resources/views/pages/library/album-show.blade.php
@resources/views/livewire/audio-player.blade.php
@resources/views/playlists/show.blade.php
@resources/views/livewire/upload-modal.blade.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add wire:navigate to all missing internal app-layout links</name>
  <files>resources/views/pages/library/index.blade.php, resources/views/playlists/show.blade.php, resources/views/livewire/upload-modal.blade.php</files>
  <action>
Add `wire:navigate` attribute to these specific links that are inside the app layout (where the audio player lives):

1. `resources/views/pages/library/index.blade.php` line 205 — album link in search results grid. Add `wire:navigate` to the `<a>` tag.
2. `resources/views/pages/library/index.blade.php` line 324 — album link in albums tab grid. Add `wire:navigate` to the `<a>` tag.
3. `resources/views/playlists/show.blade.php` line 6 — "My Playlists" breadcrumb link. Add `wire:navigate` to the `<a>` tag.
4. `resources/views/livewire/upload-modal.blade.php` line 20 — "View Uploads" link after upload complete. Add `wire:navigate` to the `<a>` tag.

NOTE: Auth page links (login, register, forgot-password, reset-password, verify-email) do NOT need wire:navigate because they use the `simple.blade.php` layout which has no audio player. The `welcome.blade.php` links are external URLs (target="_blank) and also don't need it.

Do NOT change any other attributes on these links — only add `wire:navigate`.
  </action>
  <verify>
    <automated>grep -rn "href=.*route(" resources/views/pages/library/index.blade.php resources/views/playlists/show.blade.php resources/views/livewire/upload-modal.blade.php | grep -v "wire:navigate" | grep -v "logout"</automated>
  </verify>
  <done>All four links have wire:navigate. grep returns no results (all internal app-layout route links have wire:navigate).</done>
</task>

<task type="auto">
  <name>Task 2: Add replace-queue confirmation modal to audio player</name>
  <files>resources/views/livewire/audio-player.blade.php</files>
  <action>
Modify the audio player's Alpine `audioPlayer` component to intercept `play-track` events and show a confirmation modal when audio is currently playing.

In the Alpine component (`audioPlayer` function in `<script>` block):

1. Add new state properties:
   - `showReplaceModal: false` — controls modal visibility
   - `pendingPlayDetail: null` — stores the play-track event detail to use after confirmation

2. Modify the `onPlayTrack(detail)` method:
   - If `this.isPlaying` is true, store `detail` in `this.pendingPlayDetail` and set `this.showReplaceModal = true`, then return (do NOT load the queue yet)
   - If `this.isPlaying` is false (nothing playing), proceed with existing logic immediately (call loadQueue/loadTrack as before)

3. Add a `confirmReplace()` method:
   - Set `this.showReplaceModal = false`
   - Call the existing queue loading logic with `this.pendingPlayDetail` (same if/else as original onPlayTrack)
   - Set `this.pendingPlayDetail = null`

4. Add a `cancelReplace()` method:
   - Set `this.showReplaceModal = false`
   - Set `this.pendingPlayDetail = null`

In the template (inside the main `<div>` of audio-player.blade.php), add a simple DaisyUI modal dialog AFTER the existing content but before the closing `</div>` of the root element. Use Alpine x-show for visibility, NOT a Mary modal (to keep it self-contained in the Alpine component):

```html
{{-- Replace queue confirmation --}}
<div x-show="showReplaceModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" x-on:keydown.escape.window="cancelReplace()">
    <div class="bg-zinc-800 rounded-lg p-6 max-w-sm mx-4 shadow-xl" x-on:click.away="cancelReplace()">
        <h3 class="text-lg font-semibold text-white mb-2">Replace current queue?</h3>
        <p class="text-sm text-zinc-400 mb-4">Music is currently playing. Do you want to replace the queue with this album?</p>
        <div class="flex justify-end gap-3">
            <button type="button" x-on:click="cancelReplace()" class="btn btn-ghost btn-sm">Cancel</button>
            <button type="button" x-on:click="confirmReplace()" class="btn btn-primary btn-sm">Replace</button>
        </div>
    </div>
</div>
```

This approach keeps the modal entirely within the Alpine component scope so it has direct access to `isPlaying`, `pendingPlayDetail`, etc.
  </action>
  <verify>
    <automated>grep -c "showReplaceModal\|confirmReplace\|cancelReplace\|pendingPlayDetail" resources/views/livewire/audio-player.blade.php</automated>
  </verify>
  <done>Grep returns at least 8 matches (properties + methods + template references). Modal appears when play-track fires while audio is playing; confirm loads the new queue; cancel dismisses without interruption.</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <what-built>
    1. Added wire:navigate to all album links in library index, playlist show breadcrumb, and upload modal summary link
    2. Added a replace-queue confirmation modal in the audio player that appears when clicking play while music is already playing
  </what-built>
  <how-to-verify>
    1. Go to the library page and start playing a track
    2. While music is playing, click on an album card — page should navigate WITHOUT the player stopping (wire:navigate fix)
    3. On the album detail page, click a track play button — the confirmation modal should appear asking "Replace current queue?"
    4. Click Cancel — music should continue unchanged
    5. Click a track play button again, then click Replace — new album queue should start playing
    6. Stop playback, then click a track — it should start immediately with NO modal
    7. Navigate between library, playlists, dashboard — player should persist on all transitions
  </how-to-verify>
  <resume-signal>Type "approved" or describe issues</resume-signal>
</task>

</tasks>

<verification>
- All `<a href>` tags within app-layout blade files have `wire:navigate`
- Audio player persists across all page navigations
- Replace-queue modal only shows when `isPlaying` is true
- Modal confirm loads new queue, cancel preserves current playback
</verification>

<success_criteria>
- Navigating to album detail page from library does NOT restart/destroy the audio player
- Full audit complete: no internal app-layout links missing wire:navigate
- Confirmation modal gates queue replacement when audio is actively playing
- Immediate playback when nothing is playing (no unnecessary modal)
</success_criteria>

<output>
After completion, create `.planning/quick/2-bottom-audio-player-works-fine-when-i-sw/2-SUMMARY.md`
</output>
