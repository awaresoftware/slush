# Phase 8: Shared Components & Modal Pattern - Research

**Researched:** 2026-03-15
**Domain:** Mary UI auth layout, Mary UI modal (`x-mary-modal`), Livewire event-driven modal, daisyUI card
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Auth Layout**
- Restyle `simple.blade.php` with daisyUI card wrapper around the form area — gives form a defined boundary against the background
- Match the `spotify-dark` daisyUI theme (remove the manual dark gradient `bg-linear-to-b from-neutral-950 to-neutral-900`)
- Use Mary UI components for structure: `x-mary-header` for page headings, `x-mary-button` for submit buttons, `x-mary-input` for form fields
- Layout shell migration only — do NOT restyle the auth page contents (login, register, forgot-password are already Flux-free)
- Delete unused `split.blade.php` auth layout — it is dead code
- Keep `card.blade.php` if used, delete if unused (Claude to verify)

**Modal Trigger Pattern**
- Trigger buttons use `wire:click` to set a Livewire boolean property (e.g., `wire:click="$set('showModal', true)"`)
- Modal uses `wire:model="showModal"` binding
- Every modal has an explicit `closeModal()` method that sets `showModal = false` AND resets related state (selectedTrackId, form inputs, etc.)
- Consistent modal structure across the app: title header, body content, footer with action buttons — width varies by content (sm/md/lg)

**Add-to-Playlist Refactor**
- Create a dedicated Livewire component (e.g., `AddToPlaylistModal`) with `selectedTrackId` and `showModal` properties
- Place single instance in the app layout — accessible from any page
- Trigger via Livewire event dispatch: `$dispatch('open-add-to-playlist', { trackId: X })` — component listens and sets `selectedTrackId` + `showModal = true`
- Use Livewire action for adding tracks: `wire:click="addToPlaylist(playlistId)"` — no form POST, handles DB operation directly
- After adding: modal closes immediately + toast notification confirms "Added to [playlist name]"
- Delete old per-track `add-track-modal.blade.php` Blade component — it is replaced by the Livewire component
- Update all 3 pages with trigger buttons (library index, album show, playlist show) to use the new `$dispatch` pattern

**Migration Scope**
- Phase 8 migrates only the add-to-playlist modal — establishes the pattern
- Upload modal stays in Phase 9 (library index page migration)
- Delete-user and two-factor modals stay in Phase 10 (settings page migration)

### Claude's Discretion
- daisyUI card style details (border, shadow, padding) for auth layout
- Toast notification implementation (Mary UI toast component or custom)
- Exact Livewire component structure and event naming
- Whether `card.blade.php` auth layout is used and should be kept

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| LAYOUT-05 | Migrate auth layout (`layouts/auth/split.blade.php`) to Mary UI | Auth layout analysis: `simple.blade.php` needs `spotify-dark` daisyUI card wrapper; `split.blade.php` confirmed dead code for deletion; `card.blade.php` usage verified |
| MODAL-01 | Refactor all modals from Flux name-based triggers to Mary `wire:model` booleans | Mary UI Modal component source analyzed: `wire:model` binds via `@entangle().live`; escape key auto-sets boolean false; backdrop click auto-sets false |
| MODAL-02 | Refactor per-track "add to playlist" modal to use `selectedTrackId` pattern | Livewire `#[On]` attribute for event listener; `Mary\Traits\Toast` for success notifications; single component in app layout; 3 trigger sites identified |
</phase_requirements>

---

## Summary

Phase 8 has two distinct workstreams. The first is a simple layout restyle: `simple.blade.php` wraps the `$slot` in a daisyUI card, and adds `data-theme="spotify-dark"` to the `<html>` element. The `split.blade.php` and `card.blade.php` layouts are dead code and can be deleted after verifying no views reference them (confirmed: only `confirm-password.blade.php`, `two-factor-challenge.blade.php`, and `verify-email.blade.php` use `x-layouts::auth`, which routes to `simple.blade.php` via `auth.blade.php`).

