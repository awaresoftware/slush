# Phase 3: Music Library - Research

**Researched:** 2026-03-04
**Domain:** Audio file upload, ID3 tag extraction, album organization, Livewire library view, inline search
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Albums only — no playlists in this phase (Phase 5 handles playlists)
- Album fields: title, artist, year, genre, cover art (all optional)
- Tracks can exist without an album — loose tracks are valid
- Top-level library navigation: two tabs — **Tracks** | **Albums**
- Batch upload: multiple files selected/dropped at once
- Auto-extract ID3 tags (title, artist, album, year, genre, duration) on upload — no in-phase editing
- Supported formats: MP3, WAV, FLAC, M4A, AAC, OGG
- Max file size: 100 MB per file
- Entry point: "Upload" button on the library page that opens a modal
- Optional album picker in upload modal (select existing or create new album from dropdown)
- Processing: background queue job (file saved immediately, metadata extraction async)
- Progress feedback during upload: spinner/loading state (not per-file progress bars)
- After batch completes: redirect to a summary page listing all uploaded tracks with links
- Duplicate detection: hash-based check — warn the user ("this looks like a duplicate") but allow them to proceed
- **Tracks tab**: table/list with columns — title, artist, album, duration
- **Albums tab**: card grid with cover art, album title, and artist below each card
- Clicking an album → dedicated album page at `/library/albums/{id}` with full track list
- Sort order: alphabetical by title (fixed — no sortable column headers)
- Empty state: illustration + "Upload your first track" CTA that opens the upload modal
- Search: search bar at top of library page, inline above the Tracks/Albums tabs
- Fields searched: title, artist, album
- Trigger: instant as-you-type with debounce (no submit button needed)
- Results: tracks and albums displayed in separate sections ("Songs" and "Albums")
- Livewire full-page components with `#[Layout('layouts.app')]`
- File storage: public disk under `music/{userId}/`
- Flux UI components for UI (flux:table, flux:input, flux:modal, flux:badge)

### Claude's Discretion
- Illustration choice for empty state
- Exact debounce timing for search
- Album page layout details (track numbering, play button placement)
- Cover art fallback when no image is provided (placeholder color/icon)
- Queue driver configuration (use sync driver in tests)

### Deferred Ideas (OUT OF SCOPE)
- **Local file referencing** (iTunes-style): user points to a file path on disk rather than uploading a copy.
- **Metadata editing**: edit track title, artist, album, year, genre after upload.
- **Playlist creation in this phase**: roadmap scopes this to Phase 5.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| MUSC-01 | User can upload audio files (MP3, WAV, etc.) | Livewire `WithFileUploads` + `config/livewire.php` max:102400 + `james-heinrich/getid3` for metadata; queue job for async extraction |
| MUSC-02 | User can organize music into albums | `Album` and `Track` Eloquent models with `user_id` + optional `album_id`; album picker in upload modal |
| MUSC-03 | User can view their uploaded music library | Full-page Livewire component at `/library` with Tracks/Albums tabs; `flux:table` + card grid using Tailwind |
| MUSC-04 | User can search their own music collection | `wire:model.live.debounce.300ms` on search input; computed property filtering tracks/albums by title/artist/album |
</phase_requirements>

---

## Summary

Phase 3 builds the core music management features: batch audio upload with async ID3 extraction, album-based organization, and a tabbed library view with inline search. The PHP ecosystem has one dominant choice for ID3 tag extraction: `james-heinrich/getid3` v1.9.24 (stable), used by Drupal, thousands of packages, and well-maintained through PHP 8.4. It is the correct and only library to use here.

Livewire 4's `WithFileUploads` trait handles the batch upload experience natively with `wire:model` on a `multiple` file input. The 100 MB per-file limit requires a single config change in `config/livewire.php`. The queue job pattern established in `app/Jobs/` (none exist yet — we create the first) dispatches `ExtractAudioMetadata` after immediate file save. The established project pattern for full-page components (`#[Layout('layouts.app')]` + `Route::livewire()`) applies directly to `/library`, `/library/albums/{album}`, and `/library/upload/summary`.

Duplicate detection is implemented in the upload Livewire component: before saving, compute `hash_file('sha256', $file->getRealPath())`, check `Track::where('file_hash', $hash)->where('user_id', $userId)->exists()`, and set a `$duplicateWarnings` array. The user sees a warning but can proceed. This is pure PHP — no additional package needed.

