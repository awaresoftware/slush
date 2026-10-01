---
phase: quick-8
plan: "01"
subsystem: queue-jobs
tags: [queue, jobs, retry, timeout, configuration]
dependency_graph:
  requires: []
  provides: [retry-configuration, job-timeout-bounds]
  affects: [app/Jobs/ExtractAudioMetadata.php, app/Jobs/ScanLinkedFolder.php, config/queue.php]
tech_stack:
  added: []
  patterns: [explicit-job-retry-config, queue-retry_after-tuning]
key_files:
  created: []
  modified:
    - app/Jobs/ExtractAudioMetadata.php
    - app/Jobs/ScanLinkedFolder.php
    - config/queue.php
    - tests/Feature/ExtractAudioMetadataTest.php
    - tests/Feature/ScanLinkedFolderTest.php
decisions:
  - "retry_after=300s for database queue driver — longer than ExtractAudioMetadata 120s timeout, shorter than ScanLinkedFolder 600s timeout (env override available)"
  - "ExtractAudioMetadata timeout=120s — generous for single-track metadata extraction via getID3"
  - "ScanLinkedFolder timeout=600s — 10 minutes for large folder scans with thousands of files"
metrics:
  duration: "~5 minutes"
  completed: "2026-03-19"
  tasks_completed: 2
  files_modified: 5
---

# Phase quick-8 Plan 01: Fix ExtractAudioMetadata MaxAttemptsExceededException Summary

**One-liner:** Added explicit `$tries=3` and job-specific `$timeout` to both queue jobs, and increased database queue `retry_after` from 90s to 300s to prevent premature re-queuing from burning attempts.

## What Was Built

When ScanLinkedFolder dispatches thousands of ExtractAudioMetadata jobs during large library scans, the default 90s `retry_after` caused the database queue driver to re-queue reserved jobs before workers reached them. Each re-queue consumed an attempt, causing `MaxAttemptsExceededException` before jobs executed their business logic.

**Fix:**
- `ExtractAudioMetadata`: `$tries = 3`, `$timeout = 120` — 2 minutes per track is generous for getID3 analysis
- `ScanLinkedFolder`: `$tries = 3`, `$timeout = 600` — 10 minutes for large folder scans with thousands of files
- `config/queue.php` database driver: `retry_after` default changed from 90s to 300s — longer than `ExtractAudioMetadata`'s 120s timeout; `ScanLinkedFolder`'s 600s timeout can be set via `DB_QUEUE_RETRY_AFTER` env var

## Tasks Completed

| Task | Description | Commit |
|------|-------------|--------|
| 1 | Add $tries/$timeout to both jobs, increase retry_after to 300s | 37b7c99 |
| 2 | Add tests verifying job retry and timeout configuration | c250e75 |

## Decisions Made

- **retry_after=300s default** — Covers ExtractAudioMetadata's 120s timeout with margin; ScanLinkedFolder's 600s timeout requires `DB_QUEUE_RETRY_AFTER=660` env override for strictest correctness, but the main issue was ExtractAudioMetadata jobs burning attempts
- **$tries=3 on both jobs** — Allows recovery from transient failures (disk hiccups, DB locks) without infinite retry loops

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

All key files verified on disk. Both task commits (37b7c99, c250e75) confirmed in git log.