The second workstream replaces the per-track Flux modal pattern with a single `AddToPlaylistModal` Livewire component that lives in `app/sidebar.blade.php`. The Mary UI modal source code has been directly inspected — it uses `@entangle($attributes->wire('model')).live` to bind to a Livewire boolean, so `wire:model="showModal"` on the component tag is the correct pattern. Escape and backdrop-click both set the boolean to false automatically. The `Mary\Traits\Toast` trait gives the Livewire component `$this->success(...)` to fire toast notifications — the `x-mary-toast` component must be added to the app layout for toasts to render.

**Primary recommendation:** Use `x-mary-modal` with `wire:model="showModal"` and the `Mary\Traits\Toast` trait for the add-to-playlist component. Place `<x-mary-toast />` in the app layout sidebar. No custom JavaScript is needed.

---

## Codebase State Audit

### Auth Layout Reality (HIGH confidence — read from source)

The auth layout chain is:
1. `layouts/auth.blade.php` → delegates to `x-layouts::auth.simple`
2. `layouts/auth/simple.blade.php` → the actual HTML shell (contains `@fluxScripts`, manual dark gradient)
3. Auth pages using `x-layouts::auth`: `confirm-password.blade.php`, `two-factor-challenge.blade.php`, `verify-email.blade.php`

**The login/register/forgot-password pages use `layouts.guest`**, not `x-layouts::auth`. `guest.blade.php` already has `data-theme="spotify-dark"` and a daisyUI-styled card wrapper — it is already migrated.

**`split.blade.php`**: Not referenced anywhere in the codebase. Safe to delete.

**`card.blade.php`**: Not referenced anywhere in the codebase. Safe to delete.

**What needs migrating in `simple.blade.php`:**
- Remove `@fluxScripts`
- Remove manual `dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900` gradient
- Add `data-theme="spotify-dark"` to `<html>` to match `guest.blade.php`
- Wrap the `$slot` content in a daisyUI card (`div.card`) for visual boundary
- Keep the logo link and centered layout

The pages using `x-layouts::auth` still contain Flux components (`flux:input`, `flux:button`, `flux:otp`) — but CONTEXT.md says Phase 8 migrates the layout shell only, NOT the auth page contents. Those pages belong to Phase 10 (SETTINGS-08).

### Current Modal Pattern (HIGH confidence — read from source)

**Existing Flux modal pattern (to be replaced):**
- Trigger: `<flux:modal.trigger :name="'add-to-playlist-' . $track->id">`
- Modal: `<flux:modal :name="'add-to-playlist-' . $track->id">` — renders N modal elements for N tracks
- Blade component: `resources/views/components/playlist/add-track-modal.blade.php`
- Usage in 3 places: `library/index.blade.php` (lines ~251 and ~372), `library/album-show.blade.php` (line ~94)
- `playlists/show.blade.php` does NOT currently have an add-to-playlist button

**Add-to-playlist currently uses form POST** to `playlist-tracks.store` route — the Livewire refactor replaces this with a direct Livewire action.

### App Layout Insertion Point (HIGH confidence — read from source)

`resources/views/layouts/app/sidebar.blade.php` already contains `@persist('audio-player')` for the audio player. The `AddToPlaylistModal` Livewire component should be placed immediately after the audio player persist block (before `@fluxScripts`), also inside `@auth`.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `x-mary-modal` | installed (robsontenorio/mary) | Modal dialog wrapping daisyUI `<dialog>` | Only Mary UI modal in this app |
| `Mary\Traits\Toast` | installed | Toast notifications from Livewire component | Matches `x-mary-toast` component already in vendor |
| `Livewire\Attributes\On` | Livewire 4 | Event listener on Livewire component | Standard Livewire 4 pattern for cross-component events |
| daisyUI `card` | v5 (via daisyUI plugin) | Auth layout card wrapper | App uses `spotify-dark` daisyUI theme throughout |

### Component Creation
```bash
php artisan make:livewire AddToPlaylistModal --no-interaction
```

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `$dispatch()` (Alpine) | Livewire 4 bundled Alpine | Trigger Livewire events from Blade | Trigger buttons are in non-Livewire contexts (plain Blade loops) |
| `wire:click="$set('showModal', true)"` | Livewire 4 | Alternative trigger when inside Livewire component | Trigger buttons within a Livewire component's template |

---