**Primary recommendation:** Install `james-heinrich/getid3` (composer require), configure livewire.php for 100 MB uploads, follow the `WithFileUploads` + queue job pattern for async metadata extraction, and build library views as Livewire full-page components with the established `#[Layout('layouts.app')]` attribute.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `james-heinrich/getid3` | v1.9.24 (stable) | Extract ID3 tags (title, artist, album, year, genre, duration) from MP3/WAV/FLAC/M4A/AAC/OGG | The dominant PHP media parser; used by millions of packages, actively maintained, supports all required formats natively |
| `livewire/livewire` (already installed) | v4.0 | Batch file upload component with `WithFileUploads`, search with `wire:model.live` | Already in project; handles upload progress, multiple files, validation |
| `livewire/flux` (already installed) | v2.9+ | `flux:modal` for upload dialog, `flux:table` for tracks list, `flux:input` for search | Already in project; consistent with Phases 1-2 UI patterns |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHP `hash_file()` | built-in | SHA-256 file hash for duplicate detection | In upload Livewire component before `->store()` |
| Laravel Queue (database driver) | built-in | Async metadata extraction job | After file is saved to disk; prevents upload blocking |
| Laravel `Storage::disk('public')` | built-in | Store audio files at `music/{userId}/{filename}` | Consistent with avatar storage pattern in project |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `james-heinrich/getid3` | `wapmorgan/Mp3Info` | Mp3Info is 8-10x faster but MP3-only — does not support FLAC, OGG, WAV, M4A, AAC. Not suitable. |
| `james-heinrich/getid3` | `owen-oj/laravel-getid3` | Laravel wrapper that adds S3 support; unnecessary complexity for local public disk; direct getid3 is simpler |
| `james-heinrich/getid3` | `plutuss/getid3-laravel` | Another wrapper; limited community adoption vs. direct getid3 |
| Custom SHA-256 duplicate check | `maxkhim/laravel-storage-dedupler` | The dedupler package prevents storing the file entirely — we want to warn but allow. Custom hash check is 5 lines and gives us control. |

**Installation:**
```bash
composer require james-heinrich/getid3
```

**Livewire config update** (config/livewire.php):
```php
'temporary_file_upload' => [
    'rules' => 'file|max:102400', // 100 MB
    'middleware' => 'throttle:5,1',
    'directory' => 'tmp',
],
```

---

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Models/
│   ├── Track.php           # belongs to User, optionally belongs to Album
│   └── Album.php           # belongs to User, has many Tracks
├── Jobs/
│   └── ExtractAudioMetadata.php  # queued, reads file, updates Track
├── Concerns/
│   └── AudioValidationRules.php  # validation rules concern (follows ProfileValidationRules pattern)
resources/views/pages/
├── library/
│   ├── ⚡index.blade.php         # Full-page: Tracks + Albums tabs + search + upload modal
│   ├── ⚡album-show.blade.php    # Full-page: Single album with track list
│   └── ⚡upload-summary.blade.php # Full-page: Post-upload summary listing tracks
database/migrations/
│   ├── YYYY_MM_DD_create_albums_table.php
│   └── YYYY_MM_DD_create_tracks_table.php
database/factories/
│   ├── AlbumFactory.php
│   └── TrackFactory.php
tests/Feature/
│   └── Library/
│       ├── TrackUploadTest.php
│       ├── LibraryViewTest.php
│       └── AlbumTest.php
```

### Pattern 1: Full-Page Livewire Component with Layout Attribute
**What:** Single-file (SFC) Livewire component for each page, using `#[Layout('layouts.app')]` PHP 8 attribute
**When to use:** Every `/library/*` page

```php
// Source: livewire.laravel.com/docs/4.x/pages + established project pattern (⚡show.blade.php)
<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('My Library')] class extends Component {
    // ...
}; ?>
```

Route registration (routes/web.php, inside auth middleware group):
```php
Route::livewire('library', 'pages::library.index')->name('library.index');
Route::livewire('library/albums/{album}', 'pages::library.album-show')->name('library.album.show');
Route::livewire('library/upload/summary', 'pages::library.upload-summary')->name('library.upload.summary');
```

