# Phase 12: Data Foundation - Research

**Researched:** 2026-03-18
**Domain:** Laravel Eloquent model extension, migrations, PHP enums, path normalisation
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- String enum column `source_type` on tracks table (varchar, not native DB enum)
- Standalone PHP backed enum at `app/Enums/SourceType.php` with cases `Uploaded` and `Local`
- Column default `'uploaded'` — existing tracks automatically get the value, no backfill needed
- Query scopes on Track: `scopeUploaded()` and `scopeLocal()` for downstream filtering
- Cast `source_type` to `SourceType` enum in Track model casts
- Mutator on `file_path` attribute — normalise backslashes to forward slashes on write
- Applies to ALL tracks (uploaded + local), not conditional on source type
- Single `file_path` column reused — relative paths for uploads, absolute paths for local
- `audioUrl()` checks `source_type` enum: uploaded → `Storage::disk('public')->url()`, local → streaming route URL
- Rich metadata columns on `linked_folders`: `path`, `user_id` (FK), `name`, `last_scanned_at`, `track_count`, `status`
- `linked_folder_id` nullable FK on tracks table — null for uploaded tracks, set for local tracks
- Cascade delete: deleting a linked folder cascade-deletes all its tracks
- LinkedFolder factory created with model for test support
- Two separate migrations: (1) create `linked_folders` table, (2) add `source_type` + `linked_folder_id` to tracks
- `linked_folders` migration runs first (FK dependency)
- Cascade delete on `linked_folder_id` FK
- Track factory local state deferred to later phase — not needed until Phase 14+
- Modify existing ID3 extraction job to accept and process absolute file paths
- Job determines read strategy based on track's `source_type`: uploaded → Storage disk, local → direct filesystem read

### Claude's Discretion
- Exact LinkedFolder status enum values and transitions
- Internal structure of path normalisation mutator
- Migration ordering details beyond the two-migration split
- Factory definition details for LinkedFolder

### Deferred Ideas (OUT OF SCOPE)
- None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DATA-01 | Track model supports source type (uploaded vs local-linked) | SourceType enum + `source_type` column + casts + scopes documented below |
| DATA-02 | Absolute file paths stored and normalized (Windows backslash handling) | `setFilePathAttribute` mutator pattern using `str_replace('\\', '/', $value)` |
| DATA-03 | ID3 metadata extracted for linked files using existing pipeline | `ExtractAudioMetadata` job modified to branch on `source_type` for path resolution |
</phase_requirements>

## Summary

This phase adds the data-layer scaffolding for v1.2 Local Library Linking. All changes are additive — new columns default to safe values so existing uploaded tracks are unaffected. The work spans three areas: (1) schema additions via two ordered migrations, (2) Track model enhancements (enum cast, mutator, updated `audioUrl()`, query scopes), and (3) a targeted modification to the existing `ExtractAudioMetadata` job.

The codebase already uses the `casts()` method (not `$casts` property), `$fillable` arrays, `HasFactory`, and the `getid3` library. All new code must follow those established patterns. The project runs on SQLite which handles string columns cleanly — no native enum type complications.

**Primary recommendation:** Follow the locked decisions from CONTEXT.md exactly. No architectural choices remain open except LinkedFolder status values and factory internals.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | v12 | Migrations, Eloquent, model mutators | Already in use |
| PHP | 8.4 | Backed enums, constructor promotion | Already in use |
| getid3 | installed | ID3 tag extraction | Already used in `ExtractAudioMetadata` |

### No new dependencies required
All functionality uses existing Laravel Eloquent patterns: backed enums (PHP 8.1+), attribute mutators via `set{Attribute}Attribute()` or `Attribute::make()`, and query scopes.

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Enums/
│   └── SourceType.php           # NEW — backed string enum
├── Models/
│   ├── Track.php                # MODIFIED — source_type cast, mutator, audioUrl(), scopes, fillable, relationship
│   └── LinkedFolder.php         # NEW — model with relationships
├── Jobs/
│   └── ExtractAudioMetadata.php # MODIFIED — branch on source_type for path resolution
database/
└── migrations/
    ├── TIMESTAMP_create_linked_folders_table.php   # NEW — must run first
    └── TIMESTAMP_add_source_type_to_tracks_table.php # NEW — depends on linked_folders
