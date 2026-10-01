<?php

namespace App\Models;

use App\Enums\SourceType;
use Database\Factories\TrackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Track extends Model
{
    /** @use HasFactory<TrackFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'album_id',
        'linked_folder_id',
        'track_number',
        'title',
        'artist',
        'file_path',
        'file_hash',
        'duration',
        'peaks',
        'year',
        'genre',
        'source_type',
        'is_available',
        'play_count',
        'last_played_at',
        'favorited_at',
        'lyrics',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'source_type' => SourceType::class,
            'is_available' => 'boolean',
            'play_count' => 'integer',
            'last_played_at' => 'datetime',
            'favorited_at' => 'datetime',
        ];
    }

    /**
     * Normalise backslashes to forward slashes on write.
     */
    protected function filePath(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => str_replace('\\', '/', $value),
        );
    }

    /**
     * Get the user that owns the track.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the album this track belongs to (nullable).
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * Get the linked folder this track belongs to (nullable).
     */
    public function linkedFolder(): BelongsTo
    {
        return $this->belongsTo(LinkedFolder::class);
    }

    /**
     * Whether the owner has hearted this track.
     */
    public function isFavorited(): bool
    {
        return $this->favorited_at !== null;
    }

    /**
     * Scope a query to only include favorited tracks.
     */
    public function scopeFavorited(Builder $query): Builder
    {
        return $query->whereNotNull('favorited_at');
    }

    /**
     * Scope a query to only include unavailable tracks.
     */
    public function scopeUnavailable(Builder $query): Builder
    {
        return $query->where('is_available', false);
    }

    /**
     * Scope a query to only include uploaded tracks.
     */
    public function scopeUploaded(Builder $query): Builder
    {
        return $query->where('source_type', SourceType::Uploaded);
    }

    /**
     * Scope a query to only include local tracks.
     */
    public function scopeLocal(Builder $query): Builder
    {
        return $query->where('source_type', SourceType::Local);
    }

    /**
     * The track's audio file on local disk.
     *
     * Uploaded tracks live on the public disk; linked-folder tracks are already
     * absolute. This is a path resolver, not an authorization check — callers
     * that expose the file (StreamController) must still verify a local path
     * sits inside the linked folder before serving it.
     */
    public function absolutePath(): string
    {
        return $this->source_type === SourceType::Local
            ? $this->file_path
            : Storage::disk('public')->path($this->file_path);
    }

    /**
     * Get the URL for the track's audio file.
     */
    public function audioUrl(): string
    {
        return route('stream.track', $this);
    }
}
