# Phase 13: Authenticated Streaming - Research

**Researched:** 2026-03-18
**Domain:** Laravel HTTP streaming, BinaryFileResponse, Range requests, TrackPolicy
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Owner-only streaming — no admin exception, even admin cannot stream another user's tracks
- TrackPolicy class with `stream` ability for authorization — reusable and testable
- Unified stream route for ALL tracks — both uploaded and local-linked tracks served via `/stream/{track}`
- `audioUrl()` updated to always return the stream route URL regardless of source_type
- Uploaded tracks no longer have public Storage URLs — all audio goes through authenticated route
- Return 404 with JSON error body when file doesn't exist on disk
- Stream route is read-only — no DB side effects (no marking track unavailable)
- Phase 16 background job handles all missing file state detection
- Player auto-skips to next track on 404 — seamless, no error toast
- ETag based on file modification time — browser caches, revalidates on replay
- Content-Disposition: inline — browser plays via Audio API
- No CORS headers — same-origin requests only
- Path traversal validation: local-linked tracks must have file_path within a registered linked folder
- Uploaded tracks served from Storage disk — no path traversal concern
- Reject with 403 if path is outside registered linked folders

### Claude's Discretion
- Exact BinaryFileResponse vs StreamedResponse implementation
- Range header parsing approach (Symfony vs manual)
- ETag generation strategy details
- Controller structure (single action vs resource)

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| STRM-01 | App serves linked audio files from absolute paths via authenticated route | StreamController resolves absolute path from Track model; BinaryFileResponse handles serving |
| STRM-02 | Streaming supports HTTP Range requests for seeking | Symfony BinaryFileResponse auto-handles Range headers (206 partial); setAutoEtag() supports caching |
| STRM-03 | Streaming validates file ownership and path is within a registered linked folder | TrackPolicy `stream` ability + path prefix check against LinkedFolder::path |
</phase_requirements>

---

## Summary

Phase 13 adds a single `GET /stream/{track}` route that serves audio files (both uploaded and local-linked) through authentication. The core implementation uses Symfony's `BinaryFileResponse` (available via `response()->file()` or `new BinaryFileResponse()`), which automatically handles HTTP Range requests (206 Partial Content), ETags, and `Last-Modified` headers — no manual Range parsing needed.

Authorization is handled by a new `TrackPolicy` with a `stream` ability. Laravel's `Gate::authorize('stream', $track)` or `$this->authorize('stream', $track)` in the controller will reject unauthorized users with 403. Path traversal protection for local tracks checks that the track's `file_path` starts with the `LinkedFolder::path` for the owning folder.

The breaking change is that `Track::audioUrl()` must return `route('stream.track', $track)` for all tracks (not just local), and the existing test asserting that uploaded tracks return a Storage URL will need updating.

**Primary recommendation:** Use `response()->file($absolutePath)` (which returns `BinaryFileResponse`) — it handles Range, ETag, and Content-Type automatically with no manual header code.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Symfony HttpFoundation BinaryFileResponse | bundled with Laravel 12 | Serves files with Range/ETag support | Ships with Laravel; zero configuration |
| Laravel Gates & Policies | Laravel 12 | Authorization via `TrackPolicy` | Laravel's idiomatic authorization system |
| Laravel Storage facade | Laravel 12 | Resolve uploaded track absolute path | Already used in project for Storage::disk('public') |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHP `file_exists()` | PHP 8.4 | Check file on disk before serving | Needed before BinaryFileResponse to return 404 cleanly |
| `str_starts_with()` | PHP 8.4 | Path traversal check | Check local track file_path against LinkedFolder::path prefix |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `response()->file()` (BinaryFileResponse) | `StreamedResponse` + manual Range parsing | BinaryFileResponse does Range automatically; StreamedResponse requires significant manual work |
| Laravel Policy | Inline `abort(403)` in controller | Policy is testable, reusable, declared — preferred by project conventions |

**Installation:**
No new packages required — all tools are part of the existing Laravel 12 stack.

---

## Architecture Patterns

### Recommended Project Structure
```
app/Http/Controllers/
└── StreamController.php     # Single __invoke method

app/Policies/
└── TrackPolicy.php          # stream ability

app/Providers/
└── AppServiceProvider.php   # Register TrackPolicy via $policies or automatic discovery

routes/web.php               # GET /stream/{track} inside auth middleware group

tests/Feature/
└── StreamControllerTest.php # All stream route tests
```

