<?php

namespace App\Models;

use Database\Factories\TrackPlayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackPlay extends Model
{
    /** @use HasFactory<TrackPlayFactory> */
    use HasFactory;

    /**
     * A play row is immutable once written; played_at is its only clock.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'track_id',
        'played_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'played_at' => 'datetime',
        ];
    }

    /**
     * Get the user who listened.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the track that was played.
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
