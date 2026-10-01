# Phase 16: Missing File Detection & Polish - Research

**Researched:** 2026-03-19
**Domain:** Laravel/Livewire — file availability detection, re-link UX, Alpine.js queue error handling
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Unavailable Indicator**
- Dimmed row (reduced opacity ~50%) plus red/warning "Unavailable" badge
- Play button hidden entirely on unavailable tracks (not disabled — removed)
- Badge shown consistently everywhere: library, album detail, playlist detail
- "Unavailable" option added to existing source filter dropdown (All / Uploaded / Local / Unavailable)

**Detection Trigger**
- Missing file detection runs during folder rescan only — no separate scheduled job
- ScanLinkedFolder job checks file existence for all known tracks in the folder during rescan
- If a previously-missing file reappears at its original path, auto-restore to available state
- Rescan toast includes unavailable count: "Folder scanned: X tracks found, Y unavailable"

**Re-link Flow**
- Modal with path input: click "Re-link" button on unavailable track → modal shows old path, text input for new path, validate & save
- Hash match warning: compute hash of new file — if it doesn't match original, show warning but still allow re-link
- Re-link button available from track row everywhere (library, album, playlist) — not just settings
- Bulk re-link: "Update folder path" option on linked folder that updates all track paths by replacing old prefix with new prefix — handles folder moves elegantly
- Individual re-link also available for single track path changes

**Playback Behavior**
- Queue auto-skips unavailable tracks with brief toast: "Skipped [track] — file unavailable"
- "Play All" / "Add all to queue" actions exclude unavailable tracks silently
- StreamController returns 404 when file doesn't exist on disk (Alpine player handles error)
- Unavailable tracks cannot be added to playlists (blocked in UI)

### Claude's Discretion
- Exact badge color/styling within Mary UI conventions
- Opacity value for dimmed rows
- Re-link modal layout details
- How bulk path update is validated (prefix replacement logic)
- Error handling for invalid re-link paths

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| LIBR-05 | Missing files detected and marked as unavailable with visual indicator | `is_available` boolean on Track, ScanLinkedFolder extended to check `file_exists()` per known track, dimmed row + Unavailable badge in views |
| LIBR-06 | User can re-link a moved file by updating its path | RelinkTrackModal Livewire component with path input + hash warning; bulk path prefix update on LinkedFolder from settings page |
</phase_requirements>

## Summary

Phase 16 closes the final two v1.2 requirements. The core work is: (1) add `is_available` to the `tracks` table, (2) extend `ScanLinkedFolder` to check file existence for every track already in the folder and flip the flag, (3) surface the unavailable state in every track row view with dimming + badge + hidden play button, (4) add "Unavailable" to the source filter dropdown, (5) build a `RelinkTrackModal` Livewire component following the `AddToPlaylistModal` pattern, (6) add a bulk-path-update action to the linked folders settings page, and (7) teach the Alpine audio player to show a toast and skip when a 404 arrives from the stream route.

The StreamController already returns JSON 404 when `file_exists()` fails — the Alpine `onAudioError` handler already advances to the next track. The only gap is the skip toast and the "skip unavailable tracks when loading a queue" filter.

**Primary recommendation:** Drive all availability state through the `is_available` boolean on `Track`. Keep detection entirely inside `ScanLinkedFolder::handle()`. Build the re-link modal as a standalone Livewire component dispatched via a browser event, identical in structure to `AddToPlaylistModal`.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Livewire | v4 | Reactive PHP components for modal + filter + re-link | Project standard — all interactive UI |
| Alpine.js | bundled with Livewire | Client-side toast on skip, `onAudioError` extension | Already used in AudioPlayer |
| Mary UI (`x-mary-badge`, `x-mary-modal`) | project version | Badge + modal components | Already used for Local badge and AddToPlaylist modal |
| Laravel Migrations | Laravel 12 | Add `is_available` boolean column to tracks | Standard DB change mechanism |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `Mary\Traits\Toast` | project version | `$this->success()` / `$this->error()` toasts | In every Livewire action that needs user feedback |
| `#[Computed]` attribute | Livewire v4 | Cached computed properties (`tracks`, `folders`) | Any derived data read in view |
| `#[On]` attribute | Livewire v4 | Browser event → component method (`open-relink-modal`) | Opening the re-link modal from any view |

**Installation:** No new packages required.

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Jobs/
│   └── ScanLinkedFolder.php          # Extend handle() with availability check loop
├── Livewire/
│   └── RelinkTrackModal.php          # New — mirrors AddToPlaylistModal pattern
└── Models/
    └── Track.php                     # Add is_available, scopeUnavailable()
