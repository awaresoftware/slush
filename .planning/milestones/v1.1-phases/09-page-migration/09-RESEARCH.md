# Phase 9: Page Migration - Research

**Researched:** 2026-03-16
**Domain:** Mary UI / daisyUI component migration, Livewire 4 file upload modal pattern
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

#### Upload Modal
- Create a dedicated Livewire UploadModal component (like AddToPlaylistModal from Phase 8)
- Place single instance in app.blade.php layout — global, triggerable from anywhere
- Use Livewire boolean property + `wire:model` for open/close (Phase 8 pattern)
- Show a real progress bar during file upload using Livewire's file upload progress events
- After upload completes: show success state inside the modal (not auto-close) with link to upload summary page
- User dismisses manually after reviewing the success state
- Album selection uses searchable `x-mary-select` (search enabled for larger libraries)
- Delete the inline Flux upload modal from library/index.blade.php

#### Alert & Notification Strategy
- Success messages (`session('success')`) → toast notifications via `x-mary-toast` (already in app layout from Phase 8)
- Replace inline `flux:callout variant="success"` blocks on playlist pages with toast-based flash messages
- Warning callouts (duplicate detection) → inline daisyUI `alert alert-warning` — contextual warnings stay visible inline, not as toasts
- Badges (e.g., "Processing..." on upload summary) → Mary UI badge component if available, fallback to daisyUI badge classes

#### Form Fields
- Use Mary UI's built-in `:label` prop on `x-mary-input` and `x-mary-textarea` — cleaner markup, built-in error display
- Playlist create/edit forms use `x-mary-input label="Name"` pattern (no separate label elements)
- Keep native browser `confirm()` dialog for playlist delete — no need for a styled confirmation modal
- Upload modal album picker uses `x-mary-select` with search feature enabled

#### Empty State Component
- Create a shared `x-empty-state` Blade component with: title, description, and optional action slot
- Icon is NOT part of the component — added separately outside/above the component per page
- Action slot is optional — album show's empty state uses the component without a button
- Used on: library index (empty library), playlist index (no playlists), playlist show (no tracks), album show (no tracks)

#### Component Mapping (General)
- `flux:heading` → plain HTML heading tags (`h1`, `h2`, etc.) with Tailwind classes, or `x-mary-header` where appropriate
- `flux:text` → plain `<p>` or `<span>` tags with Tailwind classes
- `flux:button` → `x-mary-button`
- `flux:icon.*` → `x-mary-icon name="*"`
- `flux:link` → `<a>` tag or `x-mary-button` with link prop
- `flux:input` → `x-mary-input`
- `flux:textarea` → `x-mary-textarea`
- `flux:select` → `x-mary-select`
- `flux:callout` → daisyUI alert div or toast (context-dependent)

### Claude's Discretion
- Exact progress bar styling for upload modal
- Upload modal success state layout details
- How to wire flash messages to Mary UI toast component
- daisyUI alert styling details (border, icon, padding)
- Empty state component internal markup and spacing
- Profile page stat layout (cards, grid, inline — whatever fits)

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| PAGE-01 | Migrate library index page to Mary UI components | UploadModal extraction pattern, flux:icon/heading/button/input/select/modal replacements documented below |
| PAGE-02 | Migrate album show page to Mary UI components | flux:link/icon/heading/text replacements; empty-state component applies |
| PAGE-03 | Migrate upload summary page to Mary UI components | flux:heading/text/callout/badge/button replacements; `x-mary-badge` confirmed available |
| PAGE-04 | Migrate playlist pages (index, show, create, edit) to Mary UI components | flux:callout → toast/alert pattern; flux:field/label/input/textarea/error replacements; `x-mary-input` `:label` prop verified |
| PAGE-05 | Migrate profile show page to Mary UI components | flux:heading/text replacements; stat card layout at discretion |
</phase_requirements>

---

## Summary

Phase 9 is a systematic view-layer migration across 9 Blade files (library/index, library/album-show, library/upload-summary, playlists/index, playlists/show, playlists/create, playlists/edit, profile/show) plus the creation of two new artifacts: the `UploadModal` Livewire component and the `x-empty-state` Blade component.

