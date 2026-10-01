<?php

namespace App\Jobs;

use App\Enums\ArtworkSource;
use App\Models\Album;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Backfills album artwork for albums whose audio files carry no embedded image.
 *
 * Resolves the album to a MusicBrainz release group by title/artist, then pulls
 * the front cover from the Cover Art Archive (an Internet Archive project
 * fronting the same images MusicBrainz links to — no API key, no new dependency).
 *
 * The silent automatic half only: a user picks their own cover from the album
 * page instead, and that choice is never overwritten from here.
 */
class FetchAlbumArtwork implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    /**
     * MusicBrainz requires an identifying User-Agent on every request and
     * allows roughly one request per second.
     */
    private const MUSICBRAINZ_ENDPOINT = 'https://musicbrainz.org/ws/2/release-group/';

    private const COVER_ART_ENDPOINT = 'https://coverartarchive.org/release-group/';

    private const USER_AGENT = 'erik-rekodo/1.0 ( https://github.com/erik-rekodo )';

    /**
     * The one-second token that keeps concurrent jobs inside MusicBrainz's rate
     * budget without any of them holding a worker open.
     */
    private const RATE_GATE = 'album-artwork-mb-gate';

    /**
     * How long a definitive "this release has no cover" answer is trusted before
     * another lookup is attempted. Without this, every re-scan would re-ask
     * about the hundreds of albums that genuinely have no art online.
     */
    private const MISS_TTL_SECONDS = 2592000;

    public function __construct(public Album $album) {}

    public function uniqueId(): string
    {
        return (string) $this->album->getKey();
    }

    /**
     * Backoff for the 503 path: without a real delay a throttled release is
     * retried immediately and fails the same way.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $album = $this->album;

        // A cover the owner chose outranks anything detected here.
        if ($album->artwork_source !== null && ! $album->artwork_source->isAuto()) {
            return;
        }

        $missKey = self::missKey($album->id);

        if (Cache::has($missKey)) {
            return;
        }

        $releaseGroupId = $this->resolveReleaseGroupId($album);

        if ($releaseGroupId === null) {
            $this->rememberMiss($missKey);

            return;
        }

        if (! $this->acquireMusicBrainzSlot()) {
            return;
        }

        $cover = $this->frontCover($releaseGroupId);

        if ($cover === null) {
            $this->rememberMiss($missKey);

            return;
        }

        try {
            $album->storeCoverArt($cover->body(), ArtworkSource::Fetched);
        } catch (RuntimeException) {
            // The archive answered 200 with something that is not an image. That
            // is an answer, not a fault, so record the miss rather than burning
            // retries on a body that will never decode.
            $this->rememberMiss($missKey);
        }
    }

    /**
     * Cache key recording that the library already tried online for an album.
     *
     * Scans dispatch one fetch per track, so without this an album's whole
     * track list would enqueue the same lookup once per track.
     */
    public static function attemptKey(int $albumId): string
    {
        return 'album-artwork-attempt:'.$albumId;
    }

    public static function missKey(int $albumId): string
    {
        return 'album-artwork-miss:'.$albumId;
    }

    /**
     * Take MusicBrainz's one-request-per-second slot, or hand the job back.
     *
     * ponytail: a global token serialises the whole library, so 485 albums take
     * ~485s of wall clock regardless — the win is that no worker is asleep for
     * it. The upgrade path is a per-host rate limiter with jittered backoff
     * rather than a fixed one-second token.
     */
    private function acquireMusicBrainzSlot(): bool
    {
        if (Cache::add(self::RATE_GATE, true, now()->addSecond())) {
            return true;
        }

        // Invoked inline by the console command there is no queue job behind
        // this one, so a release would go nowhere — proceed instead of stalling.
        if ($this->job !== null) {
            $this->release(2);
        }

        return false;
    }

    /**
     * Find the MusicBrainz release group that best matches this album.
     */
    private function resolveReleaseGroupId(Album $album): ?string
    {
        $title = trim((string) $album->title);
        $artist = trim((string) $album->artist);

        if ($title === '' || $artist === '') {
            return null;
        }

        $response = $this->request(self::MUSICBRAINZ_ENDPOINT, [
            'query' => sprintf(
                'releasegroup:"%s" AND artist:"%s"',
                $this->escapeLucene($title),
                $this->escapeLucene($artist)
            ),
            'fmt' => 'json',
            'limit' => 10,
        ])->throw()->json('release-groups') ?? [];

        if ($response === []) {
            return null;
        }

        $candidate = $this->bestCandidate($response, $title, $album->year);

        return $candidate['id'] ?? null;
    }

    /**
     * Pick the release group whose title and release year line up with the album.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>|null
     */
    private function bestCandidate(array $candidates, string $title, ?int $year): ?array
    {
        $needle = Str::lower($title);

        $exact = array_values(array_filter(
            $candidates,
            fn (array $candidate) => Str::lower((string) ($candidate['title'] ?? '')) === $needle
        ));

        $pool = $exact !== [] ? $exact : $candidates;

        if ($year === null) {
            return $pool[0];
        }

        $sameYear = array_values(array_filter(
            $pool,
            fn (array $candidate) => (int) Str::before((string) ($candidate['first-release-date'] ?? ''), '-') === $year
        ));

        return $sameYear[0] ?? $pool[0];
    }

    /**
     * Fetch the front cover for a release group, or null if the archive has none.
     *
     * The -500 variant is the archive's resized thumbnail; the original is
     * routinely several thousand pixels wide for no visible benefit here.
     */
    private function frontCover(string $releaseGroupId): ?Response
    {
        $response = $this->request(self::COVER_ART_ENDPOINT.$releaseGroupId.'/front-500');

        return $response->successful() && $response->body() !== '' ? $response : null;
    }

    private function request(string $url, array $query = []): Response
    {
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(20)
            ->get($url, $query);

        // A 503 means we are being throttled. Throw so the job is retried with
        // a backoff, rather than recording a permanent "no art" for an album we
        // never actually managed to check.
        if ($response->status() === 503) {
            throw new RuntimeException('MusicBrainz is rate limiting; backing off.');
        }

        return $response;
    }

    private function rememberMiss(string $missKey): void
    {
        Cache::put($missKey, true, self::MISS_TTL_SECONDS);
    }

    /**
     * Strip the two characters that would break out of the quoted Lucene phrase.
     *
     * Other operators are literal inside a phrase, so a title like "Sign(s) Of
     * Life" needs no further escaping — only embedded quotes and backslashes do.
     */
    private function escapeLucene(string $value): string
    {
        return str_replace(['"', '\\'], ['', ' '], $value);
    }
}
