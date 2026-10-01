# Phase 14: Scan Job & Metadata Pipeline - Research

**Researched:** 2026-03-18
**Domain:** Laravel queued jobs, PHP filesystem iteration, getid3 cover art, duplicate detection
**Confidence:** HIGH

## Summary

This phase is a purely backend pipeline: a `ScanLinkedFolder` queued job that discovers audio files via `RecursiveDirectoryIterator`, computes MD5 hashes, upserts Track records (file_path-first, hash fallback), then dispatches the existing `ExtractAudioMetadata` job per track. `ExtractAudioMetadata` needs extension for cover art extraction and album artist population.

The entire stack — getid3, queued jobs via `ShouldQueue`, RecursiveDirectoryIterator — is already in the project. No new dependencies are required. The primary work is building `ScanLinkedFolder`, extending `ExtractAudioMetadata`, and writing Pest feature tests.

**Primary recommendation:** Build `ScanLinkedFolder` as a single self-contained job. Extend `ExtractAudioMetadata` for cover art and album_artist. Never block the UI.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Duplicate Prevention**
- Match by file_path first; if no match, fall back to MD5 file_hash to catch moved/renamed files
- On hash match (file moved within folder), auto-update the existing track's file_path to new location — preserves playlist/queue associations
- File hashing (MD5) happens during the scan job, not deferred to metadata extraction
- MD5 chosen for speed; column is varchar(64), MD5 produces 32 hex chars

**Album Auto-Creation**
- ID3 album tag takes priority — each unique album tag creates its own album, even for tracks in the same folder
- When no ID3 album tag exists, use parent folder name as album title
- Album artist from ID3 `album_artist` tag when present, fall back to track artist
- Extract embedded cover art from ID3 tags — save as album cover_art_path; first track with art wins for the album
- Existing ExtractAudioMetadata job already has album auto-creation logic — extend it for cover art and album artist

**Metadata Fallbacks**
- Title: filename without extension when no ID3 title tag (no parsing of track numbers or separators)
- Artist: "Unknown Artist" when no ID3 artist tag
- Track number: extract from ID3 tag when available, null otherwise
- Duration: from getid3 audio analysis
- If getid3 cannot analyze a file at all (corrupt/unsupported), still create the track with filename-based title, "Unknown Artist", and null duration

**Job Architecture**
- Single `ScanLinkedFolder` job per folder: discovers files via RecursiveDirectoryIterator, computes MD5 hashes, creates Track records, then dispatches `ExtractAudioMetadata` per track
- ScanLinkedFolder manages LinkedFolder status transitions: set to `scanning` at start, `active` on completion, `error` on exception; update `last_scanned_at` and `track_count`
- On individual file error (permission denied, unreadable), log and skip — continue scanning rest of folder
- No file count limit — personal music app, folders are reasonable size; queue timeout handles edge cases
- Supported formats: MP3, WAV, FLAC, M4A, AAC, OGG (matches existing upload support)

### Claude's Discretion
- Exact RecursiveDirectoryIterator filter implementation
- How to store extracted cover art (file naming, directory structure)
- Error logging approach (Laravel Log vs custom)
- Queue connection/timeout configuration
- Exact hash computation strategy (full file vs partial)

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| FOLD-02 | App recursively scans linked folder discovering all audio files (MP3, WAV, FLAC, M4A, AAC, OGG) | RecursiveDirectoryIterator with extension filter; ScanLinkedFolder job |
| FOLD-04 | User can trigger a rescan on a specific linked folder | ScanLinkedFolder::dispatch($folder) callable multiple times; duplicate prevention via file_path + file_hash |
| LIBR-04 | Albums auto-created from folder structure or ID3 album tags | ExtractAudioMetadata extension: album_artist tag + cover art; folder name fallback in ScanLinkedFolder |
</phase_requirements>

---

## Standard Stack

### Core (already in project — no new installs)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| getID3 | ~1.9 | ID3 tag extraction + cover art | Already integrated; used by ExtractAudioMetadata |
| Laravel Queue (ShouldQueue) | Laravel 12 | Async job dispatch | Project pattern; all jobs use this |
| RecursiveDirectoryIterator | PHP 8.4 built-in | Recursive filesystem traversal | No deps; handles deep nesting natively |
| md5_file() | PHP 8.4 built-in | Full-file MD5 hash | Fast enough for personal library; 32 hex = fits varchar(64) |

**No additional packages needed.** Everything required is already present.

---

## Architecture Patterns

### Job: ScanLinkedFolder