The largest task is extracting the upload form from `library/index.blade.php` into a standalone `App\Livewire\UploadModal` component, following the `AddToPlaylistModal` pattern exactly (boolean `$showModal`, `#[On]` event, `wire:model`, Mary trait `Toast`). The upload logic — `WithFileUploads`, `save()`, duplicate detection, session redirect — moves verbatim into the new component. The library index view slims down to just the search/tab/track/album UI.

The remaining migrations are straightforward one-for-one component swaps: `flux:*` tags replaced with `x-mary-*` equivalents or plain HTML, `flux:callout variant="success"` replaced with toast dispatch via the `Mary\Traits\Toast` trait, and `flux:callout variant="warning"` replaced with inline daisyUI `alert alert-warning` divs. All existing PHP logic, `wire:*` directives, Alpine.js, `data-test` attributes, and `wire:navigate` links must be preserved exactly.

**Primary recommendation:** Follow the AddToPlaylistModal component as the exact structural template for UploadModal. Every component swap is pre-decided; execution is primarily mechanical with precision required.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| livewire/livewire | v4 | Reactive Livewire components including UploadModal | Already the app's reactive layer |
| livewire/flux | v2 (being removed) | Source components to replace | Migration target |
| robsontenorio/mary | v2 (x-mary- prefix) | Replacement UI components | Already installed, prefix `mary-` configured |
| tailwindcss | v4 | Utility styling for non-component markup | Already active |
| daisyUI | bundled with Mary | Alert, badge, card classes for items without Mary wrappers | Active theme: `spotify-dark` |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Mary\Traits\Toast | bundled | `$this->success()`, `$this->warning()`, `$this->error()` toast dispatch | UploadModal success notification; replaces flash-based callouts |
| Livewire\WithFileUploads | bundled | File upload handling with progress events | UploadModal inherits from library/index |
| Alpine.js | bundled with Livewire | `x-on:livewire-upload-progress` listener | Progress bar in UploadModal |

### Key Component Mapping (verified against vendor source)
| Flux Component | Mary / HTML Replacement | Notes |
|----------------|------------------------|-------|
| `flux:heading` | `<h1>`, `<h2>` with Tailwind classes | `flux:heading size="xl"` → `<h1 class="text-2xl font-bold">` |
| `flux:text` | `<p>` or `<span>` with Tailwind classes | Direct drop-in |
| `flux:button variant="primary"` | `<x-mary-button class="btn-primary" label="…" />` | label attr or slot for text |
| `flux:button` (default) | `<x-mary-button label="…" />` | Default variant |
| `flux:button variant="danger"` | `<x-mary-button class="btn-error" label="…" />` | daisyUI error class |
| `flux:link` | `<a href="…" wire:navigate>` | Plain anchor; wire:navigate preserved |
| `flux:icon.play` | `<x-mary-icon name="o-play" />` | Heroicon outline prefix `o-` |
| `flux:icon.musical-note` | `<x-mary-icon name="o-musical-note" />` | Same Heroicon name |
| `flux:icon.queue-list` | `<x-mary-icon name="o-queue-list" />` | Same Heroicon name |
| `flux:icon.x-mark` | `<x-mary-icon name="o-x-mark" />` | Same Heroicon name |
| `flux:icon.loading` | `<x-mary-icon name="o-arrow-path" class="animate-spin" />` | Spinner substitute |
| `flux:input` | `<x-mary-input label="…" />` | `:label` prop handles label + error |
| `flux:textarea` | `<x-mary-textarea label="…" />` | Same `:label` pattern |
| `flux:select` | `<x-mary-select label="…" :options="…" />` | Uses `options` collection with `optionValue`/`optionLabel` |
| `flux:field` + `flux:label` + `flux:error` | `<x-mary-input label="…" />` single tag | Collapsed into one component |
| `flux:badge` | `<x-mary-badge value="…" />` or `<span class="badge">` | Verified: `x-mary-badge` renders `<div class="badge">{{ $value }}</div>` |
| `flux:callout variant="success"` | `$this->success("msg")` via `Mary\Traits\Toast` | Toast approach; x-mary-toast already in layout |
| `flux:callout variant="warning"` | `<div role="alert" class="alert alert-warning">` | Inline warning; stays contextual |
| `flux:callout` (neutral) | `<div role="alert" class="alert">` | For "No recent uploads" info state |
| `flux:modal` / `flux:modal.trigger` | `<x-mary-modal wire:model="showModal">` + `$dispatch` | Phase 8 pattern |