### Pattern 2: Batch Audio File Upload with WithFileUploads
**What:** Multiple audio files bound to an array property; validation with audio MIME types; immediate store, then dispatch queue job
**When to use:** Upload modal in the library index component

```php
// Source: livewire.laravel.com/docs/4.x/uploads
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    #[Validate(['audioFiles.*' => 'file|mimes:mp3,wav,flac,m4a,aac,ogg|max:102400'])]
    public array $audioFiles = [];

    public array $duplicateWarnings = [];

    public function upload(): void
    {
        $this->validate();
        $user = Auth::user();

        $uploadedTrackIds = [];

        foreach ($this->audioFiles as $file) {
            $hash = hash_file('sha256', $file->getRealPath());

            // Duplicate detection — warn but allow
            if (Track::where('file_hash', $hash)->where('user_id', $user->id)->exists()) {
                $this->duplicateWarnings[] = $file->getClientOriginalName();
            }

            $path = $file->store("music/{$user->id}", 'public');

            $track = Track::create([
                'user_id'    => $user->id,
                'file_path'  => $path,
                'file_hash'  => $hash,
                'title'      => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'duration'   => null, // filled by queue job
            ]);

            ExtractAudioMetadata::dispatch($track);
            $uploadedTrackIds[] = $track->id;
        }

        session(['uploaded_track_ids' => $uploadedTrackIds]);
        $this->redirect(route('library.upload.summary'));
    }
}
```

Blade (multiple file input):
```blade
<input type="file" wire:model="audioFiles" multiple
       accept=".mp3,.wav,.flac,.m4a,.aac,.ogg">
<div wire:loading wire:target="audioFiles">Uploading...</div>
```

### Pattern 3: Queue Job for Async ID3 Extraction
**What:** ShouldQueue job dispatched after file save; reads file with getID3, updates Track record
**When to use:** After every audio file save in the upload action

```php
// Source: laravel.com/docs/12.x/queues + getid3.org
<?php

namespace App\Jobs;

use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use getID3;

class ExtractAudioMetadata implements ShouldQueue
{
    use Queueable;

    public function __construct(public Track $track) {}

    public function handle(): void
    {
        $absolutePath = Storage::disk('public')->path($this->track->file_path);

        $getID3 = new getID3();
        $info = $getID3->analyze($absolutePath);
        getid3_lib::CopyTagsToComments($info);

        $tags = $info['comments'] ?? [];

        $this->track->update([
            'title'    => $tags['title'][0]  ?? $this->track->title,
            'artist'   => $tags['artist'][0] ?? null,
            'year'     => $tags['year'][0]   ?? null,
            'genre'    => $tags['genre'][0]  ?? null,
            'duration' => isset($info['playtime_seconds'])
                          ? (int) round($info['playtime_seconds'])
                          : null,
        ]);

        // Auto-create or attach album if album tag is present
        if (!empty($tags['album'][0]) && $this->track->album_id === null) {
            $album = Album::firstOrCreate([
                'user_id' => $this->track->user_id,
                'title'   => $tags['album'][0],
            ]);
            $this->track->update(['album_id' => $album->id]);
        }
    }
}
```

**Key getID3 data structure:**
```php
$getID3 = new getID3();
$info = $getID3->analyze('/path/to/file.mp3');
getid3_lib::CopyTagsToComments($info); // merges id3v1+id3v2+ogg comments → $info['comments']

// After CopyTagsToComments, access unified tags:
$info['comments']['title'][0]   // track title
$info['comments']['artist'][0]  // artist name
$info['comments']['album'][0]   // album name
$info['comments']['year'][0]    // year
$info['comments']['genre'][0]   // genre
$info['playtime_seconds']       // float, e.g. 245.34
```

### Pattern 4: Real-Time Search with Debounce
**What:** `wire:model.live.debounce.300ms` on search input; computed property returns filtered results
**When to use:** Search bar above the Tracks/Albums tabs in library index