database/factories/
└── LinkedFolderFactory.php      # NEW — created alongside model
```

### Pattern 1: PHP Backed String Enum
**What:** PHP 8.1+ backed enum with string values, used as Eloquent cast target
**When to use:** When a column has a fixed set of string values that must be type-safe

```php
// app/Enums/SourceType.php
<?php

namespace App\Enums;

enum SourceType: string
{
    case Uploaded = 'uploaded';
    case Local = 'local';
}
```

### Pattern 2: Eloquent Enum Cast
**What:** Cast a string column to a PHP enum in the model's `casts()` method
**When to use:** Whenever a string column maps to a backed enum

```php
// In Track::casts()
protected function casts(): array
{
    return [
        'duration' => 'integer',
        'source_type' => \App\Enums\SourceType::class,
    ];
}
```

Accessing `$track->source_type` then returns `SourceType::Uploaded` or `SourceType::Local`.

### Pattern 3: Attribute Mutator for Path Normalisation
**What:** Intercept writes to `file_path` and normalise separators
**When to use:** Any time platform-specific paths may be written to the column

Laravel 9+ uses the `Attribute::make()` approach. However, the simpler `set{Attribute}Attribute` magic mutator still works in Laravel 12. Either is acceptable — check existing model for preference. No existing mutators were found in Track, so use the modern `Attribute::make()` form for consistency with Laravel 12 style.

```php
// In Track model
use Illuminate\Database\Eloquent\Casts\Attribute;