## Architecture Patterns

### Recommended Project Structure

```
app/Livewire/
├── AudioPlayer.php              # existing — reference for persistent component pattern
└── AddToPlaylistModal.php       # new — single-instance modal with selectedTrackId

resources/views/
├── layouts/auth/
│   └── simple.blade.php         # restyle: add data-theme, daisyUI card, remove @fluxScripts
├── layouts/auth/split.blade.php # DELETE — dead code
├── layouts/auth/card.blade.php  # DELETE — dead code
├── layouts/app/
│   └── sidebar.blade.php        # add <livewire:add-to-playlist-modal> and <x-mary-toast />
├── livewire/
│   └── add-to-playlist-modal.blade.php  # new
└── components/playlist/
    └── add-track-modal.blade.php # DELETE — replaced by Livewire component
```

### Pattern 1: Mary UI Modal with wire:model

**What:** The `x-mary-modal` component accepts `wire:model="boolProperty"` and uses Alpine `@entangle` to sync the dialog open/closed state with a Livewire boolean property. Escape key and backdrop click both automatically set the boolean to `false`.

**When to use:** Any time a modal needs to open/close based on server-side logic, form state, or cross-component events.

**Mary UI Modal source — key implementation detail:**
```php
// From vendor/robsontenorio/mary/src/View/Components/Modal.php
x-data="{open: @entangle($attributes->wire('model')).live }"
x-init="$watch('open', value => { if (!value){ $dispatch('close') }else{ $dispatch('open') } })"
:class="{'modal-open !animate-none': open}"
// Escape key auto-close:
@keydown.escape.window = "$wire.{{ $attributes->wire('model')->value() }} = false"
// Backdrop close button:
<button @click="$wire.{{ $attributes->wire('model')->value() }} = false" type="button">close</button>
```

**Component template usage:**
```blade
{{-- In the component's Blade template --}}
<x-mary-modal wire:model="showModal" title="Add to Playlist" box-class="max-w-md">
    {{-- body content --}}

    <x-slot:actions>
        <x-mary-button label="Cancel" @click="$wire.showModal = false" />
    </x-slot:actions>
</x-mary-modal>
```

**PHP component:**
```php
use Mary\Traits\Toast;
use Livewire\Component;
use Livewire\Attributes\On;

class AddToPlaylistModal extends Component
{
    use Toast;

    public bool $showModal = false;
    public ?int $selectedTrackId = null;

    #[On('open-add-to-playlist')]
    public function openForTrack(int $trackId): void
    {
        $this->selectedTrackId = $trackId;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->selectedTrackId = null;
    }

    public function addToPlaylist(int $playlistId): void
    {
        // ... DB logic ...
        $this->closeModal();
        $this->success("Added to {$playlist->name}");
    }
}
```

### Pattern 2: Cross-Component Event Dispatch

**What:** Alpine's `$dispatch()` fires a browser event. Livewire 4's `#[On('event-name')]` attribute picks it up on any component listening for that event name. This is the standard way for a non-Livewire button to trigger a Livewire component action.

