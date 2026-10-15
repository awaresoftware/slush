---
phase: quick-7
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - database/migrations/YYYY_add_scan_progress_to_linked_folders_table.php
  - config/foonk.php
  - .env.example
  - app/Models/LinkedFolder.php
  - app/Jobs/ScanLinkedFolder.php
  - resources/views/pages/settings/⚡linked-folders.blade.php
  - tests/Feature/ScanLinkedFolderTest.php
  - tests/Feature/Settings/LinkedFoldersSettingsTest.php
  - database/factories/LinkedFolderFactory.php
autonomous: true
requirements: [QUICK-7]

must_haves:
  truths:
    - "ScanLinkedFolder processes files in configurable batches instead of one-per-dispatch"
    - "Memory references are released between batches allowing garbage collection"
    - "LinkedFolder progress (scanned_count / total_count) updates after each batch"
    - "UI shows batch progress during scanning (e.g. '2,000 / 6,000 tracks processed')"
    - "Batch size defaults to 200 and is configurable via SCAN_BATCH_SIZE env var"
  artifacts:
    - path: "config/foonk.php"
      provides: "Application-specific config with scan_batch_size"
      contains: "scan_batch_size"
    - path: "app/Jobs/ScanLinkedFolder.php"
      provides: "Chunked file processing with progress tracking"
      contains: "array_chunk"
    - path: "database/migrations/*_add_scan_progress_to_linked_folders_table.php"
      provides: "scanned_count and total_count columns"
  key_links:
    - from: "app/Jobs/ScanLinkedFolder.php"
      to: "config/foonk.php"
      via: "config('foonk.scan_batch_size')"
      pattern: "config\\('foonk\\.scan_batch_size'"
    - from: "app/Jobs/ScanLinkedFolder.php"
      to: "app/Models/LinkedFolder.php"
      via: "folder->update scanned_count/total_count"
      pattern: "scanned_count|total_count"
    - from: "resources/views/pages/settings/⚡linked-folders.blade.php"
      to: "app/Models/LinkedFolder.php"
      via: "scanned_count and total_count display"
      pattern: "scanned_count.*total_count"
---

<objective>
Batch folder scanning to prevent queue worker memory exhaustion on large libraries (6k+ tracks).

Purpose: ScanLinkedFolder currently dispatches one ExtractAudioMetadata job per file in a single unbounded loop. With large folders (~6k tracks), the queue worker runs out of memory around 2k dispatches. Chunking the dispatch into batches of 200 (configurable) with memory release between batches prevents OOM while also providing progress feedback to the user.

Output: Chunked ScanLinkedFolder job with progress tracking columns and UI progress display.
</objective>

<execution_context>
@./.claude/get-shit-done/workflows/execute-plan.md
@./.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@./CLAUDE.md
@.planning/STATE.md

<interfaces>
<!-- Key types and contracts the executor needs -->

From app/Models/LinkedFolder.php:
```php
protected $fillable = [
    'user_id', 'path', 'name', 'status',
    'track_count', 'unavailable_count', 'last_scanned_at',
];
// Casts: status => FolderStatus, track_count => integer,
//        unavailable_count => integer, last_scanned_at => datetime
```

From app/Jobs/ScanLinkedFolder.php:
```php
class ScanLinkedFolder implements ShouldQueue
{
    use Queueable;
    private const AUDIO_EXTENSIONS = ['mp3', 'wav', 'flac', 'm4a', 'aac', 'ogg'];
    public function __construct(public LinkedFolder $folder) {}
    public function handle(): void { /* iterates all files, dispatches ExtractAudioMetadata per file */ }
}
```

From app/Enums/FolderStatus.php (inferred from usage):
```php
enum FolderStatus: string { case Active; case Scanning; case Error; }
```