### Searchable Album Picker
The user decision says "searchable `x-mary-select`" — but the actual Mary UI component for searchable selection is `x-mary-choices-offline` (component class `ChoicesOffline`), not `x-mary-select`. `x-mary-select` renders a native `<select>` with no search. `x-mary-choices-offline` supports `searchable` + `single` mode and filters options client-side.

**Usage for album picker in UploadModal:**
```blade
<x-mary-choices-offline
    label="Album (optional)"
    wire:model="selectedAlbumId"
    :options="$this->userAlbums"
    option-value="id"
    option-label="title"
    searchable
    single
    placeholder="No album"
/>
```
The `options` collection must have `id` and `title` attributes (Album model has both). Confirmed: `ChoicesOffline` does NOT support `allowAll`/`compact` combined with `single`/`searchable` — don't add those.

---

## Architecture Patterns

### Recommended Structure for New Files
```
app/Livewire/
├── AddToPlaylistModal.php   (existing — reference template)
└── UploadModal.php          (new — follows same pattern)

resources/views/
├── livewire/
│   ├── add-to-playlist-modal.blade.php  (existing)
│   └── upload-modal.blade.php           (new)
├── components/
│   └── empty-state.blade.php            (new shared component)
└── pages/library/, playlists/, pages/profile/
    (existing — migrated in place)
```

### Pattern 1: UploadModal Component (AddToPlaylistModal Template)
**What:** Extract all upload logic from library/index into a standalone Livewire component. One instance in app layout; triggered via `$dispatch('open-upload-modal')`.
**When to use:** Whenever upload needs to be triggered from anywhere in the app.

```php
// app/Livewire/UploadModal.php
namespace App\Livewire;

use App\Concerns\AudioValidationRules;
use App\Jobs\ExtractAudioMetadata;
use App\Models\Album;
use App\Models\Track;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

class UploadModal extends Component
{
    use AudioValidationRules;
    use Toast;
    use WithFileUploads;

    public bool $showModal = false;
    public bool $uploadComplete = false;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $audioFiles = [];

    public ?int $selectedAlbumId = null;

    /** @var array<int, string> */
    public array $duplicateWarnings = [];

    #[On('open-upload-modal')]
    public function openModal(): void
    {
        $this->reset(['audioFiles', 'selectedAlbumId', 'duplicateWarnings', 'uploadComplete']);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['audioFiles', 'selectedAlbumId', 'duplicateWarnings', 'uploadComplete']);
    }

    public function save(): void
    {
        $this->validate($this->audioFileRules());
        // ... upload logic from library/index ...
        // On success: set $this->uploadComplete = true (do NOT redirect)
        // Store uploaded_track_ids in session for the summary page link
    }

    #[Computed]
    public function userAlbums(): Collection
    {
        return Album::where('user_id', Auth::id())->orderBy('title')->get();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.upload-modal');
    }
}
```

### Pattern 2: Upload Progress Bar (Alpine.js + Livewire event)
**What:** Livewire dispatches a native browser `CustomEvent` named `livewire-upload-progress` while files upload. Alpine intercepts this to drive a progress bar.
**Verified:** Event name confirmed in `vendor/livewire/livewire/dist/livewire.csp.esm.js`.

```blade
{{-- Inside upload-modal.blade.php form --}}
<div
    x-data="{ progress: 0, uploading: false }"
    x-on:livewire-upload-start="uploading = true; progress = 0"
    x-on:livewire-upload-finish="uploading = false"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
>
    <input
        type="file"
        wire:model="audioFiles"
        multiple
        accept=".mp3,.wav,.flac,.m4a,.aac,.ogg"
        class="file-input file-input-bordered w-full"
    >
    <div x-show="uploading" class="mt-2">
        <progress class="progress progress-primary w-full" :value="progress" max="100"></progress>
        <p class="text-sm text-base-content/60 mt-1" x-text="`Uploading... ${progress}%`"></p>
    </div>
</div>
```

