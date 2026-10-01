# Phase 15: Folder Linking UI - Research

**Researched:** 2026-03-18
**Domain:** Livewire 4 full-page components, Mary UI, settings subpages, library integration
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Folder Management Page**
- Settings subpage — new tab in existing settings layout alongside Profile, Password, Appearance, etc.
- Text input for folder path with a "Link Folder" validate-and-submit button (server validates path is readable)
- Folder list shows: name, status badge, track count (minimal — no path or last-scanned timestamp in list)
- Actions per folder: Rescan, Remove, Edit name
- Auto-scan on link — dispatching scan job immediately after folder is validated and created

**Source Badges & Library Integration**
- Small text badge ("Local") next to track title using x-mary-badge with badge-ghost class
- Only local tracks show a badge — uploaded tracks have no badge (they're the default)
- Badge shown consistently everywhere: library, album detail, playlist detail, search results
- Source filter as dropdown/select next to existing search bar (All / Uploaded / Local)

**Scan Feedback & Status**
- Livewire polling every few seconds while any folder has "scanning" status
- Folder row shows "Scanning..." badge with loading spinner during scan
- Toast notification on scan completion: "Folder scanned: X tracks found"
- Error badge (red) + toast with error message on scan failure
- Individual file errors logged but don't block scan — Phase 14 handles this

**Folder Removal Flow**
- Custom Mary UI modal (not browser confirm) showing folder name, path, track count
- Modal includes affected playlist count: "X tracks are in Y playlists. They will be removed from those playlists too."
- Remove button disabled while folder status is "scanning" — user must wait for scan to complete
- Success toast after removal: "Folder removed. X tracks removed from library."
- Folder row disappears from list after removal

### Claude's Discretion
- Exact polling interval (2-5 seconds range)
- Settings page tab ordering/placement
- Folder name edit UX (inline edit vs modal)
- Badge color/styling details within Mary UI conventions
- Loading states and transitions

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| FOLD-01 | User can add a local folder path to link to their library | Settings subpage with path input + server-side `is_dir()` / `is_readable()` validation; `LinkedFolder::create()` + dispatch `ScanLinkedFolder` |
| FOLD-03 | User can link multiple folders from different locations | No unique constraint on path per user; list component renders all user's `LinkedFolder` records |
| FOLD-05 | User can see scan progress while folder is being processed | `wire:poll` on component while any folder has `FolderStatus::Scanning`; folder row shows "Scanning..." badge + spinner |
| FOLD-06 | User can remove a linked folder (tracks from that folder removed from library) | Mary UI modal confirmation → `$folder->delete()` (cascade handles tracks via DB FK); affected playlist tracks also removed via cascade or explicit detach |
| LIBR-01 | Linked tracks appear in existing library alongside uploaded tracks | Library index `#[Computed] tracks()` already queries all user tracks; no filter by source_type — local tracks naturally appear |
| LIBR-02 | Tracks display source badge indicating local-linked vs uploaded | `$track->source_type === SourceType::Local` check in blade; render `<x-mary-badge value="Local" class="badge-ghost" />` conditionally |
| LIBR-03 | Linked tracks work with existing search, playlists, and queue | Source filter dropdown added to library search bar; existing search/playlist/queue logic unchanged — local tracks use same `audioUrl()` → `stream.track` route |
</phase_requirements>

## Summary

This phase is purely UI and wiring — the backend data layer (models, enums, jobs, streaming) is fully in place from Phases 12-14. The work is: (1) a new settings subpage Livewire component for folder management, (2) source badges added to every track listing view, and (3) a source filter dropdown added to the library. No new database migrations or model changes are needed.

The existing codebase provides strong templates for everything required. The `⚡users.blade.php` settings page is the direct model for the folder management list. `AddToPlaylistModal` is the model for the removal confirmation modal. `FolderStatus` and `SourceType` enums are already wired into models. The `Track` model already has `scopeUploaded()` and `scopeLocal()` scopes and `audioUrl()` returning the authenticated stream route for all tracks.

The primary technical challenge is the polling mechanism for scan feedback. `wire:poll` must be conditional — only active when at least one folder is in `Scanning` status — to avoid unnecessary server requests on every library visit. The removal modal also needs to compute affected playlist counts before display, which requires a query against the `playlist_tracks` table joining through the folder's tracks.

**Primary recommendation:** Build as three discrete plans — (1) linked folders settings page, (2) source badges across track listings, (3) source filter in library. Badges and filter are pure view changes; the settings page is the only new Livewire component.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Livewire | v4 | Full-page component for settings subpage | Project standard for all settings pages |
| Mary UI | current | `x-mary-badge`, `x-mary-modal`, `x-mary-button`, `x-mary-input` | Project UI component library |
| `Mary\Traits\Toast` | current | Toast notifications for scan/removal feedback | Used in `AddToPlaylistModal`, established pattern |
| Tailwind CSS | v4 | Inline styling adjustments | Project standard |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Alpine.js | bundled in Livewire 4 | Client-side spinner state, inline edit toggling | Discrete interactions not requiring server roundtrip |
| `#[Computed]` | Livewire 4 | Cached database queries (folder list, playlist counts) | All DB-fetching properties on the component |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `wire:poll` | Livewire events / SSE | `wire:poll` is simpler and sufficient; SSE is over-engineered for personal app |
| Mary UI modal | `wire:confirm` | `wire:confirm` is too simple — need to show track/playlist counts in removal dialog |

**No new packages needed.**

## Architecture Patterns

### Recommended Project Structure

New files this phase:
```
resources/views/pages/settings/
└── ⚡linked-folders.blade.php   # New settings subpage (single-file Livewire SFC)

resources/views/livewire/
└── (no new livewire components — removal modal inline in settings page)

routes/settings.php              # Add linked-folders route

resources/views/pages/settings/layout.blade.php  # Add menu item
resources/views/pages/library/index.blade.php    # Add badge + source filter
resources/views/pages/library/album-show.blade.php  # Add badge
resources/views/pages/profile/show.blade.php        # Check if playlists rendered here
```

### Pattern 1: Settings Page Single-File Component (SFC)

**What:** PHP class + Blade template in one `⚡` prefixed file under `resources/views/pages/settings/`
**When to use:** All settings subpages in this project

```php
// Source: existing ⚡users.blade.php pattern
<?php
use App\Models\LinkedFolder;
use App\Jobs\ScanLinkedFolder;
use App\Enums\FolderStatus;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public string $path = '';
    public string $name = '';
    public bool $showRemoveModal = false;
    public ?int $removingFolderId = null;

    #[Computed]
    public function folders(): \Illuminate\Database\Eloquent\Collection
    {
        return LinkedFolder::where('user_id', auth()->id())
            ->withCount('tracks')
            ->orderBy('name')
            ->get();
    }

    public function linkFolder(): void
    {
        $this->validate([
            'path' => ['required', 'string'],
        ]);

        $normalised = str_replace('\\', '/', $this->path);

        if (! is_dir($normalised) || ! is_readable($normalised)) {
            $this->addError('path', 'Path is not a readable directory.');
            return;
        }

        $folder = LinkedFolder::create([
            'user_id' => auth()->id(),
            'path'    => $normalised,
            'name'    => $this->name ?: basename($normalised),
            'status'  => FolderStatus::Active,
        ]);

        ScanLinkedFolder::dispatch($folder);

        $this->reset('path', 'name');
        $this->success('Folder linked. Scanning started.');
    }
}; ?>
```

### Pattern 2: Conditional wire:poll for Scan Progress

**What:** Poll only while at least one folder is scanning — stops automatically when all scans complete
**When to use:** Any Livewire component that needs to react to background job state changes

```php
// In the Blade template section of the settings SFC:
// wire:poll fires every N seconds; @if guard prevents polling when unnecessary
@if ($this->folders->contains('status', FolderStatus::Scanning))
    <div wire:poll.3s></div>
@endif
```

**Why a hidden div:** `wire:poll` on an invisible div forces a component re-render at the interval without tying the poll to any specific element. The `#[Computed]` `folders` property auto-refreshes on re-render.

### Pattern 3: Removal Modal with Impact Summary

**What:** Mary UI `x-mary-modal` wired via `wire:model` boolean, shows folder name + track count + affected playlist count before deletion.
**When to use:** Destructive actions that affect related data beyond the deleted record.

```php
// Component properties
public bool $showRemoveModal = false;
public ?int $removingFolderId = null;

#[Computed]
public function folderBeingRemoved(): ?LinkedFolder
{
    if ($this->removingFolderId === null) {
        return null;
    }
    return LinkedFolder::where('id', $this->removingFolderId)
        ->where('user_id', auth()->id())
        ->withCount('tracks')
        ->first();
}

#[Computed]
public function affectedPlaylistCount(): int
{
    if (! $this->folderBeingRemoved) {
        return 0;
    }
    // Count distinct playlists containing tracks from this folder
    return \App\Models\PlaylistTrack::query()
        ->whereHas('track', fn ($q) => $q->where('linked_folder_id', $this->removingFolderId))
        ->distinct('playlist_id')
        ->count('playlist_id');
}

public function confirmRemove(int $folderId): void
{
    $this->removingFolderId = $folderId;
    $this->showRemoveModal = true;
}

public function removeFolder(): void
{
    $folder = LinkedFolder::where('id', $this->removingFolderId)
        ->where('user_id', auth()->id())
        ->firstOrFail();

    if ($folder->status === FolderStatus::Scanning) {
        $this->error('Cannot remove a folder that is currently scanning.');
        return;
    }

    $trackCount = $folder->tracks()->count();
    $folder->delete(); // cascade removes tracks and playlist_tracks via FK

    $this->showRemoveModal = false;
    $this->removingFolderId = null;
    $this->success("Folder removed. {$trackCount} tracks removed from library.");
}
```

### Pattern 4: Source Badge in Track Row

**What:** Conditional `x-mary-badge` inline next to track title in table cell
**When to use:** Every track listing — library index, album show, playlist show, search results

```blade
{{-- In the title cell of any track table row --}}
<td class="py-3 pr-4">
    <span class="flex items-center gap-2">
        {{ $track->title }}
        @if ($track->source_type === \App\Enums\SourceType::Local)
            <x-mary-badge value="Local" class="badge-ghost badge-sm" />
        @endif
    </span>
</td>
```

### Pattern 5: Source Filter Dropdown

**What:** `x-mary-select` (or native `<select>` matching project style) next to the existing search bar; filters the `#[Computed] tracks()` query via an additional `when()` scope.
**When to use:** Library index page alongside existing search.

```php
// Component property
public string $sourceFilter = 'all'; // 'all' | 'uploaded' | 'local'

// In tracks() Computed:
->when($this->sourceFilter === 'uploaded', fn ($q) => $q->uploaded())
->when($this->sourceFilter === 'local', fn ($q) => $q->local())
```

```blade
{{-- Alongside the existing x-mary-input search bar --}}
<div class="flex gap-2 mb-4">
    <x-mary-input
        wire:model.live.debounce.300ms="search"
        type="search"
        placeholder="Search your library..."
        icon="o-magnifying-glass"
        class="flex-1"
    />
    <select wire:model.live="sourceFilter" class="select select-bordered">
        <option value="all">All</option>
        <option value="uploaded">Uploaded</option>
        <option value="local">Local</option>
    </select>
</div>
```

### Pattern 6: Settings Sidebar Menu Item

**What:** Add `x-mary-menu-item` to `resources/views/pages/settings/layout.blade.php` for Linked Folders — no role guard (all authenticated users can link folders).
**When to use:** All settings subpages appear here.

```blade
{{-- In layout.blade.php, after Appearance: --}}
<x-mary-menu-item title="{{ __('Linked Folders') }}" link="{{ route('settings.linked-folders') }}" />
```

### Pattern 7: Route Registration

**What:** Add to `routes/settings.php` under the `auth` middleware group (not `auth + verified`, matching how `profile.edit` and `settings.users` are registered).

```php
Route::livewire('settings/linked-folders', 'pages::settings.linked-folders')
    ->name('settings.linked-folders');
```

### Anti-Patterns to Avoid
- **Polling always-on:** `wire:poll` unconditionally on the page will hammer the server on every library view. Gate it behind a check for scanning status.
- **Inline DB queries in Blade:** Use `#[Computed]` properties for all database access; never query in the template.
- **Browser `confirm()` for removal:** Decided against — use Mary UI modal to show impact summary.
- **`$this->authorize()` in Livewire:** This project uses `Gate::authorize()` (base Controller lacks `AuthorizesRequests` trait — confirmed in STATE.md). Alternatively, scope queries to `auth()->id()` directly (as done in `AddToPlaylistModal`).
- **Missing `wire:key`:** Every `@foreach` over folders must have `wire:key="folder-{{ $folder->id }}"` — Livewire 4 requires this for correct DOM diffing.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Toast notifications | Custom flash/session system | `Mary\Traits\Toast` — `$this->success()`, `$this->error()` | Already used in `AddToPlaylistModal`; handles auto-dismiss |
| Confirmation modal | `wire:confirm` or custom Alpine modal | `x-mary-modal` with `wire:model` | `wire:confirm` can't show computed data; Mary modal is project standard |
| Scan status updates | WebSockets / Echo | `wire:poll` conditional on scanning status | Sufficient for personal app; no infrastructure required |
| Source filtering | Custom query builder | `scopeUploaded()` / `scopeLocal()` on `Track` | Already exist on `Track` model; use them |
| Path normalisation | Inline `str_replace` in component | `LinkedFolder` model mutator normalises on save | Model already handles backslash normalisation |

## Common Pitfalls

### Pitfall 1: Cascade Delete Doesn't Detach Playlist Pivot Records

**What goes wrong:** Deleting a `LinkedFolder` cascades to `tracks` (FK), but `playlist_tracks` pivot rows referencing those track IDs may not be cascade-deleted unless the FK on `playlist_tracks.track_id` also has `cascadeOnDelete`.
**Why it happens:** Laravel cascade is defined on the `linked_folders_id` FK on `tracks`, but `playlist_tracks` has a separate FK to `tracks.id`.
**How to avoid:** Before calling `$folder->delete()`, verify the migration for `playlist_tracks` uses `->cascadeOnDelete()` on the `track_id` FK. If not, manually detach: `PlaylistTrack::whereIn('track_id', $folder->tracks->pluck('id'))->delete()` before deleting the folder.
**Warning signs:** After folder removal, playlist track counts don't drop; orphaned `playlist_tracks` rows remain.

### Pitfall 2: wire:poll on Library Page When No Folders Are Scanning

**What goes wrong:** If `wire:poll` is added to the library page for scan feedback and left unconditional, every user hitting the library page will trigger server requests every few seconds even when nothing is scanning.
**Why it happens:** `wire:poll` is always-on unless conditionally rendered.
**How to avoid:** Limit polling to the linked folders settings page only (where the user is actively watching). The library page does not need poll — it's a passive display that benefits from real-time updates only during active scanning, which is a settings-page concern.

### Pitfall 3: Stale #[Computed] Cache After Mutations

**What goes wrong:** After calling `linkFolder()` or `removeFolder()`, the `#[Computed] folders()` result may return stale data from the component's in-memory cache.
**Why it happens:** Livewire 4 caches `#[Computed]` properties per request cycle. A mutation within the same action may not reflect immediately.
**How to avoid:** Call `unset($this->folders)` after mutations to bust the computed cache, or use `$this->js('window.location.reload()')` if a full page refresh is acceptable. The established project pattern is to let Livewire re-render naturally — the computed cache resets on next request, so the UI will be correct after the Livewire roundtrip completes.

### Pitfall 4: Path Validation — Directory Existence on Server vs Client

**What goes wrong:** A user types a Windows path (e.g. `C:\Music`) and the validation passes because it's valid on the server (Herd / Windows), but the path might reference a location not accessible by the PHP process.
**Why it happens:** `is_dir()` and `is_readable()` test the PHP process's access, which is correct. The edge case is case sensitivity and trailing slashes on Windows.
**How to avoid:** Normalise the path in the component before validating: `$normalised = rtrim(str_replace('\\', '/', $this->path), '/');`. Then test `is_dir($normalised) && is_readable($normalised)`. Friendly error: "The folder path could not be found or is not readable."

### Pitfall 5: source_type Filter Breaks When Library Has No Local Tracks

**What goes wrong:** If the user selects "Local" before linking any folders, the track list is empty with no explanation.
**Why it happens:** The query returns zero results but the empty state message may say "No tracks yet" (generic), not "No local tracks found."
**How to avoid:** Show a contextual empty message when `$sourceFilter !== 'all'` and results are empty: "No local tracks. Link a folder in Settings > Linked Folders."

## Code Examples

### Linking a Folder (Component Action)

```php
// Source: established project pattern from ⚡users.blade.php + LinkedFolder model
public function linkFolder(): void
{
    $this->validate(['path' => ['required', 'string']]);

    $normalised = rtrim(str_replace('\\', '/', $this->path), '/');

    if (! is_dir($normalised) || ! is_readable($normalised)) {
        $this->addError('path', 'The folder path could not be found or is not readable.');
        return;
    }

    $folder = LinkedFolder::create([
        'user_id' => auth()->id(),
        'path'    => $normalised,
        'name'    => $this->name ?: basename($normalised),
        'status'  => FolderStatus::Active,
    ]);

    ScanLinkedFolder::dispatch($folder);
    $this->reset('path', 'name');
    $this->success('Folder linked. Scanning started.');
}
```

### Conditional wire:poll (Blade)

```blade
{{-- Gate poll behind scanning status — stops when all scans complete --}}
@if ($this->folders->contains('status', \App\Enums\FolderStatus::Scanning))
    <div wire:poll.3s></div>
@endif
```

### Rescan Action

```php
public function rescan(int $folderId): void
{
    $folder = LinkedFolder::where('id', $folderId)
        ->where('user_id', auth()->id())
        ->firstOrFail();

    ScanLinkedFolder::dispatch($folder);
    $this->success('Rescan started.');
}
```

### Source Filter in Track Query

```php
// Source: Track::scopeUploaded() and Track::scopeLocal() already exist on Track model
#[Computed]
public function tracks(): \Illuminate\Database\Eloquent\Collection
{
    return Track::query()
        ->where('user_id', Auth::id())
        ->with('album')
        ->when($this->search, function ($q) {
            $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('artist', 'like', "%{$this->search}%")
                    ->orWhereHas('album', fn ($q) => $q->where('title', 'like', "%{$this->search}%"));
            });
        })
        ->when($this->sourceFilter === 'uploaded', fn ($q) => $q->uploaded())
        ->when($this->sourceFilter === 'local', fn ($q) => $q->local())
        ->orderBy('title')
        ->get();
}
```

### Removal Modal Blade Template

```blade
{{-- x-mary-modal pattern from AddToPlaylistModal --}}
<x-mary-modal wire:model="showRemoveModal" title="Remove Linked Folder">
    @if ($this->folderBeingRemoved)
        <p>Remove <strong>{{ $this->folderBeingRemoved->name }}</strong>?</p>
        <p class="text-sm text-base-content/60 mt-1">
            {{ $this->folderBeingRemoved->tracks_count }} tracks will be removed from your library.
            @if ($this->affectedPlaylistCount > 0)
                They appear in {{ $this->affectedPlaylistCount }} {{ Str::plural('playlist', $this->affectedPlaylistCount) }}.
            @endif
        </p>
    @endif

    <x-slot:actions>
        <x-mary-button label="Cancel" wire:click="$set('showRemoveModal', false)" />
        <x-mary-button
            label="Remove Folder"
            class="btn-error"
            wire:click="removeFolder"
            wire:loading.attr="disabled"
            @if ($this->folderBeingRemoved?->status === \App\Enums\FolderStatus::Scanning) disabled @endif
        />
    </x-slot:actions>
</x-mary-modal>
```

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 + PHPUnit 12 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=LinkedFolders` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| FOLD-01 | Settings page loads; path input submits and creates LinkedFolder + dispatches job | Feature | `php artisan test --compact --filter=LinkedFoldersSettingsTest` | ❌ Wave 0 |
| FOLD-01 | Invalid/unreadable path shows validation error | Feature | `php artisan test --compact --filter=LinkedFoldersSettingsTest` | ❌ Wave 0 |
| FOLD-03 | User can link a second folder; both appear in list | Feature | `php artisan test --compact --filter=LinkedFoldersSettingsTest` | ❌ Wave 0 |
| FOLD-05 | Folder with Scanning status shows "Scanning" badge in rendered HTML | Feature | `php artisan test --compact --filter=LinkedFoldersSettingsTest` | ❌ Wave 0 |
| FOLD-06 | Remove modal shows track count + playlist count; removal deletes folder and tracks | Feature | `php artisan test --compact --filter=LinkedFoldersSettingsTest` | ❌ Wave 0 |
| FOLD-06 | Remove button disabled when status is Scanning | Feature | `php artisan test --compact --filter=LinkedFoldersSettingsTest` | ❌ Wave 0 |
| LIBR-01 | Local track appears in library track list alongside uploaded tracks | Feature | `php artisan test --compact --filter=LibraryViewTest` | ✅ (extend existing) |
| LIBR-02 | Local track row contains "Local" badge text; uploaded track does not | Feature | `php artisan test --compact --filter=LibraryViewTest` | ✅ (extend existing) |
| LIBR-03 | Source filter "local" returns only local tracks; "uploaded" returns only uploaded | Feature | `php artisan test --compact --filter=LibrarySearchTest` | ✅ (extend existing) |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=LinkedFolders`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Settings/LinkedFoldersSettingsTest.php` — covers FOLD-01, FOLD-03, FOLD-05, FOLD-06
- [ ] Extend `tests/Feature/Library/LibraryViewTest.php` — covers LIBR-01, LIBR-02
- [ ] Extend `tests/Feature/Library/LibrarySearchTest.php` — covers LIBR-03

## Integration Points Summary

| Location | Change | Notes |
|----------|--------|-------|
| `routes/settings.php` | Add `settings/linked-folders` route | Under `auth` middleware (not `auth+verified`) |
| `resources/views/pages/settings/layout.blade.php` | Add Linked Folders menu item | No role guard — available to all users |
| `resources/views/pages/settings/⚡linked-folders.blade.php` | **New file** | SFC; path input form + folder list + removal modal |
| `resources/views/pages/library/index.blade.php` | Add source badge to track title cells; add source filter dropdown; add `sourceFilter` property + query scope | Both search and tab track list sections |
| `resources/views/pages/library/album-show.blade.php` | Add source badge to track title cells | Read-only view change |
| Playlist show view | Add source badge to track title cells | Check which file renders playlist tracks |

## Sources

### Primary (HIGH confidence)
- Existing codebase: `⚡users.blade.php`, `AddToPlaylistModal.php`, `layout.blade.php`, `Track.php`, `LinkedFolder.php`, `ScanLinkedFolder.php`, `FolderStatus.php`, `SourceType.php` — direct inspection
- `routes/settings.php`, `routes/web.php` — route registration patterns confirmed
- `tests/Feature/Settings/UsersSettingsTest.php` — test pattern for settings pages
- `STATE.md` — `Gate::authorize()` vs `$this->authorize()` decision documented

### Secondary (MEDIUM confidence)
- Livewire 4 `wire:poll` conditional rendering — documented in SKILL.md and Livewire 4 docs
- Mary UI `x-mary-modal` with `wire:model` — pattern observed in `AddToPlaylistModal` + Mary UI docs

### Tertiary (LOW confidence)
- `PlaylistTrack` cascade delete behaviour — needs verification against actual migration before implementing removal logic

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries already in use; no new dependencies
- Architecture: HIGH — direct templates exist in codebase for every pattern
- Pitfalls: HIGH (cascade delete: MEDIUM — needs migration verification)

**Research date:** 2026-03-18
**Valid until:** 2026-04-18 (stable stack)