Livewire component (anonymous, resources/views/pages/settings/⚡linked-folders.blade.php):
- Already polls every 3s via `wire:poll.3s="pollFolders"` when scanning
- Shows "Scanning..." badge when `$folder->status === FolderStatus::Scanning`
- pollFolders() checks if folder left Scanning state, shows completion toast
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add progress columns, config, and refactor ScanLinkedFolder to batch dispatching</name>
  <files>
    database/migrations/YYYY_add_scan_progress_to_linked_folders_table.php
    config/foonk.php
    .env.example
    app/Models/LinkedFolder.php
    database/factories/LinkedFolderFactory.php
    app/Jobs/ScanLinkedFolder.php
  </files>
  <action>
    1. **Create migration** via `php artisan make:migration add_scan_progress_to_linked_folders_table --table=linked_folders --no-interaction`. Add two columns:
       - `scanned_count` unsignedInteger default 0 after `unavailable_count`
       - `total_count` unsignedInteger default 0 after `scanned_count`

    2. **Run migration**: `php artisan migrate --no-interaction`

    3. **Create config/foonk.php** with application-specific config:
       ```php
       return [
           'scan_batch_size' => (int) env('SCAN_BATCH_SIZE', 200),
       ];
       ```

    4. **Update .env.example** — add `SCAN_BATCH_SIZE=200` in a `# Scanning` section after the QUEUE_CONNECTION line.

    5. **Update LinkedFolder model**:
       - Add `scanned_count` and `total_count` to `$fillable`
       - Add both to `casts()` as `'integer'`

    6. **Update LinkedFolderFactory** — add `scanned_count => 0` and `total_count => 0` to `definition()`.

    7. **Refactor ScanLinkedFolder::handle()** — the core change:

       a. **File discovery phase** (unchanged logic, but collect into array first):
          - Use RecursiveIteratorIterator as before
          - Collect all audio file SplFileInfo objects into a `$files` array
          - Set `total_count` on the folder: `$this->folder->update(['status' => FolderStatus::Scanning, 'total_count' => count($files), 'scanned_count' => 0])`

       b. **Chunked processing phase**:
          - `$batchSize = config('foonk.scan_batch_size', 200);`
          - `$chunks = array_chunk($files, $batchSize);`
          - Loop over `$chunks`:
            - For each chunk, run the existing per-file logic (path-check, hash-check, create-or-update Track, dispatch ExtractAudioMetadata)
            - After processing each chunk: `$this->folder->update(['scanned_count' => min($processedSoFar, $totalCount)])`
            - Unset the chunk variable to release references for GC
          - After all chunks: unset `$files` array

       c. **Availability check phase** (unchanged — loop over folder tracks checking file_exists)

       d. **Completion update** (add scanned_count/total_count to final update):
          ```php
          $this->folder->update([
              'status' => FolderStatus::Active,
              'track_count' => $this->folder->tracks()->count(),
              'unavailable_count' => $unavailableCount,
              'scanned_count' => $totalCount,
              'total_count' => $totalCount,
              'last_scanned_at' => now(),
          ]);
          ```

       IMPORTANT: The file discovery phase should collect SplFileInfo path strings (not the SplFileInfo objects themselves) into the array to avoid holding open file handles. Store as `['path' => $rawPath, 'extension' => $extension]` associative arrays.

       IMPORTANT: Do NOT use Bus::batch() or job chains — keep it simple per user decision. The chunking happens inside the single ScanLinkedFolder job.

    8. **Run pint**: `vendor/bin/pint --dirty`
  </action>
  <verify>
    <automated>php artisan migrate:status | grep scan_progress && php artisan test --compact --filter=ScanLinkedFolderTest</automated>
  </verify>
  <done>
    - Migration creates scanned_count + total_count columns
    - Config file exists at config/foonk.php with scan_batch_size
    - ScanLinkedFolder processes files in chunks, updates scanned_count after each batch
    - All existing ScanLinkedFolderTest tests pass (no regressions)
  </done>
</task>