database/migrations/
└── YYYY_MM_DD_add_is_available_to_tracks_table.php
resources/views/
├── livewire/
│   └── relink-track-modal.blade.php  # New modal view
└── pages/library/
    ├── index.blade.php               # Add Unavailable filter, dimming, hide play btn
    ├── album-show.blade.php          # Same track row changes
    └── playlists/show.blade.php      # Same track row changes
```

### Pattern 1: is_available boolean column
**What:** Simple boolean on `tracks` table, default `true`. Only set to `false` by `ScanLinkedFolder`. Auto-restored to `true` if file reappears.
**When to use:** Uploaded tracks never need this flag — they are always `true` (or NULL treated as true).

```php
// Migration
Schema::table('tracks', function (Blueprint $table) {
    $table->boolean('is_available')->default(true)->after('source_type');
});
```

```php
// Track model — add scope
public function scopeUnavailable(Builder $query): Builder
{
    return $query->where('is_available', false);
}

// Add to fillable
'is_available',

// Add to casts()
'is_available' => 'boolean',
```

### Pattern 2: ScanLinkedFolder — availability check loop
**What:** After the filesystem scan loop, iterate every track in the folder and call `file_exists()` on each path. Flip `is_available` accordingly. Update `track_count` to reflect only available tracks, and track the unavailable count for the toast.

**Key detail:** The existing scan loop already handles the "file found on disk" path (creates or updates tracks). The new loop handles the inverse — tracks we know about but the filesystem scan did NOT encounter.

```php
// After the filesystem iterator loop completes, inside handle():

$allFolderTracks = $this->folder->tracks()->get();
$unavailableCount = 0;

foreach ($allFolderTracks as $track) {
    $exists = file_exists($track->file_path);

    if (! $exists && $track->is_available) {
        $track->update(['is_available' => false]);
        $unavailableCount++;
    } elseif ($exists && ! $track->is_available) {
        $track->update(['is_available' => true]);
    }
}

$this->folder->update([
    'status'          => FolderStatus::Active,
    'track_count'     => $this->folder->tracks()->count(),
    'last_scanned_at' => now(),
]);

// Return unavailable count for the toast — store on folder or use a job result event
// Simplest: store unavailable_count on LinkedFolder model
```

**Note:** The rescan toast "X tracks found, Y unavailable" requires passing the count back to the Livewire component that dispatched the job. The simplest approach is to store `unavailable_count` on the `linked_folders` table (add column in migration) and read it after the job completes via polling (the settings page already polls while scanning).

### Pattern 3: RelinkTrackModal Livewire component
**What:** Listens for `open-relink-modal` browser event with `trackId`. Shows old path, text input for new path, validates path exists, computes hash of new file, warns if hash differs, saves new path and restores `is_available`.

**Pattern source:** `AddToPlaylistModal` — identical lifecycle: `#[On]`, `openForTrack()`, `$this->showModal`, `wire:model="showModal"`.

```php
// app/Livewire/RelinkTrackModal.php

#[On('open-relink-modal')]
public function openForTrack(int $trackId): void
{
    $this->selectedTrackId = $trackId;
    $this->newPath = '';
    $this->hashMismatch = false;
    $this->showModal = true;
}

public function relink(): void
{
    $this->validate(['newPath' => ['required', 'string']]);

    $normalisedPath = str_replace('\\', '/', $this->newPath);

    if (! file_exists($normalisedPath)) {
        $this->addError('newPath', 'File not found at this path.');
        return;
    }

    $track = Track::where('id', $this->selectedTrackId)
        ->where('user_id', Auth::id())
        ->firstOrFail();

    $newHash = md5_file($normalisedPath);

    if ($newHash !== $track->file_hash && ! $this->confirmedMismatch) {
        $this->hashMismatch = true;
        return; // Show warning in view — user clicks "Re-link Anyway"
    }

    $track->update([
        'file_path'    => $normalisedPath,
        'file_hash'    => $newHash,
        'is_available' => true,
    ]);

    $this->closeModal();
    $this->success('Track re-linked successfully.');
}
```

### Pattern 4: Source filter — "Unavailable" option
**What:** Add `'unavailable'` value to the existing `sourceFilter` property scope chain in `library/index.blade.php`.

```php
// In tracks() computed property
->when($this->sourceFilter === 'unavailable', fn ($q) => $q->unavailable())
```

```blade
{{-- In the select dropdown --}}
<option value="unavailable">Unavailable</option>
```