### Pattern 3: Upload Success State (boolean toggle)
**What:** After `save()` completes, set `$uploadComplete = true`. View conditionally shows success state instead of form.

```blade
{{-- upload-modal.blade.php --}}
<x-mary-modal wire:model="showModal" title="Upload Music" box-class="max-w-lg">
    @if ($uploadComplete)
        {{-- Success state --}}
        <div class="py-4 text-center">
            <x-mary-icon name="o-check-circle" class="size-12 text-success mx-auto mb-3" />
            <p class="font-medium mb-1">Upload complete!</p>
            <p class="text-base-content/60 text-sm mb-4">Your files have been saved.</p>
            @foreach ($duplicateWarnings as $filename)
                <div role="alert" class="alert alert-warning text-sm mb-2">
                    <span>{{ $filename }} was already in your library.</span>
                </div>
            @endforeach
            <div class="flex justify-center gap-3 mt-4">
                <x-mary-button
                    label="View Uploads"
                    link="{{ route('library.upload.summary') }}"
                    class="btn-primary"
                />
                <x-mary-button label="Close" wire:click="closeModal" />
            </div>
        </div>
    @else
        {{-- Upload form --}}
        <form wire:submit="save" class="space-y-4">
            ...
        </form>
    @endif
</x-mary-modal>
```

### Pattern 4: Empty State Blade Component
**What:** Shared `x-empty-state` component with title, description, and optional action slot.

```blade
{{-- resources/views/components/empty-state.blade.php --}}
@props(['title', 'description'])

<div class="flex flex-col items-center justify-center py-20 text-center">
    {{ $icon ?? '' }}
    <h3 class="text-lg font-semibold mb-2">{{ $title }}</h3>
    <p class="text-base-content/60 mb-6">{{ $description }}</p>
    @isset($action)
        {{ $action }}
    @endisset
</div>
```

Usage with action:
```blade
<x-mary-icon name="o-musical-note" class="size-16 text-base-content/30 mb-4" />
<x-empty-state
    title="Your library is empty"
    description="Upload your first track to get started"
>
    <x-slot:action>
        <x-mary-button
            label="Upload Music"
            class="btn-primary"
            x-on:click="$dispatch('open-upload-modal')"
        />
    </x-slot:action>
</x-empty-state>
```

Usage without action (album show):
```blade
<x-empty-state
    title="No tracks in this album yet"
    description="Upload tracks and assign them to this album"
/>
```

### Pattern 5: Toast Flash Messages (replacing flux:callout variant="success")
**What:** Playlists/index and playlists/show have `@if (session('success'))` blocks with `flux:callout`. Replace by dispatching a toast from the controller/redirect and letting `x-mary-toast` display it. The `x-mary-toast` is already in `app/sidebar.blade.php`.

The redirect-with-flash approach: The Laravel controller already sets `session('success')` and redirects. The view currently renders the flash as a callout. To convert to toast, the Livewire page component needs to dispatch a toast on mount.

For non-Livewire playlist pages (they use `x-layouts::app` with standard controllers), the simplest approach is to read `session('success')` in the page and dispatch the toast via an Alpine.js `x-init`:

```blade
@if (session('success'))
    <div
        x-data
        x-init="$dispatch('mary-toast', { message: @js(session('success')), type: 'success' })"
    ></div>
@endif
```

**Important:** Mary UI's `x-mary-toast` listens for the `mary-toast` browser event. Verified by checking the `x-mary-toast` usage and the `Mary\Traits\Toast` trait which dispatches `$this->dispatch('mary', ...)` → triggers the same event. The Alpine dispatch approach works for non-Livewire pages.

### Pattern 6: Inline Warning Alerts (daisyUI)
**What:** Replace `flux:callout variant="warning"` with daisyUI alert classes.

