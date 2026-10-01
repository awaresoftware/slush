<?php

namespace App\Jobs;

use App\Audio\WaveformPeaks;
use App\Enums\ArtworkSource;
use App\Models\Album;
use App\Models\Track;
use getID3;
use getid3_lib;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ExtractAudioMetadata implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(public Track $track) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->track->load('linkedFolder');

        $absolutePath = $this->track->absolutePath();

        if (! file_exists($absolutePath)) {
            return;
        }

        $getID3 = new getID3;
        $info = $getID3->analyze($absolutePath);
        getid3_lib::CopyTagsToComments($info);

        $tags = $info['comments'] ?? [];

        $trackNumber = $this->parseTrackNumber($tags['track_number'][0] ?? null);

        $this->track->update([
            'title' => $tags['title'][0] ?? $this->track->title,
            'artist' => $tags['artist'][0] ?? $this->track->artist,
            'year' => $tags['year'][0] ?? null,
            'genre' => $tags['genre'][0] ?? null,
            'duration' => isset($info['playtime_seconds'])
                          ? (int) round($info['playtime_seconds'])
                          : null,
            'track_number' => $trackNumber,
            'lyrics' => $this->extractLyrics($tags, $absolutePath),
        ]);

        // Decoded once, here, where the file and its duration are both in hand.
        // Null is a normal result — no ffmpeg, or a codec it cannot read — and
        // the player falls back to a plain scrubber without it.
        $this->track->update([
            'peaks' => $this->extractPeaks($absolutePath),
        ]);

        $albumTitle = ! empty($tags['album'][0]) ? trim($tags['album'][0]) : null;

        if (! $albumTitle && $this->track->linked_folder_id && $this->track->linkedFolder) {
            $albumTitle = $this->track->linkedFolder->name;
        }

        if ($albumTitle && $this->track->album_id === null) {
            $albumArtist = $tags['album_artist'][0]
                ?? $tags['band'][0]
                ?? $tags['artist'][0]
                ?? null;

            $album = Album::query()
                ->where('user_id', $this->track->user_id)
                ->whereRaw('LOWER(title) = ?', [mb_strtolower($albumTitle)])
                ->first();

            // SQLite's LOWER() is ASCII-only, so a title with accents never
            // matches and would be duplicated as a second, artless album.
            // Retry with a Unicode-aware comparison, scoped to the rare
            // non-ASCII titles that the SQL lookup just failed to match.
            if (! $album && preg_match('/[^\x00-\x7F]/', $albumTitle) === 1) {
                $needle = mb_strtolower($albumTitle);

                $album = Album::query()
                    ->where('user_id', $this->track->user_id)
                    ->get(['id', 'title'])
                    ->first(fn (Album $candidate) => mb_strtolower($candidate->title) === $needle);
            }

            if (! $album) {
                $album = Album::create([
                    'user_id' => $this->track->user_id,
                    'title' => $albumTitle,
                    'artist' => $albumArtist,
                    'year' => isset($tags['year'][0]) ? (int) $tags['year'][0] : null,
                ]);
            }

            $this->track->update(['album_id' => $album->id]);
            $this->extractCoverArt($info, $album);
        } elseif ($this->track->album_id !== null) {
            $album = $this->track->album;
            if ($album) {
                $this->extractCoverArt($info, $album);
            }
        }
    }

    /**
     * Build the track's amplitude envelope for the player's waveform.
     *
     * Runs after the metadata update so the duration the buckets are sized
     * against is the one getID3 just measured rather than a stale row value.
     */
    private function extractPeaks(string $absolutePath): ?string
    {
        $peaks = WaveformPeaks::for($absolutePath, $this->track->duration);

        return $peaks === null ? null : json_encode($peaks);
    }

    /**
     * Find lyrics for the track: a sidecar .lrc file next to the audio wins
     * (it usually carries synced timestamps), then embedded tag lyrics.
     * The player parses LRC timestamps client-side; plain text stays plain.
     */
    private function extractLyrics(array $tags, string $absolutePath): ?string
    {
        $sidecar = preg_replace('/\.[^.\/\\\\]+$/', '.lrc', $absolutePath);

        if ($sidecar !== null && $sidecar !== $absolutePath && is_readable($sidecar)) {
            $contents = trim((string) file_get_contents($sidecar));

            if ($contents !== '') {
                return mb_substr($contents, 0, 20000);
            }
        }

        foreach (['unsynchronised_lyric', 'unsynchronized lyric', 'unsyncedlyrics', 'lyrics'] as $key) {
            $value = trim((string) ($tags[$key][0] ?? ''));

            if ($value !== '') {
                return mb_substr($value, 0, 20000);
            }
        }

        return null;
    }

    /**
     * Parse track number from ID3 tag value (handles "3/12" format).
     */
    private function parseTrackNumber(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parts = explode('/', $value);

        return (int) $parts[0] ?: null;
    }

    /**
     * Pick the best embedded picture and store it, then queue an online fetch
     * for the album if it is still artless.
     *
     * Candidates come from both getID3 picture keys, are filtered through the
     * one shared validity predicate, and are ranked front-cover-first then by
     * pixel area. Taking index 0 instead is what let a 1x1 placeholder win the
     * race and lock itself in permanently.
     */
    private function extractCoverArt(array $info, Album $album): void
    {
        $data = $this->bestEmbeddedPicture($info);

        if ($data !== null && $this->coverIsReplaceable($album)) {
            $album->storeCoverArt($data, ArtworkSource::Embedded);
        }

        $album->refresh();

        // D6: a scan or upload that ends with no artwork asks online for it, so
        // the backfill no longer depends on a human running the command.
        if ($album->cover_art_path !== null
            || trim((string) $album->artist) === ''
            || ! Cache::add(FetchAlbumArtwork::attemptKey($album->id), true, now()->addHour())) {
            return;
        }

        FetchAlbumArtwork::dispatch($album);
    }

    /**
     * The highest-ranked usable picture in the file, or null when there is none.
     */
    private function bestEmbeddedPicture(array $info): ?string
    {
        $candidates = [];

        foreach ([$info['id3v2']['APIC'] ?? [], $info['comments']['picture'] ?? []] as $frames) {
            foreach ($this->pictureFrames($frames) as $frame) {
                $data = $frame['data'] ?? null;

                if (! is_string($data) || ! Album::coverIsUsable($data)) {
                    continue;
                }

                [$width, $height] = getimagesizefromstring($data);

                $candidates[] = [
                    'data' => $data,
                    'front' => ($frame['picturetype'] ?? '') === 'Cover (front)',
                    'area' => $width * $height,
                ];
            }
        }

        if ($candidates === []) {
            return null;
        }

        // Front cover first, then largest: a front cover beats a bigger back
        // cover, and the largest front cover wins among several.
        usort($candidates, fn (array $a, array $b) => [$b['front'], $b['area']] <=> [$a['front'], $a['area']]);

        return $candidates[0]['data'];
    }

    /**
     * Normalise a getID3 picture container to a list of frames.
     *
     * Both APIC and comments.picture are normally lists, but either can hold a
     * single frame directly.
     *
     * @return array<int, mixed>
     */
    private function pictureFrames(mixed $frames): array
    {
        if (! is_array($frames) || $frames === []) {
            return [];
        }

        return array_is_list($frames) ? $frames : [$frames];
    }

    /**
     * Whether re-extraction is allowed to replace the cover already on record.
     *
     * A real image on disk is a deliberate outcome and is kept — that is what
     * stops a second track of the same album from clobbering it. A cover that
     * is missing or undecodable is exactly the garbage the backfill leaves
     * behind, and this is its chance to be replaced.
     */
    private function coverIsReplaceable(Album $album): bool
    {
        if ($album->cover_art_path === null) {
            return true;
        }

        $disk = Storage::disk('public');

        return ! $disk->exists($album->cover_art_path)
            || ! Album::coverIsUsable($disk->get($album->cover_art_path));
    }
}