### Pattern 5: Track row dimming + Unavailable badge
**What:** Wrap each `<tr>` with conditional opacity class. Conditionally render the "Unavailable" badge alongside the existing "Local" badge. Hide the play button cell entirely for unavailable tracks.

```blade
<tr wire:key="{{ $track->id }}"
    class="group border-b border-zinc-800 hover:bg-zinc-800/50 {{ $track->is_available ? '' : 'opacity-50' }}">

    {{-- Play button cell — hidden for unavailable tracks --}}
    <td class="py-3 pr-2">
        @if ($track->is_available)
            <button ...>
                <x-mary-icon name="o-play" class="size-4" />
            </button>
        @endif
    </td>

    {{-- Title cell with badges --}}
    <td class="py-3 pr-4">
        <span class="flex items-center gap-2">
            {{ $track->title }}
            @if ($track->source_type === \App\Enums\SourceType::Local)
                <x-mary-badge value="Local" class="badge-ghost badge-sm" />
            @endif
            @if (! $track->is_available)
                <x-mary-badge value="Unavailable" class="badge-error badge-sm" />
            @endif
        </span>
    </td>
```

### Pattern 6: Re-link button in track rows
**What:** A small button (icon or text) in the actions column of unavailable track rows that dispatches `open-relink-modal`. Visible only when `!$track->is_available`.

```blade
@if (! $track->is_available)
    <button
        type="button"
        x-on:click="$dispatch('open-relink-modal', { trackId: {{ $track->id }} })"
        class="flex size-7 items-center justify-center rounded opacity-0 transition-opacity group-hover:opacity-100 hover:bg-zinc-700 text-zinc-400 hover:text-white"
        aria-label="Re-link {{ $track->title }}"
    >
        <x-mary-icon name="o-link" class="size-4" />
    </button>
@endif
```

### Pattern 7: Bulk path prefix update (settings page)
**What:** New action `updateFolderPath(int $folderId)` on the linked folders settings component. Shows a modal with old path and new path input. Replaces the prefix on every track in the folder, verifies at least one file exists at the new path, then updates the folder's own path.

```php
public function updateFolderPath(): void
{
    $this->validate(['newFolderPath' => ['required', 'string']]);

    $folder = LinkedFolder::where('user_id', auth()->id())->findOrFail($this->updatingFolderId);
    $normalisedNew = rtrim(str_replace('\\', '/', $this->newFolderPath), '/');

    if (! is_dir($normalisedNew)) {
        $this->addError('newFolderPath', 'Path is not a readable directory.');
        return;
    }

    // Replace path prefix on all tracks
    $oldPath = $folder->path;
    $folder->tracks()->each(function (Track $track) use ($oldPath, $normalisedNew) {
        $newTrackPath = $normalisedNew . substr($track->file_path, strlen($oldPath));
        $track->update([
            'file_path'    => $newTrackPath,
            'is_available' => file_exists($newTrackPath),
        ]);
    });

    $folder->update(['path' => $normalisedNew]);
    // Then trigger rescan to refresh metadata
    ScanLinkedFolder::dispatch($folder);
    ...
}
```

### Pattern 8: Alpine player — skip toast on 404
**What:** The existing `onAudioError` already calls `this.$wire.nextTrack()`. Extend it to show a brief skip toast before advancing. The toast should reference the current track title.

```javascript
onAudioError() {
    this.isPlaying = false;

    // Show skip toast if a track title is known
    if (this.trackTitle) {
        // Use a simple Alpine-managed toast or dispatch a Livewire event
        this.$dispatch('toast', { message: `Skipped "${this.trackTitle}" — file unavailable` });
    }

    if (this.hasNext) {
        this.$wire.nextTrack();
    }
},
```

**Note:** The project dispatches toasts via `$this->dispatch('toast', message: '...')` on the Livewire side. For Alpine-side toasts, check if the layout listens for the same browser event. If not, the simplest approach is a brief Alpine-managed `x-data` toast div in the audio-player template itself.

### Pattern 9: Queue loading excludes unavailable tracks
**What:** `AudioPlayer::loadQueue()` currently filters by ownership. Add a second filter to exclude `is_available = false` tracks.

```php
public function loadQueue(array $trackIds, int $startIndex = 0): void
{
    $ownedIds = Track::where('user_id', Auth::id())
        ->where('is_available', true)   // NEW — exclude unavailable
        ->whereIn('id', $trackIds)
        ->pluck('id')
        ->all();
    ...
}
```

