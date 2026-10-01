<?php

namespace App\Models;

use App\Enums\FolderStatus;
use Database\Factories\LinkedFolderFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LinkedFolder extends Model
{
    /** @use HasFactory<LinkedFolderFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'path',
        'name',
        'status',
        'track_count',
        'unavailable_count',
        'scanned_count',
        'total_count',
        'last_scanned_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FolderStatus::class,
            'track_count' => 'integer',
            'unavailable_count' => 'integer',
            'scanned_count' => 'integer',
            'total_count' => 'integer',
            'last_scanned_at' => 'datetime',
        ];
    }

    /**
     * Normalise backslashes to forward slashes on write.
     */
    protected function path(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => str_replace('\\', '/', $value),
        );
    }

    /**
     * Get the user that owns this linked folder.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tracks in this linked folder.
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }
}