protected function filePath(): Attribute
{
    return Attribute::make(
        set: fn (string $value) => str_replace('\\', '/', $value),
    );
}
```

### Pattern 4: Query Scopes
**What:** Fluent filter methods on the model for common query patterns

```php
public function scopeUploaded(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
{
    return $query->where('source_type', \App\Enums\SourceType::Uploaded);
}

public function scopeLocal(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
{
    return $query->where('source_type', \App\Enums\SourceType::Local);
}
```

Usage: `Track::uploaded()->get()`, `Track::local()->get()`.

### Pattern 5: Branching `audioUrl()`
**What:** Return URL based on source type

```php
public function audioUrl(): string
{
    return match ($this->source_type) {
        \App\Enums\SourceType::Uploaded => Storage::disk('public')->url($this->file_path),
        \App\Enums\SourceType::Local   => route('stream.track', $this),
    };
}
```

The streaming route name (`stream.track`) is defined in Phase 13 — but the method shape is established here. The route reference does not need to exist during Phase 12 tests; unit tests for `audioUrl()` can mock or skip the local branch.

### Pattern 6: Migrations (Order Matters)
Migration 1 — `linked_folders` table (must run first, FK source):

```php
Schema::create('linked_folders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('path');
    $table->string('name');
    $table->string('status')->default('active');
    $table->unsignedInteger('track_count')->default(0);
    $table->timestamp('last_scanned_at')->nullable();
    $table->timestamps();
});
```

Migration 2 — columns added to `tracks`:

```php
Schema::table('tracks', function (Blueprint $table) {
    $table->string('source_type')->default('uploaded')->after('genre');
    $table->foreignId('linked_folder_id')
        ->nullable()
        ->after('source_type')
        ->constrained('linked_folders')
        ->cascadeOnDelete();
});
```

Artisan command to generate:
```bash
php artisan make:migration create_linked_folders_table --no-interaction
php artisan make:migration add_source_type_to_tracks_table --no-interaction
```

### Pattern 7: LinkedFolder Model
```bash
php artisan make:model LinkedFolder --factory --no-interaction
```

Model needs: `$fillable`, `casts()` (status could be a `FolderStatus` enum if desired — Claude's discretion), `user()` BelongsTo, `tracks()` HasMany.

### Pattern 8: ID3 Job Modification
Current job does:
```php
$absolutePath = Storage::disk('public')->path($this->track->file_path);
```

Modified job branches:
```php
$absolutePath = $this->track->source_type === \App\Enums\SourceType::Local
    ? $this->track->file_path
    : Storage::disk('public')->path($this->track->file_path);
```

Since `file_path` stores an already-normalised absolute path for local tracks, it can be used directly with `file_exists()` and `getID3::analyze()`.

### Anti-Patterns to Avoid
- **Native DB enum type:** SQLite handles `string` columns cleanly; Laravel's `$table->enum()` creates complications on schema changes. Use `string()` with application-level validation via backed enum.
- **Conditional normalisation:** Normalise `file_path` unconditionally on all writes — relative paths with forward slashes are equally valid.
- **Empty `__construct()` on LinkedFolder:** Per project rules, do not create empty constructors.
- **`$casts` property instead of `casts()` method:** Project convention uses `casts()` method — already confirmed in Track model.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Path separator normalisation | Custom recursive regex | `str_replace('\\', '/', $value)` | Simple, correct, no edge cases |
| Enum validation | Manual `in_array` checks | PHP backed enum + Eloquent cast | Type-safe, IDE-complete, fails loudly |
| FK cascade | Manual observer/event | `->cascadeOnDelete()` in migration | Database-level guarantee, atomic |

## Common Pitfalls

### Pitfall 1: Migration Order
**What goes wrong:** `add_source_type_to_tracks_table` references `linked_folders` FK before the table exists — migration fails.
**Why it happens:** Timestamp ordering is the only guarantee; if both are created in the same second or manually reordered, the FK constraint fails.
**How to avoid:** Generate `create_linked_folders_table` first, confirm its timestamp is earlier than `add_source_type_to_tracks_table`.
**Warning signs:** `SQLSTATE[HY000]: General error` on `php artisan migrate`.

### Pitfall 2: Enum Cast on Existing Null/Empty Rows
**What goes wrong:** If any existing track row has a null or unexpected `source_type` value, the enum cast throws a `ValueError` when the model is retrieved.
**Why it happens:** The column default `'uploaded'` only applies to new inserts — existing rows need the default too.
**How to avoid:** The migration adds the column with `->default('uploaded')`. For an existing non-empty database, also set existing rows explicitly in the migration `up()`:
```php
DB::table('tracks')->update(['source_type' => 'uploaded']);
```
Add this after `Schema::table()` closes, before the down method.
**Warning signs:** `ValueError: 'uploaded' is not a valid backing value for enum` (paradoxically on bad rows with empty string).

### Pitfall 3: `audioUrl()` Called on Local Track Before Route Exists
**What goes wrong:** Tests or code calling `$track->audioUrl()` where `source_type = 'local'` will throw a `RouteNotFoundException` because the streaming route is defined in Phase 13.
**Why it happens:** `route('stream.track', $track)` is evaluated eagerly.
**How to avoid:** In Phase 12 tests, only test uploaded tracks through `audioUrl()`, or mock the route helper. The method itself is correct; the dependency is deferred.

### Pitfall 4: `file_path` Mutator Conflicts with Factory
**What goes wrong:** `TrackFactory` sets `file_path` directly; the mutator applies normalisation which is fine — but if a test asserts the raw database value, it will see forward-slashed paths regardless of what was passed.
**Why it happens:** Mutator fires on every set, including factory-created records.
**How to avoid:** This is correct behaviour. Tests should not assert raw DB values contain backslashes; normalisation is the feature.

### Pitfall 5: `track_count` Integer Column — SQLite Type Affinity
**What goes wrong:** SQLite stores all integers as flexible affinity; `unsignedInteger` constraints are not enforced at DB level.
**Why it happens:** SQLite doesn't enforce unsigned constraints.
**How to avoid:** This is expected behaviour on SQLite — no action needed. The application layer maintains non-negative values.

## Code Examples

### Full Track Model After Changes
```php
// Key additions to app/Models/Track.php

use App\Enums\SourceType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

protected $fillable = [
    'user_id', 'album_id', 'linked_folder_id',
    'track_number', 'title', 'artist',
    'file_path', 'file_hash', 'duration',
    'year', 'genre', 'source_type',
];

protected function casts(): array
{
    return [
        'duration' => 'integer',
        'source_type' => SourceType::class,
    ];
}

protected function filePath(): Attribute
{
    return Attribute::make(
        set: fn (string $value) => str_replace('\\', '/', $value),
    );
}

public function audioUrl(): string
{
    return match ($this->source_type) {
        SourceType::Uploaded => Storage::disk('public')->url($this->file_path),
        SourceType::Local    => route('stream.track', $this),
    };
}

public function linkedFolder(): BelongsTo
{
    return $this->belongsTo(LinkedFolder::class);
}

public function scopeUploaded(Builder $query): Builder
{
    return $query->where('source_type', SourceType::Uploaded);
}

public function scopeLocal(Builder $query): Builder
{
    return $query->where('source_type', SourceType::Local);
}
```

### LinkedFolder Factory Pattern (follows TrackFactory conventions)
```php
public function definition(): array
{
    $path = '/Users/fake/Music/' . fake()->word();
    return [
        'user_id'        => User::factory(),
        'path'           => $path,
        'name'           => basename($path),
        'status'         => 'active',
        'track_count'    => 0,
        'last_scanned_at' => null,
    ];
}
```

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest v4 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=DataFoundation` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| DATA-01 | `source_type` column defaults to `uploaded` for existing rows | Feature | `php artisan test --compact --filter=TrackSourceTypeTest` | ❌ Wave 0 |
| DATA-01 | SourceType enum cast works on Track model | Feature | `php artisan test --compact --filter=TrackSourceTypeTest` | ❌ Wave 0 |
| DATA-01 | `scopeUploaded()` filters correctly | Feature | `php artisan test --compact --filter=TrackSourceTypeTest` | ❌ Wave 0 |
| DATA-01 | `scopeLocal()` filters correctly | Feature | `php artisan test --compact --filter=TrackSourceTypeTest` | ❌ Wave 0 |
| DATA-01 | `audioUrl()` returns Storage URL for uploaded track | Feature | `php artisan test --compact --filter=TrackSourceTypeTest` | ❌ Wave 0 |
| DATA-02 | `file_path` mutator normalises backslashes on write | Feature | `php artisan test --compact --filter=TrackPathNormalisationTest` | ❌ Wave 0 |
| DATA-02 | Forward slashes stored unchanged | Feature | `php artisan test --compact --filter=TrackPathNormalisationTest` | ❌ Wave 0 |
| DATA-03 | Job uses absolute path directly for local tracks | Feature | `php artisan test --compact --filter=ExtractAudioMetadataTest` | ❌ Wave 0 |
| DATA-03 | Job uses Storage::disk()->path() for uploaded tracks | Feature | `php artisan test --compact --filter=ExtractAudioMetadataTest` | ❌ Wave 0 |
| — | LinkedFolder factory creates valid record | Feature | `php artisan test --compact --filter=LinkedFolderTest` | ❌ Wave 0 |
| — | linked_folders table migration runs cleanly | Feature | Covered by RefreshDatabase in all tests | ✅ implicit |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=<relevant test file>`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/TrackSourceTypeTest.php` — covers DATA-01 enum, scopes, audioUrl uploaded branch
- [ ] `tests/Feature/TrackPathNormalisationTest.php` — covers DATA-02 mutator
- [ ] `tests/Feature/ExtractAudioMetadataTest.php` — covers DATA-03 job path branching
- [ ] `tests/Feature/LinkedFolderTest.php` — covers LinkedFolder factory and model

## Open Questions

1. **Streaming route name for `audioUrl()` local branch**
   - What we know: Phase 13 defines the streaming route; Phase 12 writes the method that calls it
   - What's unclear: Whether tests for `audioUrl()` on local tracks are in scope for Phase 12 or Phase 13
   - Recommendation: Test the local branch in Phase 12 by mocking the route helper or asserting the method contains the correct logic without invoking it; full integration test in Phase 13

2. **LinkedFolder `status` — plain string or second backed enum**
   - What we know: CONTEXT.md lists this as Claude's discretion; values are `active/scanning/error`
   - What's unclear: Whether a second enum file adds enough value for only 3 values
   - Recommendation: Use a `FolderStatus` backed string enum mirroring `SourceType` — consistent pattern, cheap to add, aids downstream type safety in Phase 14+

## Sources

### Primary (HIGH confidence)
- Direct code inspection of `app/Models/Track.php`, `app/Jobs/ExtractAudioMetadata.php`, `database/migrations/`, `database/factories/TrackFactory.php` — current implementation patterns confirmed
- CONTEXT.md — locked decisions from user discussion session
- PHP documentation (built-in knowledge, PHP 8.4 backed enums) — HIGH confidence, stable API

### Secondary (MEDIUM confidence)
- Laravel 12 Eloquent attribute casting with backed enums — consistent with documented Laravel 9+ behaviour, project uses Laravel v12
- Laravel `Attribute::make()` mutator pattern — standard since Laravel 9, stable through v12

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new dependencies, all existing tools
- Architecture: HIGH — all patterns derived directly from existing codebase and locked CONTEXT.md decisions
- Pitfalls: HIGH — migration FK ordering and enum cast on existing rows are well-understood failure modes

**Research date:** 2026-03-18
**Valid until:** 2026-04-18 (stable domain, framework version locked)
