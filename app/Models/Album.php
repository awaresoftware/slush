<?php

namespace App\Models;

use App\Enums\ArtworkSource;
use App\Jobs\FetchAlbumArtwork;
use Database\Factories\AlbumFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;

class Album extends Model
{
    /** @use HasFactory<AlbumFactory> */
    use HasFactory;

    /**
     * Smallest edge, in pixels, a cover may have on either side to count as
     * real artwork. Anything smaller is the 1x1/0x0/72x72 placeholder garbage
     * that embedded tags routinely carry; shared by extraction and the
     * backfill so the two thresholds cannot drift apart.
     */
    public const int MIN_COVER_EDGE = 100;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'artist',
        'year',
        'genre',
        'cover_art_path',
        'artwork_source',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'artwork_source' => ArtworkSource::class,
        ];
    }

    /**
     * Get the user that owns the album.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tracks belonging to this album.
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    /**
     * Get the public URL for the album's cover art, or null if none is set.
     *
     * Reads from the `public` disk because that is where cover art is written
     * (see storeCoverArt). Resolving against the default disk instead points
     * at storage/app/private and 403s on any host that does not special-case
     * /storage. Returns null when the file is gone so views fall back to their
     * placeholder instead of rendering a broken image.
     */
    public function coverArtUrl(): ?string
    {
        if ($this->cover_art_path === null) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($this->cover_art_path) ? $disk->url($this->cover_art_path) : null;
    }

    /**
     * Whether these bytes are a real cover image rather than a placeholder.
     *
     * Rejects anything getimagesizefromstring() cannot decode — an HTML error
     * page from a remote host, a truncated APIC frame, a literal string — and
     * anything below MIN_COVER_EDGE on either edge.
     */
    public static function coverIsUsable(string $bytes): bool
    {
        $size = @getimagesizefromstring($bytes);

        if ($size === false) {
            return false;
        }

        return $size[0] >= self::MIN_COVER_EDGE && $size[1] >= self::MIN_COVER_EDGE;
    }

    /**
     * Store image bytes as this album's cover art and record the path and source.
     *
     * The single canonical write path for all four writers — embedded
     * extraction, online fetch, local upload and the web picker — so every
     * cover in the library is WebP, at most 600px on its longest edge, quality
     * 80, at covers/{id}.webp. Because the extension is constant there is no
     * MIME→extension mapping left to get wrong, and any differently-extended
     * predecessor is deleted rather than orphaned.
     */
    public function storeCoverArt(string $imageData, ArtworkSource $source): string
    {
        // A cover the user chose by hand outranks anything detected later, so
        // re-scans, re-extraction and the online backfill leave it alone. Guarded
        // here, in the shared writer, so a future fourth caller cannot bypass it.
        if ($this->cover_art_path !== null
            && ! $this->artwork_source?->isAuto()
            && $source->isAuto()) {
            return $this->cover_art_path;
        }

        if (! self::coverIsUsable($imageData)) {
            throw new RuntimeException(
                'Cover art is not a usable image ('.strlen($imageData).' bytes).'
            );
        }

        $encoded = (string) (new ImageManager(new Driver))
            ->read($imageData)
            ->scaleDown(600, 600)
            ->toWebp(quality: 80);

        $filename = 'covers/'.$this->id.'.webp';

        if ($this->cover_art_path !== null && ! str_ends_with($this->cover_art_path, '.webp')) {
            Storage::disk('public')->delete($this->cover_art_path);
        }

        Storage::disk('public')->put($filename, $encoded);
        $this->update(['cover_art_path' => $filename, 'artwork_source' => $source]);

        return $filename;
    }

    /**
     * Delete the cover file and clear both artwork columns.
     *
     * The single Remove primitive, shared by the modal's Remove action and the
     * backfill command's degenerate-cover step. Nulling the path is what
     * re-arms the automatic online fetch for the album, so the per-album
     * "already tried" token has to go with it.
     */
    public function clearCoverArt(): void
    {
        if ($this->cover_art_path !== null) {
            Storage::disk('public')->delete($this->cover_art_path);
        }

        $this->update(['cover_art_path' => null, 'artwork_source' => null]);

        Cache::forget(FetchAlbumArtwork::attemptKey($this->id));
    }
}