```php
// Source: livewire.laravel.com/docs/4.x/wire-model
public string $search = '';

#[Computed]
public function tracks(): Collection
{
    return Track::query()
        ->where('user_id', Auth::id())
        ->when($this->search, fn ($q) => $q->where(function ($q) {
            $q->where('title', 'like', "%{$this->search}%")
              ->orWhere('artist', 'like', "%{$this->search}%")
              ->orWhereHas('album', fn ($q) => $q->where('title', 'like', "%{$this->search}%"));
        }))
        ->orderBy('title')
        ->get();
}

#[Computed]
public function albums(): Collection
{
    return Album::query()
        ->where('user_id', Auth::id())
        ->when($this->search, fn ($q) => $q->where(function ($q) {
            $q->where('title', 'like', "%{$this->search}%")
              ->orWhere('artist', 'like', "%{$this->search}%");
        }))
        ->orderBy('title')
        ->get();
}
```

Blade:
```blade
<flux:input wire:model.live.debounce.300ms="search"
            type="search"
            placeholder="Search your library..." />
```

### Pattern 5: flux:modal for Upload Dialog
**What:** Named modal controlled by `flux:modal.trigger` + `flux:modal name="upload"`; programmatic close after upload
**When to use:** "Upload" button → modal → batch file input → submit

```blade
{{-- Trigger --}}
<flux:modal.trigger name="upload">
    <flux:button variant="primary">Upload</flux:button>
</flux:modal.trigger>

{{-- Modal --}}
<flux:modal name="upload" class="md:w-[600px]">
    <flux:heading size="lg">Upload Music</flux:heading>

    <form wire:submit="upload" class="mt-4 space-y-4">
        <input type="file" wire:model="audioFiles" multiple
               accept=".mp3,.wav,.flac,.m4a,.aac,.ogg"
               class="block w-full text-sm ...">

        @error('audioFiles.*') <flux:text class="text-red-500">{{ $message }}</flux:text> @enderror

        {{-- Duplicate warnings --}}
        @foreach ($duplicateWarnings as $name)
            <flux:callout variant="warning">
                "{{ $name }}" looks like a duplicate — you may already have this file.
            </flux:callout>
        @endforeach

        <div class="flex justify-end gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="upload">Upload</span>
                <span wire:loading wire:target="upload">Uploading...</span>
            </flux:button>
        </div>
    </form>
</flux:modal>
```

### Pattern 6: Track and Album Database Schema
**What:** Two tables: `albums` (user-owned, all columns nullable except user_id) and `tracks` (user-owned, optional album FK)

```php
// albums migration
Schema::create('albums', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->string('artist')->nullable();
    $table->unsignedSmallInteger('year')->nullable();
    $table->string('genre')->nullable();
    $table->string('cover_art_path')->nullable();
    $table->timestamps();
    $table->index(['user_id', 'title']); // for alphabetical library queries
});

// tracks migration
Schema::create('tracks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('album_id')->nullable()->constrained()->nullOnDelete();
    $table->string('title');
    $table->string('artist')->nullable();
    $table->string('file_path');
    $table->string('file_hash', 64)->index(); // SHA-256 = 64 hex chars
    $table->unsignedInteger('duration')->nullable(); // seconds
    $table->string('year')->nullable();
    $table->string('genre')->nullable();
    $table->timestamps();
    $table->index(['user_id', 'title']); // for alphabetical library queries
});
```

### Anti-Patterns to Avoid
- **Inline metadata extraction during upload:** Don't call getID3 synchronously inside the Livewire `upload()` action — it blocks the request and is slow on large files. Always dispatch a queue job.
- **Storing files before validation:** Validate MIME types and max size before calling `->store()`. Livewire's `#[Validate]` attribute handles this when `validate()` is called at the start of the action.
- **Using `wire:model` without `.live` for search:** Without `.live`, the search only updates on blur/enter, not as-you-type.
- **Querying all user tracks on every page load without limiting:** Use `->orderBy('title')->get()` for now (Phase 3 scope), but note pagination becomes necessary once library grows large.
- **Calling `Storage::url()` directly in views:** The project pattern (established in Phase 2) is to use model methods (`track->audioUrl()`, `album->coverArtUrl()`) as the single source of truth.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| ID3 tag parsing | Custom binary parser for mp3/flac/ogg headers | `james-heinrich/getid3` | ID3v2 has 70+ frame types; OGG Vorbis and FLAC tags have completely different byte structures; getid3 handles all formats |
| MIME type validation for audio | Checking file extension or Content-Type header | Laravel's `mimes:mp3,wav,flac,m4a,aac,ogg` validation rule | Extension spoofing; Laravel uses `finfo_file()` to check actual file bytes |
| Batch upload progress | Custom JavaScript polling | Livewire `wire:loading` + `wire:target` | Livewire tracks active network requests natively; spinner state is automatic |
| Modal open/close | Alpine boolean + custom overlay | `flux:modal` with `name` + `flux:modal.trigger` | Already in project's component library; keyboard dismiss, focus trap, ARIA attributes all handled |
| File deduplication storage | Custom hashing table + polymorphic relations | PHP `hash_file('sha256', ...)` + `file_hash` column on `tracks` | We want to warn but allow duplicates; 5-line check is sufficient; dedupler package prevents storage entirely |