### Pattern 10: AddToPlaylist blocks unavailable tracks
**What:** In `AddToPlaylistModal::addToPlaylist()`, verify the track is available before attaching. In the view, the "Add to playlist" button can be hidden for unavailable tracks alongside the play button.

### Anti-Patterns to Avoid
- **Checking file_exists() in computed properties or view rendering:** Never call `file_exists()` at render time — it blocks and can cause timeouts on network mounts. All availability state must come from the `is_available` DB column.
- **Scheduling a separate job for detection:** Decided against — piggyback on rescan only.
- **Disabling the play button instead of hiding it:** Decided to remove it entirely per CONTEXT.md.
- **Using `file_hash` for cross-user deduplication:** Hash is per-user. Don't use it across users.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Modal lifecycle | Custom event system | `#[On]` + `wire:model="showModal"` | Already proven in `AddToPlaylistModal` |
| Path normalisation | Custom string logic | Existing attribute mutator pattern on Track | Already handles backslash/forward slash |
| Toast notifications | Custom flash system | `Mary\Traits\Toast` or `$this->dispatch('toast', ...)` | Already in use everywhere |
| Queue filtering | Manual array operations | Eloquent scope `->where('is_available', true)` | Cleaner, tested via DB |

## Common Pitfalls

### Pitfall 1: file_exists() on Windows paths in tests
**What goes wrong:** `file_exists()` returns false for forward-slash paths on Windows if the actual temp file was created with a different separator.
**Why it happens:** PHP on Windows accepts both separators but `realpath()` may normalise differently.
**How to avoid:** In `ScanLinkedFolder` tests, use `sys_get_temp_dir()` paths and the same normalisation (`str_replace('\\', '/', ...)`) the job applies. The test setup in `ScanLinkedFolderTest` already does this correctly.
**Warning signs:** Tests fail on CI (Linux) but pass locally — or vice versa.

### Pitfall 2: Unavailable count in rescan toast
**What goes wrong:** `ScanLinkedFolder` is a queued job — it cannot directly update Livewire state. The settings page must read the count after the job completes.
**How to avoid:** Store `unavailable_count` on the `linked_folders` table. The settings page already polls every 3s while scanning — it will pick up the count on the next poll after the job finishes.

### Pitfall 3: Hash mismatch confirmation state lost on re-render
**What goes wrong:** If `hashMismatch = true` triggers a Livewire re-render, the modal state resets.
**How to avoid:** Keep `hashMismatch` as a public Livewire property and `confirmedMismatch` as a separate boolean. Use `wire:click="$set('confirmedMismatch', true)"` → `wire:click="relink"` chained, or a single action that checks the confirmed flag.

### Pitfall 4: Bulk path update with no tracks
**What goes wrong:** A folder with 0 tracks triggers the `each()` loop with nothing to iterate — then updates the folder path to a potentially non-existent directory.
**How to avoid:** Validate `is_dir($normalisedNew)` before any track updates (already in pattern above).

### Pitfall 5: `loadQueue` start index out of bounds after filtering unavailable tracks
**What goes wrong:** User clicks track at index 4 in library, but after filtering unavailable tracks, the queue has fewer entries — `$startIndex` 4 is past the end.
**How to avoid:** The existing `max(0, min($startIndex, count($this->queue) - 1))` clamp handles this, but the caller must pass the index into the **filtered** queue. Recalculate index after filtering in `loadQueue`, or pass `trackId` as start anchor rather than index. Safest: pass the `trackId` to start from and resolve its index after filtering.

### Pitfall 6: Alpine skip toast method
**What goes wrong:** `$dispatch('toast', ...)` from Alpine dispatches a browser event, not a Livewire event. The layout's toast listener may expect a Livewire dispatch.
**How to avoid:** Check how the layout handles `toast` events. The settings page uses `$this->dispatch('toast', message: ...)` (Livewire). For Alpine-side, use `$wire.dispatch('toast', { message: ... })` to route through Livewire, or add a small inline Alpine toast in the audio player view.

## Code Examples

### Migration: add is_available to tracks
```php
// Source: Laravel 12 migration conventions (existing migrations in this project)
Schema::table('tracks', function (Blueprint $table) {
    $table->boolean('is_available')->default(true)->after('source_type');
});
```

### Migration: add unavailable_count to linked_folders
```php
Schema::table('linked_folders', function (Blueprint $table) {
    $table->unsignedInteger('unavailable_count')->default(0)->after('track_count');
});
```