```blade
@foreach ($duplicateWarnings as $filename)
    <div role="alert" class="alert alert-warning mb-2">
        <x-mary-icon name="o-exclamation-triangle" class="size-5 shrink-0" />
        <div>
            <p class="font-medium text-sm">Duplicate detected</p>
            <p class="text-sm">{{ $filename }} has already been uploaded.</p>
        </div>
    </div>
@endforeach
```

### Pattern 7: Upload Trigger Button (library/index after extraction)
The library/index currently uses `<flux:modal.trigger name="upload">`. After UploadModal extraction, replace with:

```blade
<x-mary-button
    label="Upload"
    class="btn-primary"
    x-on:click="$dispatch('open-upload-modal')"
/>
```

The upload PHP logic moves out of the library/index component entirely — remove `use AudioValidationRules`, `use WithFileUploads`, `$audioFiles`, `$selectedAlbumId`, `$duplicateWarnings`, `$userAlbums`, and `save()` from the library index component class.

### Pattern 8: Mary UI Button with Link
For navigation buttons (like "Back to Library", "Go to Library"), use the `link` prop:
```blade
<x-mary-button label="Back to Library" link="{{ route('library.index') }}" wire:navigate />
```

### Anti-Patterns to Avoid
- **Using `x-mary-select` for the searchable album picker:** `x-mary-select` renders a native `<select>`. Use `x-mary-choices-offline` with `searchable single` props instead.
- **Auto-closing upload modal on success:** Decision is to show success state, not redirect. Don't call `$this->redirect()` from UploadModal's `save()`. Set `$uploadComplete = true` and let user dismiss.
- **Redirecting from UploadModal:** The old library/index `save()` calls `$this->redirect(route('library.upload.summary'), navigate: false)`. In UploadModal, instead store session data and show success state with a link. User navigates manually.
- **Forgetting to strip library/index component of upload logic:** After creating UploadModal, remove all upload-related properties and methods from the library index component class to avoid duplication.
- **Using `wire:navigate` on the upload summary link inside a modal:** `wire:navigate` with `@persist` can have issues in modals. Use a plain `link` prop or standard `<a href>` for the "View Uploads" button in the modal success state.
- **Adding `flux:field` wrappers with Mary inputs:** Mary UI's `x-mary-input` handles label + error display in one tag via `:label` and `:errorField` props. Don't wrap in `flux:field` — it doesn't exist after migration.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| File upload progress | Custom XHR progress tracker | Alpine `x-on:livewire-upload-progress` + daisyUI `<progress>` | Livewire dispatches `livewire-upload-progress` CustomEvent natively |
| Searchable album dropdown | Custom Alpine search filter | `x-mary-choices-offline searchable single` | Client-side filtering built-in; handles keyboard, accessibility |
| Toast notifications | Custom toast Alpine component | `Mary\Traits\Toast` + existing `x-mary-toast` in layout | Already wired in app layout from Phase 8 |
| Form error display | Manual `@error` blocks | `x-mary-input` `:errorField` prop or `errorField` auto-detection | Mary input reads session errors automatically |
| Empty state layout | Ad-hoc flex/center blocks per page | `x-empty-state` shared component | Consistency across library, playlist, album pages |
| Badge styling | Custom span/div markup | `<span class="badge badge-warning">` or `<x-mary-badge value="…" />` | daisyUI badge classes + Mary badge component available |

**Key insight:** Mary UI components handle label/error display, searchable dropdown, toast dispatch, and modal open/close at the component level. The remaining "hand-roll" territory is layout-level HTML (headings, paragraphs, tables) which intentionally uses plain HTML + Tailwind.

---

## Common Pitfalls

### Pitfall 1: `x-mary-select` vs `x-mary-choices-offline` Confusion
**What goes wrong:** Using `x-mary-select` for the album picker and not getting search functionality. The CONTEXT.md says "searchable `x-mary-select`" but the actual searchable component is `x-mary-choices-offline`.
**Why it happens:** The naming is non-obvious; `x-mary-select` is a native `<select>` wrapper with no search.
**How to avoid:** Use `x-mary-choices-offline` with `searchable` and `single` props. Options must be a Collection with `id` and `title` fields. The `optionLabel` prop defaults to `name` — override it to `title` for Albums.
**Warning signs:** Album picker shows a plain dropdown without a search input field.