<task type="auto">
  <name>Task 2: UI progress display and tests for batching + progress</name>
  <files>
    resources/views/pages/settings/⚡linked-folders.blade.php
    tests/Feature/ScanLinkedFolderTest.php
    tests/Feature/Settings/LinkedFoldersSettingsTest.php
  </files>
  <action>
    1. **Update the scanning badge in the Blade template** — when `$folder->status === FolderStatus::Scanning`, show progress alongside the spinner:
       ```blade
       @if ($folder->status === FolderStatus::Scanning)
           <span class="badge badge-warning badge-sm">
               <span class="loading loading-spinner loading-xs mr-1"></span>
               @if ($folder->total_count > 0)
                   {{ __('Scanning: :scanned / :total', ['scanned' => number_format($folder->scanned_count), 'total' => number_format($folder->total_count)]) }}
               @else
                   {{ __('Scanning...') }}
               @endif
           </span>
       @elseif ...
       ```
       This preserves the original "Scanning..." text until total_count is known (during initial file discovery), then shows progress.

    2. **Add batching tests to ScanLinkedFolderTest.php**:

       a. `test('processes files in configurable batch size', ...)`:
          - Create a temp dir with 5 audio files
          - Set `config(['foonk.scan_batch_size' => 2])`
          - Run `(new ScanLinkedFolder($folder))->handle()`
          - Assert all 5 tracks created, all 5 ExtractAudioMetadata dispatched
          - Assert `$folder->fresh()->scanned_count` equals 5
          - Assert `$folder->fresh()->total_count` equals 5

       b. `test('updates scanned_count progressively during processing', ...)`:
          - Create 4 audio files, set batch size to 2
          - Override ExtractAudioMetadata to a no-op (Queue::fake())
          - Run scan
          - Assert final scanned_count is 4 and total_count is 4

       c. `test('sets total_count before processing begins', ...)`:
          - Create 3 audio files
          - Use Queue::fake()
          - Run scan
          - Assert `$folder->fresh()->total_count` equals 3

    3. **Add progress display test to LinkedFoldersSettingsTest.php**:

       `test('scanning folder shows progress when total_count is set', ...)`:
       - Create a LinkedFolder with `status => Scanning, scanned_count => 2000, total_count => 6000`
       - Assert Livewire component sees "2,000" and "6,000" in rendered output

    4. **Run pint**: `vendor/bin/pint --dirty`
  </action>
  <verify>
    <automated>php artisan test --compact --filter="ScanLinkedFolderTest|LinkedFoldersSettingsTest"</automated>
  </verify>
  <done>
    - Scanning badge shows "Scanning: 2,000 / 6,000" when total_count > 0
    - Scanning badge shows "Scanning..." when total_count is 0 (discovery phase)
    - Tests confirm batch processing creates all tracks regardless of batch size
    - Tests confirm scanned_count and total_count are set correctly
    - Tests confirm progress is displayed in UI
    - All existing tests still pass (no regressions)
  </done>
</task>

</tasks>

<verification>
- `php artisan test --compact --filter="ScanLinkedFolderTest|LinkedFoldersSettingsTest"` — all tests pass
- `vendor/bin/pint --dirty` — no formatting issues
- `php artisan migrate:status` — scan progress migration applied
- Config value accessible: `php artisan tinker --execute="echo config('foonk.scan_batch_size');"` returns 200
</verification>

<success_criteria>
- ScanLinkedFolder chunks file dispatching into batches of config('foonk.scan_batch_size') (default 200)
- Memory references released between batches (unset chunk arrays)
- LinkedFolder.scanned_count and total_count updated after each batch
- UI shows "Scanning: X / Y" progress during scan with 3s polling
- Batch size configurable via SCAN_BATCH_SIZE env var
- All existing + new tests pass
</success_criteria>

<output>
After completion, create `.planning/quick/7-batch-folder-scanning-to-prevent-queue-w/7-SUMMARY.md`
</output>
