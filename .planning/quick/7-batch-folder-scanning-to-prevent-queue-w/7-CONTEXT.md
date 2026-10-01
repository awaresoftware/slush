# Quick Task 7: Batch folder scanning to prevent queue worker memory exhaustion on large libraries - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Task Boundary

When scanning folders with many tracks (~6k), the queue worker breaks around 2k jobs due to memory exhaustion. ScanLinkedFolder currently dispatches one ExtractAudioMetadata job per file with no batching. Need to process files in batches, release memory between batches, and provide progress feedback.

</domain>

<decisions>
## Implementation Decisions

### Batch Size Strategy
- Use a fixed default of 200 tracks per batch, configurable via config/env (e.g. `SCAN_BATCH_SIZE=200`)
- Adaptive memory-based sizing is unreliable in PHP — memory cost per track varies with cover art size/metadata complexity
- Users can tune the value for their system; Laravel's `--max-memory` on the worker provides the safety net

### Worker Memory Handling
- Chunk dispatching inside ScanLinkedFolder job itself
- ScanLinkedFolder discovers all files first, then dispatches ExtractAudioMetadata in chunks of N
- Between chunks, references are released so PHP can garbage collect
- No need for job chains or Bus::batch() — keep it simple

### Progress Feedback
- Show batch progress in the UI: "2,000 / 6,000 tracks processed"
- Update the LinkedFolder model with progress fields so the Livewire component can poll and display progress
- User should know it's working and how far along the scan is

</decisions>

<specifics>
## Specific Ideas

- Add `scanned_count` and `total_count` fields to LinkedFolder for progress tracking
- Chunk the file discovery results in ScanLinkedFolder and dispatch ExtractAudioMetadata per chunk
- Update scanned_count on LinkedFolder after each batch completes
- Livewire polling (already 3s interval) picks up progress and displays it
- Config value: `config('queue.scan_batch_size', 200)` or similar

</specifics>