```php
// app/Jobs/ScanLinkedFolder.php
class ScanLinkedFolder implements ShouldQueue
{
    use Queueable;

    public function __construct(public LinkedFolder $folder) {}

    public function handle(): void
    {
        $this->folder->update(['status' => FolderStatus::Scanning]);

        try {
            $extensions = ['mp3', 'wav', 'flac', 'm4a', 'aac', 'ogg'];
            $count = 0;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $this->folder->path,
                    RecursiveDirectoryIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                if (! in_array(strtolower($file->getExtension()), $extensions)) {
                    continue;
                }

                try {
                    $this->processFile($file);
                    $count++;
                } catch (Throwable $e) {
                    Log::warning("ScanLinkedFolder: skipping {$file->getPathname()}: {$e->getMessage()}");
                }
            }

            $this->folder->update([
                'status' => FolderStatus::Active,
                'track_count' => $count,
                'last_scanned_at' => now(),
            ]);
        } catch (Throwable $e) {
            $this->folder->update(['status' => FolderStatus::Error]);
            throw $e;
        }
    }
}
```

### Duplicate Detection Strategy

```php
private function processFile(SplFileInfo $file): void
{
    $absolutePath = str_replace('\\', '/', $file->getPathname());
    $hash = md5_file($absolutePath);

    // 1. Exact path match — already known, rescan in place
    $track = Track::query()
        ->where('user_id', $this->folder->user_id)
        ->where('file_path', $absolutePath)
        ->first();

    if ($track) {
        // Ensure hash is current (file may have changed)
        $track->update(['file_hash' => $hash]);
        ExtractAudioMetadata::dispatch($track);
        return;
    }

    // 2. Hash match — file was moved/renamed; update path
    $track = Track::query()
        ->where('user_id', $this->folder->user_id)
        ->where('file_hash', $hash)
        ->first();

    if ($track) {
        $track->update(['file_path' => $absolutePath]);
        ExtractAudioMetadata::dispatch($track);
        return;
    }

    // 3. New file — create track record
    $title = pathinfo($file->getFilename(), PATHINFO_FILENAME);
    $track = Track::create([
        'user_id' => $this->folder->user_id,
        'linked_folder_id' => $this->folder->id,
        'source_type' => SourceType::Local,
        'file_path' => $absolutePath,
        'file_hash' => $hash,
        'title' => $title,
        'artist' => 'Unknown Artist',
        'duration' => null,
    ]);

    ExtractAudioMetadata::dispatch($track);
}
```

### Folder-Name Album Fallback (inside ScanLinkedFolder or ExtractAudioMetadata)

The folder-name fallback for albums without an ID3 album tag belongs in `ExtractAudioMetadata`, since that job already handles album creation. Add a `$folderNameFallback` parameter or derive it from `$this->track->linkedFolder->name`.

```php
// In ExtractAudioMetadata::handle() — after tags extraction
$albumTitle = $tags['album'][0] ?? null;

if (! $albumTitle && $this->track->linked_folder_id) {
    $albumTitle = $this->track->linkedFolder->name;
}

if ($albumTitle && $this->track->album_id === null) {
    // existing album find-or-create logic...
}
```

### Cover Art Extraction (getid3)

```php
// getid3 embeds cover art in $info['id3v2']['APIC'] or $info['comments']['picture']
$pictures = $info['id3v2']['APIC'] ?? [];
if (! empty($pictures)) {
    $picture = is_array($pictures[0]) ? $pictures[0] : $pictures;
    $imageData = $picture['data'] ?? null;
    $mime = $picture['image_mime'] ?? 'image/jpeg';
    $ext = match ($mime) {
        'image/png' => 'png',
        'image/gif' => 'gif',
        default => 'jpg',
    };

    if ($imageData && $album->cover_art_path === null) {
        $filename = 'covers/' . $album->id . '.' . $ext;
        Storage::disk('public')->put($filename, $imageData);
        $album->update(['cover_art_path' => $filename]);
    }
}
```

**Key:** `getid3_lib::CopyTagsToComments()` does NOT always copy picture data to `$info['comments']`. Access `$info['id3v2']['APIC']` directly for reliable cover art extraction.

### Album Artist Tag

getid3 exposes album artist via `$info['comments']['band'][0]` (ID3v2 TPE2 frame maps to `band` in getid3 comments). Also check `$info['comments']['album_artist'][0]` as some taggers write it differently.

```php
// Album artist resolution
$albumArtist = $tags['album_artist'][0]
    ?? $tags['band'][0]   // ID3v2 TPE2 frame
    ?? $tags['artist'][0]
    ?? null;
```