**When to use:** Trigger buttons are in Blade templates that are not themselves Livewire components (e.g., inside a Livewire Volt SFC's template, or a plain Blade view), and they need to open a modal that lives in a different component.

```blade
{{-- Trigger button in library/index.blade.php --}}
<button
    type="button"
    x-on:click="$dispatch('open-add-to-playlist', { trackId: {{ $track->id }} })"
    class="..."
    aria-label="Add {{ $track->title }} to playlist"
    data-test="add-to-playlist-btn"
>
    <x-mary-icon name="o-plus" class="size-4" />
</button>
```

**IMPORTANT:** `$dispatch()` in Alpine fires a browser custom event. Livewire 4's `#[On]` listens for browser events when the value starts without `self:`. This is the correct cross-component pattern.

### Pattern 3: Toast Notifications

**What:** `Mary\Traits\Toast` adds `$this->success()`, `$this->error()`, `$this->warning()`, `$this->info()` methods to any Livewire component. These call `$this->js('toast(...)')` which fires the `mary-toast` window event. `x-mary-toast` in the layout listens for this event.

**Requirement:** `<x-mary-toast />` must exist in the app layout (sidebar.blade.php). It is currently absent — must be added.

```blade
{{-- In sidebar.blade.php, before </body> --}}
<x-mary-toast />
```

```php
// In AddToPlaylistModal.php after addToPlaylist action
$this->success("Added to {$playlist->name}");
```

### Pattern 4: Auth Layout Card Structure

**What:** `simple.blade.php` needs to match `guest.blade.php` style — daisyUI `spotify-dark` theme, centered card container.

**Reference — existing `guest.blade.php` (already correctly styled):**
```html
<html lang="{{ ... }}" data-theme="spotify-dark">
<body class="min-h-screen flex items-center justify-center font-sans antialiased bg-[#121212]">
    <div class="w-full max-w-sm px-8 py-10 bg-[#282828] rounded-2xl shadow-2xl">
        @yield('content')
    </div>
</body>
```

**Target `simple.blade.php` (after migration):**
```html
<html lang="{{ ... }}" data-theme="spotify-dark">
<body class="min-h-screen flex items-center justify-center font-sans antialiased bg-base-200">
    {{-- logo link --}}
    <div class="card w-full max-w-sm shadow-xl">
        <div class="card-body">
            {{ $slot }}
        </div>
    </div>
    @livewireScripts  {{-- replaces @fluxScripts --}}
</body>
```

Note: Use `bg-base-200` (daisyUI token) not hardcoded `#121212`, so the theme system drives the background. The `@fluxScripts` directive must be removed — if Livewire scripts are needed, use `@livewireScripts` (though the app layout already handles this via Vite, so it may just be removed entirely).

### Anti-Patterns to Avoid

- **N modal instances:** Never loop `<x-playlist.add-track-modal :track="$track">` inside a `@foreach` — this creates N hidden modals in the DOM. Use a single component with `selectedTrackId`.
- **Using `$id` prop on x-mary-modal:** The `id` prop bypasses the `wire:model` binding and uses daisyUI's JavaScript dialog API instead. Always omit `id` when using `wire:model`.
- **Calling `$dispatch` from PHP:** Use `$this->dispatch('event')` from Livewire PHP to dispatch to JavaScript listeners. Use `#[On('event')]` attribute to listen for events dispatched FROM JavaScript/Alpine to Livewire.
- **Missing `wire:key` on playlist rows:** The modal's playlist list will re-render on each track selection change — `wire:key` prevents DOM thrashing.
- **Leaving `@fluxScripts` in the auth layout:** Must be removed as part of Flux elimination. The auth layout currently has `@fluxScripts` in `simple.blade.php`, `card.blade.php`, and `split.blade.php`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Modal open/close state | Manual Alpine x-data, custom events | `x-mary-modal wire:model="bool"` | Modal component handles escape, backdrop, animation, accessibility (focus trap via x-trap) |
| Toast notifications | Custom alert component, session flash divs | `Mary\Traits\Toast` + `<x-mary-toast />` | Already wired to Livewire request lifecycle, progress bar, pause on hover |
| Cross-component open trigger | Custom global JS, Livewire.dispatch from JS | `$dispatch('event', {data})` + `#[On('event')]` | Standard Livewire 4 pattern; no global state needed |
| Auth layout card | Custom div with hardcoded colors | daisyUI `card` / `card-body` classes | Theme-aware: inherits `spotify-dark` tokens automatically |

**Key insight:** The Mary UI modal handles the hard parts — focus trapping (`x-trap`), escape key, backdrop dismiss, animation — all wired to a single Livewire boolean. Building this manually would require significant Alpine boilerplate.

---

## Common Pitfalls

### Pitfall 1: `#[On]` Attribute with Alpine `$dispatch`

**What goes wrong:** Alpine's `$dispatch('open-add-to-playlist', {trackId: 1})` fires a DOM custom event bubbling up the tree. Livewire 4's `#[On('open-add-to-playlist')]` listens for this browser event. However, if the Livewire component is rendered AFTER the dispatch fires (e.g., page load timing), the event is missed.

**Why it happens:** The component must be mounted and listening before the event fires. This is not an issue during normal interaction (user clicks a button after page load).

**How to avoid:** Place `<livewire:add-to-playlist-modal>` in the persistent app layout (not in a lazy-loaded component). It will be mounted on every page load.

**Warning signs:** Modal never opens; no errors in console; component event listener is registered correctly.

### Pitfall 2: `wire:model` on Modal Requires No `id` Prop

**What goes wrong:** Setting `id` on `x-mary-modal` switches the component to daisyUI's native dialog JS API — the `wire:model` binding is bypassed (the component source code skips `@entangle` when `$id` is truthy).

**Why it happens:** The Mary UI Modal component has two modes: `id`-based (daisyUI native) and `wire:model`-based (Alpine entangle).

**How to avoid:** Never set `id` when using `wire:model`. Use only: `<x-mary-modal wire:model="showModal">`.

### Pitfall 3: Toast Component Not in Layout

**What goes wrong:** `$this->success(...)` fires a JS `toast()` call, but nothing renders because `<x-mary-toast />` is missing from the layout.

**How to avoid:** Add `<x-mary-toast />` to `sidebar.blade.php` before `</body>`. It uses `@persist('mary-toaster')` internally so it survives `wire:navigate` page transitions.

### Pitfall 4: `closeModal()` Must Reset `selectedTrackId`

**What goes wrong:** User opens modal for Track A, closes it, opens for Track B — modal briefly shows Track A's data before re-rendering.

**Why it happens:** `selectedTrackId` retains its value between opens if not explicitly reset.

**How to avoid:** Always set `$this->selectedTrackId = null` in `closeModal()`.

### Pitfall 5: Playlist Data Query in Modal Component

**What goes wrong:** The `AddToPlaylistModal` needs user playlists. If loaded eagerly on every render, this runs a query even when the modal is closed.

**How to avoid:** Use a `#[Computed]` property — it only evaluates when the value is accessed (during render). Do NOT use `mount()` to pre-load playlists since the component is always mounted in the layout.

```php
#[Computed]
public function userPlaylists(): \Illuminate\Database\Eloquent\Collection
{
    return Playlist::where('user_id', Auth::id())
        ->withCount('tracks')
        ->orderBy('name')
        ->get();
}
```

### Pitfall 6: `userPlaylists` #[Computed] in Pages No Longer Needed

**What goes wrong:** After migrating to the shared modal component, `library/index.blade.php` and `library/album-show.blade.php` still have `#[Computed] public function userPlaylists()` methods. These become dead code — they run queries that feed the old Blade component.

**How to avoid:** Remove `userPlaylists()` computed method and the `Playlist` import from both page components after removing the old modal Blade usages. Note: Phase 8 removes `userPlaylists()` from these pages since the add-to-playlist modal is now self-contained.

---

## Code Examples

### Full AddToPlaylistModal Component

```php
// app/Livewire/AddToPlaylistModal.php
<?php

namespace App\Livewire;

use App\Models\Playlist;
use App\Models\Track;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class AddToPlaylistModal extends Component
{
    use Toast;

    public bool $showModal = false;

    public ?int $selectedTrackId = null;

    #[On('open-add-to-playlist')]
    public function openForTrack(int $trackId): void
    {
        $this->selectedTrackId = $trackId;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->selectedTrackId = null;
    }

    public function addToPlaylist(int $playlistId): void
    {
        $track = Track::where('id', $this->selectedTrackId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $playlist = Playlist::where('id', $playlistId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (! $playlist->tracks()->where('track_id', $track->id)->exists()) {
            $maxPosition = $playlist->playlistTracks()->max('position') ?? -1;
            $playlist->tracks()->attach($track->id, ['position' => $maxPosition + 1]);
        }

        $this->closeModal();
        $this->success("Added to {$playlist->name}");
    }

    #[Computed]
    public function selectedTrack(): ?Track
    {
        if ($this->selectedTrackId === null) {
            return null;
        }

        return Track::find($this->selectedTrackId);
    }

    #[Computed]
    public function userPlaylists(): \Illuminate\Database\Eloquent\Collection
    {
        return Playlist::where('user_id', Auth::id())
            ->withCount('tracks')
            ->orderBy('name')
            ->get();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.add-to-playlist-modal');
    }
}
```

### Trigger Button in Blade Template

```blade
{{-- In library/index.blade.php and album-show.blade.php --}}
<button
    type="button"
    x-on:click="$dispatch('open-add-to-playlist', { trackId: {{ $track->id }} })"
    class="flex size-7 items-center justify-center rounded opacity-0 transition-opacity group-hover:opacity-100 hover:bg-zinc-700 text-zinc-400 hover:text-white"
    aria-label="Add {{ $track->title }} to playlist"
    data-test="add-to-playlist-btn"
>
    <x-mary-icon name="o-plus" class="size-4" />
</button>
```

### Modal Blade Template

```blade
{{-- resources/views/livewire/add-to-playlist-modal.blade.php --}}
<div>
    <x-mary-modal wire:model="showModal" title="Add to Playlist" box-class="max-w-sm">
        @if ($this->selectedTrack)
            <p class="text-base-content/60 text-sm mb-4">
                Adding <span class="font-medium text-base-content">{{ $this->selectedTrack->title }}</span>
            </p>
        @endif

        @if ($this->userPlaylists->isEmpty())
            <div class="py-6 text-center">
                <p class="text-base-content/60 text-sm mb-3">You have no playlists yet.</p>
                <x-mary-button label="Create a Playlist" link="{{ route('playlists.create') }}" wire:navigate size="sm" />
            </div>
        @else
            <div class="space-y-1">
                @foreach ($this->userPlaylists as $playlist)
                    <button
                        wire:key="{{ $playlist->id }}"
                        wire:click="addToPlaylist({{ $playlist->id }})"
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left hover:bg-base-300 transition-colors"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <x-mary-icon name="o-queue-list" class="size-4 text-base-content/40 shrink-0" />
                            <span class="truncate text-sm">{{ $playlist->name }}</span>
                        </div>
                        <span class="text-xs text-base-content/40 shrink-0 ml-2">
                            {{ $playlist->tracks_count }} {{ Str::plural('track', $playlist->tracks_count) }}
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="mt-4 border-t border-base-content/10 pt-4">
                <x-mary-button label="Create New Playlist" icon="o-plus" link="{{ route('playlists.create') }}" wire:navigate size="sm" class="w-full" />
            </div>
        @endif
    </x-mary-modal>
</div>
```

### App Layout Additions (sidebar.blade.php)

```blade
{{-- After @persist('audio-player') block, before @fluxScripts --}}
@auth
    @persist('audio-player')
        <livewire:audio-player />
    @endpersist

    <livewire:add-to-playlist-modal />
@endauth

<x-mary-toast />

{{-- Remove @fluxScripts entirely (or if needed, replace with @livewireScripts) --}}
```

### Restyled simple.blade.php

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="spotify-dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen flex items-center justify-center font-sans antialiased bg-base-200">
        <div class="flex w-full max-w-sm flex-col gap-6 px-4 py-8">
            <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                <span class="flex h-9 w-9 mb-1 items-center justify-center rounded-md">
                    <x-app-logo-icon class="size-9 fill-current text-base-content" />
                </span>
                <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
            </a>
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
```

---

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|------------------|--------|
| `flux:modal name="x"` + `flux:modal.trigger name="x"` | `x-mary-modal wire:model="bool"` | No named event bus; state is explicit PHP boolean |
| N modal instances in DOM loop | Single Livewire component with `selectedTrackId` | One DOM node; no N+1 modal rendering |
| Form POST to add track to playlist | Livewire action `addToPlaylist(id)` | Stays on page; shows toast; no full page reload |
| `@fluxScripts` directive | Removed (Vite handles scripts; or `@livewireScripts` if needed) | Eliminates Flux JS from auth pages |

**Deprecated/outdated in this codebase:**
- `resources/views/components/playlist/add-track-modal.blade.php`: Replaced by Livewire component
- `layouts/auth/split.blade.php`: Dead code, never referenced
- `layouts/auth/card.blade.php`: Dead code, never referenced
- `userPlaylists()` computed methods in `library/index.blade.php` and `library/album-show.blade.php`: Dead code after modal migration

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 |
| Config file | `tests/Pest.php` |
| Quick run command | `php artisan test --compact --filter=AuthLayout` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| LAYOUT-05 | Auth pages render with daisyUI card, no Flux markup, data-theme set | unit (file content) | `php artisan test --compact --filter=AuthLayoutTest` | ❌ Wave 0 |
| MODAL-01 | Modal opens/closes via Livewire boolean; no Flux modal in DOM | feature (Livewire) | `php artisan test --compact --filter=AddToPlaylistModalTest` | ❌ Wave 0 |
| MODAL-02 | Single modal instance; selectedTrackId drives content; toast fires | feature (Livewire) | `php artisan test --compact --filter=AddToPlaylistModalTest` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=AuthLayout` or `--filter=AddToPlaylistModal`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/AuthLayoutTest.php` — covers LAYOUT-05: verifies `simple.blade.php` renders with daisyUI card, `data-theme="spotify-dark"`, no `@fluxScripts`, no Flux class
- [ ] `tests/Feature/AddToPlaylistModalTest.php` — covers MODAL-01 + MODAL-02: Livewire component test verifying open event, selectedTrackId, addToPlaylist action, toast dispatch, close reset

---

## Open Questions

1. **Does `@fluxScripts` need to be replaced with `@livewireScripts` in simple.blade.php?**
   - What we know: `@fluxScripts` outputs Flux JS + Livewire scripts. The app layout does not use `@livewireScripts` — it relies on Vite (`@vite` in partials/head).
   - What's unclear: Whether removing `@fluxScripts` from `simple.blade.php` without replacement breaks Alpine.js or Livewire on the auth pages that use `x-layouts::auth` (`confirm-password`, `two-factor-challenge` use Alpine).
   - Recommendation: Check `partials/head.blade.php` — it includes `@vite(['resources/css/app.css', 'resources/js/app.js'])` and `@fluxAppearance`. The `app.js` likely bundles Alpine/Livewire. If so, `@fluxScripts` in `simple.blade.php` is redundant and can simply be removed. Verify by checking `resources/js/app.js`.

2. **Should `x-mary-toast` be inside or outside `@auth`?**
   - What we know: `x-mary-toast` uses `@persist('mary-toaster')` internally. The audio player uses `@persist('audio-player')` inside `@auth`.
   - What's unclear: Toasts should be available to authenticated users only in this app (only Livewire actions from auth components fire them).
   - Recommendation: Place `<x-mary-toast />` outside `@auth` but inside `<body>` — it renders nothing until a toast event fires, so no cost to showing it on guest pages.

---

## Sources

### Primary (HIGH confidence)
- `vendor/robsontenorio/mary/src/View/Components/Modal.php` — Modal component source: wire:model binding mechanism confirmed
- `vendor/robsontenorio/mary/src/Traits/Toast.php` — Toast trait: `success()`, `error()` etc. methods confirmed
- `vendor/robsontenorio/mary/src/View/Components/Toast.php` — Toast component: `@persist('mary-toaster')`, `mary-toast` window event confirmed
- `resources/views/layouts/auth/simple.blade.php` — Current state: `@fluxScripts`, manual dark gradient confirmed
- `resources/views/layouts/guest.blade.php` — Reference: already uses `data-theme="spotify-dark"`, daisyUI card
- `resources/views/components/playlist/add-track-modal.blade.php` — Old pattern: Flux modal, form POST
- `resources/views/pages/library/index.blade.php` — Trigger sites: lines 251, 372
- `resources/views/pages/library/album-show.blade.php` — Trigger site: line 94
- `resources/views/playlists/show.blade.php` — Confirmed: no add-to-playlist button here

### Secondary (MEDIUM confidence)
- Livewire 4 `#[On]` attribute docs — verifies Alpine `$dispatch()` → `#[On('event')]` cross-component pattern
- daisyUI v5 card classes (`card`, `card-body`) — standard daisyUI component classes

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — source code read directly
- Architecture: HIGH — all files read; patterns derived from actual codebase state
- Pitfalls: HIGH — pitfalls derived from reading Mary UI source code behavior

**Research date:** 2026-03-15
**Valid until:** 2026-04-15 (stable — Mary UI and Livewire 4 patterns unlikely to change)
