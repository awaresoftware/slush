# Deferred Items — 261001-578

Out-of-scope discoveries found while executing this plan. Not caused by this
change; not fixed here.

## 1. `WaveformPeaksTest` fails on a root-owned `/tmp/song.mp3`

- **Found during:** Task 2, full-suite regression run
- **Symptom:** `file_put_contents(/tmp/song.mp3): Failed to open stream: Permission denied`
  in `tests/Feature/Audio/WaveformPeaksTest.php:69` (via the helper `writeFixtureAudio()`)
- **Cause:** the test writes its fixture to the system temp dir. A previous run
  created `/tmp/song.mp3` as **root** (`-rw-r--r-- root root`, timestamped
  01:40, i.e. before this session began at 01:58). The suite runs as a non-root
  user and cannot overwrite a root-owned file inside a sticky-bit `/tmp`, so the
  write is denied regardless of the sticky bit's world-write permission.
- **Why it is not this change's regression:** the diff touches only the two
  appearance enums, the appearance Livewire component, and two test files. This
  test reads none of them, and the failure is a filesystem permission error, not
  an assertion failure.
- **Suggested fix (out of scope):** the fixture should not be written to a
  shared global path. `sys_get_temp_dir()`/a per-run unique directory, or
  `storage_path()` / `storage_path('framework/testing/disks')`, would remove the
  cross-user collision. Separately, the stale root-owned file can be cleared with
  `docker exec erikfoonk-laravel.test-1 rm -f /tmp/song.mp3`.

## 2. Pre-existing suite noise

The full suite reports 3 warnings and 4 skipped tests. All pre-date this change
and none are in the two files this plan touched. Not investigated — out of scope.
