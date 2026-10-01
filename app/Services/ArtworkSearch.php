<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Album artwork candidates from the two keyless public search APIs.
 *
 * MusicBrainz and the Cover Art Archive stay the silent automatic backfill (see
 * FetchAlbumArtwork) because they can only resolve one release group and
 * cannot be browsed. These two can, and between them they cover most of a
 * personal library without an API key or a new dependency.
 */
class ArtworkSearch
{
    private const ITUNES_ENDPOINT = 'https://itunes.apple.com/search';

    private const DEEZER_ENDPOINT = 'https://api.deezer.com/search/album';

    private const USER_AGENT = 'erik-rekodo/1.0 ( https://github.com/erik-rekodo )';

    private const CACHE_TTL_SECONDS = 86400;

    private const LIMIT = 12;

    /**
     * @return array<int, array{provider: string, id: string, title: string, artist: string, image_url: string, year: ?string, meta: string}>
     */
    public function itunes(string $query): array
    {
        return $this->search('itunes', $query);
    }

    /**
     * @return array<int, array{provider: string, id: string, title: string, artist: string, image_url: string, year: ?string, meta: string}>
     */
    public function deezer(string $query): array
    {
        return $this->search('deezer', $query);
    }

    /**
     * Look one provider up, cached for a day and failing on its own.
     *
     * A failure is deliberately not cached: a Deezer timeout must leave the
     * iTunes tab working now, and must not become a 24-hour blank tab either.
     *
     * @return array<int, array{provider: string, id: string, title: string, artist: string, image_url: string, year: ?string, meta: string}>
     */
    private function search(string $provider, string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $key = self::cacheKey($provider, $query);

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        try {
            $results = $provider === 'itunes'
                ? $this->normaliseItunes($this->request(self::ITUNES_ENDPOINT, [
                    'term' => $query,
                    'entity' => 'album',
                    'limit' => self::LIMIT,
                ])->json('results'))
                : $this->normaliseDeezer($this->request(self::DEEZER_ENDPOINT, [
                    'q' => $query,
                    'limit' => self::LIMIT,
                ])->json('data'));
        } catch (Throwable $e) {
            Log::warning('Artwork search failed.', ['provider' => $provider, 'error' => $e->getMessage()]);

            return [];
        }

        Cache::put($key, $results, self::CACHE_TTL_SECONDS);

        return $results;
    }

    /**
     * @return array<int, array{provider: string, id: string, title: string, artist: string, image_url: string, year: ?string, meta: string}>
     */
    private function normaliseItunes(mixed $results): array
    {
        $normalised = [];

        foreach (Arr::wrap($results) as $item) {
            $id = (string) data_get($item, 'collectionId', '');
            $thumb = (string) data_get($item, 'artworkUrl100', '');

            // A candidate with no id cannot be applied and one with no image
            // would render as a broken tile; a provider shape change should
            // shorten the list, never throw.
            if ($id === '' || $thumb === '') {
                continue;
            }

            $normalised[] = [
                'provider' => 'itunes',
                'id' => $id,
                'title' => (string) data_get($item, 'collectionName', ''),
                'artist' => (string) data_get($item, 'artistName', ''),
                // iTunes serves thumbnails on a sized path; 600x600bb is the
                // largest it offers, and asking for it is a substitution
                // rather than a request to a different host.
                'image_url' => str_replace('100x100bb', '600x600bb', $thumb),
                'year' => $this->year((string) data_get($item, 'releaseDate', '')),
                'meta' => $this->meta([
                    data_get($item, 'trackCount') ? data_get($item, 'trackCount').' tracks' : null,
                    data_get($item, 'primaryGenreName'),
                ]),
            ];
        }

        return $normalised;
    }

    /**
     * @return array<int, array{provider: string, id: string, title: string, artist: string, image_url: string, year: ?string, meta: string}>
     */
    private function normaliseDeezer(mixed $results): array
    {
        $normalised = [];

        foreach (Arr::wrap($results) as $item) {
            $id = (string) data_get($item, 'id', '');
            $cover = (string) (data_get($item, 'cover_xl') ?: data_get($item, 'cover_big', ''));

            if ($id === '' || $cover === '') {
                continue;
            }

            $normalised[] = [
                'provider' => 'deezer',
                'id' => $id,
                'title' => (string) data_get($item, 'title', ''),
                'artist' => (string) data_get($item, 'artist.name', ''),
                'image_url' => $cover,
                'year' => $this->year((string) data_get($item, 'release_date', '')),
                'meta' => $this->meta([
                    data_get($item, 'nb_tracks') ? data_get($item, 'nb_tracks').' tracks' : null,
                    data_get($item, 'record_type'),
                ]),
            ];
        }

        return $normalised;
    }

    private function request(string $url, array $query): Response
    {
        return Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(20)
            ->get($url, $query)
            ->throw();
    }

    /**
     * The four-digit release year, or null when the provider gave no date.
     */
    private function year(string $date): ?string
    {
        $year = Str::before($date, '-');

        return strlen($year) === 4 && ctype_digit($year) ? $year : null;
    }

    /**
     * Join the presentable facts about a result, dropping the missing ones.
     */
    private function meta(array $parts): string
    {
        return trim(implode(' · ', array_filter(array_map(
            fn ($part) => is_scalar($part) ? (string) $part : null,
            $parts
        ))));
    }

    /**
     * Cache key for one provider query, matching the album-artwork-miss:*
     * convention the automatic backfill already uses.
     */
    private static function cacheKey(string $provider, string $query): string
    {
        return 'artwork-search:'.$provider.':'.Str::lower(trim((string) preg_replace('/\s+/', ' ', $query)));
    }
}