### Pitfall 2: `$this->redirect()` Still in UploadModal
**What goes wrong:** Copying `save()` from library/index verbatim including `$this->redirect(route('library.upload.summary'), navigate: false)`. This closes the modal and navigates away rather than showing the success state.
**Why it happens:** Direct copy-paste of existing `save()` method.
**How to avoid:** In UploadModal's `save()`, replace the redirect with: store IDs in session, set `$this->uploadComplete = true`, do NOT call redirect.
**Warning signs:** Modal closes immediately after upload instead of showing success state.

### Pitfall 3: Upload Logic Left in Library Index After Extraction
**What goes wrong:** UploadModal created but library/index still has `WithFileUploads`, `$audioFiles`, `save()`, etc. Results in two upload forms — one broken inline modal (deleted) and one working modal — but the component class has dead code and potential conflicts.
**Why it happens:** Forgetting to clean up the source component after extraction.
**How to avoid:** After creating UploadModal, remove from library/index class: `use AudioValidationRules`, `use WithFileUploads`, `$audioFiles`, `$selectedAlbumId`, `$duplicateWarnings` (on the component level — `$duplicateWarnings` only lives in UploadModal now), `userAlbums()` computed property, and `save()` method.
**Warning signs:** `TrackUploadTest.php` tests that test `pages::library.index` upload actions will still pass (they reference the component name, not the modal). Monitor test suite carefully.

### Pitfall 4: `wire:navigate` on Summary Link Inside Modal
**What goes wrong:** Using `wire:navigate` on the "View Uploads" link inside the modal causes `@persist` conflicts with the audio player.
**Why it happens:** The audio player uses `@persist` and Livewire SPA navigation; modal interactions can disrupt the persist context.
**How to avoid:** Use `<x-mary-button link="{{ route('library.upload.summary') }}" />` without `wire:navigate`. Standard full page load is fine for post-upload navigation.
**Warning signs:** Audio player resets or page acts strange after clicking "View Uploads".

### Pitfall 5: Flash Toasts on Standard Blade Pages (non-Livewire)
**What goes wrong:** Calling `$this->success()` or `$this->warning()` on non-Livewire pages (playlists use standard controllers + Blade, not Livewire page components).
**Why it happens:** Mary Toast trait is a Livewire trait — not available in standard Blade views or controllers.
**How to avoid:** For playlist pages (standard Blade, not Livewire), use Alpine `x-init` to dispatch `mary-toast` event when `session('success')` is present. Verified approach:
```blade
@if (session('success'))
    <div x-data x-init="$dispatch('mary-toast', { message: @js(session('success')), type: 'success' })"></div>
@endif
```
**Warning signs:** Toast doesn't appear on playlist index/show pages; or PHP error about Toast trait on non-Livewire classes.

### Pitfall 6: `data-test` Attributes Missing After Migration
**What goes wrong:** Removing `data-test="add-to-playlist-btn"` or other `data-test` attributes during component swap.
**Why it happens:** Mechanical find-and-replace of Flux markup without preserving the data attributes.
**How to avoid:** Before touching any file, note all `data-test` attributes. Grep the file for `data-test` before and after. All interactive elements must retain their `data-test` attributes.
**Warning signs:** Browser tests or grep for `data-test` finds fewer matches after migration.

### Pitfall 7: `x-mary-choices-offline` Options Format
**What goes wrong:** Passing a raw Eloquent collection to `x-mary-choices-offline` but Album model's field is `title` not `name`. Default `optionLabel` is `name`.
**Why it happens:** Mary UI defaults `optionLabel` to `name`; Album model uses `title`.
**How to avoid:** Always pass `option-label="title"` explicitly: `<x-mary-choices-offline option-label="title" option-value="id" :options="$this->userAlbums" ... />`.
**Warning signs:** Dropdown shows blank option labels.

---

## Code Examples

Verified patterns from codebase inspection:

### x-mary-button with link and wire:navigate
```blade
{{-- Source: resources/views/livewire/add-to-playlist-modal.blade.php --}}
<x-mary-button label="Create a Playlist" link="{{ route('playlists.create') }}" wire:navigate size="sm" />
```

