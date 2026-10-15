---
phase: quick-8
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Jobs/ExtractAudioMetadata.php
  - app/Jobs/ScanLinkedFolder.php
  - config/queue.php
  - tests/Feature/ExtractAudioMetadataTest.php
  - tests/Feature/ScanLinkedFolderTest.php
autonomous: true
requirements: [QUICK-8]

must_haves:
  truths:
    - "ExtractAudioMetadata job allows 3 retries and has 120s timeout"
    - "ScanLinkedFolder job allows 3 retries and has 600s timeout"
    - "Database queue retry_after is 300 seconds to prevent premature re-queuing"
  artifacts:
    - path: "app/Jobs/ExtractAudioMetadata.php"
      provides: "$tries and $timeout properties"
      contains: "$tries = 3"
    - path: "app/Jobs/ScanLinkedFolder.php"
      provides: "$tries and $timeout properties"
      contains: "$tries = 3"
    - path: "config/queue.php"
      provides: "Increased retry_after for database driver"
      contains: "300"
  key_links:
    - from: "config/queue.php"
      to: "app/Jobs/ExtractAudioMetadata.php"
      via: "retry_after > timeout prevents premature re-queuing"
      pattern: "retry_after.*300"
---

<objective>
Fix MaxAttemptsExceededException during large folder scans by adding explicit $tries/$timeout to both queue jobs and increasing the database queue driver's retry_after from 90s to 300s.

Purpose: When ScanLinkedFolder dispatches thousands of ExtractAudioMetadata jobs, the default 90s retry_after causes the database queue driver to re-queue jobs before workers reach them, burning attempts. This results in MaxAttemptsExceededException before jobs ever execute their business logic.

Output: Both jobs have explicit retry/timeout config, queue driver retry_after increased, tests verify properties exist.
</objective>

<execution_context>
@./.claude/get-shit-done/workflows/execute-plan.md
@./.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@app/Jobs/ExtractAudioMetadata.php
@app/Jobs/ScanLinkedFolder.php
@config/queue.php
@tests/Feature/ExtractAudioMetadataTest.php
@tests/Feature/ScanLinkedFolderTest.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add $tries and $timeout to both jobs, increase retry_after</name>
  <files>app/Jobs/ExtractAudioMetadata.php, app/Jobs/ScanLinkedFolder.php, config/queue.php</files>
  <action>
1. In `app/Jobs/ExtractAudioMetadata.php`, add two public properties after `use Queueable;`:
   - `public int $tries = 3;` — allows retry on transient failures
   - `public int $timeout = 120;` — 2 minutes per track metadata extraction is generous

2. In `app/Jobs/ScanLinkedFolder.php`, add two public properties after `use Queueable;`:
   - `public int $tries = 3;` — allows retry on transient failures
   - `public int $timeout = 600;` — 10 minutes for large folder scans (thousands of files)

3. In `config/queue.php`, change the `database` connection's `retry_after` from `90` to `300`:
   - Change: `'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),`
   - To: `'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 300),`
   - This ensures the database driver waits 5 minutes before re-queuing a reserved job, which is longer than ExtractAudioMetadata's 120s timeout and gives ScanLinkedFolder's 600s timeout room via the env override.

Run `vendor/bin/pint --dirty` after changes.
  </action>
  <verify>
    <automated>cd /Users/defou/Herd/foonk && grep -q 'tries = 3' app/Jobs/ExtractAudioMetadata.php && grep -q 'timeout = 120' app/Jobs/ExtractAudioMetadata.php && grep -q 'tries = 3' app/Jobs/ScanLinkedFolder.php && grep -q 'timeout = 600' app/Jobs/ScanLinkedFolder.php && grep -q '300' config/queue.php && echo "PASS" || echo "FAIL"</automated>
  </verify>
  <done>Both jobs have $tries=3 and appropriate $timeout values. Database queue retry_after is 300s.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Add tests verifying job retry and timeout configuration</name>
  <files>tests/Feature/ExtractAudioMetadataTest.php, tests/Feature/ScanLinkedFolderTest.php</files>
  <behavior>
    - Test: ExtractAudioMetadata job has $tries set to 3
    - Test: ExtractAudioMetadata job has $timeout set to 120
    - Test: ScanLinkedFolder job has $tries set to 3
    - Test: ScanLinkedFolder job has $timeout set to 600
  </behavior>
  <action>
1. In `tests/Feature/ExtractAudioMetadataTest.php`, add two tests:
   ```php
   test('job allows 3 retry attempts', function () {
       $track = Track::factory()->create();
       $job = new ExtractAudioMetadata($track);
       expect($job->tries)->toBe(3);
   });

   test('job has 120 second timeout', function () {
       $track = Track::factory()->create();
       $job = new ExtractAudioMetadata($track);
       expect($job->timeout)->toBe(120);
   });
   ```

2. In `tests/Feature/ScanLinkedFolderTest.php`, add two tests:
   ```php
   test('job allows 3 retry attempts', function () {
       $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
       $job = new ScanLinkedFolder($folder);
       expect($job->tries)->toBe(3);
   });

   test('job has 600 second timeout for large folder scans', function () {
       $folder = LinkedFolder::factory()->create(['path' => $this->tmpDir]);
       $job = new ScanLinkedFolder($folder);
       expect($job->timeout)->toBe(600);
   });
   ```

Run `vendor/bin/pint --dirty` after changes.
  </action>
  <verify>
    <automated>cd /Users/defou/Herd/foonk && php artisan test --compact tests/Feature/ExtractAudioMetadataTest.php tests/Feature/ScanLinkedFolderTest.php</automated>
  </verify>
  <done>All existing tests still pass. New tests confirm $tries and $timeout properties are correctly set on both jobs.</done>
</task>

</tasks>

<verification>
- `php artisan test --compact tests/Feature/ExtractAudioMetadataTest.php tests/Feature/ScanLinkedFolderTest.php` passes all tests
- `grep -c 'tries\|timeout' app/Jobs/ExtractAudioMetadata.php` returns 2
- `grep -c 'tries\|timeout' app/Jobs/ScanLinkedFolder.php` returns 2
- `grep 'retry_after' config/queue.php` shows 300 for database driver
</verification>

<success_criteria>
- ExtractAudioMetadata has $tries=3 and $timeout=120
- ScanLinkedFolder has $tries=3 and $timeout=600
- Database queue retry_after defaults to 300 seconds
- All existing and new tests pass
</success_criteria>

<output>
After completion, create `.planning/quick/8-fix-extractaudiometadata-maxattemptsexce/8-SUMMARY.md`
</output>