**Key insight:** Audio format handling is deceptively complex. ID3v2 alone has 73 defined frame types, multiple encoding schemes (UTF-8/UTF-16/Latin-1), and version differences between ID3v2.3 and ID3v2.4. getID3's 24 years of edge-case handling is irreplaceable.

---

## Common Pitfalls

### Pitfall 1: 100 MB Upload Limit Not Configured
**What goes wrong:** Files over 12 MB are rejected by Livewire's default temporary upload middleware with a validation error like "The file failed to upload."
**Why it happens:** Livewire's default `temporary_file_upload.rules` is `file|max:12288` (12 MB).
**How to avoid:** Publish livewire config (`php artisan vendor:publish --tag=livewire:config`) and update `temporary_file_upload.rules` to `file|max:102400`. Also ensure `php.ini` has `upload_max_filesize = 100M` and `post_max_size = 110M`.
**Warning signs:** Upload works for small files but mysteriously fails for anything over 12 MB.

### Pitfall 2: getID3 Needs the Absolute File Path
**What goes wrong:** `$getID3->analyze($relativePath)` returns an empty array or errors on "file not found."
**Why it happens:** getID3 requires a real filesystem path, not a Storage URL or relative path.
**How to avoid:** Always use `Storage::disk('public')->path($track->file_path)` to get the absolute path in the queue job.
**Warning signs:** `$info['error']` array is non-empty after `analyze()`.

### Pitfall 3: Tags Not Available Before Queue Job Completes
**What goes wrong:** Track title shows raw filename on the upload summary page because the queue job hasn't run yet.
**Why it happens:** Metadata extraction is async. The summary page is shown immediately after upload.
**How to avoid:** On the summary page, show `$track->title` (which is pre-filled with the original filename from `pathinfo()`). Show a "Processing..." badge for duration if it's null. This is expected behavior — document it in the empty state.
**Warning signs:** Null title or duration on tracks that were just uploaded.

### Pitfall 4: CopyTagsToComments Must Be Called to Unify Tag Sources
**What goes wrong:** `$info['comments']` is empty even for files with tags because getID3 stores tags by format (`$info['id3v2']['comments']`, `$info['id3v1']['comments']`, etc.).
**Why it happens:** getID3 separates tags by source format by default.
**How to avoid:** Always call `getid3_lib::CopyTagsToComments($info)` immediately after `analyze()`. This merges all tag sources into `$info['comments']`, preferring ID3v2 over ID3v1 when both exist.
**Warning signs:** `$info['comments']` is empty but `$info['id3v2']['comments']` or `$info['tags']` has data.

### Pitfall 5: Multiple File Input with wire:model Requires Array Property
**What goes wrong:** Only the last file is captured when using `<input multiple>` bound to a non-array property.
**Why it happens:** PHP and Livewire require an array property for multiple file binding.
**How to avoid:** Declare `public array $audioFiles = [];` and use `#[Validate(['audioFiles.*' => '...'])]` (dot-star notation for array validation).
**Warning signs:** Only one track created despite multiple files selected.

### Pitfall 6: N+1 Queries on Tracks/Albums Tab
**What goes wrong:** A library with 50 tracks fires 50 additional queries to load album names.
**Why it happens:** Accessing `$track->album->title` in a loop without eager loading.
**How to avoid:** Use `->with('album')` in the tracks computed property: `Track::where('user_id', ...)->with('album')->orderBy('title')->get()`.
**Warning signs:** Debugbar showing many repetitive album queries.

