<?php

namespace App\Jobs;

use App\Enums\FolderStatus;
use App\Enums\SourceType;
use App\Models\LinkedFolder;
use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScanLinkedFolder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(public LinkedFolder $folder) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Read once, not per file: config/rekodo.php is the single source of
            // truth for what counts as audio, and a lookup inside the loop would
            // cost one call per file across a library of any real size.
            $extensions = config('rekodo.audio_extensions');

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->folder->path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var list<array{path: string, extension: string}> $files */
            $files = [];

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $extension = strtolower($file->getExtension());

                if (! in_array($extension, $extensions, true)) {
                    continue;
                }

                $rawPath = $file->getRealPath();

                if ($rawPath === false) {
                    continue;
                }

                $files[] = [
                    'path' => $rawPath,
                    'extension' => $extension,
                ];
            }

            $totalCount = count($files);

            $this->folder->update([
                'status' => FolderStatus::Scanning,
                'total_count' => $totalCount,
                'scanned_count' => 0,
            ]);

            $batchSize = config('rekodo.scan_batch_size', 200);
            $chunks = array_chunk($files, $batchSize);
            $processedSoFar = 0;

            foreach ($chunks as $chunk) {
                foreach ($chunk as $fileInfo) {
                    try {
                        $rawPath = $fileInfo['path'];
                        $filePath = str_replace('\\', '/', $rawPath);
                        $fileHash = md5_file($rawPath);

                        $existingByPath = Track::query()
                            ->where('user_id', $this->folder->user_id)
                            ->where('file_path', $filePath)
                            ->first();

                        if ($existingByPath) {
                            $existingByPath->update(['file_hash' => $fileHash]);
                            ExtractAudioMetadata::dispatch($existingByPath);

                            continue;
                        }

                        $existingByHash = Track::query()
                            ->where('user_id', $this->folder->user_id)
                            ->where('file_hash', $fileHash)
                            ->first();

                        if ($existingByHash) {
                            $existingByHash->update(['file_path' => $filePath]);
                            ExtractAudioMetadata::dispatch($existingByHash);

                            continue;
                        }

                        $title = pathinfo(basename($rawPath), PATHINFO_FILENAME);

                        $track = Track::create([
                            'user_id' => $this->folder->user_id,
                            'linked_folder_id' => $this->folder->id,
                            'title' => $title,
                            'artist' => 'Unknown Artist',
                            'file_path' => $filePath,
                            'file_hash' => $fileHash,
                            'duration' => null,
                            'source_type' => SourceType::Local,
                        ]);

                        ExtractAudioMetadata::dispatch($track);
                    } catch (\Throwable $e) {
                        Log::warning('ScanLinkedFolder: skipping unreadable file', [
                            'file' => $fileInfo['path'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $processedSoFar += count($chunk);
                $this->folder->update(['scanned_count' => min($processedSoFar, $totalCount)]);

                unset($chunk);
            }

            unset($files);

            $unavailableCount = 0;

            // cursor() rather than get(): this pass touches every track in the
            // folder, and a large library's worth of hydrated models is the one
            // part of a scan whose memory grows with the collection. Writing
            // while the cursor is open is safe here — no transaction wraps this
            // loop, and the updates go out on their own statement.
            foreach ($this->folder->tracks()->cursor() as $track) {
                if (! file_exists($track->file_path)) {
                    if ($track->is_available) {
                        $track->update(['is_available' => false]);
                    }
                    $unavailableCount++;
                } elseif (! $track->is_available) {
                    $track->update(['is_available' => true]);
                }
            }

            $this->folder->update([
                'status' => FolderStatus::Active,
                'track_count' => $this->folder->tracks()->count(),
                'unavailable_count' => $unavailableCount,
                'scanned_count' => $totalCount,
                'total_count' => $totalCount,
                'last_scanned_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->folder->update(['status' => FolderStatus::Error]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->folder->update(['status' => FolderStatus::Error]);
    }
}
