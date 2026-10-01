<?php

use App\Audio\WaveformPeaks;
use App\Jobs\FetchAlbumArtwork;
use App\Models\Album;
use App\Models\Track;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'albums:fetch-missing-artwork {--limit=0 : Maximum albums to process} {--sync : Run inline instead of queueing}',
    function () {
        $limit = max(0, (int) $this->option('limit'));
        $sync = (bool) $this->option('sync');

        $query = Album::query()
            ->whereNull('cover_art_path')
            ->whereNotNull('artist')
            ->where('artist', '!=', '')
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $albums = $query->get();

        if ($albums->isEmpty()) {
            $this->info('Every album with a known artist already has artwork.');

            return;
        }

        $bar = $this->output->createProgressBar($albums->count());
        $bar->start();

        $failed = 0;

        foreach ($albums as $album) {
            try {
                $sync
                    ? (new FetchAlbumArtwork($album))->handle()
                    : FetchAlbumArtwork::dispatch($album);
            } catch (Throwable $e) {
                // One throttled or unreachable album must not abort the rest of
                // the run; re-run the command later to pick these back up.
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $covered = Album::query()->whereNotNull('cover_art_path')->count();
        $this->info("Processed {$albums->count()} album(s). Library now has {$covered} with artwork.");

        if ($failed > 0) {
            $this->warn("{$failed} album(s) could not be checked (throttled or unreachable). Re-run to retry them.");
        }

        if (! $sync) {
            $this->comment('Jobs queued. Run the queue worker to process them.');
        }
    }
)->purpose('Backfill album artwork for albums whose files have no embedded image');

Artisan::command(
    'albums:backfill-artwork {--dry-run : Report what would change and touch nothing}',
    function () {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');

        // This is the one command in the app that deletes files no database row
        // points at, so it refuses to run at all unless the public disk root
        // really is the public disk. The second allowed root is where
        // Storage::fake() puts it, so the command stays testable.
        $root = realpath($disk->path(''));
        $allowed = array_filter(array_map('realpath', [
            storage_path('app/public'),
            storage_path('framework/testing/disks/public'),
        ]));

        if ($root === false || ! in_array($root, $allowed, true)) {
            $this->error('Refusing to run: the public disk root '.($root ?: $disk->path('')).' is not the public disk.');

            return 1;
        }

        $renamed = 0;
        $pruned = 0;
        $cleared = 0;

        // 1. Re-encode referenced covers to the canonical 600px WebP path.
        $albums = Album::query()->whereNotNull('cover_art_path')->orderBy('id')->get();

        $bar = $this->output->createProgressBar($albums->count());
        $bar->start();

        foreach ($albums as $album) {
            $bar->advance();

            $previous = $album->cover_art_path;

            if ($previous === 'covers/'.$album->id.'.webp') {
                continue;
            }

            $bytes = $disk->exists($previous) ? $disk->get($previous) : null;

            // Unusable art is left for phase 3, which clears the row instead.
            if ($bytes === null || ! Album::coverIsUsable($bytes)) {
                continue;
            }

            $renamed++;

            if ($dryRun) {
                continue;
            }

            $encoded = (string) (new ImageManager(new Driver))
                ->read($bytes)
                ->scaleDown(600, 600)
                ->toWebp(quality: 80);

            $filename = 'covers/'.$album->id.'.webp';
            $disk->put($filename, $encoded);
            $album->update(['cover_art_path' => $filename]);
            $disk->delete($previous);
        }

        $bar->finish();
        $this->newLine();

        // 2. Prune the files no row references — the bulk of the wasted space.
        $referenced = Album::query()
            ->whereNotNull('cover_art_path')
            ->pluck('cover_art_path')
            ->all();

        foreach ($disk->files('covers') as $file) {
            if (in_array($file, $referenced, true)) {
                continue;
            }

            $pruned++;

            if (! $dryRun) {
                $disk->delete($file);
            }
        }

        // 3. Clear covers that are missing or undecodable, which can otherwise
        //    never be replaced. Nulling the path re-arms the automatic fetch.
        foreach ($albums as $album) {
            $album->refresh();

            if ($album->cover_art_path === null) {
                continue;
            }

            $bytes = $disk->exists($album->cover_art_path) ? $disk->get($album->cover_art_path) : null;

            if ($bytes !== null && Album::coverIsUsable($bytes)) {
                continue;
            }

            $cleared++;

            if (! $dryRun) {
                $album->clearCoverArt();
            }
        }

        $prefix = $dryRun ? 'Would ' : '';
        $this->info("{$prefix}re-encode {$renamed} cover(s) to 600px WebP, prune {$pruned} orphaned file(s), clear {$cleared} degenerate cover(s).");

        if ($dryRun) {
            $this->comment('Dry run — nothing was written. Re-run without --dry-run to apply.');
        }

        return 0;
    }
)->purpose('Re-encode album covers to 600px WebP, prune orphaned cover files and clear unusable ones');

Artisan::command(
    'tracks:recompute-peaks {--missing : Only tracks that have no envelope yet} {--limit=0 : Stop after this many tracks} {--force : Redo tracks that already have one}',
    function () {
        if (! WaveformPeaks::available()) {
            $this->error('ffmpeg was not found on this machine, so no waveform can be produced.');
            $this->comment('Install it (macOS: brew install ffmpeg) or set FFMPEG_PATH, then re-run.');

            return 1;
        }

        $query = Track::query()->where('is_available', true);

        $onlyMissing = (bool) $this->option('missing');
        $force = (bool) $this->option('force');

        // Default: fill gaps and refresh anything already recorded, since the
        // bucket count may have changed since a track was first scanned.
        if (! $onlyMissing && ! $force) {
            $query->where(function ($q) {
                $q->whereNull('peaks')->orWhere('peaks', '[]');
            });
        }

        $limit = max(0, (int) $this->option('limit'));

        if ($limit > 0) {
            $query->limit($limit);
        }

        $tracks = $query->orderBy('id')->get();

        if ($tracks->isEmpty()) {
            $this->info('Nothing to do — no matching tracks.');

            return 0;
        }

        $buckets = (int) config('rekodo.waveform.buckets', 200);
        $bar = $this->output->createProgressBar($tracks->count());
        $bar->start();

        $written = 0;
        $failed = 0;

        foreach ($tracks as $track) {
            $bar->advance();

            $peaks = WaveformPeaks::for($track->absolutePath(), $track->duration);

            if ($peaks === null) {
                $failed++;

                continue;
            }

            $track->update(['peaks' => json_encode($peaks)]);
            $written++;
        }

        $bar->finish();
        $this->newLine();

        $this->info("Wrote envelopes for {$written} track(s) at {$buckets} buckets.");

        if ($failed > 0) {
            $this->warn("{$failed} track(s) could not be decoded (unreadable, or a codec ffmpeg skipped). Re-run to retry them.");
        }

        return 0;
    }
)->purpose('Build the waveform envelope for tracks, backfilling the ones that have none');