### RecursiveDirectoryIterator on Windows (Herd)

- Paths returned use backslashes on Windows — must normalize via `str_replace('\\', '/')`. The Track model mutator already handles this on write, but compute the normalized path before hash lookup.
- `SKIP_DOTS` flag prevents infinite recursion on `.` and `..`
- Wrap in try/catch per file for permission errors

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| ID3 tag reading | Custom binary parser | getid3 (already in project) | Handles MP3/FLAC/M4A/OGG/WAV variants, cover art, encoding edge cases |
| Recursive directory walk | Manual `scandir()` recursion | `RecursiveIteratorIterator` + `RecursiveDirectoryIterator` | Built into PHP, handles symlinks, permissions gracefully |
| File uniqueness | Custom hash scheme | `md5_file()` | Single call, deterministic, fits varchar(64) |

---

## Common Pitfalls

### Pitfall 1: getid3 APIC Cover Art Access
**What goes wrong:** Accessing `$info['comments']['picture']` returns nothing for many files even when cover art exists.
**Why it happens:** `CopyTagsToComments()` doesn't reliably copy binary APIC data.
**How to avoid:** Access `$info['id3v2']['APIC']` directly before falling back to comments.
**Warning signs:** Album cover_art_path stays null despite files having embedded art.

### Pitfall 2: Windows Path Separators
**What goes wrong:** `RecursiveDirectoryIterator` returns backslash paths on Windows; hash lookup fails because stored path uses forward slashes.
**Why it happens:** Herd runs on Windows; stored paths are normalised but iterator paths are not yet normalised.
**How to avoid:** Normalise `$file->getPathname()` to forward slashes before any DB lookup or hash computation. Do NOT rely on the Track model mutator for the lookup key — normalise first, then query.

### Pitfall 3: track_count vs Actual DB Count
**What goes wrong:** `track_count` on LinkedFolder drifts from actual track count when individual files fail.
**Why it happens:** Counter incremented in loop but exceptions cause skips.
**How to avoid:** Count only successfully processed files (increment counter only after successful `Track::create` or update). Alternatively, recount from DB after loop: `$this->folder->tracks()->count()`.

### Pitfall 4: Dispatching ExtractAudioMetadata on Rescan
**What goes wrong:** Rescan dispatches `ExtractAudioMetadata` for every existing track, causing redundant metadata re-extraction.
**Why it happens:** No distinction between "already has good metadata" and "new track needing extraction."
**How to avoid:** For phase 14, always dispatch on rescan — metadata may have changed and the job is idempotent. This is acceptable for a personal app.

### Pitfall 5: Album Eager Loading in ExtractAudioMetadata
**What goes wrong:** Accessing `$this->track->linkedFolder->name` triggers a lazy load inside the queued job.
**Why it happens:** Queue serialization doesn't eager-load relationships.
**How to avoid:** Either eager-load in the job (`$this->track->load('linkedFolder')`) or store `$this->track->linked_folder_id` and query separately. Prefer explicit load call at the start of `handle()`.

### Pitfall 6: SQLite Concurrency Under Queue
**What goes wrong:** Multiple `ExtractAudioMetadata` jobs running concurrently hit SQLite lock contention.
**Why it happens:** SQLite allows only one writer at a time.
**How to avoid:** Use `QUEUE_CONNECTION=sync` in tests. For production (Herd), the default `sync` driver runs jobs immediately and sequentially — no concurrency. If ever moving to `database` driver, use `--queue=default` with single worker.

---

## Code Examples

### File Extension Filter Pattern
```php
// Source: PHP RecursiveDirectoryIterator docs
$supportedExtensions = ['mp3', 'wav', 'flac', 'm4a', 'aac', 'ogg'];

foreach ($iterator as $file) {
    if (! $file->isFile()) {
        continue;
    }
    if (! in_array(strtolower($file->getExtension()), $supportedExtensions, strict: true)) {
        continue;
    }
    // process $file
}
```

### Track Upsert (path-first, hash-fallback)
```php
// Finds by path, then by hash, then creates
$track = Track::query()
    ->where('user_id', $userId)
    ->where('file_path', $normalizedPath)
    ->first()
    ?? Track::query()
        ->where('user_id', $userId)
        ->where('file_hash', $hash)
        ->first();

if ($track) {
    $track->update(['file_path' => $normalizedPath, 'file_hash' => $hash]);
} else {
    $track = Track::create([...]);
}
```