### Track model additions
```php
protected $fillable = [
    // existing...
    'is_available',
];

protected function casts(): array
{
    return [
        // existing...
        'is_available' => 'boolean',
    ];
}

public function scopeUnavailable(Builder $query): Builder
{
    return $query->where('is_available', false);
}
```

### ScanLinkedFolder — availability check (appended to handle())
```php
// After filesystem loop, before folder->update()
$unavailableCount = 0;
$this->folder->tracks()->each(function (Track $track) use (&$unavailableCount): void {
    $exists = file_exists($track->file_path);
    if (! $exists && $track->is_available) {
        $track->update(['is_available' => false]);
        $unavailableCount++;
    } elseif ($exists && ! $track->is_available) {
        $track->update(['is_available' => true]);
    }
});

$this->folder->update([
    'status'            => FolderStatus::Active,
    'track_count'       => $this->folder->tracks()->count(),
    'unavailable_count' => $unavailableCount,
    'last_scanned_at'   => now(),
]);
```

### Rescan toast with counts (settings page)
```php
// After ScanLinkedFolder::dispatch($folder) — toast shown on next poll completion
// The poll re-reads $this->folders which includes unavailable_count
// Show count in the folder row, not in a toast (since toast fires before job completes)
// Alternative: dispatch job synchronously in tests using ->handle() directly
```

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest v4 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=MissingFile` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| LIBR-05 | ScanLinkedFolder marks track unavailable when file deleted | Feature | `php artisan test --compact --filter=ScanLinkedFolder` | ✅ (extend existing) |
| LIBR-05 | ScanLinkedFolder restores track when file reappears | Feature | `php artisan test --compact --filter=ScanLinkedFolder` | ✅ (extend existing) |
| LIBR-05 | Library view shows dimmed row + Unavailable badge | Feature/Livewire | `php artisan test --compact --filter=LibraryUnavailableTrack` | ❌ Wave 0 |
| LIBR-05 | Unavailable filter option filters correctly | Feature/Livewire | `php artisan test --compact --filter=LibrarySourceFilter` | ✅ (extend existing) |
| LIBR-05 | Play button absent for unavailable tracks | Feature/Livewire | `php artisan test --compact --filter=LibraryUnavailableTrack` | ❌ Wave 0 |
| LIBR-05 | loadQueue excludes unavailable tracks | Feature | `php artisan test --compact --filter=AudioPlayer` | ❌ Wave 0 |
| LIBR-06 | RelinkTrackModal re-links track with valid path | Feature/Livewire | `php artisan test --compact --filter=RelinkTrackModal` | ❌ Wave 0 |
| LIBR-06 | RelinkTrackModal warns on hash mismatch | Feature/Livewire | `php artisan test --compact --filter=RelinkTrackModal` | ❌ Wave 0 |
| LIBR-06 | Bulk path update replaces prefix on all folder tracks | Feature | `php artisan test --compact --filter=LinkedFoldersSettings` | ✅ (extend existing) |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=<task-relevant filter>`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Library/LibraryUnavailableTrackTest.php` — covers LIBR-05 UI (dimming, badge, missing play button)
- [ ] `tests/Feature/Livewire/RelinkTrackModalTest.php` — covers LIBR-06 re-link modal
- [ ] `tests/Feature/AudioPlayerQueueTest.php` — covers unavailable track exclusion from loadQueue

*(Extend existing files: `ScanLinkedFolderTest.php`, `LibrarySourceFilterTest.php`, `LinkedFoldersSettingsTest.php`)*

## Sources

### Primary (HIGH confidence)
- Direct codebase inspection — `app/Jobs/ScanLinkedFolder.php`, `app/Livewire/AddToPlaylistModal.php`, `app/Livewire/AudioPlayer.php`, `app/Models/Track.php`, `resources/views/pages/library/index.blade.php`, `resources/views/pages/settings/⚡linked-folders.blade.php`
- Project `STATE.md` — established decisions (path normalisation, Gate::authorize, test patterns)
- `tests/Feature/ScanLinkedFolderTest.php` — test setup patterns (tmp dir, unique file content)

### Secondary (MEDIUM confidence)
- PHP `file_exists()` behaviour on Windows paths with forward-slash normalisation — consistent with existing project mutator pattern

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new libraries needed, all patterns already in codebase
- Architecture: HIGH — directly derived from existing `AddToPlaylistModal`, `ScanLinkedFolder`, and track row templates
- Pitfalls: HIGH — based on `STATE.md` recorded gotchas (hash collisions, Queue::fake, bool attribute binding)

**Research date:** 2026-03-19
**Valid until:** End of v1.2 milestone (stable stack, no fast-moving dependencies)