### Pattern 1: Single Action Controller (Invokable)
**What:** A `__invoke` method handles the single stream GET request.
**When to use:** Route has one action; matches PlaylistController convention where controllers are focused.
**Example:**
```php
// app/Http/Controllers/StreamController.php
class StreamController extends Controller
{
    public function __invoke(Track $track): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('stream', $track);

        $absolutePath = $this->resolvePath($track);

        if (! file_exists($absolutePath)) {
            return response()->json(['error' => 'Track file not found.'], 404);
        }

        return response()->file($absolutePath, [
            'Content-Disposition' => 'inline',
        ]);
    }
}
```

### Pattern 2: TrackPolicy
**What:** Laravel Policy class with `stream` ability enforcing owner-only access.
**When to use:** This is the first policy in the project — set the pattern correctly.
**Example:**
```php
// app/Policies/TrackPolicy.php
class TrackPolicy
{
    public function stream(User $user, Track $track): bool
    {
        return $user->id === $track->user_id;
    }
}
```

Laravel 12 uses automatic policy discovery — if `TrackPolicy` is in `app/Policies/`, it auto-maps to the `Track` model without manual registration in `AppServiceProvider`. Confirm by checking the `AuthServiceProvider` or `AppServiceProvider` for `$policies`.

### Pattern 3: Path Resolution by SourceType
**What:** Controller branches on `SourceType` to get the real absolute path.
**When to use:** Needed to handle both uploaded (Storage disk) and local (raw `file_path`) tracks.
**Example:**
```php
private function resolvePath(Track $track): string
{
    return match ($track->source_type) {
        SourceType::Uploaded => Storage::disk('public')->path($track->file_path),
        SourceType::Local    => $track->file_path,
    };
}
```

### Pattern 4: Path Traversal Validation (Local Tracks Only)
**What:** Ensure local track's `file_path` is prefixed by its registered `LinkedFolder::path`.
**When to use:** Only for `SourceType::Local` tracks.
**Example:**
```php
private function validateLocalPath(Track $track): bool
{
    if ($track->source_type !== SourceType::Local) {
        return true; // Uploaded tracks have no traversal risk
    }

    if ($track->linkedFolder === null) {
        return false;
    }

    // Both paths are normalised to forward slashes via Attribute mutators
    return str_starts_with($track->file_path, $track->linkedFolder->path);
}
```

This check must happen AFTER the Policy check (ownership is already confirmed), so 403 is the correct response for path traversal failures.

### Pattern 5: Route Registration
```php
// routes/web.php — inside existing auth middleware group
Route::get('stream/{track}', \App\Http\Controllers\StreamController::class)
    ->name('stream.track');
```

### Pattern 6: audioUrl() Unified
```php
// Track::audioUrl() — after this phase
public function audioUrl(): string
{
    return route('stream.track', $this);
}
```

### Anti-Patterns to Avoid
- **Manual Range parsing:** BinaryFileResponse handles it. Never re-implement HTTP Range logic.
- **`readfile()` or `echo file_get_contents()`:** Loads entire file into memory; blocks Range support.
- **Returning the Storage URL directly for uploads:** Phase decision requires all audio through authenticated route.
- **Setting `Content-Disposition: attachment`:** Must be `inline` so the browser's Audio API plays the file.
- **Trusting `$_SERVER['HTTP_RANGE']` directly:** Let Symfony handle it.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| HTTP Range / seeking support | Custom Range header parser | `response()->file()` / BinaryFileResponse | Symfony handles full RFC 7233; edge cases include multi-range, If-Range, boundary conditions |
| ETag generation | Custom hash/timestamp string | `BinaryFileResponse::setAutoEtag()` or rely on default | Symfony uses file mtime+size automatically; handles weak/strong etag conditionals |
| MIME type detection | `mime_content_type()` or hardcoded strings | BinaryFileResponse auto-detects | Handles mp3/flac/wav/m4a/aac/ogg correctly |
| Authorization | `if ($track->user_id !== auth()->id()) abort(403)` | TrackPolicy + `$this->authorize()` | Policy is testable; consistent with Laravel conventions |

**Key insight:** `response()->file($path)` wraps `BinaryFileResponse`, which internally calls `prepare()` on the Symfony `Request` and sets all Range/ETag/cache headers automatically — the controller stays under 30 lines.

---

## Common Pitfalls

### Pitfall 1: BinaryFileResponse ETag and Seeking in Chrome
**What goes wrong:** Chrome/Edge sends `Range: bytes=X-` requests. If `Content-Length` is missing or ETag mismatches, seeking breaks.
**Why it happens:** Symfony's BinaryFileResponse needs `setAutoEtag(true)` explicitly called, or it won't set the ETag header.
**How to avoid:** Call `->setAutoEtag()` on the returned `BinaryFileResponse` instance.
**Warning signs:** STATE.md explicitly flags: "Symfony BinaryFileResponse Range bug: test seeking in Chrome/Edge during Phase 13; apply setAutoEtag() if needed."