### Pitfall 7: Livewire Temporary Files Expire
**What goes wrong:** If user selects files but doesn't submit for a few minutes, uploads fail silently.
**Why it happens:** Livewire temporary upload files are cleaned up after a configurable duration (default 24 hours). However, network timeouts for 100 MB files can occur before upload completes.
**How to avoid:** For 100 MB files, increase `max_upload_time` in livewire config and ensure PHP `max_execution_time` is generous. The spinner/loading state gives feedback.
**Warning signs:** Users reporting files "disappear" from the input without error.

---

## Code Examples

Verified patterns from official sources and project codebase:

### getID3 Usage in Queue Job
```php
// Source: github.com/JamesHeinrich/getID3 + getid3.org
use getID3;
use getid3_lib;

$getID3 = new getID3();
$info   = $getID3->analyze(Storage::disk('public')->path($track->file_path));
getid3_lib::CopyTagsToComments($info);

// Unified tags after CopyTagsToComments:
$title    = $info['comments']['title'][0]  ?? null;
$artist   = $info['comments']['artist'][0] ?? null;
$album    = $info['comments']['album'][0]  ?? null;
$year     = $info['comments']['year'][0]   ?? null;
$genre    = $info['comments']['genre'][0]  ?? null;
$duration = isset($info['playtime_seconds']) ? (int) round($info['playtime_seconds']) : null;
```

### Livewire Multiple File Upload Validation
```php
// Source: livewire.laravel.com/docs/4.x/uploads
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

use WithFileUploads;

#[Validate(['audioFiles.*' => 'file|mimes:mp3,wav,flac,m4a,aac,ogg|max:102400'])]
public array $audioFiles = [];
```

### SHA-256 Duplicate Detection
```php
// Source: php.net/manual/en/function.hash-file.php + Laravel docs
$hash = hash_file('sha256', $file->getRealPath());

$isDuplicate = Track::where('file_hash', $hash)
    ->where('user_id', Auth::id())
    ->exists();
```

### Route Model Binding for Album Page
```php
// Source: livewire.laravel.com/docs/4.x/pages + routes/settings.php pattern
// routes/web.php:
Route::livewire('library/albums/{album}', 'pages::library.album-show')->name('library.album.show');

// Component:
new #[Layout('layouts.app')] class extends Component {
    public Album $album;

    public function mount(Album $album): void
    {
        // Authorization: ensure album belongs to authenticated user
        abort_unless($album->user_id === Auth::id(), 403);
        $this->album = $album;
    }
}
```

### Livewire Test Pattern (follows ProfileUpdateTest.php convention)
```php
// Source: pestphp.com + tests/Feature/Settings/ProfileUpdateTest.php
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use App\Models\Track;

test('user can upload an audio file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $this->actingAs($user);

    $file = UploadedFile::fake()->create('song.mp3', 5000, 'audio/mpeg');

    Livewire::test('pages::library.index')
        ->set('audioFiles', [$file])
        ->call('upload')
        ->assertHasNoErrors()
        ->assertRedirect(route('library.upload.summary'));

    expect(Track::where('user_id', $user->id)->count())->toBe(1);
});
```