### x-mary-modal with wire:model boolean
```blade
{{-- Source: resources/views/livewire/add-to-playlist-modal.blade.php --}}
<x-mary-modal wire:model="showModal" title="Add to Playlist" box-class="max-w-sm">
    {{-- content --}}
</x-mary-modal>
```

### Dispatch to open Livewire modal from Alpine
```blade
{{-- Pattern: $dispatch triggers #[On] listener in Livewire component --}}
<button x-on:click="$dispatch('open-upload-modal')">Upload</button>
```

### x-mary-input with label (replaces flux:field + flux:label + flux:input + flux:error)
```blade
{{-- Source: resources/views/pages/auth/login.blade.php --}}
<x-mary-input
    label="Email address"
    name="email"
    type="email"
    value="{{ old('email') }}"
    errorField="email"
/>
```

### x-mary-icon usage
```blade
{{-- Source: resources/views/livewire/add-to-playlist-modal.blade.php --}}
<x-mary-icon name="o-queue-list" class="size-4 text-base-content/40 shrink-0" />
```

### daisyUI progress bar (upload progress)
```blade
{{-- daisyUI class; works with :value binding in Alpine --}}
<progress class="progress progress-primary w-full" :value="progress" max="100"></progress>
```

### daisyUI alert warning (replaces flux:callout variant="warning")
```blade
<div role="alert" class="alert alert-warning">
    <x-mary-icon name="o-exclamation-triangle" class="size-5 shrink-0" />
    <span>{{ $filename }} has already been uploaded.</span>
</div>
```

### x-mary-badge (replaces flux:badge)
```blade
{{-- Source: vendor/robsontenorio/mary — renders <div class="badge">{{ $value }}</div> --}}
<x-mary-badge value="Processing..." class="badge-warning" />
```
Note: `x-mary-badge` only has `$value` and `$id` props. Style via `class` attribute with daisyUI badge modifier classes.

### x-mary-choices-offline (searchable single select)
```blade
<x-mary-choices-offline
    label="Album (optional)"
    wire:model="selectedAlbumId"
    :options="$this->userAlbums"
    option-value="id"
    option-label="title"
    searchable
    single
    placeholder="No album"
/>
```

### Toast from non-Livewire Blade page (session flash)
```blade
@if (session('success'))
    <div x-data x-init="$dispatch('mary-toast', { message: @js(session('success')), type: 'success' })"></div>
@endif
```

### UploadModal placement in app layout
```blade
{{-- resources/views/layouts/app/sidebar.blade.php — after existing livewire: tags --}}
@auth
    @persist('audio-player')
        <livewire:audio-player />
    @endpersist
    <livewire:add-to-playlist-modal />
    <livewire:upload-modal />   {{-- NEW --}}
@endauth
<x-mary-toast />
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `flux:modal` + named trigger | `x-mary-modal` with `wire:model` boolean | Phase 8 | Modal state is Livewire property, not Flux event |
| `flux:callout variant="success"` | `Mary\Traits\Toast` `$this->success()` | Phase 8 established pattern | Toasts replace inline callout banners |
| Inline upload form in page component | Standalone `UploadModal` Livewire component | Phase 9 (this phase) | Reusable, triggerable from anywhere |
| `flux:modal.trigger name="upload"` | `x-on:click="$dispatch('open-upload-modal')"` | Phase 9 (this phase) | Alpine dispatch replaces Flux named trigger |

**Deprecated/outdated in this codebase:**
- `flux:modal.trigger`: Removed in favor of `$dispatch` + `#[On]` pattern
- `flux:callout variant="success"` on playlist pages: Replaced by toast or Alpine dispatch
- Inline upload form inside `library/index.blade.php`: Extracted to `UploadModal` component

---

## Open Questions