```php
return response()->file($absolutePath, ['Content-Disposition' => 'inline'])
    ->setAutoEtag();
```

### Pitfall 2: Policy Not Discovered Automatically
**What goes wrong:** `Gate::authorize('stream', $track)` throws `AuthorizationException` even for the owner.
**Why it happens:** Laravel 12 automatic policy discovery requires policy class name to follow `{Model}Policy` convention in `app/Policies/`. If the namespace is wrong, discovery fails silently.
**How to avoid:** Ensure `app/Policies/TrackPolicy.php` uses namespace `App\Policies` and the class is named exactly `TrackPolicy`.
**Warning signs:** All stream requests return 403 regardless of ownership.

### Pitfall 3: Uploaded Track Path Resolution
**What goes wrong:** `Storage::disk('public')->path($track->file_path)` returns an incorrect path because `file_path` for uploads is a relative Storage path (e.g., `music/1/song.mp3`), not an absolute path.
**Why it happens:** The `file_path` column has dual semantics — relative for uploads, absolute for local. `Storage::disk('public')->path()` converts relative Storage paths to absolute filesystem paths.
**How to avoid:** Always use `Storage::disk('public')->path($track->file_path)` for uploaded tracks, never `$track->file_path` directly.

### Pitfall 4: Path Normalisation Mismatch
**What goes wrong:** `str_starts_with($track->file_path, $folder->path)` returns false even for valid paths.
**Why it happens:** Both models normalise to forward slashes on write, but on Windows the actual filesystem path returned by `Storage::disk()->path()` may use backslashes.
**How to avoid:** For the local path traversal check, both `$track->file_path` and `$folder->path` are already normalised (mutators in both models). The check is safe. For uploaded tracks, path traversal check is skipped entirely.

### Pitfall 5: audioUrl() Test Regression
**What goes wrong:** Existing test `'audioUrl returns Storage URL for uploaded track'` fails after `audioUrl()` is unified.
**Why it happens:** The old test asserts a Storage URL; the new implementation always returns a route URL.
**How to avoid:** Update `TrackSourceTypeTest.php` — change the test to assert the stream route URL is returned for uploaded tracks.

### Pitfall 6: Missing linkedFolder Relationship Loaded
**What goes wrong:** `$track->linkedFolder` is null on a local track even though the DB has a `linked_folder_id`.
**Why it happens:** Relationship not eager-loaded; lazy loading disabled or `linkedFolder` not accessed.
**How to avoid:** In the controller, call `$track->load('linkedFolder')` before path validation, or use `$track->loadMissing('linkedFolder')`.

---

## Code Examples

### Full StreamController Implementation
```php
// Source: Symfony BinaryFileResponse + Laravel Policy conventions
<?php

namespace App\Http\Controllers;

use App\Enums\SourceType;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StreamController extends Controller
{
    public function __invoke(Track $track): BinaryFileResponse|JsonResponse
    {
        $this->authorize('stream', $track);

        $absolutePath = $this->resolvePath($track);

        if (! $this->isPathSafe($track)) {
            abort(403, 'Path is outside registered linked folders.');
        }

        if (! file_exists($absolutePath)) {
            return response()->json(['error' => 'Track file not found.'], 404);
        }

        return response()
            ->file($absolutePath, ['Content-Disposition' => 'inline'])
            ->setAutoEtag();
    }

    private function resolvePath(Track $track): string
    {
        return match ($track->source_type) {
            SourceType::Uploaded => Storage::disk('public')->path($track->file_path),
            SourceType::Local    => $track->file_path,
        };
    }

    private function isPathSafe(Track $track): bool
    {
        if ($track->source_type !== SourceType::Local) {
            return true;
        }

        $track->loadMissing('linkedFolder');

        if ($track->linkedFolder === null) {
            return false;
        }

        return str_starts_with($track->file_path, $track->linkedFolder->path);
    }
}
```

### TrackPolicy
```php
<?php

namespace App\Policies;

use App\Models\Track;
use App\Models\User;

class TrackPolicy
{
    public function stream(User $user, Track $track): bool
    {
        return $user->id === $track->user_id;
    }
}
```

### Route Registration
```php
// routes/web.php — inside existing Route::middleware(['auth'])->group()
Route::get('stream/{track}', \App\Http\Controllers\StreamController::class)
    ->name('stream.track');
```

### Updated audioUrl()
```php
public function audioUrl(): string
{
    return route('stream.track', $this);
}
```