### Queue Job Test with Fake Queue
```php
// Source: laravel.com/docs/12.x/queues#testing
use Illuminate\Support\Facades\Queue;
use App\Jobs\ExtractAudioMetadata;

test('uploading a file dispatches metadata extraction job', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    $file = UploadedFile::fake()->create('song.mp3', 5000, 'audio/mpeg');

    Livewire::test('pages::library.index')
        ->set('audioFiles', [$file])
        ->call('upload');

    Queue::assertPushed(ExtractAudioMetadata::class);
});
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| ID3 extraction in synchronous controller | Queue job after file save | Laravel 5+ queue maturity | Upload is instant; heavy parsing happens in background |
| `wire:model` (deferred update) | `wire:model.live.debounce.Xms` | Livewire v3+ | Instant search without explicit form submission |
| Manual modal HTML + Alpine | `flux:modal` + `flux:modal.trigger` | Flux UI v2 | Consistent ARIA, keyboard handling, focus trap built-in |
| Checking file extension | `mimes:` validation rule (checks actual bytes) | Laravel 5+ | Prevents extension spoofing of malicious files |
| `$casts` property | `casts()` method on model | Laravel 10+ | Project convention (established in Phase 2) |

**Deprecated/outdated:**
- `getid3_lib::CopyTagsToComments()` is not deprecated — it is still the correct approach to unify multi-format tags. Do not skip this call.
- Direct file extension checks (`$ext = pathinfo(..., PATHINFO_EXTENSION)`) are insufficient for security — always use Laravel's `mimes:` rule.

---

## Open Questions

1. **Cover art storage for albums**
   - What we know: Albums have an optional `cover_art_path`. getID3 can extract embedded album art from ID3v2 tags as raw binary data.
   - What's unclear: Should we auto-extract embedded cover art and store it during the metadata queue job? The CONTEXT.md does not lock this in.
   - Recommendation: Auto-extract cover art during the metadata job and store it at `music/{userId}/covers/{albumId}.webp` using Intervention Image (already installed). Show placeholder if none. This is Claude's Discretion.

2. **Album deduplication during batch upload**
   - What we know: `Album::firstOrCreate(['user_id' => ..., 'title' => ...])` will create one album per unique title per user.
   - What's unclear: What if two tracks in the same batch have slightly different album name casing (e.g., "Dark Side of the Moon" vs "dark side of the moon")?
   - Recommendation: Normalize album titles with `mb_strtolower()` before `firstOrCreate()`, or use case-insensitive comparison. Flag for implementation decision during planning.

3. **Queue driver for local development**
   - What we know: CONTEXT.md delegates queue driver config to Claude's Discretion. The `dev` composer script already runs `php artisan queue:listen`.
   - What's unclear: Whether to use `database` or `sync` as the default local driver.
   - Recommendation: Use `database` driver for local dev (already configured in `composer run dev`), `sync` in tests (set `QUEUE_CONNECTION=sync` in phpunit config). This matches `.env.testing` conventions.

---

## Sources

### Primary (HIGH confidence)
- `livewire.laravel.com/docs/4.x/uploads` — WithFileUploads, multiple files, validation, config
- `livewire.laravel.com/docs/4.x/pages` — Route::livewire(), #[Layout], route model binding
- `livewire.laravel.com/docs/4.x/wire-model` — wire:model.live, debounce syntax (150ms default, .debounce.Xms override)
- `fluxui.dev/components/modal` — flux:modal trigger, wire:model.self, Flux::modal() programmatic control
- `laravel.com/docs/12.x/queues` — ShouldQueue, Queueable, dispatch(), sync driver for tests
- `github.com/JamesHeinrich/getID3` — getID3 v1.9.24, analyze(), CopyTagsToComments(), tag structure
- Project codebase: `⚡profile.blade.php`, `ProfileUpdateTest.php`, `ProfileValidationRules.php` — established patterns

### Secondary (MEDIUM confidence)
- `packagist.org/packages/james-heinrich/getid3` — v1.9.24 is stable, 10.9M downloads, PHP 5.4+ requirement verified
- getid3.org phpBB posts — tag array structure confirmation (`comments` array keys: title, artist, album, year, genre)
- `getid3.sourceforge.net/source/structure.txt` — 404 at research time; array structure cross-verified via forum posts and HotExamples

### Tertiary (LOW confidence)
- `fly.io/laravel-bytes/chunked-file-upload-livewire/` — Chunked uploads for very large files; not needed at 100 MB limit but available if required
- `hotexamples.com/examples/-/getID3/Analyze/php-getid3-analyze-method-examples.html` — Community getID3 usage patterns (not official)

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — getid3 v1.9.24 verified on Packagist; WithFileUploads verified on official Livewire 4 docs; queue pattern verified on Laravel 12 docs
- Architecture: HIGH — Full-page component pattern, route pattern, and Concerns pattern all confirmed against existing codebase files
- Pitfalls: HIGH — 100 MB config pitfall verified via Livewire docs; getID3 file path pitfall verified via getid3.org documentation; N+1 is standard Laravel knowledge
- ID3 tag key names: MEDIUM — Confirmed via multiple community sources; `getid3_lib::CopyTagsToComments()` unified array confirmed; exact key names cross-referenced from forum posts (not official API docs)

**Research date:** 2026-03-04
**Valid until:** 2026-04-04 (stable stack — Livewire 4, Laravel 12, getID3 1.9.x all stable releases)