1. **`wire:navigate` compatibility for UploadModal's "View Uploads" link**
   - What we know: `@persist` with SPA navigation has a known issue (GitHub #384, noted in STATE.md)
   - What's unclear: Whether that specific issue affects links inside modals or only drawer init
   - Recommendation: Avoid `wire:navigate` on the "View Uploads" link in the success state to be safe; use standard page load

2. **`x-mary-choices-offline` behavior when `selectedAlbumId` is null**
   - What we know: Component supports `single` mode, `placeholder` prop available
   - What's unclear: Exact behavior when wire:model value is null vs empty string vs 0
   - Recommendation: Initialize `$selectedAlbumId` as `null` in UploadModal (same as current library/index), and set `placeholder="No album"` on the choices component

3. **Mary toast event name for Alpine dispatch**
   - What we know: `Mary\Traits\Toast` trait uses `$this->dispatch()` which Livewire maps to a browser event
   - What's unclear: Whether the Alpine `$dispatch('mary-toast', {...})` approach exactly matches what the trait dispatches
   - Recommendation: Inspect `x-mary-toast` component's event listener name before implementing the playlist flash approach; the pattern described is based on known Mary UI toast architecture but should be verified against the actual component source

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest v4 (PHPUnit v12 backend) |
| Config file | `phpunit.xml` (root) |
| Quick run command | `php artisan test --compact --filter=UploadModal` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| PAGE-01 | Library index renders, upload button present, search/tab UI intact | feature | `php artisan test --compact tests/Feature/Library/TrackUploadTest.php` | ✅ existing |
| PAGE-01 | Library search filtering still works | feature | `php artisan test --compact tests/Feature/Library/LibrarySearchTest.php` | ✅ existing |
| PAGE-02 | Album show page renders correctly, empty state, track list | feature | `php artisan test --compact tests/Feature/Library/LibraryViewTest.php` | ✅ existing |
| PAGE-03 | Upload summary page renders, session data, badges, links | feature | `php artisan test --compact tests/Feature/Library/LibraryViewTest.php` | ✅ existing |
| PAGE-04 | Playlist CRUD routes, views render, authorization | feature | `php artisan test --compact tests/Feature/PlaylistControllerTest.php` | ✅ existing |
| PAGE-05 | Profile page renders, counts, bio display | feature | `php artisan test --compact tests/Feature/Profile/PublicProfileTest.php` | ✅ existing |
| PAGE-01 | UploadModal component: open, close, upload, duplicate warnings, toast | feature | `php artisan test --compact tests/Feature/UploadModalTest.php` | ❌ Wave 0 gap |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=<relevant test file>`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/UploadModalTest.php` — covers UploadModal component: opens via event, shows form, shows progress state, shows success state after save, duplicate warnings inline, toast dispatched. Pattern: mirrors `AddToPlaylistModalTest.php` structure.

---

## Sources

### Primary (HIGH confidence)
- Codebase inspection — `app/Livewire/AddToPlaylistModal.php`, `resources/views/livewire/add-to-playlist-modal.blade.php` — reference component pattern
- Codebase inspection — `resources/views/layouts/app/sidebar.blade.php` — layout integration point
- Codebase inspection — `vendor/robsontenorio/mary/src/View/Components/` — `Select.php`, `ChoicesOffline.php`, `Badge.php` — actual component signatures
- Codebase inspection — `vendor/livewire/livewire/dist/livewire.csp.esm.js` — `livewire-upload-progress` event name confirmed
- Codebase inspection — `resources/views/pages/auth/login.blade.php` — `x-mary-input` with `label` and `errorField` props usage

### Secondary (MEDIUM confidence)
- All 9 target Blade files read and catalogued — all Flux components inventoried for replacement
- Existing test suite read — 6 of 7 test files already exist; only `UploadModalTest.php` is new

### Tertiary (LOW confidence)
- Alpine `$dispatch('mary-toast', {...})` pattern for non-Livewire pages — architectural inference from Mary UI toast implementation; should be verified against `x-mary-toast` component source before implementing

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries and components verified from vendor source
- Architecture: HIGH — patterns derived directly from existing Phase 8 reference component
- Pitfalls: HIGH — sourced from codebase inspection and known Phase 7/8 decisions in STATE.md
- Toast for non-Livewire pages: MEDIUM — architectural inference, flagged for verification

**Research date:** 2026-03-16
**Valid until:** 2026-04-15 (stable libraries; Mary UI and Livewire APIs very stable)