### Alpine.js auto-skip on 404
The `onEnded` handler already auto-advances. The `onerror` event on the `<audio>` element needs to trigger auto-skip for 404 responses:
```javascript
// In audio-player.blade.php, add to the <audio> element:
x-on:error="onAudioError"

// In audioPlayer() Alpine component:
onAudioError() {
    // Server returned 404 (file not found) — skip to next track silently
    this.isPlaying = false;
    if (this.hasNext) {
        this.$wire.nextTrack();
    }
},
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Uploaded tracks served via public Storage URL | All tracks served via authenticated `/stream/{track}` | This phase | Breaking change — audioUrl() unified; existing test must update |
| No authorization on audio files (uploaded tracks were public) | TrackPolicy `stream` ability gates all audio | This phase | Security improvement — no unauthenticated audio access |

**Deprecated/outdated:**
- `Storage::disk('public')->url($this->file_path)` in `audioUrl()`: replaced by `route('stream.track', $this)` for all tracks.

---

## Open Questions

1. **Auto-discovery of TrackPolicy**
   - What we know: Laravel 12 auto-discovers policies in `app/Policies/` following the `{Model}Policy` naming convention
   - What's unclear: Whether any override in `AppServiceProvider` is needed (no existing policies in the project)
   - Recommendation: Use auto-discovery; if tests fail with 403, add explicit registration in `AppServiceProvider::boot()` as a fallback

2. **BinaryFileResponse and Windows paths**
   - What we know: Paths are normalised to forward slashes in model mutators; `Storage::disk()->path()` on Windows Herd returns backslash paths
   - What's unclear: Whether `response()->file()` on Windows handles forward-slash absolute paths correctly
   - Recommendation: Convert result of `Storage::disk('public')->path()` with `str_replace('\\', '/', ...)` before passing to `response()->file()`, consistent with existing path normalisation pattern

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 / PHPUnit 12 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=StreamController` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| STRM-01 | Authenticated request streams an uploaded track | feature | `php artisan test --compact --filter=StreamControllerTest` | ❌ Wave 0 |
| STRM-01 | Authenticated request streams a local-linked track | feature | `php artisan test --compact --filter=StreamControllerTest` | ❌ Wave 0 |
| STRM-01 | Returns 404 JSON when file does not exist on disk | feature | `php artisan test --compact --filter=StreamControllerTest` | ❌ Wave 0 |
| STRM-02 | HTTP Range requests return 206 Partial Content | feature | `php artisan test --compact --filter=StreamControllerTest` | ❌ Wave 0 |
| STRM-03 | Unauthenticated request returns 403 | feature | `php artisan test --compact --filter=StreamControllerTest` | ❌ Wave 0 |
| STRM-03 | Request for another user's track returns 403 | feature | `php artisan test --compact --filter=StreamControllerTest` | ❌ Wave 0 |
| STRM-03 | Local track outside registered folder returns 403 | feature | `php artisan test --compact --filter=StreamControllerTest` | ❌ Wave 0 |
| N/A | audioUrl() returns stream route for all track types | feature | `php artisan test --compact --filter=TrackSourceTypeTest` | ✅ (update needed) |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=StreamControllerTest`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/StreamControllerTest.php` — covers STRM-01, STRM-02, STRM-03
- [ ] `app/Policies/TrackPolicy.php` — prerequisite for any streaming test
- [ ] `app/Http/Controllers/StreamController.php` — prerequisite for route tests

*(Update `tests/Feature/TrackSourceTypeTest.php` — test `'audioUrl returns Storage URL for uploaded track'` must be changed to assert stream route URL)*

---

## Sources

### Primary (HIGH confidence)
- Symfony HttpFoundation BinaryFileResponse — official Symfony docs on file serving and Range support
- Laravel 12 Authorization (Policies) — `php artisan make:policy` creates app/Policies; auto-discovery by naming convention
- Existing project code — `Track`, `LinkedFolder`, `AudioPlayer`, `PlaylistController` patterns confirmed by direct reading

### Secondary (MEDIUM confidence)
- Laravel STATE.md project note: "Symfony BinaryFileResponse Range bug: test seeking in Chrome/Edge during Phase 13; apply setAutoEtag() if needed" — project-specific known issue

### Tertiary (LOW confidence)
- Windows path handling with `response()->file()` — untested; recommendation is to normalise before passing

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — BinaryFileResponse is part of Symfony/Laravel core; no external packages needed
- Architecture: HIGH — patterns derived directly from existing codebase conventions (PlaylistController, model mutators)
- Pitfalls: HIGH for ETag/Range (noted in STATE.md); MEDIUM for Windows path edge case

**Research date:** 2026-03-18
**Valid until:** 2026-04-18 (stable Laravel/Symfony internals)