### Status Lifecycle in Job
```php
// ScanLinkedFolder job status management
$this->folder->update(['status' => FolderStatus::Scanning]);

try {
    // ... scan loop ...
    $this->folder->update([
        'status' => FolderStatus::Active,
        'last_scanned_at' => now(),
        'track_count' => $processedCount,
    ]);
} catch (Throwable $e) {
    $this->folder->update(['status' => FolderStatus::Error]);
    throw $e; // Re-throw so queue marks job as failed
}
```

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=ScanLinkedFolder` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| FOLD-02 | Scan discovers MP3/WAV/FLAC/M4A/AAC/OGG recursively | Feature | `php artisan test --compact --filter=ScanLinkedFolderTest` | ❌ Wave 0 |
| FOLD-02 | Non-audio files are ignored | Feature | `php artisan test --compact --filter=ScanLinkedFolderTest` | ❌ Wave 0 |
| FOLD-02 | Track records created with correct file_path and file_hash | Feature | `php artisan test --compact --filter=ScanLinkedFolderTest` | ❌ Wave 0 |
| FOLD-02 | LinkedFolder status transitions scanning→active | Feature | `php artisan test --compact --filter=ScanLinkedFolderTest` | ❌ Wave 0 |
| FOLD-02 | LinkedFolder status set to error on exception | Feature | `php artisan test --compact --filter=ScanLinkedFolderTest` | ❌ Wave 0 |
| FOLD-02 | track_count and last_scanned_at updated on completion | Feature | `php artisan test --compact --filter=ScanLinkedFolderTest` | ❌ Wave 0 |
| FOLD-04 | Rescan: existing track by file_path not duplicated | Feature | `php artisan test --compact --filter=ScanLinkedFolderTest` | ❌ Wave 0 |
| FOLD-04 | Rescan: moved file (hash match) has path updated | Feature | `php artisan test --compact --filter=ScanLinkedFolderTest` | ❌ Wave 0 |
| LIBR-04 | Album auto-created from ID3 album tag | Feature | `php artisan test --compact --filter=ExtractAudioMetadataTest` | ✅ (partial) |
| LIBR-04 | Album uses folder name when no ID3 album tag | Feature | `php artisan test --compact --filter=ExtractAudioMetadataTest` | ❌ Wave 0 |
| LIBR-04 | Album artist populated from ID3 band/album_artist tag | Feature | `php artisan test --compact --filter=ExtractAudioMetadataTest` | ❌ Wave 0 |
| LIBR-04 | Cover art extracted and saved; album cover_art_path populated | Feature | `php artisan test --compact --filter=ExtractAudioMetadataTest` | ❌ Wave 0 |
| LIBR-04 | First-track-wins for cover art (existing art not overwritten) | Feature | `php artisan test --compact --filter=ExtractAudioMetadataTest` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=ScanLinkedFolderTest`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/ScanLinkedFolderTest.php` — covers FOLD-02, FOLD-04 (new job, no test file exists)
- [ ] `tests/Feature/ExtractAudioMetadataTest.php` — extend for LIBR-04 (cover art, album artist, folder fallback); file exists but needs new test cases

---

## Sources

### Primary (HIGH confidence)
- Direct code inspection: `app/Jobs/ExtractAudioMetadata.php` — existing getid3 integration, album creation logic
- Direct code inspection: `app/Models/Track.php`, `app/Models/LinkedFolder.php`, `app/Models/Album.php` — confirmed fillable fields, casts, relationships
- Direct code inspection: `database/migrations/` — confirmed `file_hash varchar(64) indexed`, `track_number`, `cover_art_path` all exist
- PHP documentation: `RecursiveDirectoryIterator`, `RecursiveIteratorIterator`, `md5_file()` — built-in, stable

### Secondary (MEDIUM confidence)
- getid3 APIC access via `$info['id3v2']['APIC']` — verified against getid3 source conventions and project usage of `getid3_lib::CopyTagsToComments`
- album_artist via `$tags['band'][0]` — ID3v2 TPE2 maps to `band` key in getid3 comments; also check `album_artist` key for compatibility

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Standard Stack: HIGH — all libraries confirmed in codebase, no new deps
- Architecture: HIGH — existing job patterns followed exactly, existing models inspected
- Pitfalls: HIGH — Windows-specific path issue confirmed from STATE.md decisions; others from direct code analysis
- Test map: HIGH — existing test files inspected, gaps identified from file listing

**Research date:** 2026-03-18
**Valid until:** 2026-04-18 (stable Laravel/PHP domain)
